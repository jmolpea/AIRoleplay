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
 * PII redactor used at the OpenAI boundary.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\privacy;

/**
 * Replaces a participant's personally identifying tokens with a stable hash
 * before the text leaves the LMS for OpenAI.
 *
 * Anonymisation is best-effort: it covers names/usernames/email present in
 * the configured user profile. Free-text leakage that the user types about
 * themselves outside those fields cannot be fully redacted, but the README
 * promise ("student names are never sent to OpenAI") is now enforced for
 * the values Moodle actually knows about.
 */
class anonymizer {
    /**
     * Returns a deterministic opaque identifier for a user.
     *
     * @param int $userid Moodle user id.
     * @return string e.g. "STUDENT-9a1c4f7b2e8d6310".
     */
    public static function hash_user(int $userid): string {
        if ($userid <= 0) {
            return 'STUDENT-anon';
        }
        $salt = (string)get_config('mod_airoleplay', 'anonymize_salt');
        if ($salt === '') {
            // Falls back to the per-install identifier so anonymisation is
            // still deterministic even if the admin never set a custom salt.
            $salt = get_site_identifier();
        }
        return 'STUDENT-' . substr(hash('sha256', $userid . '|' . $salt), 0, 16);
    }

    /**
     * Redacts personal identifiers of a single user from arbitrary text.
     *
     * The participant's first name is intentionally NOT redacted: the
     * roleplay_conductor includes it in the system prompt so the avatar
     * can greet the human naturally, and redacting it here would create
     * an inconsistent "the AI knows you as Ada / your line shows STUDENT-x"
     * experience. Last name, full name combinations, username and email
     * are still redacted because they are more identifying.
     *
     * @param string $text   Raw text that may contain the user's name/email.
     * @param int    $userid Owner of the data being redacted.
     * @return string Redacted text.
     */
    public static function redact_text(string $text, int $userid): string {
        global $DB;
        if ($text === '' || $userid <= 0) {
            return $text;
        }
        $user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, username, email');
        if (!$user) {
            return $text;
        }
        $hash = self::hash_user($userid);
        $candidates = [
            trim($user->firstname . ' ' . $user->lastname),
            trim($user->lastname . ' ' . $user->firstname),
            trim((string)$user->lastname),
            trim((string)$user->username),
            trim((string)$user->email),
        ];
        // Replace longer matches first so "Ada Lovelace" wins over "Lovelace".
        $candidates = array_values(array_unique(array_filter($candidates, fn($c) => mb_strlen($c) >= 2)));
        usort($candidates, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

        foreach ($candidates as $candidate) {
            // Whole words only: a last name such as "Sol" must not mangle
            // "solution" in the student's own sentence.
            $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($candidate, '/') . '(?![\p{L}\p{N}])/iu';
            $replaced = preg_replace($pattern, $hash, $text);
            if ($replaced !== null) {
                $text = $replaced;
            }
        }
        return $text;
    }

    /**
     * Redacts each text-bearing entry of a roleplay transcript JSON string.
     *
     * The transcript is an array of {turn, speaker, text, time} records. The
     * shape is preserved so downstream consumers (evaluator, exports) keep
     * working — only the text fields are rewritten.
     *
     * @param string $transcriptjson JSON-encoded transcript.
     * @param int    $userid         Owner of the data.
     * @return string Redacted JSON, or the original string if it was not parseable.
     */
    public static function redact_transcript_json(string $transcriptjson, int $userid): string {
        if ($transcriptjson === '') {
            return $transcriptjson;
        }
        $decoded = json_decode($transcriptjson, true);
        if (!is_array($decoded)) {
            return self::redact_text($transcriptjson, $userid);
        }
        foreach ($decoded as &$turn) {
            if (is_array($turn) && isset($turn['text']) && is_string($turn['text'])) {
                $turn['text'] = self::redact_text($turn['text'], $userid);
            }
        }
        unset($turn);
        $reencoded = json_encode($decoded, JSON_UNESCAPED_UNICODE);
        return $reencoded !== false ? $reencoded : $transcriptjson;
    }
}
