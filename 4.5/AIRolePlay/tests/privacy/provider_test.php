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
 * Privacy provider tests for mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_airoleplay\privacy\provider::class)]
/**
 * Tests for {@see \mod_airoleplay\privacy\provider}.
 *
 * @covers \mod_airoleplay\privacy\provider
 */
final class provider_test extends provider_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Activity. */
    private \stdClass $airoleplay;

    /** @var \context_module Activity context. */
    private \context_module $context;

    /**
     * Creates a course with one activity.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course     = $this->getDataGenerator()->create_course();
        $this->airoleplay = $this->getDataGenerator()->create_module('airoleplay', ['course' => $this->course->id]);
        $this->context    = \context_module::instance($this->airoleplay->cmid);
    }

    /**
     * Creates a student with one attempt and one conversation turn.
     *
     * @return array [user, submission]
     */
    private function create_student_with_attempt(): array {
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $generator  = $this->getDataGenerator()->get_plugin_generator('mod_airoleplay');
        $submission = $generator->create_submission([
            'airoleplay' => $this->airoleplay->id,
            'userid'     => $user->id,
            'status'     => 'active',
        ]);
        $generator->add_turn($submission, 'participant', 'Hello, how can I help you?');
        return [$user, $submission];
    }

    public function test_metadata_declares_every_external_provider(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('mod_airoleplay'));
        $names = array_map(fn($item) => $item->get_name(), $collection->get_collection());
        $expected = [
            'airoleplay_submissions', 'airoleplay_messages', 'airoleplay_overrides',
            'openai', 'anthropic', 'gemini', 'deepseek',
        ];
        foreach ($expected as $name) {
            $this->assertContains($name, $names);
        }
    }

    public function test_contexts_users_and_export(): void {
        [$user] = $this->create_student_with_attempt();

        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertEquals([$this->context->id], $contextlist->get_contextids());

        $userlist = new userlist($this->context, 'mod_airoleplay');
        provider::get_users_in_context($userlist);
        $this->assertContains((int)$user->id, array_map('intval', $userlist->get_userids()));

        $this->export_context_data_for_user($user->id, $this->context, 'mod_airoleplay');
        $this->assertTrue(writer::with_context($this->context)->has_any_data());
    }

    public function test_delete_for_one_user_keeps_others(): void {
        global $DB;
        [$user1] = $this->create_student_with_attempt();
        [$user2, $sub2] = $this->create_student_with_attempt();

        provider::delete_data_for_user(new approved_contextlist($user1, 'mod_airoleplay', [$this->context->id]));
        $this->assertFalse($DB->record_exists('airoleplay_submissions', ['userid' => $user1->id]));
        $this->assertTrue($DB->record_exists('airoleplay_submissions', ['userid' => $user2->id]));
        $this->assertTrue($DB->record_exists('airoleplay_messages', ['submission_id' => $sub2->id]));

        provider::delete_data_for_users(new approved_userlist($this->context, 'mod_airoleplay', [$user2->id]));
        $this->assertFalse($DB->record_exists('airoleplay_messages', ['submission_id' => $sub2->id]));
    }

    public function test_delete_all_in_context(): void {
        global $DB;
        $this->create_student_with_attempt();
        provider::delete_data_for_all_users_in_context($this->context);
        $this->assertSame(0, $DB->count_records('airoleplay_submissions', ['airoleplay' => $this->airoleplay->id]));
    }
}
