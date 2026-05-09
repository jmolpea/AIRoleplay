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
 * Main participant view for mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/airoleplay/lib.php');

$id     = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);

if ($action === 'gdpr_consent' || $action === 'gdpr_revoke') {
    require_sesskey();
}

$cm         = get_coursemodule_from_id('airoleplay', $id, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$airoleplay = $DB->get_record('airoleplay', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/airoleplay:view', $context);

\mod_airoleplay\event\course_module_viewed::create_from_cm($cm, $course, $airoleplay)->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$userid  = $USER->id;
$groupid = 0;
if ($airoleplay->group_submission) {
    $group   = groups_get_activity_group($cm, true);
    $groupid = $group ?: 0;
}

$submission = $DB->get_record('airoleplay_submissions', [
    'airoleplay' => $airoleplay->id,
    'userid'     => $userid,
    'attempt'    => $DB->get_field_sql(
        'SELECT COALESCE(MAX(attempt), 0) FROM {airoleplay_submissions} WHERE airoleplay = ? AND userid = ?',
        [$airoleplay->id, $userid]
    ) ?: 1,
]);

// Handle GDPR consent action.
if ($action === 'gdpr_consent') {
    $consentgiven = required_param('consent', PARAM_INT);
    if ($consentgiven == 1) {
        if (!$submission) {
            $submission                    = new stdClass();
            $submission->airoleplay        = $airoleplay->id;
            $submission->userid            = $userid;
            $submission->groupid           = $groupid;
            $submission->status            = 'draft';
            $submission->attempt           = 1;
            $submission->gdpr_consent      = 1;
            $submission->gdpr_consent_time = time();
            $submission->timecreated       = time();
            $submission->timemodified      = time();
            $submission->id = $DB->insert_record('airoleplay_submissions', $submission);
        } else {
            $DB->set_field('airoleplay_submissions', 'gdpr_consent', 1, ['id' => $submission->id]);
            $DB->set_field('airoleplay_submissions', 'gdpr_consent_time', time(), ['id' => $submission->id]);
            $submission->gdpr_consent = 1;
        }
    }
    redirect(new moodle_url('/mod/airoleplay/view.php', ['id' => $id]));
}

// Handle GDPR consent withdrawal (Art. 7(3) RGPD).
if ($action === 'gdpr_revoke') {
    $usersubs = $DB->get_records('airoleplay_submissions', [
        'airoleplay' => $airoleplay->id,
        'userid'     => $userid,
    ]);
    foreach ($usersubs as $usersub) {
        $DB->delete_records('airoleplay_messages', ['submission_id' => $usersub->id]);
    }
    $DB->delete_records('airoleplay_submissions', [
        'airoleplay' => $airoleplay->id,
        'userid'     => $userid,
    ]);
    \airoleplay_update_grades($airoleplay, $userid);
    redirect(
        new moodle_url('/mod/airoleplay/view.php', ['id' => $id]),
        get_string('gdpr_revoked_notice', 'mod_airoleplay'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$attemptsused      = (int)$DB->count_records_select('airoleplay_submissions', 'airoleplay = ? AND userid = ?', [$airoleplay->id, $userid]);
$maxattempts       = (int)$airoleplay->max_attempts;
$cansubmit         = has_capability('mod/airoleplay:submit', $context);
$attemptsremaining = ($maxattempts === 0) ? PHP_INT_MAX : max(0, $maxattempts - $attemptsused);

// Map Moodle lang code to BCP-47 for the Web Speech API.
$moodlelang = current_language();
$langbcp47map = [
    'es'    => 'es-ES', 'es_es' => 'es-ES', 'pt_br' => 'pt-BR',
    'pt'    => 'pt-PT', 'fr'    => 'fr-FR', 'de'    => 'de-DE',
    'it'    => 'it-IT', 'ca'    => 'ca-ES', 'eu'    => 'eu-ES',
    'gl'    => 'gl-ES', 'en'    => 'en-US',
];
$speechlang = $langbcp47map[$moodlelang] ?? str_replace('_', '-', $moodlelang);

// Build avatar names array for JS (used for avatar addressing detection).
$numavatars  = max(1, min(3, (int)($airoleplay->num_avatars ?? 1)));
$avatarnames = [];
for ($i = 1; $i <= $numavatars; $i++) {
    $nf = "avatar_{$i}_name";
    $avatarnames[] = ['index' => $i, 'name' => s($airoleplay->$nf ?? "Avatar {$i}")];
}

$PAGE->set_url('/mod/airoleplay/view.php', ['id' => $id]);
$PAGE->set_title(format_string($airoleplay->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$PAGE->requires->css('/mod/airoleplay/styles.css');
$PAGE->requires->js_call_amd('mod_airoleplay/roleplay', 'init', [[
    'cmid'             => $cm->id,
    'sesskey'          => sesskey(),
    'submissionid'     => $submission ? $submission->id : 0,
    'durationmins'     => (int)$airoleplay->session_duration,
    'numavatars'       => $numavatars,
    'avatarnames'      => $avatarnames,
    'speechlang'       => $speechlang,
    'submissionstatus' => $submission ? $submission->status : 'draft',
]]);

echo $OUTPUT->header();

echo html_writer::start_div('airoleplay-container');

// Attempts info.
$attemptsinfo = get_string('attemptsinfo', 'mod_airoleplay', [
    'used'      => $attemptsused,
    'remaining' => ($maxattempts === 0) ? get_string('unlimited', 'mod_airoleplay') : $attemptsremaining,
    'max'       => ($maxattempts === 0) ? get_string('unlimited', 'mod_airoleplay') : $maxattempts,
]);
echo html_writer::div($attemptsinfo, 'airoleplay-attempts-info');

// GDPR Consent (shown if not yet given).
if ($cansubmit && (!$submission || !$submission->gdpr_consent)) {
    $gdprtext = get_string('gdpr_default_notice', 'mod_airoleplay');

    echo html_writer::start_div('airoleplay-gdpr-notice card');
    echo html_writer::div(
        html_writer::tag('h4', get_string('gdpr_notice_title', 'mod_airoleplay')) . $gdprtext,
        'card-body'
    );

    $consentform  = html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/mod/airoleplay/view.php', ['id' => $id]),
    ]);
    $consentform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'gdpr_consent']);
    $consentform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $consentform .= html_writer::div(
        html_writer::checkbox('consent', 1, false, get_string('gdpr_consent_label', 'mod_airoleplay'),
            ['id' => 'airoleplay_gdpr_consent', 'required' => 'required']) .
        html_writer::tag('button', get_string('start_activity', 'mod_airoleplay'),
            ['type' => 'submit', 'class' => 'btn btn-primary btn-lg mt-3', 'id' => 'airoleplay_start_btn']),
        'card-footer'
    );
    $consentform .= html_writer::end_tag('form');
    echo $consentform;
    echo html_writer::end_div();
}

// Main activity panels (shown when GDPR accepted).
if ($submission && $submission->gdpr_consent) {

    // Scenario context card (shown before session and during).
    $showscenario = !in_array($submission->status, ['graded']);
    if ($showscenario) {
        echo html_writer::start_div('airoleplay-scenario-card card mb-3');
        echo html_writer::start_div('card-body');

        if ($airoleplay->scenario_description) {
            echo html_writer::tag('h5', get_string('scenario_label', 'mod_airoleplay'), ['class' => 'card-title']);
            echo html_writer::div(
                format_text($airoleplay->scenario_description, $airoleplay->scenario_descriptionformat),
                'airoleplay-scenario-text'
            );
        }

        if ($airoleplay->participant_role) {
            echo html_writer::tag('h6', get_string('your_role_label', 'mod_airoleplay'), ['class' => 'mt-3 fw-bold']);
            echo html_writer::div(
                format_text($airoleplay->participant_role, FORMAT_HTML),
                'airoleplay-participant-role alert alert-info'
            );
        }

        echo html_writer::end_div();
        echo html_writer::end_div();
    }

    // Evaluating panel.
    $evaluatingstatuses = ['submitted', 'grading'];
    $evalvisible = in_array($submission->status, $evaluatingstatuses) ? '' : 'd-none';
    echo html_writer::start_div("airoleplay-panel text-center py-5 {$evalvisible}", ['id' => 'airoleplay_evaluating_panel']);
    echo html_writer::tag('div', '', ['class' => 'spinner-border text-primary mb-3', 'role' => 'status']);
    echo html_writer::tag('p', get_string('evaluation_pending', 'mod_airoleplay'), ['class' => 'lead']);
    echo html_writer::div('', 'airoleplay-status-message', ['id' => 'airoleplay_eval_status']);
    echo html_writer::end_div();

    // Roleplay panel (the main interaction UI).
    $rpexcluded = ['submitted', 'grading', 'graded'];
    $rpvisible  = !in_array($submission->status, $rpexcluded) ? '' : 'd-none';
    echo html_writer::start_div("airoleplay-panel airoleplay-roleplay {$rpvisible}", ['id' => 'airoleplay_roleplay_panel']);
    echo html_writer::start_div('airoleplay-room');

    // Header with timer.
    echo html_writer::start_div('airoleplay-header');
    echo html_writer::tag('h3', format_string($airoleplay->name), ['class' => 'airoleplay-title']);
    echo html_writer::div('', 'airoleplay-timer', ['id' => 'airoleplay_timer']);
    echo html_writer::end_div();

    // Avatar panel — only show avatars up to num_avatars.
    echo html_writer::start_div('airoleplay-avatars');
    for ($m = 1; $m <= $numavatars; $m++) {
        $namefield   = "avatar_{$m}_name";
        $rolefield   = "avatar_{$m}_role";
        $avatarfield = "avatar_{$m}_avatar";

        echo html_writer::start_div('airoleplay-avatar', ['id' => "airoleplay_avatar_{$m}", 'data-avatar' => $m]);
        $avatarnum = (int)($airoleplay->$avatarfield);
        if ($avatarnum >= 1 && $avatarnum <= 3) {
            $pixbase    = (new moodle_url('/mod/airoleplay/pix/avatars/'))->out(false);
            $idleurl    = $pixbase . "avatar_{$avatarnum}_idle.mp4";
            $talkingurl = $pixbase . "avatar_{$avatarnum}_talking.mp4";
            $posterurl  = $pixbase . "avatar_{$avatarnum}_poster.png";
            $videotag   = html_writer::tag('video', '', [
                'id'           => "avatar_video_{$m}",
                'class'        => 'avatar-video',
                'src'          => $idleurl,
                'poster'       => $posterurl,
                'loop'         => 'loop',
                'autoplay'     => 'autoplay',
                'muted'        => 'muted',
                'playsinline'  => 'playsinline',
                'data-idle'    => $idleurl,
                'data-talking' => $talkingurl,
            ]);
            echo html_writer::div($videotag, 'avatar-glow');
        } else {
            $avatarfile = $CFG->dirroot . "/mod/airoleplay/pix/avatars/avatar_1.svg";
            $avatarsvg  = file_exists($avatarfile) ? file_get_contents($avatarfile) : '';
            echo html_writer::div($avatarsvg, 'avatar-glow');
        }
        echo html_writer::div(
            html_writer::tag('strong', s($airoleplay->$namefield)) .
            html_writer::tag('span', s($airoleplay->$rolefield), ['class' => 'avatar-role']),
            'avatar-info'
        );
        echo html_writer::end_div();
    }
    echo html_writer::end_div(); // .airoleplay-avatars

    // Transcript area.
    echo html_writer::div('', 'airoleplay-transcript', ['id' => 'airoleplay_transcript']);

    // Participant controls.
    echo html_writer::start_div('airoleplay-participant-controls');
    echo html_writer::div('', 'participant-waveform', ['id' => 'airoleplay_waveform']);
    echo html_writer::tag(
        'button',
        '🎤 ' . get_string('push_to_talk', 'mod_airoleplay'),
        [
            'type'  => 'button',
            'class' => 'btn btn-primary btn-lg push-to-talk d-none',
            'id'    => 'airoleplay_ptt_btn',
        ]
    );
    echo html_writer::div('', 'airoleplay-status-message', ['id' => 'airoleplay_status']);
    echo html_writer::end_div();

    // Sidebar: conversation log.
    echo html_writer::start_div('airoleplay-sidebar', ['id' => 'airoleplay_sidebar']);
    echo $OUTPUT->heading(get_string('conversation_log', 'mod_airoleplay'), 4);
    echo html_writer::div('', 'conversation-log', ['id' => 'airoleplay_conversation_log']);
    echo html_writer::end_div();

    echo html_writer::end_div(); // .airoleplay-room
    echo html_writer::end_div(); // .airoleplay-roleplay

    // Graded state display.
    if ($submission->status === 'graded' && isset($submission->final_grade)) {
        echo html_writer::start_div('airoleplay-results card mt-4');
        echo html_writer::start_div('card-body');
        echo $OUTPUT->heading(get_string('results_title', 'mod_airoleplay'), 3);

        if ($submission->workflow_state === 'released') {
            echo html_writer::div(
                get_string('your_grade', 'mod_airoleplay') . ' ' .
                html_writer::tag('strong', format_float($submission->final_grade, 2)),
                'airoleplay-final-grade alert alert-success'
            );

            if ($submission->final_feedback) {
                // Feedback comes from the AI evaluator (or from a teacher
                // override saved through submissions.php). Render as plain
                // text so a hostile transcript that survived prompt-injection
                // checks cannot smuggle HTML into the student's screen.
                echo html_writer::div(
                    html_writer::tag('h5', get_string('feedback', 'mod_airoleplay')) .
                    format_text($submission->final_feedback, FORMAT_PLAIN, ['context' => $context]),
                    'airoleplay-feedback mt-3'
                );
            }

            // Show grade breakdown if available.
            if ($submission->grade_breakdown) {
                $breakdown = json_decode($submission->grade_breakdown, true);
                if (is_array($breakdown) && !empty($breakdown)) {
                    echo html_writer::tag('h5', get_string('grade_breakdown', 'mod_airoleplay'), ['class' => 'mt-4']);
                    echo html_writer::start_tag('ul', ['class' => 'list-group']);
                    foreach ($breakdown as $dimension => $data) {
                        if (!is_string($dimension) || !is_array($data)) {
                            continue;
                        }
                        $label = get_string('dimension_' . $dimension, 'mod_airoleplay', $dimension);
                        echo html_writer::tag('li',
                            html_writer::tag('strong', $label) . ': ' .
                            (int)($data['score'] ?? 0) . '/100 — ' .
                            s((string)($data['feedback'] ?? '')),
                            ['class' => 'list-group-item']
                        );
                    }
                    echo html_writer::end_tag('ul');
                }
            }
        } else {
            echo html_writer::div(
                get_string('grade_pending_review', 'mod_airoleplay'),
                'alert alert-info'
            );
        }

        echo html_writer::end_div();
        echo html_writer::end_div();
    }

    // GDPR Art. 7(3): the participant must be able to withdraw consent
    // at any time and trigger deletion of their data on this activity.
    $revokeurl = new moodle_url('/mod/airoleplay/view.php', [
        'id'      => $id,
        'action'  => 'gdpr_revoke',
        'sesskey' => sesskey(),
    ]);
    echo html_writer::start_div('airoleplay-gdpr-revoke mt-4 text-end');
    echo html_writer::link(
        $revokeurl,
        get_string('gdpr_revoke_button', 'mod_airoleplay'),
        [
            'class'   => 'btn btn-sm btn-outline-secondary',
            'onclick' => 'return confirm(' . json_encode(get_string('gdpr_revoke_confirm', 'mod_airoleplay')) . ');',
        ]
    );
    echo html_writer::end_div();
}

echo html_writer::end_div(); // .airoleplay-container

echo $OUTPUT->footer();
