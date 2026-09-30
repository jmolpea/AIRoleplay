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
 * Tests for the output widgets and their templates.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay;

use mod_airoleplay\output\consent;
use mod_airoleplay\output\results;
use mod_airoleplay\output\room;
use mod_airoleplay\output\submission_detail;

#[\PHPUnit\Framework\Attributes\CoversClass(consent::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(room::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(results::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(submission_detail::class)]
/**
 * Renders every widget with realistic data, checking the ids the JavaScript
 * relies on and that stored text is escaped.
 *
 * @covers \mod_airoleplay\output\consent
 * @covers \mod_airoleplay\output\room
 * @covers \mod_airoleplay\output\results
 * @covers \mod_airoleplay\output\breakdown
 * @covers \mod_airoleplay\output\submission_detail
 */
final class output_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Student. */
    private \stdClass $student;

    /** @var \stdClass Activity. */
    private \stdClass $airoleplay;

    /** @var \context_module Module context. */
    private \context_module $context;

    /** @var \mod_airoleplay_generator Plugin generator. */
    private \mod_airoleplay_generator $generator;

    /**
     * Creates a course, a student and a two-avatar activity.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course     = $this->getDataGenerator()->create_course();
        $this->student    = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->generator  = $this->getDataGenerator()->get_plugin_generator('mod_airoleplay');
        $this->airoleplay = $this->generator->create_instance([
            'course'        => $this->course->id,
            'num_avatars'   => 2,
            'avatar_2_name' => 'Jordan',
            'avatar_2_role' => 'Manager',
        ]);
        $cm = get_coursemodule_from_instance('airoleplay', $this->airoleplay->id);
        $this->context = \context_module::instance($cm->id);
    }

    public function test_consent_renders_form(): void {
        global $OUTPUT;
        $html = $OUTPUT->render(new consent(new \moodle_url('/mod/airoleplay/view.php', ['id' => 1])));
        $this->assertStringContainsString('id="airoleplay_gdpr_consent"', $html);
        $this->assertStringContainsString('name="sesskey" value="' . sesskey() . '"', $html);
    }

    public function test_room_renders_the_elements_the_script_needs(): void {
        global $OUTPUT;
        $html = $OUTPUT->render(new room($this->airoleplay, $this->context, true, false));
        foreach (
            [
                'airoleplay_roleplay_panel', 'airoleplay_timer', 'airoleplay_avatar_1', 'airoleplay_avatar_2',
                'avatar_video_1', 'airoleplay_transcript', 'airoleplay_ptt_btn', 'airoleplay_text_form',
                'airoleplay_text_input', 'airoleplay_text_send', 'airoleplay_status', 'airoleplay_mode_notice',
                'airoleplay_evaluating_panel', 'airoleplay_eval_status',
            ] as $id
        ) {
            $this->assertStringContainsString('id="' . $id . '"', $html);
        }
        $this->assertStringContainsString('Jordan', $html);
        $this->assertStringContainsString('A customer returns a faulty product.', $html);

        // While evaluating, only the evaluating panel and the scenario show.
        $html = $OUTPUT->render(new room($this->airoleplay, $this->context, false, true));
        $this->assertStringNotContainsString('airoleplay_roleplay_panel', $html);
        $this->assertMatchesRegularExpression(
            '/class="airoleplay-panel text-center py-5 *" id="airoleplay_evaluating_panel"/',
            $html
        );
        $this->assertStringContainsString('A customer returns a faulty product.', $html);
    }

    public function test_results_escape_model_output_and_hide_unreleased_grades(): void {
        global $OUTPUT;
        $submission = $this->generator->create_submission([
            'airoleplay'        => $this->airoleplay->id,
            'userid'            => $this->student->id,
            'status'            => 'graded',
            'workflow_state'    => 'released',
            'final_grade'       => 82.5,
            'final_feedback'    => 'Good <b>work</b>',
            'roleplay_analysis' => json_encode(['strengths' => ['<script>alert(1)</script>']]),
            'grade_breakdown'   => json_encode(['communication' => ['score' => 80, 'weight' => 0.3, 'feedback' => '<i>x</i>']]),
        ]);
        $html = $OUTPUT->render(new results($submission, $this->context, null, ''));
        $this->assertStringContainsString('82.50', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>work</b>', $html);
        $this->assertStringNotContainsString('<i>x</i>', $html);
        $this->assertStringContainsString(get_string('dimension_communication', 'mod_airoleplay'), $html);

        $submission->workflow_state = 'inreview';
        $html = $OUTPUT->render(new results($submission, $this->context, null, ''));
        $this->assertStringNotContainsString('82.50', $html);
        $this->assertStringContainsString(get_string('grade_pending_review', 'mod_airoleplay'), $html);
    }

    public function test_submission_detail_shows_transcript_flags_and_form(): void {
        global $OUTPUT;
        $submission = $this->generator->create_submission([
            'airoleplay'        => $this->airoleplay->id,
            'userid'            => $this->student->id,
            'status'            => 'graded',
            'workflow_state'    => 'inreview',
            'final_grade'       => 40,
            'roleplay_analysis' => json_encode(['academic_integrity_flags' => ['insufficient_participation']]),
        ]);
        $this->generator->add_turn($submission, 'avatar_2', 'Welcome.');
        $this->generator->add_turn($submission, 'participant', 'Hi <b>there</b>');
        $submission = $this->reload($submission->id);

        $html = $OUTPUT->render(new submission_detail($this->airoleplay, $this->student, $submission, $this->context, true));
        $this->assertStringContainsString('Jordan', $html);
        $this->assertStringContainsString('Hi &lt;b&gt;there&lt;/b&gt;', $html);
        $this->assertStringContainsString(get_string('flag_insufficient_participation', 'mod_airoleplay'), $html);
        $this->assertStringContainsString('value="publish"', $html);
        $this->assertStringNotContainsString('value="return"', $html);

        // Without the grade capability there are no grading controls.
        $html = $OUTPUT->render(new submission_detail($this->airoleplay, $this->student, $submission, $this->context, false));
        $this->assertStringNotContainsString('value="publish"', $html);
        $this->assertStringNotContainsString('airoleplay-regen-btn', $html);
    }

    /**
     * Reloads an attempt from the database.
     *
     * @param int $id Attempt id.
     * @return \stdClass
     */
    private function reload(int $id): \stdClass {
        global $DB;
        return $DB->get_record('airoleplay_submissions', ['id' => $id], '*', MUST_EXIST);
    }
}
