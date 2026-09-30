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
 * Text-to-speech provider contract for mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\api\provider;

/**
 * Contract implemented by every TTS backend (OpenAI, Gemini).
 */
interface tts_provider {
    /**
     * Synthesises speech for a text string.
     *
     * Implementations must validate the voice id against their own catalogue
     * and fall back to a sensible default when it belongs to another provider
     * (e.g. an activity configured with OpenAI voices after the site switched
     * its TTS provider to Gemini).
     *
     * @param string $text   Text to synthesise.
     * @param string $voice  Voice id (provider-specific).
     * @param int    $userid Moodle user id for rate limiting.
     * @return array ['audio' => raw audio bytes, 'mime' => MIME type string].
     * @throws \moodle_exception on API error.
     */
    public function speak(string $text, string $voice, int $userid = 0): array;
}
