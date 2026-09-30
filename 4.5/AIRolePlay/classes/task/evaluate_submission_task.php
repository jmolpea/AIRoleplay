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

use mod_airoleplay\local\evaluation_runner;

/**
 * Background task that evaluates a submitted attempt.
 *
 * Queued as a safety net when a session closes and as the fallback when the
 * synchronous evaluation fails. A failure is re-thrown so Moodle retries the
 * task with back-off, instead of leaving the student waiting forever.
 *
 * Custom data keys:
 *  - submissionid (int) — airoleplay_submissions.id
 *  - cmid         (int) — course_modules.id (informational)
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
        $data         = $this->get_custom_data();
        $submissionid = (int)($data->submissionid ?? 0);
        if (!$submissionid) {
            mtrace('airoleplay evaluate_submission_task: missing submissionid');
            return;
        }

        try {
            $result = evaluation_runner::run($submissionid);
        } catch (\Throwable $e) {
            \airoleplay_log_internal_error('evaluate_submission_task', $e, ['submissionid' => $submissionid]);
            mtrace('airoleplay evaluate_submission_task: failed for submission ' . $submissionid . ', will retry');
            throw $e;
        }
        mtrace('airoleplay evaluate_submission_task: ' . $result . ' for submission ' . $submissionid);
    }
}
