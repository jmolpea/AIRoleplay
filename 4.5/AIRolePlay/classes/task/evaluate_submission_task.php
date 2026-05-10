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
 * Adhoc task: generates the final AI evaluation for a completed roleplay submission.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\task;

/**
 * Background task that calls the evaluator to produce the final grade and feedback
 * after the roleplay session ends (used as fallback when synchronous eval fails).
 *
 * Custom data keys:
 *  - submissionid (int) — airoleplay_submissions.id
 *  - cmid         (int) — course_modules.id
 */
class evaluate_submission_task extends \core\task\adhoc_task {
    /**
     * Returns the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_evaluate_submission', 'mod_airoleplay');
    }

    /**
     * Executes the evaluation task.
     */
    public function execute(): void {
        global $DB;

        $data         = $this->get_custom_data();
        $submissionid = (int)($data->submissionid ?? 0);
        $cmid         = (int)($data->cmid ?? 0);

        if (!$submissionid || !$cmid) {
            mtrace('airoleplay evaluate_submission_task: missing submissionid or cmid');
            return;
        }

        $submission = $DB->get_record('airoleplay_submissions', ['id' => $submissionid]);
        if (!$submission) {
            mtrace('airoleplay evaluate_submission_task: submission not found: ' . $submissionid);
            return;
        }

        $cm         = get_coursemodule_from_id('airoleplay', $cmid, 0, false, MUST_EXIST);
        $course     = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $airoleplay = $DB->get_record('airoleplay', ['id' => $cm->instance], '*', MUST_EXIST);

        \mod_airoleplay\local\submission_state::assert_status_transition(
            (string)$submission->status,
            'grading'
        );
        $DB->set_field('airoleplay_submissions', 'status', 'grading', ['id' => $submissionid]);
        $submission->status = 'grading';

        try {
            $evaluator = new \mod_airoleplay\api\evaluator();
            $evaluator->evaluate($submission, $airoleplay, $course, $cm);
            mtrace('airoleplay evaluate_submission_task: completed for submission ' . $submissionid);
        } catch (\moodle_exception $e) {
            \airoleplay_log_internal_error('evaluate_submission_task', $e, ['submissionid' => $submissionid]);
            mtrace('airoleplay evaluate_submission_task: failed for submission ' . $submissionid . ' (see error log)');
            \mod_airoleplay\local\submission_state::assert_status_transition('grading', 'submitted');
            $DB->set_field('airoleplay_submissions', 'status', 'submitted', ['id' => $submissionid]);
        }
    }
}
