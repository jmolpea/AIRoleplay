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
 * Tests for the teacher-review setting (site default, lock and activity value).
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/airoleplay/lib.php');

#[\PHPUnit\Framework\Attributes\CoversFunction('airoleplay_grading_workflow_enabled')]
/**
 * Tests for the teacher-review setting.
 *
 * @covers ::airoleplay_grading_workflow_enabled
 * @covers ::airoleplay_grading_workflow_default
 * @covers ::airoleplay_grading_workflow_locked
 * @covers ::airoleplay_process_form_data
 */
final class grading_workflow_test extends \advanced_testcase {
    public function test_review_is_the_default_until_the_admin_decides(): void {
        $this->resetAfterTest();
        unset_config('grading_workflow', 'mod_airoleplay');
        $this->assertSame(1, airoleplay_grading_workflow_default());

        set_config('grading_workflow', 0, 'mod_airoleplay');
        $this->assertSame(0, airoleplay_grading_workflow_default());

        // A new activity without an explicit choice takes the site default.
        $data = (object)[];
        airoleplay_process_form_data($data);
        $this->assertSame(0, $data->grading_workflow);
    }

    public function test_teacher_choice_applies_when_not_locked(): void {
        $this->resetAfterTest();
        set_config('grading_workflow', 1, 'mod_airoleplay');
        set_config('grading_workflow_locked', 0, 'mod_airoleplay');

        $this->assertFalse(airoleplay_grading_workflow_enabled((object)['grading_workflow' => 0]));
        $this->assertTrue(airoleplay_grading_workflow_enabled((object)['grading_workflow' => 1]));

        $data = (object)['grading_workflow' => 0];
        airoleplay_process_form_data($data);
        $this->assertSame(0, $data->grading_workflow);
    }

    public function test_lock_overrides_every_activity(): void {
        $this->resetAfterTest();

        // Locked to automatic grading: even activities saved with review publish directly.
        set_config('grading_workflow', 0, 'mod_airoleplay');
        set_config('grading_workflow_locked', 1, 'mod_airoleplay');
        $this->assertFalse(airoleplay_grading_workflow_enabled((object)['grading_workflow' => 1]));
        $data = (object)['grading_workflow' => 1];
        airoleplay_process_form_data($data);
        $this->assertSame(0, $data->grading_workflow);

        // Locked to teacher review.
        set_config('grading_workflow', 1, 'mod_airoleplay');
        $this->assertTrue(airoleplay_grading_workflow_enabled((object)['grading_workflow' => 0]));
    }
}
