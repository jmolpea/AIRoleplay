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
 * Data generator for mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Creates AI Roleplay activities, attempts and conversation turns for tests.
 */
class mod_airoleplay_generator extends testing_module_generator {
    /**
     * Creates an activity instance with sensible defaults.
     *
     * @param array|stdClass|null $record Instance data.
     * @param array|null          $options Generator options.
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (array)($record ?? []) + [
            'scenario_description'       => 'A customer returns a faulty product.',
            'scenario_descriptionformat' => FORMAT_HTML,
            'participant_role'           => 'Shop assistant',
            'session_duration'           => 10,
            'num_avatars'                => 1,
            'max_attempts'               => 2,
            'grade'                      => 100,
            'grading_workflow'           => 0,
            'notify_student'             => 0,
            'openai_model_roleplay'      => 'gpt-6-sol',
            'openai_model_eval'          => 'gpt-6-sol',
            'avatar_1_name'              => 'Alex',
            'avatar_1_role'              => 'Customer',
            'avatar_1_voice'             => 'onyx',
            'avatar_1_avatar'            => 1,
        ];
        return parent::create_instance($record, (array)$options);
    }

    /**
     * Creates an attempt for a user.
     *
     * @param array $record Must contain airoleplay and userid.
     * @return stdClass
     */
    public function create_submission(array $record): stdClass {
        global $DB;
        $now = time();
        $record = (object)($record + [
            'groupid'           => 0,
            'status'            => 'draft',
            'attempt'           => 1,
            'gdpr_consent'      => 1,
            'gdpr_consent_time' => $now,
            'timecreated'       => $now,
            'timemodified'      => $now,
        ]);
        $record->id = $DB->insert_record('airoleplay_submissions', $record);
        return $record;
    }

    /**
     * Adds a conversation turn to an attempt (message log and transcript).
     *
     * @param stdClass $submission Attempt.
     * @param string   $speaker    participant|avatar_N.
     * @param string   $text       Line text.
     */
    public function add_turn(stdClass $submission, string $speaker, string $text): void {
        global $DB;
        $turn = (int)$DB->count_records('airoleplay_messages', ['submission_id' => $submission->id]);
        $DB->insert_record('airoleplay_messages', (object)[
            'submission_id' => $submission->id,
            'turn_number'   => $turn,
            'speaker'       => $speaker,
            'message_text'  => $text,
            'timestamp'     => time(),
        ]);
        $transcript = json_decode((string)$DB->get_field(
            'airoleplay_submissions',
            'roleplay_transcript',
            ['id' => $submission->id]
        ), true) ?: [];
        $transcript[] = ['turn' => $turn, 'speaker' => $speaker, 'text' => $text, 'time' => time()];
        $DB->set_field('airoleplay_submissions', 'roleplay_transcript', json_encode($transcript), ['id' => $submission->id]);
    }
}
