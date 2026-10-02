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
 * Tests for the regenerate_evaluation external service.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\external;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_airoleplay\external\regenerate_evaluation::class)]
/**
 * Tests the access checks of {@see \mod_airoleplay\external\regenerate_evaluation}.
 *
 * No licence key exists in the test site, so a caller who passes every access
 * check is stopped by the licence gate, before any AI call.
 *
 * @covers \mod_airoleplay\external\regenerate_evaluation
 */
final class regenerate_evaluation_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Activity in separate groups mode. */
    private \stdClass $airoleplay;

    /** @var \stdClass Graded attempt of a student in group A. */
    private \stdClass $submission;

    /** @var \stdClass Group A. */
    private \stdClass $groupa;

    /** @var \stdClass Group B. */
    private \stdClass $groupb;

    /**
     * Creates a separate-groups activity with a graded attempt in group A.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $this->course     = $generator->create_course();
        $this->groupa     = $generator->create_group(['courseid' => $this->course->id]);
        $this->groupb     = $generator->create_group(['courseid' => $this->course->id]);
        $this->airoleplay = $generator->create_module('airoleplay', [
            'course'    => $this->course->id,
            'groupmode' => SEPARATEGROUPS,
        ]);
        $student = $generator->create_and_enrol($this->course, 'student');
        $generator->create_group_member(['groupid' => $this->groupa->id, 'userid' => $student->id]);
        $this->submission = $generator->get_plugin_generator('mod_airoleplay')->create_submission([
            'airoleplay' => $this->airoleplay->id,
            'userid'     => $student->id,
            'status'     => 'graded',
        ]);
    }

    /**
     * Calls the service as a non-editing teacher who belongs to one group.
     *
     * @param int $groupid Group of the teacher.
     * @return string Error code of the exception thrown.
     */
    private function regenerate_as_teacher_of(int $groupid): string {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $this->getDataGenerator()->create_group_member(['groupid' => $groupid, 'userid' => $teacher->id]);
        $this->setUser($teacher);
        try {
            regenerate_evaluation::execute($this->airoleplay->cmid, $this->submission->id);
        } catch (\moodle_exception $e) {
            return $e->errorcode;
        }
        return '';
    }

    public function test_teacher_of_another_group_is_refused(): void {
        $this->assertSame('nopermissions', $this->regenerate_as_teacher_of($this->groupb->id));
    }

    public function test_teacher_of_the_same_group_passes_the_access_checks(): void {
        $this->assertSame('license_banner_missing', $this->regenerate_as_teacher_of($this->groupa->id));
    }

    public function test_student_is_refused(): void {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        regenerate_evaluation::execute($this->airoleplay->cmid, $this->submission->id);
    }
}
