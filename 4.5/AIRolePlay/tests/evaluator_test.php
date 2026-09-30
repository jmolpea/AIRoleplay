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
 * Unit tests for the evaluator's participation guard.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay;

use mod_airoleplay\api\evaluator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/airoleplay/lib.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_airoleplay\api\evaluator::class)]
/**
 * Tests for {@see \mod_airoleplay\api\evaluator}.
 *
 * @covers \mod_airoleplay\api\evaluator
 */
final class evaluator_test extends \advanced_testcase {
    /**
     * A session where only the avatars spoke must never earn a grade and must
     * wait for a teacher, even when the activity auto-publishes grades.
     */
    public function test_no_participant_input_scores_zero_and_forces_review(): void {
        global $DB;
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_airoleplay');
        $airoleplay = $generator->create_instance(['course' => $course->id, 'grading_workflow' => 0]);
        $cm = get_coursemodule_from_instance('airoleplay', $airoleplay->id);

        $submission = $generator->create_submission([
            'airoleplay' => $airoleplay->id,
            'userid'     => $student->id,
            'status'     => 'grading',
        ]);
        // The avatars talk; the participant's microphone never worked.
        $generator->add_turn($submission, 'avatar_1', 'Hello! I would like to return this kettle, it does not work.');
        $generator->add_turn($submission, 'avatar_1', 'Are you there? Can you help me with the refund?');
        $generator->add_turn($submission, 'avatar_1', 'Thank you for your time, goodbye.');

        $submission = $DB->get_record('airoleplay_submissions', ['id' => $submission->id]);
        $result = (new evaluator())->evaluate($submission, $airoleplay, $course, $cm);

        $this->assertEquals(0.0, (float)$result->final_grade);
        $this->assertSame('graded', $result->status);
        $this->assertSame('inreview', $result->workflow_state);
        $analysis = json_decode($result->roleplay_analysis, true);
        $this->assertContains('no_participant_input', $analysis['academic_integrity_flags']);

        // Nothing reaches the gradebook while the attempt is in review.
        $grades = grade_get_grades($course->id, 'mod', 'airoleplay', $airoleplay->id, $student->id);
        $this->assertNull($grades->items[0]->grades[$student->id]->grade);
    }

    /**
     * Word counting is Unicode-aware and ignores punctuation.
     */
    public function test_count_words(): void {
        $this->assertSame(0, evaluator::count_words(''));
        $this->assertSame(0, evaluator::count_words('   ...  '));
        $this->assertSame(4, evaluator::count_words('Hola, ¿qué tal estás?'));
        $this->assertSame(3, evaluator::count_words("I'm well-prepared today"));
        $this->assertSame(2, evaluator::count_words('Olá, você'));
    }
}
