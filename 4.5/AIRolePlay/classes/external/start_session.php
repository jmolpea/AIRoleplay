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

namespace mod_airoleplay\external;

use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_airoleplay\api\roleplay_conductor;
use mod_airoleplay\local\session_manager;
use mod_airoleplay\local\submission_state;

/**
 * Starts the roleplay session, or resumes it after a page reload.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class start_session extends base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'         => new external_value(PARAM_INT, 'Course module id'),
            'submissionid' => new external_value(PARAM_INT, 'Attempt id (0 = the latest attempt)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Starts (or resumes) the attempt and its server-side clock.
     *
     * @param int $cmid         Course module id.
     * @param int $submissionid Attempt id (0 = latest).
     * @return array
     */
    public static function execute(int $cmid, int $submissionid = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'         => $cmid,
            'submissionid' => $submissionid,
        ]);

        return self::guarded('start_session', $params['cmid'], static function () use ($params): array {
            global $DB, $USER;

            [, , $airoleplay, $context] = self::load_activity($params['cmid']);
            require_capability('mod/airoleplay:submit', $context);
            self::begin_work();
            $submission = self::own_submission($airoleplay, $params['submissionid']);
            if (!$submission->gdpr_consent) {
                throw new \moodle_exception('gdpr_consent_required', 'mod_airoleplay');
            }

            $lock = airoleplay_acquire_submission_lock($submission->id);
            if (!$lock) {
                throw new \moodle_exception('busy', 'mod_airoleplay');
            }
            $opening = null;
            try {
                // Re-read state under the lock so concurrent callers cannot
                // both observe 'draft' and both start the session.
                $submission = $DB->get_record('airoleplay_submissions', ['id' => $submission->id], '*', MUST_EXIST);
                if ($submission->status === 'draft') {
                    if ($problem = airoleplay_availability_problem($airoleplay, $USER->id)) {
                        throw new \moodle_exception($problem, 'mod_airoleplay');
                    }
                    if (airoleplay_attempts_remaining($airoleplay, $USER->id) <= 0) {
                        throw new \moodle_exception('noattemptsleft', 'mod_airoleplay');
                    }
                    // Generate the opening line before the attempt starts: if
                    // the AI provider fails (missing key, outage), the attempt
                    // stays unstarted instead of burning the student's time.
                    $conductor = new roleplay_conductor($airoleplay, $submission);
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
                    throw new \moodle_exception('invalidsubmissionstatus', 'mod_airoleplay');
                }
                session_manager::start_clock($submission);
            } finally {
                $lock->release();
            }

            $result = [
                'submissionid' => (int)$submission->id,
                'remaining'    => 0,
                'expired'      => false,
                'resume'       => false,
                'transcript'   => [],
            ] + self::empty_turn();

            if ($opening !== null) {
                $result['remaining'] = session_manager::remaining_seconds($airoleplay, $submission);
                return $opening + $result;
            }

            $conductor = new roleplay_conductor($airoleplay, $submission);
            $remaining = session_manager::remaining_seconds($airoleplay, $submission);
            if (session_manager::is_expired($airoleplay, $submission)) {
                return ['expired' => true] + $result;
            }
            if ($conductor->has_started()) {
                return [
                    'resume'     => true,
                    'remaining'  => $remaining,
                    'transcript' => $conductor->get_transcript_for_client(),
                ] + $result;
            }
            // Active attempt without any turn yet (e.g. started by an older version).
            $opening = $conductor->opening_statement();
            $result['remaining'] = session_manager::remaining_seconds($airoleplay, $submission);
            return $opening + $result;
        });
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'submissionid' => new external_value(PARAM_INT, 'Attempt id'),
            'remaining'    => new external_value(PARAM_INT, 'Seconds left in the session'),
            'expired'      => new external_value(PARAM_BOOL, 'The session time ran out before this call'),
            'resume'       => new external_value(PARAM_BOOL, 'The session was already running; the transcript is returned'),
            'transcript'   => new external_multiple_structure(
                new external_single_structure([
                    'speaker' => new external_value(PARAM_ALPHA, 'avatar or participant'),
                    'avatar'  => new external_value(PARAM_INT, 'Avatar number (0 for the participant)'),
                    'text'    => new external_value(PARAM_RAW, 'The line, plain text'),
                ]),
                'Conversation so far, when resuming'
            ),
        ] + self::turn_fields());
    }
}
