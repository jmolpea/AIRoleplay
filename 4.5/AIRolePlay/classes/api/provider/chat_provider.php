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
 * Chat provider contract for mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\api\provider;

/**
 * Contract implemented by every AI chat backend (OpenAI, Anthropic, Gemini, DeepSeek).
 *
 * Messages use the internal (OpenAI-style) format: an array of
 * ['role' => 'system'|'user'|'assistant', 'content' => string] entries.
 * Each implementation translates that to its own wire format.
 */
interface chat_provider {
    /** @var string Live roleplay turn: the student is waiting, minimise latency. */
    public const PROFILE_REALTIME = 'realtime';

    /** @var string Final evaluation: runs once per attempt, favour judgement. */
    public const PROFILE_EVALUATION = 'evaluation';

    /**
     * Sends a chat completion request and returns the generated text.
     *
     * Supported options:
     *  - 'max_tokens'  (int)    Per-call output token cap (defaults to the global setting).
     *                           Providers raise this to a safe floor on thinking models,
     *                           whose hidden reasoning tokens count against the same cap.
     *  - 'json'        (bool)   Request a JSON-object response.
     *  - 'json_schema' (array)  JSON schema for the response when 'json' is set.
     *  - 'profile'     (string) PROFILE_REALTIME or PROFILE_EVALUATION. Each provider
     *                           maps it to its own thinking/effort parameter for the
     *                           given model (defaults to PROFILE_REALTIME).
     *  - 'moderate'    (bool)   The last user message is participant text: providers
     *                           with a moderation endpoint check it first when the
     *                           site content filter is on.
     *
     * @param array  $messages Internal-format messages array.
     * @param string $model    Model id (e.g. 'gpt-6-sol', 'claude-sonnet-5').
     * @param array  $options  Extra options, see above.
     * @param int    $userid   Moodle user id for rate limiting (0 = skip per-user check).
     * @return string Generated text (for JSON mode, the raw JSON string).
     * @throws \moodle_exception on API error or rate limit exceeded.
     */
    public function chat(array $messages, string $model, array $options = [], int $userid = 0): string;
}
