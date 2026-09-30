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
 * Google Gemini provider (chat and TTS) for mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\api\provider;

/**
 * Gemini backend using the Generative Language API.
 *
 * Differences handled here vs. the OpenAI wire format:
 *  - Auth uses the x-goog-api-key header.
 *  - The system prompt travels as systemInstruction; turns use role user/model.
 *  - JSON output is requested via generationConfig.responseMimeType.
 *  - TTS returns raw 16-bit 24 kHz mono PCM, which is wrapped into a WAV
 *    container here so browsers can play it.
 */
class gemini_provider extends base_provider implements chat_provider, tts_provider {
    /** @var string API base URL. */
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    /** @var string Model used for speech synthesis. */
    private const TTS_MODEL = 'gemini-3.8-flash-tts';

    /** @var int Output cap floor for live turns (thinking tokens count against it). */
    private const REALTIME_MIN_OUTPUT_TOKENS = 4096;

    /** @var int Output cap floor for the evaluation. */
    private const EVALUATION_MIN_OUTPUT_TOKENS = 16000;

    /** @var string[] Curated subset of Gemini prebuilt voice ids. */
    public const VOICES = ['Zephyr', 'Puck', 'Charon', 'Kore', 'Fenrir', 'Leda', 'Orus', 'Aoede'];

    /** @var string Fallback voice when the configured one is unknown. */
    public const DEFAULT_VOICE = 'Kore';

    /** @var int Sample rate of the PCM audio returned by the TTS endpoint. */
    private const TTS_SAMPLE_RATE = 24000;

    /**
     * Human-readable provider name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'Google Gemini';
    }

    /**
     * Config setting holding the API key.
     *
     * @return string
     */
    protected function apikey_config_name(): string {
        return 'gemini_apikey';
    }

    /**
     * Sends a chat request to the Gemini generateContent endpoint.
     *
     * @param array  $messages Internal-format messages array.
     * @param string $model    Model id (e.g. 'gemini-2.5-flash').
     * @param array  $options  See {@see chat_provider::chat()}.
     * @param int    $userid   Moodle user id for rate limiting.
     * @return string Generated text.
     * @throws \moodle_exception on API error or rate limit exceeded.
     */
    public function chat(array $messages, string $model, array $options = [], int $userid = 0): string {
        $this->require_api_key();
        $this->check_rate_limit($userid);

        $normalised = $this->normalise_turns($messages);

        $contents = [];
        foreach ($normalised['turns'] as $turn) {
            $contents[] = [
                'role'  => ($turn['role'] === 'assistant') ? 'model' : 'user',
                'parts' => [['text' => $turn['content']]],
            ];
        }

        $profile  = $this->profile($options);
        $thinking = $this->thinking_config($model, $profile);

        // The maxOutputTokens cap covers thinking and visible tokens combined.
        $cap = (int)($options['max_tokens'] ?? $this->maxtokens);
        $floor = ($profile === chat_provider::PROFILE_EVALUATION)
            ? self::EVALUATION_MIN_OUTPUT_TOKENS
            : self::REALTIME_MIN_OUTPUT_TOKENS;
        $generationconfig = [
            'maxOutputTokens' => max($cap, $floor),
        ];
        if ($thinking !== null) {
            $generationconfig['thinkingConfig'] = $thinking;
        }
        if (!empty($options['json'])) {
            $generationconfig['responseMimeType'] = 'application/json';
            if (!empty($options['json_schema'])) {
                $generationconfig['responseJsonSchema'] = $options['json_schema'];
            }
        }

        $body = [
            'contents'         => $contents,
            'generationConfig' => $generationconfig,
        ];
        if ($normalised['system'] !== '') {
            $body['systemInstruction'] = ['parts' => [['text' => $normalised['system']]]];
        }

        $response = $this->request_json(
            self::BASE_URL . '/' . rawurlencode($model) . ':generateContent',
            $body,
            [
                'x-goog-api-key: ' . $this->apikey,
                'Content-Type: application/json',
            ],
            [$this, 'simplify_body'],
            $this->chat_budget($profile)
        );

        if (!empty($response['promptFeedback']['blockReason'])) {
            throw new \moodle_exception('content_flagged', 'mod_airoleplay');
        }
        $candidate = $response['candidates'][0] ?? [];
        $finish    = (string)($candidate['finishReason'] ?? '');
        if (in_array($finish, ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII'], true)) {
            throw new \moodle_exception('content_flagged', 'mod_airoleplay');
        }

        $text = '';
        foreach ($candidate['content']['parts'] ?? [] as $part) {
            // Thought summaries are flagged with 'thought' and never shown.
            if (empty($part['thought'])) {
                $text .= $part['text'] ?? '';
            }
        }
        $text = trim($text);
        if ($text === '') {
            throw $this->empty_response($model, $finish ?: 'empty_response');
        }
        return $text;
    }

    /**
     * Fallback body used when the API rejects a request with a 400: drops the
     * thinking configuration and the response schema, which some models do
     * not accept, keeping plain JSON mode.
     *
     * @param array $body Rejected request body.
     * @return array|null Simplified body, or null when nothing can be dropped.
     */
    public function simplify_body(array $body): ?array {
        $simplified = $body;
        unset(
            $simplified['generationConfig']['thinkingConfig'],
            $simplified['generationConfig']['responseJsonSchema']
        );
        return ($simplified === $body) ? null : $simplified;
    }

    /**
     * Thinking configuration for a model and profile.
     *
     * Gemini 3 models take a thinking level (thinking cannot be switched off,
     * 'low' is the fastest level every 3.x model accepts). Gemini 2.5 Flash
     * models take a token budget, where 0 disables thinking; 2.5 Pro cannot
     * disable it and keeps its default.
     *
     * @param string $model   Model id.
     * @param string $profile Inference profile.
     * @return array|null thinkingConfig object, or null to use the model default.
     */
    private function thinking_config(string $model, string $profile): ?array {
        $model = strtolower(trim($model));
        $evaluation = ($profile === chat_provider::PROFILE_EVALUATION);
        if (preg_match('/^gemini-[3-9]/', $model)) {
            return ['thinkingLevel' => $evaluation ? 'medium' : 'low'];
        }
        if (str_starts_with($model, 'gemini-2.5-flash') && !$evaluation) {
            return ['thinkingBudget' => 0];
        }
        return null;
    }

    /**
     * Generates TTS audio via the Gemini speech model.
     *
     * @param string $text   Text to synthesise.
     * @param string $voice  Voice id (e.g. 'Kore').
     * @param int    $userid Moodle user id for rate limiting.
     * @return array ['audio' => WAV bytes, 'mime' => 'audio/wav'].
     * @throws \moodle_exception on API error.
     */
    public function speak(string $text, string $voice, int $userid = 0): array {
        $this->require_api_key();
        $this->check_rate_limit($userid);

        if (!in_array($voice, self::VOICES, true)) {
            $voice = self::DEFAULT_VOICE;
        }

        $body = [
            'contents' => [
                ['parts' => [['text' => $text]]],
            ],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => [
                    'voiceConfig' => [
                        'prebuiltVoiceConfig' => ['voiceName' => $voice],
                    ],
                ],
            ],
        ];

        $response = $this->request_json(
            self::BASE_URL . '/' . self::TTS_MODEL . ':generateContent',
            $body,
            [
                'x-goog-api-key: ' . $this->apikey,
                'Content-Type: application/json',
            ],
            null,
            self::TTS_TIMEOUT,
            true
        );

        $inline  = $response['candidates'][0]['content']['parts'][0]['inlineData'] ?? [];
        $encoded = (string)($inline['data'] ?? '');
        $audio   = $encoded !== '' ? base64_decode($encoded, true) : false;
        if ($audio === false || $audio === '') {
            throw new api_exception(200, 'Gemini TTS returned no audio');
        }

        // Already a WAV container: pass it through untouched.
        if (str_starts_with($audio, 'RIFF')) {
            return ['audio' => $audio, 'mime' => 'audio/wav'];
        }
        // Raw 16-bit PCM; the sample rate travels in the MIME type
        // (e.g. "audio/L16;codec=pcm;rate=24000").
        $samplerate = self::TTS_SAMPLE_RATE;
        if (preg_match('/rate=(\d+)/', (string)($inline['mimeType'] ?? ''), $matches)) {
            $samplerate = (int)$matches[1];
        }
        return ['audio' => $this->wrap_pcm_in_wav($audio, $samplerate), 'mime' => 'audio/wav'];
    }

    /**
     * Wraps raw 16-bit mono PCM data in a WAV (RIFF) container.
     *
     * @param string $pcm        Raw PCM bytes (s16le, mono).
     * @param int    $samplerate Sample rate in Hz.
     * @return string WAV file bytes.
     */
    private function wrap_pcm_in_wav(string $pcm, int $samplerate): string {
        $channels      = 1;
        $bitspersample = 16;
        $blockalign    = $channels * ($bitspersample / 8);
        $byterate      = $samplerate * $blockalign;
        $datasize      = strlen($pcm);

        return 'RIFF'
            . pack('V', 36 + $datasize)
            . 'WAVE'
            . 'fmt '
            . pack('V', 16)                 // Sub-chunk size.
            . pack('v', 1)                  // PCM format.
            . pack('v', $channels)
            . pack('V', $samplerate)
            . pack('V', $byterate)
            . pack('v', $blockalign)
            . pack('v', $bitspersample)
            . 'data'
            . pack('V', $datasize)
            . $pcm;
    }
}
