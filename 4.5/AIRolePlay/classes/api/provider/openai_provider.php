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
 * OpenAI provider (chat, moderation and TTS) for mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\api\provider;

/**
 * OpenAI backend: Chat Completions, the Moderation endpoint (only applied when
 * OpenAI is the chat provider, per plugin policy) and MP3 text-to-speech.
 */
class openai_provider extends openai_compatible_provider implements tts_provider {
    /** @var string[] Voice ids accepted by the OpenAI TTS endpoint. */
    public const VOICES = ['alloy', 'ash', 'coral', 'echo', 'fable', 'onyx', 'nova', 'sage', 'shimmer'];

    /** @var string Fallback voice when the configured one is unknown. */
    public const DEFAULT_VOICE = 'onyx';

    /**
     * Human-readable provider name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'OpenAI';
    }

    /**
     * Config setting holding the API key.
     *
     * @return string
     */
    protected function apikey_config_name(): string {
        return 'openai_apikey';
    }

    /**
     * OpenAI API base URL.
     *
     * @return string
     */
    protected function base_url(): string {
        return 'https://api.openai.com/v1';
    }

    /**
     * Models that produce reasoning tokens but reject an explicit
     * reasoning_effort parameter, returning a 400 if one is sent.
     *
     * @var string[]
     */
    private const NO_REASONING_EFFORT = ['gpt-5.6-luna'];

    /**
     * OpenAI reasoning models (GPT-6, GPT-5 and the o-series) reject the
     * legacy max_tokens parameter and require max_completion_tokens instead.
     *
     * @param string $model Model id.
     * @return string Request body key.
     */
    protected function max_tokens_param(string $model): string {
        return $this->is_reasoning_model($model) ? 'max_completion_tokens' : 'max_tokens';
    }

    /**
     * Identifies the reasoning-model families: gpt-6*, gpt-5* and the o-series.
     *
     * @param string $model Model id.
     * @return bool
     */
    private function is_reasoning_model(string $model): bool {
        return (bool)preg_match('/^(gpt-[6-9]|gpt-5|o\d)/', strtolower(trim($model)));
    }

    /**
     * GPT-6 accepts reasoning_effort 'none', which gives the lowest latency for
     * live roleplay turns; GPT-5.x does not, so 'low' is its fastest setting.
     * Evaluation uses 'medium' everywhere.
     *
     * @param string $model   Model id.
     * @param string $profile Inference profile.
     * @return string|null
     */
    protected function reasoning_effort(string $model, string $profile): ?string {
        $model = strtolower(trim($model));
        if (!$this->is_reasoning_model($model)) {
            return null;
        }
        foreach (self::NO_REASONING_EFFORT as $excluded) {
            // Prefix match so dated snapshots of an excluded model are covered too.
            if (str_starts_with($model, $excluded)) {
                return null;
            }
        }
        if ($profile === chat_provider::PROFILE_EVALUATION) {
            return 'medium';
        }
        return preg_match('/^gpt-[6-9]/', $model) ? 'none' : 'low';
    }

    /**
     * Reasoning models think unless they run with effort 'none'.
     *
     * @param string $model   Model id.
     * @param string $profile Inference profile.
     * @return bool
     */
    protected function thinks(string $model, string $profile): bool {
        return $this->is_reasoning_model($model) && $this->reasoning_effort($model, $profile) !== 'none';
    }

    /**
     * All current OpenAI chat models support strict structured outputs.
     *
     * @param string $model Model id.
     * @return bool
     */
    protected function supports_json_schema(string $model): bool {
        return true;
    }

    /**
     * Runs the content filter (moderation endpoint) on the participant's
     * reply when enabled. Only calls flagged with 'moderate' carry student
     * text: the opening and closing lines are built from plugin prompts
     * alone, and the evaluation is never moderated (a flag there would leave
     * the attempt impossible to grade).
     *
     * @param array $messages Internal-format messages array.
     * @param array $options  Chat options.
     */
    protected function before_chat(array $messages, array $options): void {
        if (!empty($options['moderate']) && !empty($this->config->safety_content_filter)) {
            $this->moderate_messages($messages);
        }
    }

    /**
     * Generates TTS audio via the OpenAI TTS endpoint.
     *
     * @param string $text   Text to synthesise.
     * @param string $voice  Voice id.
     * @param int    $userid Moodle user id for rate limiting.
     * @return array ['audio' => MP3 bytes, 'mime' => 'audio/mpeg'].
     * @throws \moodle_exception on API error.
     */
    public function speak(string $text, string $voice, int $userid = 0): array {
        // The optional secondary key exists specifically to separate TTS spend.
        $encryptedsecondary = (string)($this->config->openai_apikey_secondary ?? '');
        $ttskey = $encryptedsecondary ? $this->decrypt_key($encryptedsecondary) : '';
        if ($ttskey === '') {
            $this->require_api_key();
            $ttskey = $this->apikey;
        }
        $this->check_rate_limit($userid);

        if (!in_array($voice, self::VOICES, true)) {
            $voice = self::DEFAULT_VOICE;
        }

        $audio = $this->request_raw(
            $this->base_url() . '/audio/speech',
            [
                'model' => 'gpt-4o-mini-tts',
                'input' => $text,
                'voice' => $voice,
            ],
            [
                'Authorization: Bearer ' . $ttskey,
                'Content-Type: application/json',
            ],
            self::TTS_TIMEOUT
        );

        return ['audio' => $audio, 'mime' => 'audio/mpeg'];
    }

    /**
     * Checks the student's latest message against the OpenAI moderation endpoint.
     *
     * Only the newest user turn is sent: earlier turns were already checked
     * when they were spoken, and re-sending the whole history every turn
     * added latency without catching anything new.
     *
     * @param array $messages Chat messages array.
     * @throws \moodle_exception if content is flagged.
     */
    private function moderate_messages(array $messages): void {
        $usermessages = array_values(array_filter($messages, fn($m) => ($m['role'] ?? '') === 'user'));
        $latest = $usermessages ? trim((string)($usermessages[count($usermessages) - 1]['content'] ?? '')) : '';
        if ($latest === '') {
            return;
        }

        try {
            $response = $this->request_json(
                $this->base_url() . '/moderations',
                ['model' => 'omni-moderation-latest', 'input' => $latest],
                [
                    'Authorization: Bearer ' . $this->apikey,
                    'Content-Type: application/json',
                ],
                null,
                self::MODERATION_TIMEOUT
            );
            foreach ($response['results'] ?? [] as $result) {
                if (!empty($result['flagged'])) {
                    throw new \moodle_exception('content_flagged', 'mod_airoleplay');
                }
            }
        } catch (\moodle_exception $e) {
            if ($e->errorcode === 'content_flagged') {
                throw $e;
            }
            // Fail open: a moderation outage must not block every turn.
            \airoleplay_log_internal_error('moderation_endpoint', $e);
        }
    }
}
