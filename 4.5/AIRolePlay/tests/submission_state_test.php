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
 * Unit tests for {@see \mod_airoleplay\local\submission_state}.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_airoleplay\local\submission_state
 */

namespace mod_airoleplay;

use mod_airoleplay\local\submission_state;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the status / workflow_state transition whitelist.
 */
final class submission_state_test extends \basic_testcase {

    public static function valid_status_transitions(): array {
        return [
            ['draft',     'active'],
            ['draft',     'submitted'],
            ['active',    'submitted'],
            ['submitted', 'grading'],
            ['submitted', 'graded'],
            ['grading',   'graded'],
            ['grading',   'submitted'],
            ['graded',    'graded'],
        ];
    }

    /** @dataProvider valid_status_transitions */
    public function test_status_transitions_accept_valid_pairs(string $from, string $to): void {
        $this->assertTrue(
            submission_state::can_transition_status($from, $to),
            $from . ' -> ' . $to . ' should be allowed'
        );
        // assert variant must not throw.
        submission_state::assert_status_transition($from, $to);
        $this->addToAssertionCount(1);
    }

    public static function invalid_status_transitions(): array {
        return [
            ['draft',     'graded'],
            ['active',    'graded'],
            ['active',    'draft'],
            ['submitted', 'draft'],
            ['graded',    'submitted'],
            ['graded',    'draft'],
            ['unknown',   'active'],
        ];
    }

    /** @dataProvider invalid_status_transitions */
    public function test_status_transitions_reject_invalid_pairs(string $from, string $to): void {
        $this->assertFalse(
            submission_state::can_transition_status($from, $to),
            $from . ' -> ' . $to . ' must be rejected'
        );
        $this->expectException(\moodle_exception::class);
        submission_state::assert_status_transition($from, $to);
    }

    public static function valid_workflow_transitions(): array {
        return [
            ['',         'inreview'],
            ['',         'released'],
            [null,       'inreview'],
            [null,       'released'],
            ['inreview', 'released'],
            ['inreview', 'inreview'],
            ['released', 'inreview'],
            ['released', 'released'],
        ];
    }

    /** @dataProvider valid_workflow_transitions */
    public function test_workflow_transitions_accept_valid_pairs(?string $from, string $to): void {
        $this->assertTrue(
            submission_state::can_transition_workflow($from, $to),
            ($from ?? 'null') . ' -> ' . $to . ' should be allowed'
        );
        submission_state::assert_workflow_transition($from, $to);
        $this->addToAssertionCount(1);
    }

    public function test_workflow_transitions_reject_unknown_destination(): void {
        $this->assertFalse(submission_state::can_transition_workflow('inreview', 'archived'));
        $this->expectException(\moodle_exception::class);
        submission_state::assert_workflow_transition('inreview', 'archived');
    }

    public function test_workflow_transitions_reject_unknown_source(): void {
        $this->assertFalse(submission_state::can_transition_workflow('archived', 'released'));
    }
}
