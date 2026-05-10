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
 * Unit tests for {@see \mod_airoleplay\privacy\anonymizer}.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_airoleplay\privacy\anonymizer
 */

namespace mod_airoleplay;

use mod_airoleplay\privacy\anonymizer;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the PII redactor at the OpenAI boundary.
 */
final class anonymizer_test extends \advanced_testcase {

    public function test_hash_user_returns_anon_token_for_invalid_userid(): void {
        $this->resetAfterTest();
        $this->assertSame('STUDENT-anon', anonymizer::hash_user(0));
        $this->assertSame('STUDENT-anon', anonymizer::hash_user(-5));
    }

    public function test_hash_user_is_deterministic_for_same_userid(): void {
        $this->resetAfterTest();
        set_config('anonymize_salt', str_repeat('a', 32), 'mod_airoleplay');
        $hash1 = anonymizer::hash_user(42);
        $hash2 = anonymizer::hash_user(42);
        $this->assertSame($hash1, $hash2);
        $this->assertStringStartsWith('STUDENT-', $hash1);
    }

    public function test_hash_user_changes_when_salt_changes(): void {
        $this->resetAfterTest();
        set_config('anonymize_salt', str_repeat('a', 32), 'mod_airoleplay');
        $h1 = anonymizer::hash_user(42);
        set_config('anonymize_salt', str_repeat('b', 32), 'mod_airoleplay');
        $h2 = anonymizer::hash_user(42);
        $this->assertNotSame($h1, $h2);
    }

    public function test_hash_user_differs_per_user(): void {
        $this->resetAfterTest();
        set_config('anonymize_salt', str_repeat('a', 32), 'mod_airoleplay');
        $this->assertNotSame(anonymizer::hash_user(1), anonymizer::hash_user(2));
    }

    public function test_redact_text_replaces_full_name_username_and_email(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Ada',
            'lastname'  => 'Lovelace',
            'username'  => 'ada42',
            'email'     => 'ada@example.org',
        ]);

        $hash = anonymizer::hash_user((int)$user->id);

        $cases = [
            'Hi, I am Ada Lovelace and I love Moodle.',
            'Lovelace, Ada speaking.',
            'My handle is ada42 online.',
            'Reach me at ada@example.org any time.',
        ];
        foreach ($cases as $case) {
            $redacted = anonymizer::redact_text($case, (int)$user->id);
            $this->assertStringNotContainsString('Ada', $redacted, $case);
            $this->assertStringNotContainsString('Lovelace', $redacted, $case);
            $this->assertStringNotContainsString('ada42', $redacted, $case);
            $this->assertStringNotContainsString('ada@example.org', $redacted, $case);
            $this->assertStringContainsString($hash, $redacted, $case);
        }
    }

    public function test_redact_text_returns_input_when_user_missing_or_empty(): void {
        $this->resetAfterTest();
        $this->assertSame('', anonymizer::redact_text('', 1));
        $this->assertSame('hello', anonymizer::redact_text('hello', 0));
        $this->assertSame('hello', anonymizer::redact_text('hello', 999999));
    }

    public function test_redact_transcript_json_replaces_text_in_each_turn(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Ada',
            'lastname'  => 'Lovelace',
        ]);

        $transcript = json_encode([
            ['turn' => 1, 'speaker' => 'avatar_1', 'text' => 'Welcome!'],
            ['turn' => 2, 'speaker' => 'participant', 'text' => 'I am Ada Lovelace.'],
        ]);

        $redacted = anonymizer::redact_transcript_json($transcript, (int)$user->id);
        $decoded  = json_decode($redacted, true);
        $this->assertIsArray($decoded);
        $this->assertSame('Welcome!', $decoded[0]['text']);
        $this->assertStringNotContainsString('Ada', $decoded[1]['text']);
        $this->assertStringNotContainsString('Lovelace', $decoded[1]['text']);
        $this->assertStringContainsString(anonymizer::hash_user((int)$user->id), $decoded[1]['text']);
    }

    public function test_redact_transcript_json_falls_back_to_plain_redaction(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Ada',
            'lastname'  => 'Lovelace',
        ]);

        $notjson  = 'Ada Lovelace says hi.';
        $redacted = anonymizer::redact_transcript_json($notjson, (int)$user->id);
        $this->assertStringNotContainsString('Ada', $redacted);
        $this->assertStringContainsString(anonymizer::hash_user((int)$user->id), $redacted);
    }
}
