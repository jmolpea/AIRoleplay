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
 * State-machine validators for submission status and workflow transitions.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Centralised whitelist of legal status / workflow_state transitions.
 *
 * The plugin previously mutated state with bare set_field() calls. Anything
 * with DB write privilege could move a submission into nonsense states
 * (draft -> graded, released -> draft, ...) and skip the workflow gates.
 *
 * Every mutation site now calls assert_status_transition() or
 * assert_workflow_transition() right before the set_field, so an attempted
 * illegal transition raises moodle_exception('invalid_state_transition')
 * instead of corrupting the row.
 */
class submission_state {
    /**
     * Allowed source -> [destinations] for the lifecycle status field.
     */
    private const STATUS_TRANSITIONS = [
        'draft'     => ['draft', 'active', 'submitted'],
        'active'    => ['active', 'submitted'],
        'submitted' => ['submitted', 'grading', 'graded'],
        'grading'   => ['grading', 'graded', 'submitted'],
        'graded'    => ['graded'],
    ];

    /**
     * Allowed source -> [destinations] for the teacher workflow_state field.
     * `null` and the empty string represent a freshly-created submission
     * that has not yet entered the review workflow.
     */
    private const WORKFLOW_TRANSITIONS = [
        ''         => ['inreview', 'released'],
        'inreview' => ['inreview', 'released'],
        'released' => ['released', 'inreview'],
    ];

    /**
     * @param string $from Current status.
     * @param string $to   Desired status.
     * @return bool True if the move is allowed.
     */
    public static function can_transition_status(string $from, string $to): bool {
        $allowed = self::STATUS_TRANSITIONS[$from] ?? [];
        return in_array($to, $allowed, true);
    }

    /**
     * @param string|null $from Current workflow_state (null/empty = unset).
     * @param string      $to   Desired workflow_state.
     * @return bool True if the move is allowed.
     */
    public static function can_transition_workflow(?string $from, string $to): bool {
        $key     = (string)$from;
        $allowed = self::WORKFLOW_TRANSITIONS[$key] ?? [];
        return in_array($to, $allowed, true);
    }

    /**
     * Throws if the requested status transition is not in the whitelist.
     *
     * @param string $from Current status.
     * @param string $to   Desired status.
     * @throws \moodle_exception
     */
    public static function assert_status_transition(string $from, string $to): void {
        if (!self::can_transition_status($from, $to)) {
            throw new \moodle_exception(
                'invalid_state_transition',
                'mod_airoleplay',
                '',
                'status: ' . $from . ' -> ' . $to
            );
        }
    }

    /**
     * Throws if the requested workflow_state transition is not in the whitelist.
     *
     * @param string|null $from Current workflow_state (null/empty = unset).
     * @param string      $to   Desired workflow_state.
     * @throws \moodle_exception
     */
    public static function assert_workflow_transition(?string $from, string $to): void {
        if (!self::can_transition_workflow($from, $to)) {
            throw new \moodle_exception(
                'invalid_state_transition',
                'mod_airoleplay',
                '',
                'workflow: ' . ($from ?? 'null') . ' -> ' . $to
            );
        }
    }
}
