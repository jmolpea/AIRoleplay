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
require_once($CFG->libdir . '/completionlib.php');

use mod_airoleplay\form\mod_form_helper;
use mod_airoleplay\local\session_manager;

$id     = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);

$cm         = get_coursemodule_from_id('airoleplay', $id, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$airoleplay = $DB->get_record('airoleplay', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/airoleplay:view', $context);

$pageurl = new moodle_url('/mod/airoleplay/view.php', ['id' => $id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($airoleplay->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->add_body_class('limitedwidth');

// License gate. Without a valid key bound to this site the activity is blocked
// (a message is shown instead of the activity). Done before firing events,
// completion or submission/GDPR handling so no side effects occur while
// unlicensed. A site administrator can still reach the plugin settings to
// paste a key.
if (!\mod_airoleplay\license\validator::is_valid()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        \mod_airoleplay\license\validator::get_banner(),
        \core\output\notification::NOTIFY_ERROR
    );
    echo $OUTPUT->footer();
    exit;
}

$userid    = (int)$USER->id;
$cansubmit = has_capability('mod/airoleplay:submit', $context);
$groupid   = 0;
if ($airoleplay->group_submission) {
    $groupid = (int)groups_get_activity_group($cm, true);
}
$latest = airoleplay_get_latest_attempt((int)$airoleplay->id, $userid);

// Participant actions (all POST/GET with sesskey, all redirect).
if ($action !== '' && $cansubmit) {
    require_sesskey();

    if ($action === 'gdpr_consent') {
        if (optional_param('consent', 0, PARAM_INT) === 1) {
            if (!$latest) {
                $now = time();
                $DB->insert_record('airoleplay_submissions', (object)[
                    'airoleplay'        => $airoleplay->id,
                    'userid'            => $userid,
                    'groupid'           => $groupid,
                    'status'            => 'draft',
                    'attempt'           => 1,
                    'gdpr_consent'      => 1,
                    'gdpr_consent_time' => $now,
                    'timecreated'       => $now,
                    'timemodified'      => $now,
                ]);
            } else {
                $DB->set_field('airoleplay_submissions', 'gdpr_consent', 1, ['airoleplay' => $airoleplay->id, 'userid' => $userid]);
                $DB->set_field(
                    'airoleplay_submissions',
                    'gdpr_consent_time',
                    time(),
                    ['airoleplay' => $airoleplay->id, 'userid' => $userid]
                );
            }
        }
        redirect($pageurl);
    }

    if ($action === 'newattempt') {
        airoleplay_start_new_attempt($airoleplay, $userid, $groupid);
        redirect($pageurl);
    }

    if ($action === 'gdpr_revoke') {
        if ($latest && in_array($latest->status, ['active', 'submitted', 'grading'], true)) {
            redirect(
                $pageurl,
                get_string('gdpr_revoke_busy', 'mod_airoleplay'),
                null,
                \core\output\notification::NOTIFY_WARNING
            );
        }
        // Withdrawing consent (GDPR Art. 7(3)) erases everything the AI
        // processed: transcripts, messages and generated feedback. The attempt
        // count and the grade already recorded stay, as the institution's
        // assessment record, so withdrawing consent cannot be used to reset
        // attempts. An administrator can still erase everything through
        // Moodle's privacy tools.
        $usersubs = $DB->get_records('airoleplay_submissions', ['airoleplay' => $airoleplay->id, 'userid' => $userid]);
        foreach ($usersubs as $usersub) {
            $DB->delete_records('airoleplay_messages', ['submission_id' => $usersub->id]);
            if ($usersub->status === 'draft') {
                $DB->delete_records('airoleplay_submissions', ['id' => $usersub->id]);
                continue;
            }
            $DB->update_record('airoleplay_submissions', (object)[
                'id'                  => $usersub->id,
                'gdpr_consent'        => 0,
                'roleplay_transcript' => null,
                'roleplay_analysis'   => null,
                'grade_breakdown'     => null,
                'final_feedback'      => null,
                'timemodified'        => time(),
            ]);
        }
        redirect(
            $pageurl,
            get_string('gdpr_revoked_notice', 'mod_airoleplay'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

\mod_airoleplay\event\course_module_viewed::create_from_cm($cm, $course, $airoleplay)->trigger();
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$settings          = airoleplay_get_effective_settings($airoleplay, $userid);
$attemptsremaining = airoleplay_attempts_remaining($airoleplay, $userid);
$availability      = airoleplay_availability_problem($airoleplay, $userid);
$status            = $latest ? (string)$latest->status : '';
$hasconsent        = $latest && $latest->gdpr_consent;

// The roleplay room is offered for a draft attempt that may start, or for an
// active attempt that can be resumed (even after the closing date).
$canstart = $cansubmit && $hasconsent && (
    $status === 'active'
    || ($status === 'draft' && !$availability && $attemptsremaining > 0)
);

$numavatars  = max(1, min(3, (int)($airoleplay->num_avatars ?? 1)));
$avatarnames = [];
for ($i = 1; $i <= $numavatars; $i++) {
    $nf = "avatar_{$i}_name";
    $avatarnames[] = [
        'index' => $i,
        'name'  => format_string(
            trim((string)($airoleplay->$nf ?? '')) ?: get_string('avatar_default_name', 'mod_airoleplay', $i),
            true,
            ['context' => $context]
        ),
    ];
}

if ($canstart || in_array($status, ['submitted', 'grading'], true)) {
    $PAGE->requires->js_call_amd('mod_airoleplay/roleplay', 'init', [[
        'cmid'             => (int)$cm->id,
        'submissionid'     => $latest ? (int)$latest->id : 0,
        'durationmins'     => (int)$airoleplay->session_duration,
        'remaining'        => $latest ? session_manager::remaining_seconds($airoleplay, $latest) : 0,
        'numavatars'       => $numavatars,
        'avatarnames'      => $avatarnames,
        'speechlang'       => airoleplay_speech_language(current_language()),
        'submissionstatus' => $status ?: 'draft',
        'ttsmode'          => mod_form_helper::tts_provider(),
    ]]);
}

echo $OUTPUT->header();

echo html_writer::start_div('airoleplay-container');

// Attempts and availability.
if ($cansubmit) {
    $maxlabel = $settings->max_attempts === 0 ? get_string('unlimited', 'mod_airoleplay') : $settings->max_attempts;
    $used     = $latest ? (int)$DB->count_records_select(
        'airoleplay_submissions',
        'airoleplay = ? AND userid = ? AND status <> ?',
        [$airoleplay->id, $userid, 'draft']
    ) : 0;
    echo html_writer::div(get_string('attemptsinfo', 'mod_airoleplay', [
        'used'      => $used,
        'remaining' => $settings->max_attempts === 0 ? get_string('unlimited', 'mod_airoleplay') : $attemptsremaining,
        'max'       => $maxlabel,
    ]), 'airoleplay-attempts-info');

    if ($availability && $status !== 'active') {
        $date = $availability === 'notopenyet' ? $settings->timeopen : $settings->timeclose;
        echo $OUTPUT->notification(
            get_string($availability . '_date', 'mod_airoleplay', userdate($date)),
            \core\output\notification::NOTIFY_INFO
        );
    }
} else if (has_capability('mod/airoleplay:viewallsubmissions', $context)) {
    echo $OUTPUT->notification(
        html_writer::link(
            new moodle_url('/mod/airoleplay/submissions.php', ['id' => $cm->id]),
            get_string('teacher_view_submissions', 'mod_airoleplay')
        ),
        \core\output\notification::NOTIFY_INFO
    );
}

// Data-processing consent (shown until given).
if ($cansubmit && !$hasconsent) {
    echo $OUTPUT->render(new \mod_airoleplay\output\consent($pageurl));
}

if ($hasconsent && $cansubmit) {
    echo $OUTPUT->render(new \mod_airoleplay\output\room(
        $airoleplay,
        $context,
        $canstart,
        in_array($status, ['submitted', 'grading'], true)
    ));

    $newattempturl = ($status === 'graded' && $attemptsremaining > 0 && !$availability) ? $pageurl : null;

    // GDPR Art. 7(3): the participant must be able to withdraw consent.
    $revokebutton = '';
    if (!in_array($status, ['active', 'submitted', 'grading'], true)) {
        $revoke = new single_button(
            new moodle_url($pageurl, ['action' => 'gdpr_revoke', 'sesskey' => sesskey()]),
            get_string('gdpr_revoke_button', 'mod_airoleplay'),
            'post'
        );
        $revoke->add_confirm_action(get_string('gdpr_revoke_confirm', 'mod_airoleplay'));
        $revokebutton = $OUTPUT->render($revoke);
    }

    echo $OUTPUT->render(new \mod_airoleplay\output\results(
        $status === 'graded' ? $latest : null,
        $context,
        $newattempturl,
        $revokebutton
    ));
}

echo html_writer::end_div(); // End of .airoleplay-container.

echo $OUTPUT->footer();
