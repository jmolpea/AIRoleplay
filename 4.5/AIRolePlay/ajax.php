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
 * AJAX endpoint for all mod_airoleplay frontend requests.
 *
 * All responses are JSON. Session lifecycle:
 *   roleplay_opening  -> starts (or resumes) the attempt and its server-side clock
 *   roleplay_turn     -> one participant line + one avatar reply
 *   roleplay_closing  -> closes the attempt (active -> submitted) and says goodbye
 *   roleplay_finalise -> evaluates the attempt (falls back to a background task)
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/airoleplay/lib.php');
require_once($CFG->libdir . '/completionlib.php');

use mod_airoleplay\local\evaluation_runner;
use mod_airoleplay\local\session_manager;
use mod_airoleplay\local\submission_state;

header('Content-Type: application/json; charset=utf-8');

// Pick exactly one source of truth: a JSON request body when the caller
// declared application/json (the only path the bundled JS uses), or the
// URL/form parameters otherwise. Mixing the two enabled WAF-evading
// parameter pollution attacks where querystring and body disagreed.
$contenttype = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
$isjsonrequest = $contenttype !== '' && str_starts_with($contenttype, 'application/json');

$jsonbody = [];
if ($isjsonrequest) {
    $rawbody  = file_get_contents('php://input');
    $jsonbody = json_decode((string)$rawbody, true);
    if (!is_array($jsonbody)) {
        airoleplay_json_error(get_string('badrequest', 'mod_airoleplay'));
    }
    $action = isset($jsonbody['action']) && is_string($jsonbody['action'])
        ? clean_param($jsonbody['action'], PARAM_ALPHANUMEXT)
        : '';
    $cmid         = isset($jsonbody['cmid']) ? (int)$jsonbody['cmid'] : 0;
    $submissionid = isset($jsonbody['submissionid']) ? (int)$jsonbody['submissionid'] : 0;
} else {
    $action       = required_param('action', PARAM_ALPHANUMEXT);
    $cmid         = required_param('cmid', PARAM_INT);
    $submissionid = optional_param('submissionid', 0, PARAM_INT);
}

try {
    if ($cmid <= 0 || $action === '') {
        // Generic message to avoid confirming the existence of specific cmids.
        airoleplay_json_error(get_string('badrequest', 'mod_airoleplay'));
    }
    $cm         = get_coursemodule_from_id('airoleplay', $cmid, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $airoleplay = $DB->get_record('airoleplay', ['id' => $cm->instance], '*', MUST_EXIST);

    require_login($course, true, $cm);
    $context = context_module::instance($cm->id);

    // License gate — block every AI action when no valid key is bound to this site.
    if (!\mod_airoleplay\license\validator::is_valid()) {
        airoleplay_json_error(\mod_airoleplay\license\validator::get_banner());
    }

    // Every state-changing action requires the session key.
    $mutating = ['roleplay_opening', 'roleplay_turn', 'roleplay_closing', 'roleplay_finalise', 'regen_evaluation'];
    if (in_array($action, $mutating, true)) {
        if ($isjsonrequest) {
            $sesskey = isset($jsonbody['sesskey']) && is_string($jsonbody['sesskey']) ? $jsonbody['sesskey'] : '';
        } else {
            $sesskey = required_param('sesskey', PARAM_RAW);
        }
        if (!confirm_sesskey($sesskey)) {
            airoleplay_json_error(get_string('badrequest', 'mod_airoleplay'));
        }
    }

    // Nothing below writes to the session. Releasing its lock now keeps the
    // AI calls (seconds each) from blocking every other request of the same
    // user, such as Moodle's own notification polling or a second tab.
    \core\session\manager::write_close();

    switch ($action) {
        // Start the session, or resume it after a page reload.
        case 'roleplay_opening':
            require_capability('mod/airoleplay:submit', $context);
            $submission = airoleplay_ajax_own_submission($airoleplay, $submissionid);
            if (!$submission->gdpr_consent) {
                throw new moodle_exception('gdpr_consent_required', 'mod_airoleplay');
            }

            $lock = airoleplay_acquire_submission_lock($submission->id);
            if (!$lock) {
                airoleplay_json_error(get_string('busy', 'mod_airoleplay'));
            }
            $opening = null;
            try {
                // Re-read state under the lock so concurrent callers cannot
                // both observe 'draft' and both start the session.
                $submission = $DB->get_record('airoleplay_submissions', ['id' => $submission->id], '*', MUST_EXIST);
                if ($submission->status === 'draft') {
                    if ($problem = airoleplay_availability_problem($airoleplay, $USER->id)) {
                        throw new moodle_exception($problem, 'mod_airoleplay');
                    }
                    if (airoleplay_attempts_remaining($airoleplay, $USER->id) <= 0) {
                        throw new moodle_exception('noattemptsleft', 'mod_airoleplay');
                    }
                    // Generate the opening line before the attempt starts: if
                    // the AI provider fails (missing key, outage), the attempt
                    // stays unstarted instead of burning the student's time.
                    $conductor = new \mod_airoleplay\api\roleplay_conductor($airoleplay, $submission);
                    if (!$conductor->has_started()) {
                        $opening = $conductor->opening_statement();
                    }
                    submission_state::assert_status_transition('draft', 'active');
                    $DB->set_field('airoleplay_submissions', 'status', 'active', ['id' => $submission->id]);
                    $submission->status = 'active';
                    session_manager::start_clock($submission);
                    \mod_airoleplay\event\submission_created::create([
                        'context'  => $context,
                        'objectid' => $submission->id,
                    ])->trigger();
                } else if ($submission->status !== 'active') {
                    throw new moodle_exception('invalidsubmissionstatus', 'mod_airoleplay');
                }
                session_manager::start_clock($submission);
            } finally {
                $lock->release();
            }

            if ($opening !== null) {
                airoleplay_json_success($opening + [
                    'submissionid' => (int)$submission->id,
                    'remaining'    => session_manager::remaining_seconds($airoleplay, $submission),
                ]);
            }

            $conductor = new \mod_airoleplay\api\roleplay_conductor($airoleplay, $submission);
            $remaining = session_manager::remaining_seconds($airoleplay, $submission);
            if (session_manager::is_expired($airoleplay, $submission)) {
                airoleplay_json_success(['expired' => true, 'submissionid' => (int)$submission->id]);
            }
            if ($conductor->has_started()) {
                airoleplay_json_success([
                    'resume'       => true,
                    'submissionid' => (int)$submission->id,
                    'remaining'    => $remaining,
                    'transcript'   => $conductor->get_transcript_for_client(),
                ]);
            }
            // Active attempt without any turn yet (e.g. started by an older version).
            $result = $conductor->opening_statement();
            airoleplay_json_success($result + [
                'submissionid' => (int)$submission->id,
                'remaining'    => session_manager::remaining_seconds($airoleplay, $submission),
            ]);
            break;

        // One participant line and the avatar's reply.
        case 'roleplay_turn':
            require_capability('mod/airoleplay:submit', $context);
            $submission = airoleplay_ajax_own_submission($airoleplay, $submissionid);
            if ($submission->status !== 'active') {
                throw new moodle_exception('invalidsubmissionstatus', 'mod_airoleplay');
            }
            if (session_manager::is_expired($airoleplay, $submission)) {
                airoleplay_json_success(['timeup' => true]);
            }

            $participantinput = trim(clean_param((string)($jsonbody['response'] ?? ''), PARAM_NOTAGS));
            $participantinput = mb_substr($participantinput, 0, 5000);
            if ($participantinput === '') {
                throw new moodle_exception('emptyresponse', 'mod_airoleplay');
            }
            $suggestedavatar = max(1, min(3, (int)($jsonbody['suggested_avatar'] ?? 1)));
            $preferredavatar = max(0, min(3, (int)($jsonbody['preferred_avatar'] ?? 0)));

            $conductor = new \mod_airoleplay\api\roleplay_conductor($airoleplay, $submission);
            $result    = $conductor->next_turn($suggestedavatar, $participantinput, $preferredavatar);
            airoleplay_json_success($result + ['remaining' => session_manager::remaining_seconds($airoleplay, $submission)]);
            break;

        // Time is up: close the attempt and play the closing line.
        case 'roleplay_closing':
            require_capability('mod/airoleplay:submit', $context);
            $submission = airoleplay_ajax_own_submission($airoleplay, $submissionid);

            // Only the worker that actually performs active -> submitted says
            // goodbye; concurrent callers get the current state back.
            if (!session_manager::close((int)$submission->id)) {
                $status = (string)$DB->get_field('airoleplay_submissions', 'status', ['id' => $submission->id]);
                airoleplay_json_success(['evaluation_status' => $status, 'duplicate' => true]);
            }

            // Safety net: if the browser never asks for the evaluation (tab
            // closed, network lost), the background task grades the attempt.
            session_manager::queue_evaluation((int)$submission->id, (int)$cm->id, 2 * MINSECS);

            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) && !empty($airoleplay->completionsubmit)) {
                $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
            }

            $result = [];
            try {
                $submission->status = 'submitted';
                $conductor = new \mod_airoleplay\api\roleplay_conductor($airoleplay, $submission);
                $result    = $conductor->closing_statement();
            } catch (\Throwable $e) {
                // The attempt is already safely submitted; a missing goodbye
                // line must not surface as an error to the student.
                airoleplay_log_internal_error('closing_statement', $e, ['submissionid' => $submission->id]);
            }
            airoleplay_json_success($result + ['evaluation_status' => 'submitted']);
            break;

        // Evaluate the attempt now (the browser calls this after the goodbye line).
        case 'roleplay_finalise':
            require_capability('mod/airoleplay:submit', $context);
            $submission = airoleplay_ajax_own_submission($airoleplay, $submissionid);
            \core_php_time_limit::raise(300);
            ignore_user_abort(true);
            try {
                evaluation_runner::run((int)$submission->id);
            } catch (\Throwable $e) {
                airoleplay_log_internal_error('evaluation_sync_failed', $e, ['submissionid' => $submission->id]);
                session_manager::queue_evaluation((int)$submission->id, (int)$cm->id);
            }
            $status = (string)$DB->get_field('airoleplay_submissions', 'status', ['id' => $submission->id]);
            airoleplay_json_success(['evaluation_status' => $status]);
            break;

        // Poll evaluation status.
        case 'check_evaluation':
            require_capability('mod/airoleplay:submit', $context);
            // Look up by the full triple (id, airoleplay, userid) so a missing
            // row and a foreign row produce the same generic error — preventing
            // an attacker from enumerating submission ids by response shape.
            $sub = $DB->get_record('airoleplay_submissions', [
                'id'         => $submissionid,
                'airoleplay' => $airoleplay->id,
                'userid'     => $USER->id,
            ], 'id, status');
            if (!$sub) {
                airoleplay_json_error(get_string('badrequest', 'mod_airoleplay'));
            }
            airoleplay_json_success(['status' => $sub->status]);
            break;

        // Teacher: regenerate the evaluation of an attempt.
        case 'regen_evaluation':
            require_capability('mod/airoleplay:grade', $context);
            $sub = $DB->get_record(
                'airoleplay_submissions',
                ['id' => $submissionid, 'airoleplay' => $airoleplay->id],
                '*',
                MUST_EXIST
            );
            if (!in_array($sub->status, ['submitted', 'graded'], true)) {
                throw new moodle_exception('invalidsubmissionstatus', 'mod_airoleplay');
            }
            airoleplay_regen_rate_check($USER->id, $sub->id, 'evaluation');
            \core_php_time_limit::raise(600);
            if ($sub->status === 'submitted') {
                evaluation_runner::run((int)$sub->id);
            } else {
                (new \mod_airoleplay\api\evaluator())->evaluate($sub, $airoleplay, $course, $cm);
            }
            airoleplay_json_success([]);
            break;

        default:
            airoleplay_json_error(get_string('badrequest', 'mod_airoleplay'));
    }
} catch (\moodle_exception $e) {
    // Moodle_exception messages are translated language strings; provider
    // errors carry the vendor detail in debuginfo only, which is logged here
    // and never sent to the browser.
    if (!empty($e->debuginfo)) {
        airoleplay_log_internal_error('ajax_' . $action, $e, ['cmid' => $cmid]);
    }
    airoleplay_json_error($e->getMessage(), $e->errorcode);
} catch (\Throwable $e) {
    airoleplay_log_internal_error('ajax_dispatch', $e, ['cmid' => $cmid]);
    airoleplay_json_error(get_string('unexpectederror', 'error'));
}

/**
 * Outputs a JSON success response and exits.
 *
 * @param array $data Response payload.
 */
function airoleplay_json_success(array $data): never {
    echo json_encode(['success' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Outputs a JSON error response and exits.
 *
 * @param string $message   Error message (already translated).
 * @param string $errorcode Machine-readable error code for the frontend.
 */
function airoleplay_json_error(string $message, string $errorcode = ''): never {
    echo json_encode(['success' => false, 'error' => $message, 'errorcode' => $errorcode], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Loads the current user's attempt for this activity.
 *
 * With an explicit id, the attempt must belong to the user and the activity;
 * otherwise the user's latest attempt is returned.
 *
 * @param stdClass $airoleplay   Activity record.
 * @param int      $submissionid Attempt id sent by the browser (0 = latest).
 * @return stdClass
 * @throws moodle_exception when the user has no attempt yet (consent missing).
 */
function airoleplay_ajax_own_submission(stdClass $airoleplay, int $submissionid): stdClass {
    global $DB, $USER;

    if ($submissionid > 0) {
        $submission = $DB->get_record('airoleplay_submissions', [
            'id'         => $submissionid,
            'airoleplay' => $airoleplay->id,
            'userid'     => $USER->id,
        ]);
    } else {
        $submission = airoleplay_get_latest_attempt((int)$airoleplay->id, (int)$USER->id);
    }
    if (!$submission) {
        throw new moodle_exception('gdpr_consent_required', 'mod_airoleplay');
    }
    return $submission;
}
