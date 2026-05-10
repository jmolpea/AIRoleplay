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

// Handle delete action.
if ($action === 'delete' && $submissionid) {
    require_sesskey();
    require_capability('mod/airoleplay:grade', $context);
    $sub = $DB->get_record(
        'airoleplay_submissions',
        ['id' => $submissionid, 'airoleplay' => $airoleplay->id],
        '*',
        MUST_EXIST
    );
    $DB->delete_records('airoleplay_messages', ['submission_id' => $sub->id]);
    $DB->delete_records('airoleplay_submissions', ['id' => $sub->id]);
    redirect(
        new moodle_url('/mod/airoleplay/submissions.php', ['id' => $id]),
        get_string('submission_deleted', 'mod_airoleplay'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Handle workflow/grade actions.
if ($action && $userid) {
    require_sesskey();
    require_capability('mod/airoleplay:grade', $context);
    if (!is_enrolled($context, $userid)) {
        throw new \moodle_exception('nopermissions', 'error', '', 'view submission');
    }

    $submission = airoleplay_fetch_submission($airoleplay->id, $userid, $submissionid);

    if ($action === 'publish') {
        \mod_airoleplay\local\submission_state::assert_workflow_transition(
            (string)($submission->workflow_state ?? ''),
            'released'
        );
        $DB->set_field('airoleplay_submissions', 'workflow_state', 'released', ['id' => $submission->id]);
        $DB->set_field('airoleplay_submissions', 'grader_userid', $USER->id, ['id' => $submission->id]);
        $DB->set_field('airoleplay_submissions', 'timegraded', time(), ['id' => $submission->id]);
        $submission->workflow_state = 'released';
        airoleplay_update_grades($airoleplay, $userid);
        airoleplay_notify_student_grade_released($airoleplay, $submission, $course, $cm);
    } else if ($action === 'return') {
        \mod_airoleplay\local\submission_state::assert_workflow_transition(
            (string)($submission->workflow_state ?? ''),
            'inreview'
        );
        $DB->set_field('airoleplay_submissions', 'workflow_state', 'inreview', ['id' => $submission->id]);
    } else if ($action === 'savegarde') {
        $newgrade = required_param('grade', PARAM_FLOAT);
        // Teacher feedback is shown to students with FORMAT_PLAIN, so we
        // only need printable text. PARAM_NOTAGS strips any markup the
        // teacher (or a CSRF-tricked browser) might have submitted.
        $newfeedback = optional_param('feedback', '', PARAM_NOTAGS);
        $DB->set_field('airoleplay_submissions', 'final_grade', $newgrade, ['id' => $submission->id]);
        $DB->set_field('airoleplay_submissions', 'final_feedback', $newfeedback, ['id' => $submission->id]);
        $DB->set_field('airoleplay_submissions', 'grader_userid', $USER->id, ['id' => $submission->id]);
        $DB->set_field('airoleplay_submissions', 'timemodified', time(), ['id' => $submission->id]);
        redirect(
            new moodle_url('/mod/airoleplay/submissions.php', ['id' => $id, 'userid' => $userid]),
            get_string('grade_override_saved', 'mod_airoleplay'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    redirect(new moodle_url('/mod/airoleplay/submissions.php', ['id' => $id, 'userid' => $userid]));
}

$PAGE->set_url('/mod/airoleplay/submissions.php', ['id' => $id]);
$PAGE->set_title(get_string('submissions_heading', 'mod_airoleplay') . ': ' . format_string($airoleplay->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->css('/mod/airoleplay/styles.css');

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($airoleplay->name) . ' — ' . get_string('submissions_heading', 'mod_airoleplay'));

// Detail view for a single submission.
if ($userid) {
    render_submission_detail($airoleplay, $userid, $id, $context, $cm, $course, $submissionid);
    echo $OUTPUT->footer();
    exit;
}

// Summary table of all submissions.
$userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
$sql = "SELECT s.*, $userfields, u.email
          FROM {airoleplay_submissions} s
          JOIN {user} u ON u.id = s.userid
         WHERE s.airoleplay = :airoleplay
      ORDER BY u.lastname ASC, u.firstname ASC, s.attempt ASC";
$submissions = $DB->get_records_sql($sql, ['airoleplay' => $airoleplay->id]);

if (empty($submissions)) {
    echo $OUTPUT->notification(get_string('no_submissions_yet', 'mod_airoleplay'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Table output.
$table = new html_table();
$table->head = [
    get_string('col_student', 'mod_airoleplay'),
    get_string('col_status', 'mod_airoleplay'),
    get_string('col_submitted', 'mod_airoleplay'),
    get_string('col_grade', 'mod_airoleplay'),
    get_string('col_workflow', 'mod_airoleplay'),
    get_string('col_actions', 'mod_airoleplay'),
];
$table->attributes['class'] = 'generaltable airoleplay-submissions-table';

foreach ($submissions as $sub) {
    $detailparams = ['id' => $id, 'userid' => $sub->userid, 'submissionid' => $sub->id];
    $studentlink = html_writer::link(
        new moodle_url('/mod/airoleplay/submissions.php', $detailparams),
        fullname($sub)
    );
    $submitted     = $sub->timesubmitted ? userdate($sub->timesubmitted) : '—';
    $grade         = isset($sub->final_grade) ? format_float($sub->final_grade, 2) : '—';
    $workflowbadge = '';
    if ($sub->workflow_state) {
        $workflowbadge = html_writer::span(
            get_string('workflow_' . $sub->workflow_state, 'mod_airoleplay'),
            'badge badge-' . $sub->workflow_state
        );
    }

    $deleteurl = new moodle_url('/mod/airoleplay/submissions.php', [
        'id' => $id, 'submissionid' => $sub->id, 'action' => 'delete', 'sesskey' => sesskey(),
    ]);
    $actions = html_writer::link(
        new moodle_url('/mod/airoleplay/submissions.php', $detailparams),
        get_string('view'),
        ['class' => 'btn btn-sm btn-outline-primary me-1']
    );
    if (has_capability('mod/airoleplay:grade', $context)) {
        $actions .= html_writer::link(
            $deleteurl,
            get_string('delete'),
            [
                'class'   => 'btn btn-sm btn-outline-danger',
                'onclick' => 'return confirm(' . json_encode(get_string('confirm_delete_submission', 'mod_airoleplay')) . ');',
            ]
        );
    }

    $table->data[] = [
        $studentlink,
        s($sub->status),
        $submitted,
        $grade,
        $workflowbadge,
        $actions,
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();

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
    $records = $DB->get_records(
        'airoleplay_submissions',
        ['airoleplay' => $airoleplayid, 'userid' => $userid],
        'attempt DESC, id DESC',
        '*',
        0,
        1
    );
    if (empty($records)) {
        throw new \dml_missing_record_exception('airoleplay_submissions');
    }
    return reset($records);
}

/**
 * Renders the detailed view for a single student submission.
 *
 * @param stdClass $airoleplay   Airoleplay instance.
 * @param int      $userid       Student user id.
 * @param int      $cmid         Course module id.
 * @param context  $context      Module context.
 * @param stdClass $cm           Course module record.
 * @param stdClass $course       Course record.
 * @param int      $submissionid Optional explicit submission id (0 = latest attempt).
 */
function render_submission_detail(
    stdClass $airoleplay,
    int $userid,
    int $cmid,
    context $context,
    stdClass $cm,
    stdClass $course,
    int $submissionid = 0
): void {
    global $DB, $OUTPUT, $USER;

    $student    = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    $submission = airoleplay_fetch_submission($airoleplay->id, $userid, $submissionid);
    $submissionid = (int)$submission->id;

    echo $OUTPUT->heading(fullname($student), 3);

    // Breadcrumb + delete button.
    echo html_writer::start_div('d-flex justify-content-between align-items-center mb-3');
    echo html_writer::link(
        new moodle_url('/mod/airoleplay/submissions.php', ['id' => $cmid]),
        '← ' . get_string('submissions_heading', 'mod_airoleplay'),
        ['class' => 'btn btn-sm btn-outline-secondary']
    );
    if (has_capability('mod/airoleplay:grade', $context)) {
        $deleteurl = new moodle_url('/mod/airoleplay/submissions.php', [
            'id'           => $cmid,
            'submissionid' => $submission->id,
            'action'       => 'delete',
            'sesskey'      => sesskey(),
        ]);
        echo html_writer::link(
            $deleteurl,
            get_string('delete_submission', 'mod_airoleplay'),
            [
                'class'   => 'btn btn-sm btn-danger',
                'onclick' => 'return confirm(' . json_encode(get_string('confirm_delete_submission', 'mod_airoleplay')) . ');',
            ]
        );
    }
    echo html_writer::end_div();

    // Roleplay transcript.
    if ($submission->roleplay_transcript) {
        $transcript = json_decode($submission->roleplay_transcript, true);
        if (is_array($transcript) && !empty($transcript)) {
            echo html_writer::start_div('card card-body mb-3');
            echo html_writer::tag('h5', get_string('conversation_log', 'mod_airoleplay'));
            echo html_writer::start_div('airoleplay-log-detail');
            foreach ($transcript as $turn) {
                if (!is_array($turn)) {
                    continue;
                }
                $speakerraw = (string)($turn['speaker'] ?? '');
                $speaker    = s($speakerraw);
                $text       = s((string)($turn['text'] ?? ''));
                $cssclass   = ($speakerraw === 'participant') ? 'participant-turn' : 'avatar-turn';
                echo html_writer::div(
                    html_writer::tag('strong', $speaker . ': ') . $text,
                    'transcript-item ' . $cssclass
                );
            }
            echo html_writer::end_div();
            echo html_writer::end_div();
        }
    }

    // Grade breakdown.
    if ($submission->grade_breakdown) {
        $breakdown = json_decode($submission->grade_breakdown, true);
        if (is_array($breakdown) && !empty($breakdown)) {
            echo html_writer::start_div('card card-body mb-3');
            echo html_writer::tag('h5', get_string('grade_breakdown', 'mod_airoleplay'));
            foreach ($breakdown as $dimension => $data) {
                if (!is_string($dimension) || !is_array($data)) {
                    continue;
                }
                $label = get_string('dimension_' . $dimension, 'mod_airoleplay', $dimension);
                echo html_writer::div(
                    html_writer::tag('strong', $label) .
                    ' — Score: ' . (int)($data['score'] ?? 0) . '/100' .
                    ' (weight: ' . (float)($data['weight'] ?? 0) . ')<br/>' .
                    s((string)($data['feedback'] ?? '')),
                    'mb-2'
                );
            }
            echo html_writer::end_div();
        }
    }

    // Regenerate AI evaluation section.
    if (has_capability('mod/airoleplay:grade', $context)) {
        $ajaxurl    = (new moodle_url('/mod/airoleplay/ajax.php'))->out(false);
        $sesskey    = sesskey();
        $confirmmsg = get_string('regen_confirm', 'mod_airoleplay');
        $runningmsg = get_string('regen_running', 'mod_airoleplay');
        $successmsg = get_string('regen_success', 'mod_airoleplay');

        echo html_writer::start_div('card card-body mb-3 border-warning');
        echo html_writer::tag('h5', get_string('regen_heading', 'mod_airoleplay'));

        echo html_writer::tag('button', get_string('regen_evaluation', 'mod_airoleplay'), [
            'type'              => 'button',
            'class'             => 'btn btn-sm btn-outline-warning airoleplay-regen-btn',
            'data-action'       => 'regen_evaluation',
            'data-submissionid' => $submission->id,
            'data-cmid'         => $cmid,
            'data-sesskey'      => $sesskey,
            'data-ajaxurl'      => $ajaxurl,
            'data-confirm'      => $confirmmsg,
            'data-running'      => $runningmsg,
            'data-success'      => $successmsg,
        ]);

        echo html_writer::tag('div', '', ['id' => 'airoleplay-regen-status', 'class' => 'mt-2']);
        echo html_writer::end_div();

        // Inline JS.
        echo html_writer::script(<<<JS
(function() {
    function makeAlert(level, message) {
        var div = document.createElement('div');
        div.className = 'alert alert-' + level;
        div.textContent = message;
        return div;
    }
    document.querySelectorAll('.airoleplay-regen-btn').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            if (!window.confirm(btn.dataset.confirm)) return;
            var statusEl = document.getElementById('airoleplay-regen-status');
            document.querySelectorAll('.airoleplay-regen-btn').forEach(function(b) { b.disabled = true; });
            statusEl.replaceChildren(makeAlert('info', btn.dataset.running));
            try {
                var resp = await fetch(btn.dataset.ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action:       btn.dataset.action,
                        cmid:         parseInt(btn.dataset.cmid),
                        submissionid: parseInt(btn.dataset.submissionid),
                        sesskey:      btn.dataset.sesskey
                    })
                });
                var data = await resp.json();
                if (data.success) {
                    statusEl.replaceChildren(makeAlert('success', btn.dataset.success));
                    setTimeout(function() { window.location.reload(); }, 1000);
                } else {
                    statusEl.replaceChildren(makeAlert('danger', data.error || 'Unknown error'));
                    document.querySelectorAll('.airoleplay-regen-btn').forEach(function(b) { b.disabled = false; });
                }
            } catch (e) {
                statusEl.replaceChildren(makeAlert('danger', e.message));
                document.querySelectorAll('.airoleplay-regen-btn').forEach(function(b) { b.disabled = false; });
            }
        });
    });
})();
JS);
    }

    // Grade & feedback override form.
    if (has_capability('mod/airoleplay:grade', $context)) {
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => new moodle_url('/mod/airoleplay/submissions.php'),
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cmid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => $userid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'submissionid', 'value' => $submissionid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'savegarde']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::start_div('card card-body mb-3');
        echo html_writer::tag('h5', get_string('grading_header', 'mod_airoleplay'));
        echo html_writer::div(
            html_writer::label(get_string('maximumgrade', 'mod_airoleplay'), 'grade_input') .
            html_writer::empty_tag('input', [
                'type'  => 'number',
                'id'    => 'grade_input',
                'name'  => 'grade',
                'value' => format_float($submission->final_grade ?? 0, 2),
                'class' => 'form-control d-inline-block w-auto ms-2',
                'min'   => 0,
                'max'   => $airoleplay->grade,
                'step'  => '0.01',
            ]),
            'mb-2'
        );
        echo html_writer::div(
            html_writer::label(get_string('feedback', 'mod_airoleplay'), 'feedback_input') .
            html_writer::tag('textarea', s($submission->final_feedback ?? ''), [
                'id'    => 'feedback_input',
                'name'  => 'feedback',
                'class' => 'form-control mt-2',
                'rows'  => 5,
            ]),
            'mb-2'
        );

        // Workflow action buttons.
        if ($airoleplay->grading_workflow && $submission->workflow_state !== 'released') {
            echo html_writer::tag(
                'button',
                get_string('publish_grade', 'mod_airoleplay'),
                ['type' => 'submit', 'class' => 'btn btn-success me-2',
                    'formaction' => new moodle_url('/mod/airoleplay/submissions.php', [
                        'id'           => $cmid,
                        'userid'       => $userid,
                        'submissionid' => $submissionid,
                        'action'       => 'publish',
                        'sesskey'      => sesskey(),
                    ])]
            );
        }
        echo html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary']);
        echo html_writer::end_div();
        echo html_writer::end_tag('form');
    }
}
