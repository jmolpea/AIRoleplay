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
 * Server-side roleplay session lifecycle.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\local;

/**
 * Owns the session clock and the active -> submitted transition.
 *
 * The countdown shown in the browser is only a display: the deadline is
 * derived from submissions.timestarted, which is set once, the first time the
 * session opens. Reloading the page therefore resumes the same session with
 * the time that is really left, instead of restarting the clock.
 */
class session_manager {
    /**
     * Seconds accepted after the deadline, so a reply the student started
     * speaking just before time ran out (plus network/model latency) still counts.
     */
    public const GRACE_SECONDS = 45;

    /** @var int Seconds after which a 'grading' status is considered abandoned. */
    public const STALE_GRADING_SECONDS = 1800;

    /**
     * Unix time at which the session ends, or 0 when it has not started.
     *
     * @param \stdClass $airoleplay Activity record.
     * @param \stdClass $submission Submission record.
     * @return int
     */
    public static function deadline(\stdClass $airoleplay, \stdClass $submission): int {
        $started = (int)($submission->timestarted ?? 0);
        if ($started <= 0) {
            return 0;
        }
        return $started + max(1, (int)$airoleplay->session_duration) * MINSECS;
    }

    /**
     * Seconds left in the session (full duration when it has not started).
     *
     * @param \stdClass $airoleplay Activity record.
     * @param \stdClass $submission Submission record.
     * @param int|null  $now        Current time (for tests).
     * @return int Never negative.
     */
    public static function remaining_seconds(\stdClass $airoleplay, \stdClass $submission, ?int $now = null): int {
        $deadline = self::deadline($airoleplay, $submission);
        if ($deadline === 0) {
            return max(1, (int)$airoleplay->session_duration) * MINSECS;
        }
        return max(0, $deadline - ($now ?? time()));
    }

    /**
     * Whether the session clock has run out, including the grace period.
     *
     * @param \stdClass $airoleplay Activity record.
     * @param \stdClass $submission Submission record.
     * @param int|null  $now        Current time (for tests).
     * @return bool
     */
    public static function is_expired(\stdClass $airoleplay, \stdClass $submission, ?int $now = null): bool {
        $deadline = self::deadline($airoleplay, $submission);
        return $deadline > 0 && ($now ?? time()) > $deadline + self::GRACE_SECONDS;
    }

    /**
     * Starts the session clock the first time the session opens.
     *
     * @param \stdClass $submission Submission record (updated in place).
     */
    public static function start_clock(\stdClass $submission): void {
        global $DB;
        if ((int)($submission->timestarted ?? 0) > 0) {
            return;
        }
        $now = time();
        $DB->set_field('airoleplay_submissions', 'timestarted', $now, ['id' => $submission->id]);
        $submission->timestarted = $now;
    }

    /**
     * Moves an active (or draft) submission to 'submitted'.
     *
     * Serialised through the submission lock: when several workers race (the
     * browser timer, a retry, the expired-session task), exactly one wins.
     *
     * @param int $submissionid Submission id.
     * @return bool True when this call performed the transition.
     */
    public static function close(int $submissionid): bool {
        global $DB;

        $lock = \airoleplay_acquire_submission_lock($submissionid);
        if (!$lock) {
            return false;
        }
        try {
            $current = $DB->get_record('airoleplay_submissions', ['id' => $submissionid], 'id, status');
            if (!$current || !in_array($current->status, ['active', 'draft'], true)) {
                return false;
            }
            submission_state::assert_status_transition($current->status, 'submitted');
            $now = time();
            $DB->update_record('airoleplay_submissions', (object)[
                'id'            => $submissionid,
                'status'        => 'submitted',
                'timesubmitted' => $now,
                'timemodified'  => $now,
            ]);
            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * Queues the background evaluation of a submitted attempt.
     *
     * Used both as the fallback when the synchronous evaluation fails and as
     * a safety net when the browser never asks for it (tab closed).
     *
     * @param int $submissionid Submission id.
     * @param int $cmid         Course module id.
     * @param int $delay        Seconds to wait before the task may run.
     */
    public static function queue_evaluation(int $submissionid, int $cmid, int $delay = 0): void {
        $task = new \mod_airoleplay\task\evaluate_submission_task();
        $task->set_custom_data(['submissionid' => $submissionid, 'cmid' => $cmid]);
        if ($delay > 0) {
            $task->set_next_run_time(time() + $delay);
        }
        // Reuse an identical queued task instead of stacking duplicates.
        \core\task\manager::queue_adhoc_task($task, true);
    }
}
