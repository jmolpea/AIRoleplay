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
 * Unit tests for {@see ::airoleplay_regen_rate_check()}.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::airoleplay_regen_rate_check
 */

namespace mod_airoleplay;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/airoleplay/lib.php');

#[\PHPUnit\Framework\Attributes\CoversFunction('airoleplay_regen_rate_check')]
/**
 * Tests the regen cooldown + daily-cap helper.
 *
 * @covers ::airoleplay_regen_rate_check
 */
final class regen_rate_test extends \advanced_testcase {
    public function test_first_call_succeeds(): void {
        $this->resetAfterTest();
        // Should not throw.
        \airoleplay_regen_rate_check(1, 100, 'evaluation');
        $this->addToAssertionCount(1);
    }

    public function test_second_call_within_cooldown_throws(): void {
        $this->resetAfterTest();
        \airoleplay_regen_rate_check(1, 100, 'evaluation', 60, 5);
        $this->expectException(\moodle_exception::class);
        \airoleplay_regen_rate_check(1, 100, 'evaluation', 60, 5);
    }

    public function test_different_users_have_independent_cooldowns(): void {
        $this->resetAfterTest();
        \airoleplay_regen_rate_check(1, 100, 'evaluation', 60, 5);
        // A different user must not be blocked.
        \airoleplay_regen_rate_check(2, 100, 'evaluation', 60, 5);
        $this->addToAssertionCount(1);
    }

    public function test_different_submissions_have_independent_cooldowns(): void {
        $this->resetAfterTest();
        \airoleplay_regen_rate_check(1, 100, 'evaluation', 60, 5);
        \airoleplay_regen_rate_check(1, 101, 'evaluation', 60, 5);
        $this->addToAssertionCount(1);
    }

    public function test_zero_cooldown_allows_back_to_back_until_cap(): void {
        $this->resetAfterTest();
        // Cooldown=0 lets us hit the daily cap quickly.
        \airoleplay_regen_rate_check(1, 100, 'evaluation', 0, 3);
        \airoleplay_regen_rate_check(1, 100, 'evaluation', 0, 3);
        \airoleplay_regen_rate_check(1, 100, 'evaluation', 0, 3);
        $this->expectException(\moodle_exception::class);
        \airoleplay_regen_rate_check(1, 100, 'evaluation', 0, 3);
    }

    public function test_daily_cap_is_per_operation(): void {
        $this->resetAfterTest();
        \airoleplay_regen_rate_check(1, 100, 'evaluation', 0, 1);
        // Different operation namespace must not be capped.
        \airoleplay_regen_rate_check(1, 100, 'feedback', 0, 1);
        $this->addToAssertionCount(1);
    }
}
