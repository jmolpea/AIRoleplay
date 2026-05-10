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
 * All responses are JSON.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/airoleplay/lib.php');

header('Content-Type: application/json');

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
        echo json_encode(['success' => false, 'error' => 'Malformed JSON body']);
        exit;
    }
}

if ($isjsonrequest) {
    $action = isset($jsonbody['action']) && is_string($jsonbody['action'])
        ? clean_param($jsonbody['action'], PARAM_ALPHANUMEXT)
        : '';
    if ($action === '') {
        echo json_encode(['success' => false, 'error' => 'Missing action']);
        exit;
    }
    $cmid         = isset($jsonbody['cmid']) ? (int)$jsonbody['cmid'] : 0;
    $submissionid = isset($jsonbody['submissionid']) ? (int)$jsonbody['submissionid'] : 0;
} else {
    $action       = required_param('action', PARAM_ALPHANUMEXT);
    $cmid         = required_param('cmid', PARAM_INT);
    $submissionid = optional_param('submissionid', 0, PARAM_INT);
}

try {
    if ($cmid <= 0) {
        // Generic message to avoid confirming the existence of specific cmids.
        json_error(get_string('badrequest', 'mod_airoleplay'));
    }
    $cm         = get_coursemodule_from_id('airoleplay', $cmid, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $airoleplay = $DB->get_record('airoleplay', ['id' => $cm->instance], '*', MUST_EXIST);

    require_login($course, true, $cm);
    $context = context_module::instance($cm->id);

    // POST-mutating actions require sesskey.
    $mutating = ['roleplay_opening', 'roleplay_turn', 'roleplay_closing', 'regen_evaluation'];
    if (in_array($action, $mutating, true)) {
        if ($isjsonrequest) {
            $sesskey = isset($jsonbody['sesskey']) && is_string($jsonbody['sesskey'])
                ? $jsonbody['sesskey']
                : '';
        } else {
            $sesskey = required_param('sesskey', PARAM_RAW);
        }
        if (!confirm_sesskey($sesskey)) {
            json_error('Invalid session key');
        }
    }

    switch ($action) {

        // Roleplay: opening statement from avatar 1.
        case 'roleplay_opening':
            require_capability('mod/airoleplay:submit', $context);
            $submission = get_or_create_submission($airoleplay, $cm, $context);

            $lock = airoleplay_acquire_submission_lock($submission->id);
            if (!$lock) {
                json_error('Submission is busy, please retry');
            }
            try {
                // Re-read state under the lock so concurrent callers cannot
                // both observe 'draft' and both fire submission_created.
                $current = $DB->get_record(
                    'airoleplay_submissions',
                    ['id' => $submission->id],
                    'id, status',
                    MUST_EXIST
                );
                if ($current->status === 'draft') {
                    \mod_airoleplay\local\submission_state::assert_status_transition($current->status, 'active');
                    $DB->set_field('airoleplay_submissions', 'status', 'active', ['id' => $submission->id]);
                    $submission->status = 'active';
                    \mod_airoleplay\event\submission_created::create([
                        'context'  => $context,
                        'objectid' => $submission->id,
                        'userid'   => $USER->id,
                    ])->trigger();
                } else {
                    $submission->status = $current->status;
                }
            } finally {
                $lock->release();
            }

            $conductor = new \mod_airoleplay\api\roleplay_conductor($airoleplay, $submission);
            $result    = $conductor->opening_statement();
            echo json_encode(array_merge(['success' => true], $result));
            break;

        // Roleplay: next avatar turn.
        case 'roleplay_turn':
            require_capability('mod/airoleplay:submit', $context);
            $submission = $DB->get_record(
                'airoleplay_submissions',
                ['id' => $submissionid, 'airoleplay' => $airoleplay->id, 'userid' => $USER->id],
                '*',
                MUST_EXIST
            );

            $lock = airoleplay_acquire_submission_lock($submission->id);
            if (!$lock) {
                json_error('Submission is busy, please retry');
            }
            try {
                $current = $DB->get_record(
                    'airoleplay_submissions',
                    ['id' => $submission->id],
                    'id, status',
                    MUST_EXIST
                );
                if (!in_array($current->status, ['active', 'draft'], true)) {
                    throw new \moodle_exception('invalidsubmissionstatus', 'mod_airoleplay');
                }
                if ($current->status === 'draft') {
                    \mod_airoleplay\local\submission_state::assert_status_transition($current->status, 'active');
                    $DB->set_field('airoleplay_submissions', 'status', 'active', ['id' => $submission->id]);
                }
                $submission->status = 'active';
            } finally {
                $lock->release();
            }

            $participantinput = mb_substr(trim($jsonbody['response'] ?? ''), 0, 5000);
            $suggestedavatar  = max(1, min(3, (int)($jsonbody['suggested_avatar'] ?? 1)));
            $preferredavatar  = max(0, min(3, (int)($jsonbody['preferred_avatar'] ?? 0)));
            $turn             = max(1, (int)($jsonbody['turn'] ?? 1));

            $conductor = new \mod_airoleplay\api\roleplay_conductor($airoleplay, $submission);
            $result    = $conductor->next_turn($suggestedavatar, $participantinput, $turn, $preferredavatar);
            echo json_encode(array_merge(['success' => true], $result));
            break;

        // Roleplay: closing statement (time's up).
        case 'roleplay_closing':
            require_capability('mod/airoleplay:submit', $context);
            $submission = $DB->get_record(
                'airoleplay_submissions',
                ['id' => $submissionid, 'airoleplay' => $airoleplay->id, 'userid' => $USER->id],
                '*',
                MUST_EXIST
            );

            $turn = (int)($jsonbody['turn'] ?? 0);

            // Claim the submission under the lock. Only the worker that
            // actually transitions active/draft -> submitted runs the
            // closing statement and the evaluator; every concurrent caller
            // sees a non-active state and bails idempotently.
            $lock = airoleplay_acquire_submission_lock($submission->id);
            if (!$lock) {
                json_error('Submission is busy, please retry');
            }
            $iswinner = false;
            try {
                $current = $DB->get_record(
                    'airoleplay_submissions',
                    ['id' => $submission->id],
                    '*',
                    MUST_EXIST
                );
                if (!in_array($current->status, ['active', 'draft'], true)) {
                    // Already closed by another worker — return its outcome
                    // without retriggering the evaluator.
                    echo json_encode([
                        'success'           => true,
                        'evaluation_status' => $current->status,
                        'duplicate'         => true,
                    ]);
                    break;
                }
                \mod_airoleplay\local\submission_state::assert_status_transition($current->status, 'submitted');
                $now = time();
                $DB->set_field('airoleplay_submissions', 'status', 'submitted', ['id' => $submission->id]);
                $DB->set_field('airoleplay_submissions', 'timesubmitted', $now, ['id' => $submission->id]);
                $submission->status        = 'submitted';
                $submission->timesubmitted = $now;
                $iswinner = true;
            } finally {
                $lock->release();
            }

            $conductor = new \mod_airoleplay\api\roleplay_conductor($airoleplay, $submission);
            $result    = $conductor->closing_statement($turn);

            // Run evaluation synchronously.
            \core_php_time_limit::raise(300);
            $evalstatus = 'submitted';
            try {
                $evaluator = new \mod_airoleplay\api\evaluator();
                $evaluator->evaluate($submission, $airoleplay, $course, $cm);
                $evalstatus = 'graded';
            } catch (\Throwable $evalerr) {
                airoleplay_log_internal_error(
                    'evaluation_sync_failed',
                    $evalerr,
                    ['submissionid' => $submission->id]
                );
                $task = new \mod_airoleplay\task\evaluate_submission_task();
                $task->set_custom_data(['submissionid' => $submission->id, 'cmid' => $cmid]);
                \core\task\manager::queue_adhoc_task($task);
            }

            $result['evaluation_status'] = $evalstatus;
            echo json_encode(array_merge(['success' => true], $result));
            break;

        // Poll evaluation status.
        case 'check_evaluation':
            require_capability('mod/airoleplay:submit', $context);
            // Look up by the full triple (id, airoleplay, userid) so a missing
            // row and a foreign row produce the same generic 4xx — preventing
            // an attacker from enumerating submission ids by response shape.
            $sub = $DB->get_record('airoleplay_submissions', [
                'id'         => $submissionid,
                'airoleplay' => $airoleplay->id,
                'userid'     => $USER->id,
            ]);
            if (!$sub) {
                json_error(get_string('badrequest', 'mod_airoleplay'));
            }
            echo json_encode(['status' => $sub->status]);
            break;

        // Teacher: Regenerate final evaluation.
        case 'regen_evaluation':
            require_capability('mod/airoleplay:grade', $context);
            $sub = $DB->get_record('airoleplay_submissions', ['id' => $submissionid, 'airoleplay' => $airoleplay->id], '*', MUST_EXIST);
            regen_rate_check($USER->id, $sub->id, 'evaluation');
            \core_php_time_limit::raise(600);
            $evaluator = new \mod_airoleplay\api\evaluator();
            $evaluator->evaluate($sub, $airoleplay, $course, $cm);
            echo json_encode(['success' => true]);
            break;

        default:
            // Server-side log records the rejected action; the response stays generic.
            error_log('[mod_airoleplay] rejected unknown ajax action: ' . $action);
            json_error(get_string('badrequest', 'mod_airoleplay'));
    }
} catch (\moodle_exception $e) {
    // moodle_exception messages are already translated language strings
    // safe to surface to the caller; no internal details leak.
    json_error($e->getMessage());
} catch (\Throwable $e) {
    airoleplay_log_internal_error('ajax_dispatch', $e, ['cmid' => $cmid]);
    json_error(get_string('unexpectederror', 'error'));
}

/**
 * Outputs a JSON error response and exits.
 *
 * @param string $message Error message.
 */
function json_error(string $message): never {
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

/**
 * Backwards-compatible alias for {@see airoleplay_regen_rate_check()}.
 *
 * The body moved to lib.php so PHPUnit can call it without booting this
 * AJAX dispatcher; this thin wrapper keeps the historical name.
 *
 * @param int    $userid       Teacher's user id.
 * @param int    $submissionid Submission being regenerated.
 * @param string $operation    Short operation name for cache key namespacing.
 * @param int    $cooldownsecs Minimum seconds between calls.
 * @param int    $dailycap     Max calls per teacher+submission per day.
 * @throws \moodle_exception if the cooldown has not yet expired or the cap is reached.
 */
function regen_rate_check(
    int $userid,
    int $submissionid,
    string $operation,
    int $cooldownsecs = 300,
    int $dailycap = 5
): void {
    airoleplay_regen_rate_check($userid, $submissionid, $operation, $cooldownsecs, $dailycap);
}

/**
 * Fetches or creates the current user's active submission.
 *
 * Creates the submission record when the student gives GDPR consent (via view.php).
 * If no consented submission exists yet, throws so the student is redirected back.
 *
 * @param stdClass $airoleplay Airoleplay instance.
 * @param stdClass $cm         Course module.
 * @param context  $context    Module context.
 * @return stdClass Submission record.
 */
function get_or_create_submission(stdClass $airoleplay, stdClass $cm, context $context): stdClass {
    global $DB, $USER;

    $attempt = (int)$DB->get_field_sql(
        'SELECT COALESCE(MAX(attempt), 0) FROM {airoleplay_submissions} WHERE airoleplay = ? AND userid = ?',
        [$airoleplay->id, $USER->id]
    );

    if ($attempt > 0) {
        $sub = $DB->get_record('airoleplay_submissions', [
            'airoleplay' => $airoleplay->id,
            'userid'     => $USER->id,
            'attempt'    => $attempt,
        ]);
        if ($sub && $sub->gdpr_consent) {
            return $sub;
        }
    }

    throw new \moodle_exception('gdpr_consent_required', 'mod_airoleplay');
}
