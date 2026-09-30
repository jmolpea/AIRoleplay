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
 * Unit tests for attempts, overrides, availability and gradebook sync.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/airoleplay/lib.php');

#[\PHPUnit\Framework\Attributes\CoversFunction('airoleplay_get_effective_settings')]
#[\PHPUnit\Framework\Attributes\CoversFunction('airoleplay_attempts_remaining')]
#[\PHPUnit\Framework\Attributes\CoversFunction('airoleplay_start_new_attempt')]
#[\PHPUnit\Framework\Attributes\CoversFunction('airoleplay_availability_problem')]
#[\PHPUnit\Framework\Attributes\CoversFunction('airoleplay_update_grades')]
/**
 * Tests for the attempt and override helpers in lib.php.
 *
 * @covers ::airoleplay_get_effective_settings
 * @covers ::airoleplay_attempts_remaining
 * @covers ::airoleplay_start_new_attempt
 * @covers ::airoleplay_availability_problem
 * @covers ::airoleplay_update_grades
 */
final class attempts_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Student. */
    private \stdClass $student;

    /** @var \stdClass Activity. */
    private \stdClass $airoleplay;

    /** @var \mod_airoleplay_generator Plugin generator. */
    private \mod_airoleplay_generator $generator;

    /**
     * Creates a course, a student and an activity allowing two attempts.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course     = $this->getDataGenerator()->create_course();
        $this->student    = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->generator  = $this->getDataGenerator()->get_plugin_generator('mod_airoleplay');
        $this->airoleplay = $this->generator->create_instance(['course' => $this->course->id, 'max_attempts' => 2]);
    }

    public function test_new_attempt_requires_previous_graded_and_respects_limit(): void {
        $this->generator->create_submission([
            'airoleplay' => $this->airoleplay->id,
            'userid'     => $this->student->id,
            'status'     => 'active',
        ]);
        $this->assertSame(1, airoleplay_attempts_remaining($this->airoleplay, $this->student->id));

        // Cannot start a new attempt while one is in progress.
        try {
            airoleplay_start_new_attempt($this->airoleplay, $this->student->id);
            $this->fail('Expected attempt_in_progress');
        } catch (\moodle_exception $e) {
            $this->assertSame('attempt_in_progress', $e->errorcode);
        }

        global $DB;
        $DB->set_field('airoleplay_submissions', 'status', 'graded', ['userid' => $this->student->id]);
        $second = airoleplay_start_new_attempt($this->airoleplay, $this->student->id);
        $this->assertSame(2, (int)$second->attempt);
        $this->assertSame('draft', $second->status);

        // A draft attempt does not consume the allowance until it starts.
        $this->assertSame(1, airoleplay_attempts_remaining($this->airoleplay, $this->student->id));
        $DB->set_field('airoleplay_submissions', 'status', 'graded', ['id' => $second->id]);
        $this->assertSame(0, airoleplay_attempts_remaining($this->airoleplay, $this->student->id));

        $this->expectException(\moodle_exception::class);
        airoleplay_start_new_attempt($this->airoleplay, $this->student->id);
    }

    public function test_user_override_beats_group_override_and_activity(): void {
        global $DB;
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $this->student->id]);

        $DB->insert_record('airoleplay_overrides', (object)[
            'airoleplay' => $this->airoleplay->id, 'groupid' => $group->id, 'max_attempts' => 5,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->assertSame(5, airoleplay_get_effective_settings($this->airoleplay, $this->student->id)->max_attempts);

        $DB->insert_record('airoleplay_overrides', (object)[
            'airoleplay' => $this->airoleplay->id, 'userid' => $this->student->id, 'max_attempts' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->assertSame(1, airoleplay_get_effective_settings($this->airoleplay, $this->student->id)->max_attempts);
    }

    public function test_availability_window(): void {
        $now = time();
        $this->airoleplay->timeopen  = $now + HOURSECS;
        $this->airoleplay->timeclose = $now + DAYSECS;
        $userid = (int)$this->student->id;
        $this->assertSame('notopenyet', airoleplay_availability_problem($this->airoleplay, $userid, $now));
        $this->assertNull(airoleplay_availability_problem($this->airoleplay, $userid, $now + 2 * HOURSECS));
        $this->assertSame('activityclosed', airoleplay_availability_problem($this->airoleplay, $userid, $now + 2 * DAYSECS));
    }

    public function test_gradebook_gets_best_released_grade_only(): void {
        foreach ([[1, 55.0, 'released'], [2, 80.0, 'released'], [3, 95.0, 'inreview']] as [$attempt, $grade, $workflow]) {
            $this->generator->create_submission([
                'airoleplay'     => $this->airoleplay->id,
                'userid'         => $this->student->id,
                'attempt'        => $attempt,
                'status'         => 'graded',
                'workflow_state' => $workflow,
                'final_grade'    => $grade,
                'timegraded'     => time(),
            ]);
        }
        airoleplay_update_grades($this->airoleplay, $this->student->id);
        $grades = grade_get_grades($this->course->id, 'mod', 'airoleplay', $this->airoleplay->id, $this->student->id);
        $this->assertEquals(80.0, (float)$grades->items[0]->grades[$this->student->id]->grade);
    }
}
