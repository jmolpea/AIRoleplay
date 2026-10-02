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

/**
 * Returns the status of the user's own attempt (polled while it is evaluated).
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_evaluation_status extends base {
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
     * Returns the attempt status.
     *
     * @param int $cmid         Course module id.
     * @param int $submissionid Attempt id.
     * @return array
     */
    public static function execute(int $cmid, int $submissionid = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'         => $cmid,
            'submissionid' => $submissionid,
        ]);

        return self::guarded('get_evaluation_status', $params['cmid'], static function () use ($params): array {
            global $DB, $USER;

            [, , $airoleplay, $context] = self::load_activity($params['cmid']);
            require_capability('mod/airoleplay:submit', $context);
            self::begin_work();
            // Look up by the full triple (id, airoleplay, userid) so a missing
            // row and a foreign row produce the same generic error, preventing
            // an attacker from enumerating submission ids by response shape.
            $status = $DB->get_field('airoleplay_submissions', 'status', [
                'id'         => $params['submissionid'],
                'airoleplay' => $airoleplay->id,
                'userid'     => $USER->id,
            ]);
            if ($status === false) {
                throw new \moodle_exception('badrequest', 'mod_airoleplay');
            }
            return ['status' => (string)$status];
        });
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'Status of the attempt'),
        ]);
    }
}
