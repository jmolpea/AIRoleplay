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
 * Prompt-injection detection and delimiter neutralisation.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\local;

/**
 * Stateless helpers used by the evaluator to harden untrusted text.
 *
 * Lives outside the evaluator so the patterns can be unit-tested in
 * isolation and reused elsewhere if other code paths start sending
 * student-controlled text to the model.
 */
class prompt_guard {
    /**
     * Patterns commonly seen in prompt-injection payloads. Hits flag the
     * submission for manual review and bypass auto-publishing.
     */
    public const INJECTION_PATTERNS = [
        '/===\s*(ROLEPLAY|PARTICIPANT|SCENARIO|TEACHER)[^=]{0,40}(START|END)\s*===/iu',
        '/(ignore|disregard|forget)\b.{0,40}(previous|above|prior|all|these|the)\b.{0,40}\binstructions?\b/iu',
        '/^\s*(system|assistant|developer|tool)\s*:\s*/imu',
        '/<\|im_(start|end)\|>/iu',
        '/\x60{3}\s*(system|json|tool_call)/iu',
    ];

    /**
     * Returns true when the text contains any known prompt-injection pattern.
     *
     * @param string $text Untrusted text such as the participant transcript.
     * @return bool
     */
    public static function detect_injection(string $text): bool {
        if ($text === '') {
            return false;
        }
        foreach (self::INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * Replaces literal delimiter strings with a visibly different variant so
     * a participant cannot fake a section boundary in the assembled prompt.
     *
     * @param string $text Possibly hostile text.
     * @return string Same text with delimiter triplets neutralised.
     */
    public static function neutralise_delimiters(string $text): string {
        if ($text === '') {
            return $text;
        }
        return preg_replace(
            '/===\s*([A-Za-z][A-Za-z0-9 _-]{0,60})\s*(START|END)\s*===/iu',
            '[$1 $2]',
            $text
        );
    }
}
