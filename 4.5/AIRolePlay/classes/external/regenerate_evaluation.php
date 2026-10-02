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

/**
 * Teacher: regenerates the AI evaluation of an attempt.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class regenerate_evaluation extends base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'         => new external_value(PARAM_INT, 'Course module id'),
            'submissionid' => new external_value(PARAM_INT, 'Attempt id'),
        ]);
    }

    /**
     * Regenerates the evaluation.
     *
     * @param int $cmid         Course module id.
     * @param int $submissionid Attempt id.
     * @return array
     */
    public static function execute(int $cmid, int $submissionid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'         => $cmid,
            'submissionid' => $submissionid,
        ]);

        return self::guarded('regenerate_evaluation', $params['cmid'], static function () use ($params): array {
            global $DB, $USER;

            [$cm, $course, $airoleplay, $context] = self::load_activity($params['cmid']);
            require_capability('mod/airoleplay:grade', $context);
            $sub = $DB->get_record(
                'airoleplay_submissions',
                ['id' => $params['submissionid'], 'airoleplay' => $airoleplay->id],
                '*',
                MUST_EXIST
            );
            // In separate groups mode a teacher only reaches their own groups.
            airoleplay_require_user_access($cm, $context, (int)$sub->userid);
            self::begin_work();
            if (!in_array($sub->status, ['submitted', 'graded'], true)) {
                throw new \moodle_exception('invalidsubmissionstatus', 'mod_airoleplay');
            }
            airoleplay_regen_rate_check($USER->id, $sub->id, 'evaluation');
            \core_php_time_limit::raise(600);
            if ($sub->status === 'submitted') {
                evaluation_runner::run((int)$sub->id);
            } else {
                (new \mod_airoleplay\api\evaluator())->evaluate($sub, $airoleplay, $course, $cm);
            }
            $status = (string)$DB->get_field('airoleplay_submissions', 'status', ['id' => $sub->id]);
            return ['status' => $status];
        });
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'Status of the attempt after the call'),
        ]);
    }
}
