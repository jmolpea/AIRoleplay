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
 * Anthropic (Claude) provider for mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\api\provider;

/**
 * Anthropic Messages API backend (chat only — Anthropic offers no TTS).
 *
 * Differences handled here vs. the OpenAI wire format:
 *  - Auth uses x-api-key + anthropic-version headers instead of Bearer.
 *  - The system prompt is a top-level parameter, not a message role.
 *  - Messages must strictly alternate user/assistant and start with user,
 *    so consecutive same-role turns are merged (see base::normalise_turns()).
 *  - max_tokens is mandatory.
 *  - JSON output is enforced via output_config.format (structured outputs)
 *    when a schema is supplied; there is no generic json_object mode.
 */
class anthropic_provider extends base_provider implements chat_provider {
    /** @var string Messages API endpoint. */
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    /** @var string Pinned API version header. */
    private const API_VERSION = '2023-06-01';

    /** @var int Output cap floor for live turns on thinking models. */
    private const REALTIME_MIN_OUTPUT_TOKENS = 4096;

    /** @var int Output cap floor for the evaluation on thinking models. */
    private const EVALUATION_MIN_OUTPUT_TOKENS = 16000;

    /**
     * Human-readable provider name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'Anthropic';
    }

    /**
     * Config setting holding the API key.
     *
     * @return string
     */
    protected function apikey_config_name(): string {
        return 'anthropic_apikey';
    }

    /**
     * Sends a chat request to the Anthropic Messages API.
     *
     * @param array  $messages Internal-format messages array.
     * @param string $model    Model id (e.g. 'claude-sonnet-5').
     * @param array  $options  See {@see chat_provider::chat()}.
     * @param int    $userid   Moodle user id for rate limiting.
     * @return string Generated text.
     * @throws \moodle_exception on API error or rate limit exceeded.
     */
    public function chat(array $messages, string $model, array $options = [], int $userid = 0): string {
        $this->require_api_key();
        $this->check_rate_limit($userid);

        $normalised = $this->normalise_turns($messages);
        $profile    = $this->profile($options);
        $effort     = $this->effort($model, $profile);

        $cap = (int)($options['max_tokens'] ?? $this->maxtokens);
        if ($effort !== null) {
            // Current models think adaptively and thinking tokens count against
            // max_tokens, so a short cap could end the turn before any text.
            $floor = ($profile === chat_provider::PROFILE_EVALUATION)
                ? self::EVALUATION_MIN_OUTPUT_TOKENS
                : self::REALTIME_MIN_OUTPUT_TOKENS;
            $cap = max($cap, $floor);
        }

        $body = [
            'model'      => $model,
            'max_tokens' => $cap,
            'messages'   => $normalised['turns'],
        ];
        if ($normalised['system'] !== '') {
            $body['system'] = $normalised['system'];
        }
        if ($effort !== null) {
            $body['output_config']['effort'] = $effort;
        }

        if (!empty($options['json'])) {
            if (!empty($options['json_schema'])) {
                // Structured outputs guarantee schema-valid JSON.
                $body['output_config']['format'] = [
                    'type'   => 'json_schema',
                    'schema' => $options['json_schema'],
                ];
            } else {
                // Best effort without a schema; the caller strips code fences.
                $last = count($body['messages']) - 1;
                $body['messages'][$last]['content'] .=
                    "\n\nRespond with a single valid JSON object only — no markdown fences, no prose.";
            }
        }

        $response = $this->request_json(
            self::ENDPOINT,
            $body,
            [
                'x-api-key: ' . $this->apikey,
                'anthropic-version: ' . self::API_VERSION,
                'Content-Type: application/json',
            ],
            [$this, 'simplify_body'],
            $this->chat_budget($profile)
        );

        $stopreason = (string)($response['stop_reason'] ?? '');
        if ($stopreason === 'refusal') {
            throw new \moodle_exception('content_flagged', 'mod_airoleplay');
        }

        // Concatenate all text content blocks (thinking blocks etc. are skipped).
        $text = '';
        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'] ?? '';
            }
        }
        $text = trim($text);
        if ($text === '') {
            throw $this->empty_response($model, $stopreason ?: 'empty_response');
        }
        return $text;
    }

    /**
     * Fallback body used when the API rejects a request with a 400: drops the
     * effort setting, which older or future models may not accept.
     *
     * @param array $body Rejected request body.
     * @return array|null Simplified body, or null when nothing can be dropped.
     */
    public function simplify_body(array $body): ?array {
        if (!isset($body['output_config']['effort'])) {
            return null;
        }
        unset($body['output_config']['effort']);
        if (empty($body['output_config'])) {
            unset($body['output_config']);
        }
        return $body;
    }

    /**
     * Effort level for a model and profile, or null when the model does not
     * accept the effort parameter (Claude Haiku 4.5 rejects it).
     *
     * Thinking itself is never configured explicitly: current models decide
     * adaptively, and effort is the documented way to trade depth for speed.
     *
     * @param string $model   Model id.
     * @param string $profile Inference profile.
     * @return string|null
     */
    private function effort(string $model, string $profile): ?string {
        $model = strtolower(trim($model));
        if (str_starts_with($model, 'claude-haiku') || str_starts_with($model, 'claude-3')) {
            return null;
        }
        return ($profile === chat_provider::PROFILE_EVALUATION) ? 'medium' : 'low';
    }
}
