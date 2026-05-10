<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Library functions for mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Concurrency helpers.

/**
 * Acquires a per-submission lock so concurrent ajax workers cannot duplicate
 * state transitions or trigger the evaluator twice.
 *
 * @param int $submissionid The airoleplay_submissions.id to guard.
 * @param int $timeoutsecs  How long to wait before giving up.
 * @return \core\lock\lock|null Acquired lock, or null on timeout.
 */
function airoleplay_acquire_submission_lock(int $submissionid, int $timeoutsecs = 10): ?\core\lock\lock {
    $factory = \core\lock\lock_config::get_lock_factory('mod_airoleplay');
    $lock    = $factory->get_lock('submission_' . $submissionid, $timeoutsecs);
    return $lock ?: null;
}

// Rate-limit helpers.

/**
 * Enforces a per-teacher, per-submission cooldown plus a daily cap on
 * regen operations to prevent runaway OpenAI cost.
 *
 * @param int    $userid       Teacher's user id.
 * @param int    $submissionid Submission being regenerated.
 * @param string $operation    Short operation name for cache key namespacing.
 * @param int    $cooldownsecs Minimum seconds between calls (default 300).
 * @param int    $dailycap     Max calls per teacher+submission per day.
 * @throws \moodle_exception if the cooldown has not yet expired or the cap
 *                           is reached.
 */
function airoleplay_regen_rate_check(
    int $userid,
    int $submissionid,
    string $operation,
    int $cooldownsecs = 300,
    int $dailycap = 5
): void {
    $cache = \cache::make('mod_airoleplay', 'ratelimit');
    $now   = time();

    $lastkey = 'regen_' . $operation . '_' . $userid . '_' . $submissionid;
    $last    = (int)($cache->get($lastkey) ?: 0);
    if ($last > 0 && ($now - $last) < $cooldownsecs) {
        throw new \moodle_exception('regen_cooldown', 'mod_airoleplay');
    }

    // Daily cap, keyed on the UTC day so the counter resets at midnight.
    $daykey   = 'regen_day_' . $operation . '_' . $userid . '_' . $submissionid . '_' . gmdate('Ymd', $now);
    $daycount = (int)($cache->get($daykey) ?: 0);
    if ($daycount >= $dailycap) {
        throw new \moodle_exception('regen_daily_cap', 'mod_airoleplay');
    }

    $cache->set($lastkey, $now);
    $cache->set($daykey, $daycount + 1);
}

// Logging helpers.

/**
 * Records an internal error.
 *
 * Sends the full message, file/line and stack trace to PHP's error_log
 * (server-side only) and emits a short, identifier-only debugging() line
 * so an admin running with debug display on does not see the raw message,
 * which can contain prompt fragments, request bodies or file paths.
 *
 * @param string     $context Short, free-text context (e.g. "ajax dispatch").
 * @param \Throwable $e       The caught exception.
 * @param array      $ids     Optional integer identifiers to include in the
 *                            short debug line (submission id, cmid, ...).
 */
function airoleplay_log_internal_error(string $context, \Throwable $e, array $ids = []): void {
    error_log(sprintf(
        '[mod_airoleplay] %s: %s in %s:%d%s%s',
        $context,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        PHP_EOL,
        $e->getTraceAsString()
    ));
    $idstr = '';
    foreach ($ids as $key => $value) {
        $idstr .= ' ' . $key . '=' . (int)$value;
    }
    debugging(
        'mod_airoleplay ' . $context . ' error (' . get_class($e) . ')' . $idstr,
        DEBUG_DEVELOPER
    );
}

// Course module API.

/**
 * Adds a new instance of airoleplay to the database.
 *
 * @param stdClass $data Form data.
 * @param mod_airoleplay_mod_form|null $mform The form object.
 * @return int The new instance id.
 */
function airoleplay_add_instance(stdClass $data, ?mod_airoleplay_mod_form $mform = null): int {
    global $DB;

    $data->timecreated  = time();
    $data->timemodified = time();

    airoleplay_process_form_data($data);

    $id      = $DB->insert_record('airoleplay', $data);
    $data->id = $id;

    airoleplay_grade_item_update($data);

    return $id;
}

/**
 * Updates an existing instance of airoleplay.
 *
 * @param stdClass $data Form data.
 * @param mod_airoleplay_mod_form|null $mform The form object.
 * @return bool True on success.
 */
function airoleplay_update_instance(stdClass $data, ?mod_airoleplay_mod_form $mform = null): bool {
    global $DB;

    $data->id           = $data->instance;
    $data->timemodified = time();

    airoleplay_process_form_data($data);

    $DB->update_record('airoleplay', $data);

    airoleplay_grade_item_update($data);

    return true;
}

/**
 * Deletes an instance of airoleplay and all associated data.
 *
 * @param int $id The instance id.
 * @return bool True on success.
 */
function airoleplay_delete_instance(int $id): bool {
    global $DB, $CFG;

    if (!$airoleplay = $DB->get_record('airoleplay', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('airoleplay_overrides', ['airoleplay' => $id]);

    $submissions = $DB->get_records('airoleplay_submissions', ['airoleplay' => $id]);
    foreach ($submissions as $submission) {
        $DB->delete_records('airoleplay_messages', ['submission_id' => $submission->id]);
    }
    $DB->delete_records('airoleplay_submissions', ['airoleplay' => $id]);

    airoleplay_grade_item_delete($airoleplay);

    $cm = get_coursemodule_from_instance('airoleplay', $id);
    if ($cm) {
        $context = context_module::instance($cm->id);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_airoleplay');
    }

    $DB->delete_records('airoleplay', ['id' => $id]);

    return true;
}

/**
 * Pre-processes form data before saving to database.
 *
 * @param stdClass $data Form data (modified in-place).
 */
function airoleplay_process_form_data(stdClass $data): void {
    if (!empty($data->openai_apikey)) {
        try {
            $data->openai_apikey = \core\encryption::encrypt($data->openai_apikey);
        } catch (\moodle_exception $e) {
            airoleplay_log_internal_error('apikey_encrypt', $e);
        }
    }

    if (isset($data->scenario_description_editor)) {
        $data->scenario_description       = $data->scenario_description_editor['text'];
        $data->scenario_descriptionformat = $data->scenario_description_editor['format'];
    }

    $data->num_avatars      = isset($data->num_avatars) ? max(1, min(3, (int)$data->num_avatars)) : 1;
    $data->max_attempts     = isset($data->max_attempts) ? (int)$data->max_attempts : 2;
    $data->group_submission = isset($data->group_submission) ? (int)$data->group_submission : 0;
    $data->grading_workflow = isset($data->grading_workflow) ? (int)$data->grading_workflow : 0;
    $data->notify_student   = isset($data->notify_student) ? (int)$data->notify_student : 1;
    $data->safety_content_filter = isset($data->safety_content_filter) ? (int)$data->safety_content_filter : 1;
}

// Gradebook integration.

/**
 * Creates or updates the grade item for an airoleplay instance.
 *
 * @param stdClass $airoleplay The airoleplay record.
 * @param mixed    $grades Optional grades array.
 * @return int GRADE_UPDATE_OK on success.
 */
function airoleplay_grade_item_update(stdClass $airoleplay, mixed $grades = null): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = [
        'itemname'  => $airoleplay->name,
        'gradetype' => GRADE_TYPE_VALUE,
        'grademax'  => $airoleplay->grade ?? 100,
        'grademin'  => 0,
    ];

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update(
        'mod/airoleplay',
        $airoleplay->course,
        'mod',
        'airoleplay',
        $airoleplay->id,
        0,
        $grades,
        $params
    );
}

/**
 * Updates grades in the gradebook for one or all users.
 *
 * @param stdClass $airoleplay The airoleplay instance.
 * @param int      $userid     User id, 0 = all.
 * @param bool     $nullifnone If true, insert null grade if none found.
 */
function airoleplay_update_grades(stdClass $airoleplay, int $userid = 0, bool $nullifnone = true): void {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    if ($airoleplay->grade == 0) {
        airoleplay_grade_item_update($airoleplay);
        return;
    }

    $sql = "SELECT s.userid,
                   s.final_grade AS rawgrade,
                   s.timegraded  AS dategraded
              FROM {airoleplay_submissions} s
             WHERE s.airoleplay = :airoleplay
               AND s.status = 'graded'
               AND s.workflow_state = 'released'";
    $params = ['airoleplay' => $airoleplay->id];

    if ($userid) {
        $sql    .= ' AND s.userid = :userid';
        $params['userid'] = $userid;
    }

    $grades = [];
    foreach ($DB->get_records_sql($sql, $params) as $row) {
        $grade             = new stdClass();
        $grade->userid     = $row->userid;
        $grade->rawgrade   = $row->rawgrade;
        $grade->dategraded = $row->dategraded;
        $grades[$row->userid] = $grade;
    }

    if (!$grades) {
        if ($nullifnone && $userid) {
            $grade           = new stdClass();
            $grade->userid   = $userid;
            $grade->rawgrade = null;
            $grades[$userid] = $grade;
        } else {
            airoleplay_grade_item_update($airoleplay);
            return;
        }
    }

    airoleplay_grade_item_update($airoleplay, $grades);
}

/**
 * Deletes the grade item from the gradebook.
 *
 * @param stdClass $airoleplay The airoleplay record.
 * @return int GRADE_UPDATE_OK on success.
 */
function airoleplay_grade_item_delete(stdClass $airoleplay): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update('mod/airoleplay', $airoleplay->course, 'mod', 'airoleplay', $airoleplay->id, 0, null, ['deleted' => 1]);
}

// Course-level listing.

/**
 * Returns course module info for display in the course listing.
 *
 * @param stdClass $coursemodule The course module object.
 * @return cached_cm_info|null The populated info object, or null on failure.
 */
function airoleplay_get_coursemodule_info(stdClass $coursemodule): ?cached_cm_info {
    global $DB;

    if (!$airoleplay = $DB->get_record('airoleplay', ['id' => $coursemodule->instance], 'id, name, intro, introformat')) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $airoleplay->name;

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('airoleplay', $airoleplay, $coursemodule->id, false);
    }

    return $info;
}

// File serving.

/**
 * Serves files from the mod_airoleplay file areas.
 *
 * @param stdClass $course        The course record.
 * @param stdClass $cm            The course module record.
 * @param context  $context       The module context.
 * @param string   $filearea      The name of the file area.
 * @param array    $args          Extra arguments (itemid, path).
 * @param bool     $forcedownload Whether or not force download.
 * @param array    $options       Additional options affecting the file serving.
 * @return bool False if file not found; does not return if successful.
 */
function airoleplay_pluginfile(
    stdClass $course,
    stdClass $cm,
    context $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = []
) {
    global $DB, $USER;

    require_login($course, true, $cm);

    $allowedareas = ['intro', 'avatar_custom'];

    if (!in_array($filearea, $allowedareas)) {
        return false;
    }

    $itemid = (int)array_shift($args);

    $fs       = get_file_storage();
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $file = $fs->get_file($context->id, 'mod_airoleplay', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

// Activity completion.

/**
 * Returns the custom completion rule descriptions for display in the activity settings.
 *
 * @param stdClass $cm The course module object with customdata.
 * @return array Array of completion rule descriptions keyed by rule name.
 */
function airoleplay_get_completion_active_rule_descriptions(stdClass $cm): array {
    $descriptions = [];
    $airoleplay = $cm->customdata['customcompletionrules'] ?? null;

    if (empty($airoleplay)) {
        return $descriptions;
    }

    if (!empty($airoleplay['completionsubmit'])) {
        $descriptions['completionsubmit'] = get_string('completionsubmit', 'mod_airoleplay');
    }
    if (!empty($airoleplay['completiongrade'])) {
        $descriptions['completiongrade'] = get_string('completiongrade', 'mod_airoleplay');
    }

    return $descriptions;
}

/**
 * Returns a list of features supported by this activity module.
 *
 * @param string $feature FEATURE_xx constant.
 * @return mixed True if feature is supported, null if unknown.
 */
function airoleplay_supports(string $feature): mixed {
    switch ($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return false;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

// Notification helpers.

/**
 * Sends a notification to the student when their grade is published.
 *
 * @param stdClass $airoleplay  The airoleplay instance.
 * @param stdClass $submission  The submission record.
 * @param stdClass $course      The course record.
 * @param stdClass $cm          The course module record.
 */
function airoleplay_notify_student_grade_released(
    stdClass $airoleplay,
    stdClass $submission,
    stdClass $course,
    stdClass $cm
): void {
    global $DB;

    if (!$airoleplay->notify_student) {
        return;
    }

    $student = $DB->get_record('user', ['id' => $submission->userid], '*', MUST_EXIST);
    $context = context_module::instance($cm->id);

    $a               = new stdClass();
    $a->activityname = format_string($airoleplay->name, true, ['context' => $context]);
    $a->coursename   = format_string($course->fullname, true, ['context' => $context]);
    $a->grade        = format_float($submission->final_grade, 2);
    $a->link         = new moodle_url('/mod/airoleplay/view.php', ['id' => $cm->id]);

    $message                     = new \core\message\message();
    $message->component          = 'mod_airoleplay';
    $message->name               = 'gradenotification';
    $message->userfrom           = core_user::get_noreply_user();
    $message->userto             = $student;
    $message->subject            = get_string('gradenotification_subject', 'mod_airoleplay', $a);
    $message->fullmessage        = get_string('gradenotification_body', 'mod_airoleplay', $a);
    $message->fullmessageformat  = FORMAT_PLAIN;
    $message->fullmessagehtml    = get_string('gradenotification_bodyhtml', 'mod_airoleplay', $a);
    $message->smallmessage       = get_string('gradenotification_small', 'mod_airoleplay', $a);
    $message->notification       = 1;
    $message->contexturl         = $a->link->out(false);
    $message->contexturlname     = $a->activityname;

    message_send($message);
}

/**
 * Notifies enrolled graders that a submission is ready for review.
 *
 * @param stdClass $airoleplay  The airoleplay activity instance.
 * @param stdClass $submission  The student submission record.
 * @param stdClass $course      The course record.
 * @param stdClass $cm          The course module record.
 */
function airoleplay_notify_teacher_submission_ready(
    stdClass $airoleplay,
    stdClass $submission,
    stdClass $course,
    stdClass $cm
): void {
    global $DB;

    $context  = context_module::instance($cm->id);
    $teachers = get_enrolled_users($context, 'mod/airoleplay:grade');

    if (!$teachers) {
        return;
    }

    $student = $DB->get_record('user', ['id' => $submission->userid], '*', MUST_EXIST);

    $a               = new stdClass();
    $a->activityname = format_string($airoleplay->name, true, ['context' => $context]);
    $a->coursename   = format_string($course->fullname, true, ['context' => $context]);
    $a->studentname  = fullname($student);
    $a->link         = new moodle_url('/mod/airoleplay/submissions.php', ['id' => $cm->id]);

    foreach ($teachers as $teacher) {
        $message                     = new \core\message\message();
        $message->component          = 'mod_airoleplay';
        $message->name               = 'submissionnotification';
        $message->userfrom           = core_user::get_noreply_user();
        $message->userto             = $teacher;
        $message->subject            = get_string('submissionnotification_subject', 'mod_airoleplay', $a);
        $message->fullmessage        = get_string('submissionnotification_body', 'mod_airoleplay', $a);
        $message->fullmessageformat  = FORMAT_PLAIN;
        $message->fullmessagehtml    = get_string('submissionnotification_bodyhtml', 'mod_airoleplay', $a);
        $message->smallmessage       = get_string('submissionnotification_small', 'mod_airoleplay', $a);
        $message->notification       = 1;
        $message->contexturl         = $a->link->out(false);
        $message->contexturlname     = $a->activityname;

        message_send($message);
    }
}

// Navigation.

/**
 * Adds extra items to the activity's settings navigation.
 *
 * @param settings_navigation $settingsnav The settings nav tree.
 * @param navigation_node     $navref      The module navigation node.
 */
function airoleplay_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $navref): void {
    global $PAGE;

    $cm = $PAGE->cm;
    if (!$cm) {
        return;
    }

    $context = context_module::instance($cm->id);

    $node = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING);
    if (!$node) {
        return;
    }

    if (has_capability('mod/airoleplay:viewallsubmissions', $context)) {
        $node->add(
            get_string('submissions_heading', 'mod_airoleplay'),
            new moodle_url('/mod/airoleplay/submissions.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'airoleplay_submissions',
            new pix_icon('i/grades', '')
        );
    }

    if (has_capability('mod/airoleplay:manageoverrides', $context)) {
        $node->add(
            get_string('overrides_heading', 'mod_airoleplay'),
            new moodle_url('/mod/airoleplay/overrides.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'airoleplay_overrides',
            new pix_icon('i/user', '')
        );
    }
}
