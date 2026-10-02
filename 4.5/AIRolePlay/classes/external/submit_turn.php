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
 * Sends one participant line and returns the avatar's reply.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_turn extends base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'         => new external_value(PARAM_INT, 'Course module id'),
            'submissionid' => new external_value(PARAM_INT, 'Attempt id (0 = the latest attempt)', VALUE_DEFAULT, 0),
            'response'     => new external_value(PARAM_NOTAGS, 'The participant reply'),
            'suggested_avatar' => new external_value(PARAM_INT, 'Avatar suggested by the rotation (1-3)', VALUE_DEFAULT, 1),
            'preferred_avatar' => new external_value(PARAM_INT, 'Avatar addressed by name (0 = none)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Stores the participant line and generates the avatar reply.
     *
     * @param int    $cmid            Course module id.
     * @param int    $submissionid    Attempt id (0 = latest).
     * @param string $response        Participant reply.
     * @param int    $suggestedavatar Avatar suggested by the rotation.
     * @param int    $preferredavatar Avatar addressed by name (0 = none).
     * @return array
     */
    public static function execute(
        int $cmid,
        int $submissionid,
        string $response,
        int $suggestedavatar = 1,
        int $preferredavatar = 0
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'         => $cmid,
            'submissionid' => $submissionid,
            'response'         => $response,
            'suggested_avatar' => $suggestedavatar,
            'preferred_avatar' => $preferredavatar,
        ]);

        return self::guarded('submit_turn', $params['cmid'], static function () use ($params): array {
            [, , $airoleplay, $context] = self::load_activity($params['cmid']);
            require_capability('mod/airoleplay:submit', $context);
            self::begin_work();
            $submission = self::own_submission($airoleplay, $params['submissionid']);
            if ($submission->status !== 'active') {
                throw new \moodle_exception('invalidsubmissionstatus', 'mod_airoleplay');
            }
            if (session_manager::is_expired($airoleplay, $submission)) {
                return ['timeup' => true, 'remaining' => 0] + self::empty_turn();
            }

            $participantinput = mb_substr(trim($params['response']), 0, 5000);
            if ($participantinput === '') {
                throw new \moodle_exception('emptyresponse', 'mod_airoleplay');
            }
            $suggestedavatar = max(1, min(3, $params['suggested_avatar']));
            $preferredavatar = max(0, min(3, $params['preferred_avatar']));

            $conductor = new roleplay_conductor($airoleplay, $submission);
            $result    = $conductor->next_turn($suggestedavatar, $participantinput, $preferredavatar);
            return $result + [
                'timeup'    => false,
                'remaining' => session_manager::remaining_seconds($airoleplay, $submission),
            ];
        });
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'timeup'    => new external_value(PARAM_BOOL, 'The session time is over; no reply was generated'),
            'remaining' => new external_value(PARAM_INT, 'Seconds left in the session'),
        ] + self::turn_fields());
    }
}
