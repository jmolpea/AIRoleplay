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
 * Single entry point that evaluates a submitted attempt exactly once.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\local;

/**
 * Claims a 'submitted' attempt (-> 'grading') under the submission lock and
 * runs the evaluator.
 *
 * The browser (right after the closing line), the ad-hoc task and the
 * expired-session task all go through here, so concurrent callers can never
 * evaluate the same attempt twice or leave it stuck half-way.
 */
class evaluation_runner {
    /** @var string The attempt was evaluated by this call. */
    public const RESULT_GRADED = 'graded';

    /** @var string Another worker owns the attempt, or it is already graded. */
    public const RESULT_SKIPPED = 'skipped';

    /**
     * Evaluates a submitted attempt.
     *
     * @param int $submissionid Submission id.
     * @return string RESULT_GRADED or RESULT_SKIPPED.
     * @throws \Throwable when the evaluation fails; the attempt is returned to
     *                    'submitted' first so it can be retried.
     */
    public static function run(int $submissionid): string {
        global $DB;

        $submission = $DB->get_record('airoleplay_submissions', ['id' => $submissionid]);
        if (!$submission) {
            return self::RESULT_SKIPPED;
        }
        $airoleplay = $DB->get_record('airoleplay', ['id' => $submission->airoleplay], '*', MUST_EXIST);
        $cm         = get_coursemodule_from_instance('airoleplay', $airoleplay->id, $airoleplay->course, false, MUST_EXIST);
        $course     = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

        if (!self::claim($submission)) {
            return self::RESULT_SKIPPED;
        }

        try {
            (new \mod_airoleplay\api\evaluator())->evaluate($submission, $airoleplay, $course, $cm);
        } catch (\Throwable $e) {
            submission_state::assert_status_transition('grading', 'submitted');
            $DB->set_field('airoleplay_submissions', 'status', 'submitted', ['id' => $submissionid]);
            throw $e;
        }
        return self::RESULT_GRADED;
    }

    /**
     * Moves the attempt to 'grading' when nobody else is evaluating it.
     *
     * @param \stdClass $submission Submission record (updated in place).
     * @return bool True when this caller now owns the evaluation.
     */
    private static function claim(\stdClass $submission): bool {
        global $DB;

        $lock = \airoleplay_acquire_submission_lock((int)$submission->id);
        if (!$lock) {
            return false;
        }
        try {
            $current = $DB->get_record(
                'airoleplay_submissions',
                ['id' => $submission->id],
                'id, status, timemodified',
                MUST_EXIST
            );
            $stale = $current->status === 'grading'
                && (time() - (int)$current->timemodified) > session_manager::STALE_GRADING_SECONDS;
            if ($current->status !== 'submitted' && !$stale) {
                return false;
            }
            submission_state::assert_status_transition($current->status, 'grading');
            $DB->update_record('airoleplay_submissions', (object)[
                'id'           => $submission->id,
                'status'       => 'grading',
                'timemodified' => time(),
            ]);
            $submission->status = 'grading';
            return true;
        } finally {
            $lock->release();
        }
    }
}
