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
 * Teacher submissions review page for mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/airoleplay/lib.php');

use mod_airoleplay\local\submission_state;

$id           = required_param('id', PARAM_INT);  // Course module id.
$userid       = optional_param('userid', 0, PARAM_INT);
$action       = optional_param('action', '', PARAM_ALPHA);
$submissionid = optional_param('submissionid', 0, PARAM_INT);

$cm         = get_coursemodule_from_id('airoleplay', $id, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$airoleplay = $DB->get_record('airoleplay', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/airoleplay:viewallsubmissions', $context);

$baseurl = new moodle_url('/mod/airoleplay/submissions.php', ['id' => $id]);

// Handle delete action.
if ($action === 'delete' && $submissionid) {
    require_sesskey();
    require_capability('mod/airoleplay:grade', $context);
    $sub = $DB->get_record('airoleplay_submissions', ['id' => $submissionid, 'airoleplay' => $airoleplay->id], '*', MUST_EXIST);
    airoleplay_require_user_access($cm, $context, (int)$sub->userid);
    $DB->delete_records('airoleplay_messages', ['submission_id' => $sub->id]);
    $DB->delete_records('airoleplay_submissions', ['id' => $sub->id]);
    // Another attempt may now be the best grade, or none may be left.
    airoleplay_update_grades($airoleplay, (int)$sub->userid);
    redirect($baseurl, get_string('submission_deleted', 'mod_airoleplay'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Handle grading actions on one attempt.
if (in_array($action, ['savegrade', 'publish', 'return'], true) && $userid) {
    require_sesskey();
    require_capability('mod/airoleplay:grade', $context);
    airoleplay_require_user_access($cm, $context, $userid);

    $submission = airoleplay_fetch_submission($airoleplay->id, $userid, $submissionid);
    $detailurl  = new moodle_url($baseurl, ['userid' => $userid, 'submissionid' => $submission->id]);
    if ($submission->status !== 'graded') {
        redirect(
            $detailurl,
            get_string('invalidsubmissionstatus', 'mod_airoleplay'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    if ($action === 'savegrade' || $action === 'publish') {
        $maxgrade = (float)$airoleplay->grade;
        $newgrade = unformat_float(optional_param('grade', '', PARAM_RAW_TRIMMED), true);
        if ($newgrade === false || $newgrade === null || $newgrade < 0 || $newgrade > $maxgrade) {
            redirect(
                $detailurl,
                get_string('grade_out_of_range', 'mod_airoleplay', format_float($maxgrade, 2)),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
        // Teacher feedback is shown to students with FORMAT_PLAIN, so only
        // printable text is kept.
        $newfeedback = optional_param('feedback', '', PARAM_NOTAGS);
        $DB->update_record('airoleplay_submissions', (object)[
            'id'             => $submission->id,
            'final_grade'    => round((float)$newgrade, 5),
            'final_feedback' => $newfeedback,
            'grader_userid'  => $USER->id,
            'timemodified'   => time(),
        ]);
        $submission->final_grade    = round((float)$newgrade, 5);
        $submission->final_feedback = $newfeedback;
    }

    if ($action === 'publish') {
        submission_state::assert_workflow_transition((string)($submission->workflow_state ?? ''), 'released');
        $DB->update_record('airoleplay_submissions', (object)[
            'id'             => $submission->id,
            'workflow_state' => 'released',
            'timegraded'     => time(),
        ]);
        $submission->workflow_state = 'released';
        \mod_airoleplay\event\grade_issued::create([
            'context'       => $context,
            'objectid'      => $submission->id,
            'relateduserid' => $userid,
        ])->trigger();
        airoleplay_notify_student_grade_released($airoleplay, $submission, $course, $cm);
    } else if ($action === 'return') {
        submission_state::assert_workflow_transition((string)($submission->workflow_state ?? ''), 'inreview');
        $DB->set_field('airoleplay_submissions', 'workflow_state', 'inreview', ['id' => $submission->id]);
    }

    // Keep the gradebook in step with whatever changed (released grades only).
    airoleplay_update_grades($airoleplay, $userid);

    $messages = [
        'savegrade' => 'grade_override_saved',
        'publish'   => 'grade_published',
        'return'    => 'grade_returned',
    ];
    redirect($detailurl, get_string($messages[$action], 'mod_airoleplay'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$PAGE->set_url($baseurl);
$PAGE->set_title(get_string('submissions_heading', 'mod_airoleplay') . ': ' . format_string($airoleplay->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($airoleplay->name) . ' — ' . get_string('submissions_heading', 'mod_airoleplay'));

// Detail view for a single submission.
if ($userid) {
    airoleplay_require_user_access($cm, $context, $userid);
    $PAGE->requires->js_call_amd('mod_airoleplay/submissions', 'init');
    echo $OUTPUT->render(new \mod_airoleplay\output\submission_detail(
        $airoleplay,
        core_user::get_user($userid, '*', MUST_EXIST),
        airoleplay_fetch_submission($airoleplay->id, $userid, $submissionid),
        $context,
        has_capability('mod/airoleplay:grade', $context)
    ));
    echo $OUTPUT->footer();
    exit;
}

// Group selector, honouring separate groups.
$groupmode = groups_get_activity_groupmode($cm);
$groupid   = 0;
if ($groupmode) {
    groups_print_activity_menu($cm, $baseurl);
    $groupid = (int)groups_get_activity_group($cm, true);
}

// Summary table of all submissions.
$userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
$params     = ['airoleplay' => $airoleplay->id];
$groupjoin  = '';
if ($groupid) {
    $groupjoin = 'JOIN {groups_members} gm ON gm.userid = s.userid AND gm.groupid = :groupid';
    $params['groupid'] = $groupid;
} else if ($groupmode == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context)) {
    // A teacher without access to all groups and not in any group sees nobody.
    $groupjoin = 'JOIN {groups_members} gm ON gm.userid = s.userid AND gm.groupid = -1';
}
$sql = "SELECT s.id, s.userid, s.attempt, s.status, s.workflow_state, s.final_grade, s.timesubmitted,
               s.roleplay_analysis, {$userfields}
          FROM {airoleplay_submissions} s
          JOIN {user} u ON u.id = s.userid
               {$groupjoin}
         WHERE s.airoleplay = :airoleplay
      ORDER BY u.lastname ASC, u.firstname ASC, s.attempt ASC";
$submissions = $DB->get_records_sql($sql, $params);

if (empty($submissions)) {
    echo $OUTPUT->notification(get_string('no_submissions_yet', 'mod_airoleplay'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$cangrade = has_capability('mod/airoleplay:grade', $context);
$table = new html_table();
$table->head = [
    get_string('col_student', 'mod_airoleplay'),
    get_string('col_attempt', 'mod_airoleplay'),
    get_string('col_status', 'mod_airoleplay'),
    get_string('col_submitted', 'mod_airoleplay'),
    get_string('col_grade', 'mod_airoleplay'),
    get_string('col_workflow', 'mod_airoleplay'),
    get_string('col_actions', 'mod_airoleplay'),
];
$table->attributes['class'] = 'generaltable airoleplay-submissions-table';

foreach ($submissions as $sub) {
    $detailurl   = new moodle_url($baseurl, ['userid' => $sub->userid, 'submissionid' => $sub->id]);
    $studentlink = html_writer::link($detailurl, fullname($sub));
    $grade       = $sub->final_grade !== null ? format_float($sub->final_grade, 2) : '—';
    $flags       = \mod_airoleplay\output\submission_detail::flags($sub);
    if ($flags) {
        $grade .= ' ' . html_writer::span(get_string('flagged', 'mod_airoleplay'), 'badge bg-warning text-dark');
    }
    $workflowbadge = '';
    if ($sub->workflow_state) {
        $workflowbadge = html_writer::span(
            get_string('workflow_' . $sub->workflow_state, 'mod_airoleplay'),
            'badge ' . ($sub->workflow_state === 'released' ? 'bg-success' : 'bg-secondary')
        );
    }

    $actions = html_writer::link($detailurl, get_string('view'), ['class' => 'btn btn-sm btn-outline-primary me-1']);
    if ($cangrade) {
        $deletebutton = new single_button(
            new moodle_url($baseurl, ['submissionid' => $sub->id, 'action' => 'delete', 'sesskey' => sesskey()]),
            get_string('delete'),
            'post'
        );
        $deletebutton->add_confirm_action(get_string('confirm_delete_submission', 'mod_airoleplay'));
        $deletebutton->class = 'd-inline-block';
        $actions .= $OUTPUT->render($deletebutton);
    }

    $table->data[] = [
        $studentlink,
        (int)$sub->attempt,
        get_string('status_' . $sub->status, 'mod_airoleplay'),
        $sub->timesubmitted ? userdate($sub->timesubmitted) : '—',
        $grade,
        $workflowbadge,
        $actions,
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
