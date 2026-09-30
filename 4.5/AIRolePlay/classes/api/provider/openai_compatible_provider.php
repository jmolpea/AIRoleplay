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
 * Base class for providers exposing an OpenAI-compatible Chat Completions API.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\api\provider;

/**
 * Shared chat implementation for OpenAI and DeepSeek (which speaks the same
 * wire format on a different base URL).
 */
abstract class openai_compatible_provider extends base_provider implements chat_provider {
    /**
     * Floor applied to the output-token cap while the model is thinking.
     *
     * Reasoning tokens are billed and counted inside the output cap, and they
     * are produced *before* any visible text. With a small cap the model can
     * spend the whole budget thinking and return an empty message while still
     * charging for the request. The floor is only a ceiling: short roleplay
     * turns still cost what they actually use.
     */
    protected const THINKING_MIN_OUTPUT_TOKENS = 16000;

    /**
     * Base URL of the provider's OpenAI-compatible API (no trailing slash).
     *
     * @return string
     */
    abstract protected function base_url(): string;

    /**
     * Sends a chat completion request.
     *
     * @param array  $messages Internal-format messages array.
     * @param string $model    Model id.
     * @param array  $options  See {@see chat_provider::chat()}.
     * @param int    $userid   Moodle user id for rate limiting.
     * @return string Generated text.
     * @throws \moodle_exception on API error or rate limit exceeded.
     */
    public function chat(array $messages, string $model, array $options = [], int $userid = 0): string {
        $this->require_api_key();
        $this->check_rate_limit($userid);
        $profile = $this->profile($options);
        $this->before_chat($messages, $options);
        $cap     = (int)($options['max_tokens'] ?? $this->maxtokens);
        if ($this->thinks($model, $profile)) {
            $cap = max($cap, self::THINKING_MIN_OUTPUT_TOKENS);
        }

        $body = [
            'model'    => $model,
            'messages' => $messages,
            $this->max_tokens_param($model) => $cap,
        ];

        // Sampling parameters (temperature, top_p) are deliberately never sent:
        // reasoning models reject them and the roleplay does not need them.
        $effort = $this->reasoning_effort($model, $profile);
        if ($effort !== null) {
            $body['reasoning_effort'] = $effort;
        }
        $body += $this->extra_body_params($model, $profile);

        if (!empty($options['json'])) {
            if (!empty($options['json_schema']) && $this->supports_json_schema($model)) {
                $body['response_format'] = [
                    'type'        => 'json_schema',
                    'json_schema' => [
                        'name'   => 'airoleplay_response',
                        'strict' => true,
                        'schema' => $options['json_schema'],
                    ],
                ];
            } else {
                $body['response_format'] = ['type' => 'json_object'];
            }
        }

        $response = $this->request_json(
            $this->base_url() . '/chat/completions',
            $body,
            [
                'Authorization: Bearer ' . $this->apikey,
                'Content-Type: application/json',
            ],
            [$this, 'simplify_body'],
            $this->chat_budget($profile)
        );

        $choice  = $response['choices'][0] ?? [];
        $content = trim((string)($choice['message']['content'] ?? ''));
        if ($content === '') {
            throw $this->empty_response($model, (string)($choice['finish_reason'] ?? 'empty_response'));
        }

        return $content;
    }

    /**
     * Fallback body used when the API rejects the request with a 400: drops
     * the optional tuning parameters and downgrades strict schemas to plain
     * JSON mode, keeping only what every chat model accepts.
     *
     * @param array $body Rejected request body.
     * @return array|null Simplified body, or null when nothing can be dropped.
     */
    public function simplify_body(array $body): ?array {
        $simplified = $body;
        if (isset($simplified['reasoning_effort'])) {
            // Without an explicit effort the model thinks at its default level,
            // so the output cap must leave room for the hidden reasoning tokens.
            foreach (['max_completion_tokens', 'max_tokens'] as $capkey) {
                if (isset($simplified[$capkey])) {
                    $simplified[$capkey] = max((int)$simplified[$capkey], self::THINKING_MIN_OUTPUT_TOKENS);
                }
            }
        }
        unset($simplified['reasoning_effort'], $simplified['thinking']);
        if (($simplified['response_format']['type'] ?? '') === 'json_schema') {
            $simplified['response_format'] = ['type' => 'json_object'];
        }
        return ($simplified === $body) ? null : $simplified;
    }

    /**
     * Hook executed before the chat request (used by OpenAI for moderation).
     *
     * @param array $messages Internal-format messages array.
     * @param array $options  Chat options, see {@see chat_provider::chat()}.
     */
    protected function before_chat(array $messages, array $options): void {
        // No-op by default.
    }

    /**
     * Name of the output-token-cap parameter for a given model.
     *
     * @param string $model Model id.
     * @return string Request body key ('max_tokens' or 'max_completion_tokens').
     */
    protected function max_tokens_param(string $model): string {
        return 'max_tokens';
    }

    /**
     * Value for the reasoning_effort parameter, or null to omit it.
     *
     * @param string $model   Model id.
     * @param string $profile Inference profile.
     * @return string|null
     */
    protected function reasoning_effort(string $model, string $profile): ?string {
        return null;
    }

    /**
     * Additional provider-specific body parameters for a model and profile.
     *
     * @param string $model   Model id.
     * @param string $profile Inference profile.
     * @return array
     */
    protected function extra_body_params(string $model, string $profile): array {
        return [];
    }

    /**
     * Whether the model will spend hidden reasoning tokens for this profile,
     * which requires raising the output cap.
     *
     * @param string $model   Model id.
     * @param string $profile Inference profile.
     * @return bool
     */
    protected function thinks(string $model, string $profile): bool {
        return false;
    }

    /**
     * Whether the model accepts response_format of type json_schema.
     *
     * @param string $model Model id.
     * @return bool
     */
    protected function supports_json_schema(string $model): bool {
        return false;
    }
}
