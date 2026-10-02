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
use core_external\external_single_structure;
use core_external\external_value;
use mod_airoleplay\api\roleplay_conductor;
use mod_airoleplay\local\session_manager;

/**
 * Closes the attempt when time is up and returns the closing line.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class close_session extends base {
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
     * Closes the attempt (active -> submitted) and says goodbye.
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

        return self::guarded('close_session', $params['cmid'], static function () use ($params): array {
            global $CFG, $DB, $USER;
            require_once($CFG->libdir . '/completionlib.php');

            [$cm, $course, $airoleplay, $context] = self::load_activity($params['cmid']);
            require_capability('mod/airoleplay:submit', $context);
            self::begin_work();
            $submission = self::own_submission($airoleplay, $params['submissionid']);

            // Only the worker that actually performs active -> submitted says
            // goodbye; concurrent callers get the current state back.
            if (!session_manager::close((int)$submission->id)) {
                $status = (string)$DB->get_field('airoleplay_submissions', 'status', ['id' => $submission->id]);
                return ['evaluation_status' => $status, 'duplicate' => true] + self::empty_turn();
            }

            // Safety net: if the browser never asks for the evaluation (tab
            // closed, network lost), the background task grades the attempt.
            session_manager::queue_evaluation((int)$submission->id, (int)$cm->id, 2 * MINSECS);

            $completion = new \completion_info($course);
            if ($completion->is_enabled($cm) && !empty($airoleplay->completionsubmit)) {
                $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
            }

            $result = [];
            try {
                $submission->status = 'submitted';
                $conductor = new roleplay_conductor($airoleplay, $submission);
                $result    = $conductor->closing_statement();
            } catch (\Throwable $e) {
                // The attempt is already safely submitted; a missing goodbye
                // line must not surface as an error to the student.
                airoleplay_log_internal_error('closing_statement', $e, ['submissionid' => $submission->id]);
            }
            return $result + ['evaluation_status' => 'submitted', 'duplicate' => false] + self::empty_turn();
        });
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'evaluation_status' => new external_value(PARAM_ALPHA, 'Status of the attempt after the call'),
            'duplicate'         => new external_value(PARAM_BOOL, 'Another request had already closed the attempt'),
        ] + self::turn_fields());
    }
}
