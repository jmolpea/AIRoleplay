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
use mod_airoleplay\local\evaluation_runner;
use mod_airoleplay\local\session_manager;

/**
 * Evaluates a closed attempt (the browser calls this after the goodbye line).
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class finalise_session extends base {
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
     * Evaluates the attempt now; on failure the background task takes over.
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

        return self::guarded('finalise_session', $params['cmid'], static function () use ($params): array {
            global $DB;

            [$cm, , $airoleplay, $context] = self::load_activity($params['cmid']);
            require_capability('mod/airoleplay:submit', $context);
            self::begin_work();
            $submission = self::own_submission($airoleplay, $params['submissionid']);
            \core_php_time_limit::raise(300);
            ignore_user_abort(true);
            try {
                evaluation_runner::run((int)$submission->id);
            } catch (\Throwable $e) {
                airoleplay_log_internal_error('evaluation_sync_failed', $e, ['submissionid' => $submission->id]);
                session_manager::queue_evaluation((int)$submission->id, (int)$cm->id);
            }
            $status = (string)$DB->get_field('airoleplay_submissions', 'status', ['id' => $submission->id]);
            return ['evaluation_status' => $status];
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
        ]);
    }
}
