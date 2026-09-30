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
 * Unit tests for the server-side session clock and the expired-session task.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay;

use mod_airoleplay\local\session_manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/airoleplay/lib.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_airoleplay\local\session_manager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_airoleplay\task\close_expired_sessions::class)]
/**
 * Tests for {@see \mod_airoleplay\local\session_manager}.
 *
 * @covers \mod_airoleplay\local\session_manager
 * @covers \mod_airoleplay\task\close_expired_sessions
 */
final class session_manager_test extends \advanced_testcase {
    public function test_clock_is_derived_from_start_time(): void {
        $airoleplay = (object)['session_duration' => 10];
        $submission = (object)['timestarted' => 0];
        $this->assertSame(600, session_manager::remaining_seconds($airoleplay, $submission));
        $this->assertFalse(session_manager::is_expired($airoleplay, $submission));

        $submission->timestarted = 1000;
        $this->assertSame(1600, session_manager::deadline($airoleplay, $submission));
        $this->assertSame(100, session_manager::remaining_seconds($airoleplay, $submission, 1500));
        $this->assertSame(0, session_manager::remaining_seconds($airoleplay, $submission, 1700));
        // Replies within the grace period still count.
        $this->assertFalse(session_manager::is_expired($airoleplay, $submission, 1600 + session_manager::GRACE_SECONDS));
        $this->assertTrue(session_manager::is_expired($airoleplay, $submission, 1601 + session_manager::GRACE_SECONDS));
    }

    public function test_close_happens_once(): void {
        global $DB;
        $this->resetAfterTest();
        $course     = $this->getDataGenerator()->create_course();
        $student    = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $generator  = $this->getDataGenerator()->get_plugin_generator('mod_airoleplay');
        $airoleplay = $generator->create_instance(['course' => $course->id]);
        $submission = $generator->create_submission([
            'airoleplay' => $airoleplay->id, 'userid' => $student->id, 'status' => 'active', 'timestarted' => time(),
        ]);

        $this->assertTrue(session_manager::close((int)$submission->id));
        $this->assertFalse(session_manager::close((int)$submission->id));
        $this->assertSame('submitted', $DB->get_field('airoleplay_submissions', 'status', ['id' => $submission->id]));
    }

    public function test_task_closes_only_expired_sessions_and_queues_evaluation(): void {
        global $DB;
        $this->resetAfterTest();
        $course     = $this->getDataGenerator()->create_course();
        $student1   = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2   = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $generator  = $this->getDataGenerator()->get_plugin_generator('mod_airoleplay');
        $airoleplay = $generator->create_instance(['course' => $course->id, 'session_duration' => 5]);

        $expired = $generator->create_submission([
            'airoleplay' => $airoleplay->id, 'userid' => $student1->id, 'status' => 'active',
            'timestarted' => time() - HOURSECS,
        ]);
        $running = $generator->create_submission([
            'airoleplay' => $airoleplay->id, 'userid' => $student2->id, 'status' => 'active',
            'timestarted' => time() - 60,
        ]);

        $this->expectOutputRegex('/closed expired session/');
        (new \mod_airoleplay\task\close_expired_sessions())->execute();

        $this->assertSame('submitted', $DB->get_field('airoleplay_submissions', 'status', ['id' => $expired->id]));
        $this->assertSame('active', $DB->get_field('airoleplay_submissions', 'status', ['id' => $running->id]));
        $tasks = \core\task\manager::get_adhoc_tasks(\mod_airoleplay\task\evaluate_submission_task::class);
        $this->assertCount(1, $tasks);
    }
}
