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
 * Acquires a per-submission lock so concurrent web service workers cannot duplicate
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

// Language helpers.

/**
 * English name of a Moodle language, used to tell the model which language to speak.
 *
 * @param string $code Moodle language code (e.g. 'es', 'pt_br').
 * @return string Language name in English.
 */
function airoleplay_language_name(string $code): string {
    $map = [
        'en' => 'English', 'en_us' => 'English', 'es' => 'Spanish', 'es_mx' => 'Spanish',
        'es_es' => 'Spanish', 'pt_br' => 'Brazilian Portuguese', 'pt' => 'Portuguese',
        'fr' => 'French', 'fr_ca' => 'French', 'de' => 'German', 'it' => 'Italian',
        'ca' => 'Catalan', 'eu' => 'Basque', 'gl' => 'Galician', 'nl' => 'Dutch',
        'pl' => 'Polish', 'ru' => 'Russian', 'uk' => 'Ukrainian', 'tr' => 'Turkish',
        'zh_cn' => 'Simplified Chinese', 'zh_tw' => 'Traditional Chinese',
        'ja' => 'Japanese', 'ko' => 'Korean', 'ar' => 'Arabic', 'he' => 'Hebrew',
        'sv' => 'Swedish', 'da' => 'Danish', 'no' => 'Norwegian', 'fi' => 'Finnish',
        'cs' => 'Czech', 'ro' => 'Romanian', 'el' => 'Greek', 'hu' => 'Hungarian',
    ];
    if (isset($map[$code])) {
        return $map[$code];
    }
    // Fall back to the parent language (e.g. 'es_ar' -> 'es'), then to English.
    $parent = explode('_', $code)[0];
    return $map[$parent] ?? 'English';
}

/**
 * Maps a Moodle language code to the BCP-47 tag used by the Web Speech API.
 *
 * @param string $moodlelang Moodle language code.
 * @return string
 */
function airoleplay_speech_language(string $moodlelang): string {
    $map = [
        'es' => 'es-ES', 'es_es' => 'es-ES', 'es_mx' => 'es-MX', 'pt_br' => 'pt-BR',
        'pt' => 'pt-PT', 'fr' => 'fr-FR', 'de' => 'de-DE', 'it' => 'it-IT',
        'ca' => 'ca-ES', 'eu' => 'eu-ES', 'gl' => 'gl-ES', 'en' => 'en-US',
        'en_us' => 'en-US', 'nl' => 'nl-NL', 'pl' => 'pl-PL', 'ja' => 'ja-JP',
        'zh_cn' => 'zh-CN', 'zh_tw' => 'zh-TW', 'ko' => 'ko-KR', 'ar' => 'ar-SA',
    ];
    return $map[$moodlelang] ?? str_replace('_', '-', $moodlelang);
}

/**
 * The language a participant works in: the course's forced language, else the
 * user's preference, else the site default.
 *
 * Used for text generated outside the participant's own session (evaluations
 * run by cron or regenerated by a teacher), where current_language() would be
 * the wrong person's language.
 *
 * @param int           $userid User id.
 * @param stdClass|null $course Course record.
 * @return string Moodle language code.
 */
function airoleplay_user_language(int $userid, ?stdClass $course = null): string {
    global $CFG, $DB;
    $candidates = [];
    if ($course && !empty($course->lang)) {
        $candidates[] = $course->lang;
    }
    $userlang = $DB->get_field('user', 'lang', ['id' => $userid]);
    if (!empty($userlang)) {
        $candidates[] = $userlang;
    }
    $candidates[] = $CFG->lang ?? 'en';
    $manager = get_string_manager();
    foreach ($candidates as $lang) {
        if ($manager->translation_exists($lang, false)) {
            return $lang;
        }
    }
    return 'en';
}

// Teacher access helpers.

/**
 * Stops a teacher from reaching a student outside their groups (separate groups).
 *
 * @param stdClass       $cm      Course module.
 * @param context_module $context Module context.
 * @param int            $userid  Student id.
 * @throws moodle_exception
 */
function airoleplay_require_user_access(stdClass $cm, context_module $context, int $userid): void {
    global $USER;
    if (groups_get_activity_groupmode($cm) != SEPARATEGROUPS || has_capability('moodle/site:accessallgroups', $context)) {
        return;
    }
    $mygroups    = array_keys(groups_get_all_groups($cm->course, $USER->id, $cm->groupingid));
    $theirgroups = array_keys(groups_get_all_groups($cm->course, $userid, $cm->groupingid));
    if (!array_intersect($mygroups, $theirgroups)) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('submissions_heading', 'mod_airoleplay'));
    }
}


/**
 * Fetches a single submission, preferring an explicit submissionid to
 * avoid ambiguity when a student has more than one attempt.
 *
 * @param int $airoleplayid Activity instance id.
 * @param int $userid       Student user id.
 * @param int $submissionid Specific submission id (0 = latest attempt).
 * @return stdClass Submission record.
 * @throws \dml_exception when no submission exists.
 */
function airoleplay_fetch_submission(int $airoleplayid, int $userid, int $submissionid = 0): stdClass {
    global $DB;
    if ($submissionid > 0) {
        return $DB->get_record(
            'airoleplay_submissions',
            ['id' => $submissionid, 'airoleplay' => $airoleplayid, 'userid' => $userid],
            '*',
            MUST_EXIST
        );
    }
    $latest = airoleplay_get_latest_attempt($airoleplayid, $userid);
    if (!$latest) {
        throw new \dml_missing_record_exception('airoleplay_submissions');
    }
    return $latest;
}


// Grading workflow.

/**
 * Whether the site administrator imposes the teacher-review setting on every activity.
 *
 * @return bool
 */
function airoleplay_grading_workflow_locked(): bool {
    return !empty(get_config('mod_airoleplay', 'grading_workflow_locked'));
}

/**
 * Site default for teacher review before release (1 = review, 0 = automatic).
 *
 * @return int
 */
function airoleplay_grading_workflow_default(): int {
    $value = get_config('mod_airoleplay', 'grading_workflow');
    // Unset until the administrator saves the settings: review is the safe default.
    return ($value === false || $value === null) ? 1 : (int)!empty($value);
}

/**
 * Whether AI grades of an activity wait for a teacher before being released.
 *
 * A site-wide lock wins over the activity setting, so an administrator's
 * choice also applies to activities created before it was made.
 *
 * @param stdClass $airoleplay Activity record.
 * @return bool
 */
function airoleplay_grading_workflow_enabled(stdClass $airoleplay): bool {
    if (airoleplay_grading_workflow_locked()) {
        return (bool)airoleplay_grading_workflow_default();
    }
    return !empty($airoleplay->grading_workflow);
}

// Attempts, availability and overrides.

/**
 * Resolves the settings that apply to one participant: a user override wins,
 * otherwise the most generous of the overrides of the groups they belong to,
 * otherwise the activity settings.
 *
 * @param stdClass $airoleplay Activity record.
 * @param int      $userid     User id.
 * @return stdClass {max_attempts, timeopen, timeclose}; 0 means unlimited / no limit.
 */
function airoleplay_get_effective_settings(stdClass $airoleplay, int $userid): stdClass {
    global $DB;

    $settings = (object)[
        'max_attempts' => (int)$airoleplay->max_attempts,
        'timeopen'     => (int)($airoleplay->timeopen ?? 0),
        'timeclose'    => (int)($airoleplay->timeclose ?? 0),
    ];

    $useroverride = $DB->get_record('airoleplay_overrides', ['airoleplay' => $airoleplay->id, 'userid' => $userid]);
    if ($useroverride) {
        $overrides = [$useroverride];
    } else {
        $overrides = [];
        $groupings = groups_get_user_groups($airoleplay->course, $userid);
        $groupids  = $groupings[0] ?? [];
        if ($groupids) {
            [$insql, $params] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED);
            $params['airoleplay'] = $airoleplay->id;
            $overrides = $DB->get_records_select('airoleplay_overrides', "airoleplay = :airoleplay AND groupid {$insql}", $params);
        }
    }
    if (!$overrides) {
        return $settings;
    }

    // Combine the overrides that set each field, keeping the most permissive value.
    $attempts = $opens = $closes = [];
    foreach ($overrides as $override) {
        if ($override->max_attempts !== null) {
            $attempts[] = (int)$override->max_attempts;
        }
        if ($override->timeopen !== null) {
            $opens[] = (int)$override->timeopen;
        }
        if ($override->timeclose !== null) {
            $closes[] = (int)$override->timeclose;
        }
    }
    if ($attempts) {
        $settings->max_attempts = in_array(0, $attempts, true) ? 0 : max($attempts);
    }
    if ($opens) {
        $settings->timeopen = min($opens);
    }
    if ($closes) {
        $settings->timeclose = in_array(0, $closes, true) ? 0 : max($closes);
    }
    return $settings;
}

/**
 * Why a participant cannot start a session right now, or null when they can.
 *
 * @param stdClass $airoleplay Activity record.
 * @param int      $userid     User id.
 * @param int|null $now        Current time (for tests).
 * @return string|null Language string key in mod_airoleplay, or null.
 */
function airoleplay_availability_problem(stdClass $airoleplay, int $userid, ?int $now = null): ?string {
    $now      = $now ?? time();
    $settings = airoleplay_get_effective_settings($airoleplay, $userid);
    if ($settings->timeopen && $now < $settings->timeopen) {
        return 'notopenyet';
    }
    if ($settings->timeclose && $now > $settings->timeclose) {
        return 'activityclosed';
    }
    return null;
}

/**
 * Number of attempts the participant has left (PHP_INT_MAX when unlimited).
 *
 * Only attempts whose session actually started are counted: an attempt that
 * was created but never opened does not consume the allowance.
 *
 * @param stdClass $airoleplay Activity record.
 * @param int      $userid     User id.
 * @return int
 */
function airoleplay_attempts_remaining(stdClass $airoleplay, int $userid): int {
    global $DB;
    $settings = airoleplay_get_effective_settings($airoleplay, $userid);
    if ($settings->max_attempts === 0) {
        return PHP_INT_MAX;
    }
    $used = $DB->count_records_select(
        'airoleplay_submissions',
        "airoleplay = :airoleplay AND userid = :userid AND status <> :draft",
        ['airoleplay' => $airoleplay->id, 'userid' => $userid, 'draft' => 'draft']
    );
    return max(0, $settings->max_attempts - $used);
}

/**
 * Returns the participant's latest attempt, or null.
 *
 * @param int $airoleplayid Activity id.
 * @param int $userid       User id.
 * @return stdClass|null
 */
function airoleplay_get_latest_attempt(int $airoleplayid, int $userid): ?stdClass {
    global $DB;
    $records = $DB->get_records(
        'airoleplay_submissions',
        ['airoleplay' => $airoleplayid, 'userid' => $userid],
        'attempt DESC, id DESC',
        '*',
        0,
        1
    );
    return $records ? reset($records) : null;
}

/**
 * Creates the participant's next attempt after the previous one was graded.
 *
 * @param stdClass $airoleplay Activity record.
 * @param int      $userid     User id.
 * @param int      $groupid    Group id for group submissions (0 otherwise).
 * @return stdClass The new draft attempt.
 * @throws moodle_exception when no new attempt is allowed.
 */
function airoleplay_start_new_attempt(stdClass $airoleplay, int $userid, int $groupid = 0): stdClass {
    global $DB;

    $latest = airoleplay_get_latest_attempt($airoleplay->id, $userid);
    if ($latest && $latest->status !== 'graded') {
        throw new moodle_exception('attempt_in_progress', 'mod_airoleplay');
    }
    if (airoleplay_attempts_remaining($airoleplay, $userid) <= 0) {
        throw new moodle_exception('noattemptsleft', 'mod_airoleplay');
    }

    $now = time();
    $submission = (object)[
        'airoleplay'        => $airoleplay->id,
        'userid'            => $userid,
        'groupid'           => $groupid,
        'status'            => 'draft',
        'attempt'           => $latest ? (int)$latest->attempt + 1 : 1,
        'gdpr_consent'      => 1,
        'gdpr_consent_time' => $now,
        'timecreated'       => $now,
        'timemodified'      => $now,
    ];
    $submission->id = $DB->insert_record('airoleplay_submissions', $submission);
    return $submission;
}

// Logging helpers.

/**
 * Records an internal error in the web server's PHP error log.
 *
 * Deliberately not debugging(): with debug display on, debugging() prints
 * into the response, which corrupts the JSON the roleplay page relies on and
 * could show provider error text to students. The error log is server-side only.
 *
 * A short identifier-only line is always written; the exception detail (which
 * may quote provider responses) is added only in developer debug mode.
 *
 * @param string     $context Short, free-text context (e.g. "service_submit_turn").
 * @param \Throwable $e       The caught exception.
 * @param array      $ids     Optional integer identifiers to include in the
 *                            log line (submission id, cmid, ...).
 */
function airoleplay_log_internal_error(string $context, \Throwable $e, array $ids = []): void {
    global $CFG;
    $idstr = '';
    foreach ($ids as $key => $value) {
        $idstr .= ' ' . $key . '=' . (int)$value;
    }
    $line = 'mod_airoleplay ' . $context . ' error (' . get_class($e) . ')' . $idstr;
    if (!empty($CFG->debugdeveloper)) {
        $detail = $e->getMessage();
        if ($e instanceof \moodle_exception && !empty($e->debuginfo)) {
            $detail .= ' | ' . $e->debuginfo;
        }
        $line .= ': ' . $detail . ' in ' . $e->getFile() . ':' . $e->getLine();
    }
    // phpcs:ignore moodle.PHP.ForbiddenFunctions.FoundWithAlternative
    error_log($line);
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

    airoleplay_save_avatar_files($data);
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

    airoleplay_save_avatar_files($data);
    airoleplay_grade_item_update($data);
    // The maximum grade may have changed: rescale what is already in the gradebook.
    airoleplay_update_grades($DB->get_record('airoleplay', ['id' => $data->id], '*', MUST_EXIST));

    return true;
}

/**
 * Stores the custom avatar images uploaded through the activity form.
 *
 * @param stdClass $data Form data including coursemodule and the draft item ids.
 */
function airoleplay_save_avatar_files(stdClass $data): void {
    if (empty($data->coursemodule)) {
        return;
    }
    $context = context_module::instance($data->coursemodule);
    for ($i = 1; $i <= 3; $i++) {
        $field = "avatar_{$i}_avatar_custom";
        if (isset($data->$field)) {
            file_save_draft_area_files(
                $data->$field,
                $context->id,
                'mod_airoleplay',
                'avatar_custom',
                $i,
                airoleplay_avatar_file_options()
            );
        }
    }
}

/**
 * File manager options for custom avatar images.
 *
 * @return array
 */
function airoleplay_avatar_file_options(): array {
    return [
        'subdirs'        => 0,
        'maxfiles'       => 1,
        'maxbytes'       => 2 * 1024 * 1024,
        'accepted_types' => ['.png', '.jpg', '.jpeg', '.gif', '.webp'],
    ];
}

/**
 * URL of the custom avatar image uploaded for an avatar slot, or null.
 *
 * @param context_module $context Module context.
 * @param int            $index   Avatar index (1-3).
 * @return moodle_url|null
 */
function airoleplay_custom_avatar_url(context_module $context, int $index): ?moodle_url {
    $fs    = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_airoleplay', 'avatar_custom', $index, 'filename', false);
    $file  = reset($files);
    if (!$file) {
        return null;
    }
    return moodle_url::make_pluginfile_url(
        $context->id,
        'mod_airoleplay',
        'avatar_custom',
        $index,
        $file->get_filepath(),
        $file->get_filename()
    );
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
    if (isset($data->scenario_description_editor)) {
        $data->scenario_description       = $data->scenario_description_editor['text'];
        $data->scenario_descriptionformat = $data->scenario_description_editor['format'];
    }

    $data->num_avatars      = isset($data->num_avatars) ? max(1, min(3, (int)$data->num_avatars)) : 1;
    $data->max_attempts     = isset($data->max_attempts) ? max(0, (int)$data->max_attempts) : 2;
    $data->group_submission = isset($data->group_submission) ? (int)$data->group_submission : 0;
    if (airoleplay_grading_workflow_locked() || !isset($data->grading_workflow)) {
        $data->grading_workflow = airoleplay_grading_workflow_default();
    } else {
        $data->grading_workflow = (int)!empty($data->grading_workflow);
    }
    $data->notify_student   = isset($data->notify_student) ? (int)$data->notify_student : 1;
    $data->timeopen         = isset($data->timeopen) ? (int)$data->timeopen : 0;
    $data->timeclose        = isset($data->timeclose) ? (int)$data->timeclose : 0;
    $data->completionsubmit = !empty($data->completionsubmit) ? 1 : 0;
}

// Gradebook integration.

/**
 * Creates or updates the grade item for an airoleplay instance.
 *
 * @param stdClass $airoleplay The airoleplay record.
 * @param mixed    $grades Optional grades array, or 'reset'.
 * @return int GRADE_UPDATE_OK on success.
 */
function airoleplay_grade_item_update(stdClass $airoleplay, mixed $grades = null): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = ['itemname' => $airoleplay->name];
    if ((int)($airoleplay->grade ?? 100) > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = (int)$airoleplay->grade;
        $params['grademin']  = 0;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

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
 * With several attempts, the highest released grade counts. Attempts still
 * in teacher review never reach the gradebook.
 *
 * @param stdClass $airoleplay The airoleplay instance.
 * @param int      $userid     User id, 0 = all.
 * @param bool     $nullifnone If true, insert null grade if none found.
 */
function airoleplay_update_grades(stdClass $airoleplay, int $userid = 0, bool $nullifnone = true): void {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    if ((int)$airoleplay->grade <= 0) {
        airoleplay_grade_item_update($airoleplay);
        return;
    }

    $sql = "SELECT s.userid,
                   MAX(s.final_grade) AS rawgrade,
                   MAX(s.timegraded)  AS dategraded
              FROM {airoleplay_submissions} s
             WHERE s.airoleplay = :airoleplay
               AND s.status = :status
               AND s.workflow_state = :released";
    $params = ['airoleplay' => $airoleplay->id, 'status' => 'graded', 'released' => 'released'];

    if ($userid) {
        $sql    .= ' AND s.userid = :userid';
        $params['userid'] = $userid;
    }
    $sql .= ' GROUP BY s.userid';

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

// Course reset.

/**
 * Adds the "delete all roleplay attempts" option to the course reset form.
 *
 * @param MoodleQuickForm $mform Course reset form.
 */
function airoleplay_reset_course_form_definition(&$mform): void {
    $mform->addElement('header', 'airoleplayheader', get_string('modulenameplural', 'mod_airoleplay'));
    $mform->addElement('advcheckbox', 'reset_airoleplay_submissions', get_string('reset_submissions', 'mod_airoleplay'));
    $mform->addElement('advcheckbox', 'reset_airoleplay_overrides', get_string('reset_overrides', 'mod_airoleplay'));
}

/**
 * Default values for the course reset form.
 *
 * @param stdClass $course Course record.
 * @return array
 */
function airoleplay_reset_course_form_defaults($course): array {
    return ['reset_airoleplay_submissions' => 1, 'reset_airoleplay_overrides' => 1];
}

/**
 * Removes user data from every airoleplay activity of a course being reset.
 *
 * @param stdClass $data Reset form data.
 * @return array Status array for the reset report.
 */
function airoleplay_reset_userdata($data): array {
    global $DB;

    $status    = [];
    $component = get_string('modulenameplural', 'mod_airoleplay');
    $instances = $DB->get_records('airoleplay', ['course' => $data->courseid]);

    if (!empty($data->reset_airoleplay_submissions)) {
        foreach ($instances as $airoleplay) {
            $subids = $DB->get_fieldset_select('airoleplay_submissions', 'id', 'airoleplay = ?', [$airoleplay->id]);
            if ($subids) {
                $DB->delete_records_list('airoleplay_messages', 'submission_id', $subids);
            }
            $DB->delete_records('airoleplay_submissions', ['airoleplay' => $airoleplay->id]);
            if (empty($data->reset_gradebook_grades)) {
                airoleplay_grade_item_update($airoleplay, 'reset');
            }
        }
        $status[] = ['component' => $component, 'item' => get_string('reset_submissions', 'mod_airoleplay'), 'error' => false];
    }

    if (!empty($data->reset_airoleplay_overrides)) {
        foreach ($instances as $airoleplay) {
            $DB->delete_records('airoleplay_overrides', ['airoleplay' => $airoleplay->id]);
        }
        $status[] = ['component' => $component, 'item' => get_string('reset_overrides', 'mod_airoleplay'), 'error' => false];
    }

    if ($data->timeshift) {
        shift_course_mod_dates('airoleplay', ['timeopen', 'timeclose'], $data->timeshift, $data->courseid);
        $status[] = ['component' => $component, 'item' => get_string('datechanged'), 'error' => false];
    }

    return $status;
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

    $fields = 'id, name, intro, introformat, completionsubmit, timeopen, timeclose';
    if (!$airoleplay = $DB->get_record('airoleplay', ['id' => $coursemodule->instance], $fields)) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $airoleplay->name;

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('airoleplay', $airoleplay, $coursemodule->id, false);
    }

    // Custom completion rules, read by \mod_airoleplay\completion\custom_completion.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionsubmit'] = (int)$airoleplay->completionsubmit;
    }
    if ($airoleplay->timeopen) {
        $info->customdata['timeopen'] = (int)$airoleplay->timeopen;
    }
    if ($airoleplay->timeclose) {
        $info->customdata['timeclose'] = (int)$airoleplay->timeclose;
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
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, true, $cm);

    if (!in_array($filearea, ['intro', 'avatar_custom'], true)) {
        return false;
    }
    if (!has_capability('mod/airoleplay:view', $context)) {
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

    send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
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
        case FEATURE_GROUPINGS:
        case FEATURE_MOD_INTRO:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return false;
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
    $cm = $settingsnav->get_page()->cm;
    if (!$cm) {
        return;
    }

    $context = context_module::instance($cm->id);

    if (has_capability('mod/airoleplay:viewallsubmissions', $context)) {
        $navref->add(
            get_string('submissions_heading', 'mod_airoleplay'),
            new moodle_url('/mod/airoleplay/submissions.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'airoleplay_submissions',
            new pix_icon('i/grades', '')
        );
    }

    if (has_capability('mod/airoleplay:manageoverrides', $context)) {
        $navref->add(
            get_string('overrides_heading', 'mod_airoleplay'),
            new moodle_url('/mod/airoleplay/overrides.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'airoleplay_overrides',
            new pix_icon('i/user', '')
        );
    }
}
