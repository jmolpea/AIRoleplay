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
 * DeepSeek provider (chat only) for mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\api\provider;

/**
 * DeepSeek backend. The API is OpenAI Chat Completions compatible, including
 * response_format json_object; it adds a 'thinking' switch for the V4 family.
 * DeepSeek has no TTS or moderation endpoint.
 */
class deepseek_provider extends openai_compatible_provider {
    /**
     * Human-readable provider name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'DeepSeek';
    }

    /**
     * Config setting holding the API key.
     *
     * @return string
     */
    protected function apikey_config_name(): string {
        return 'deepseek_apikey';
    }

    /**
     * DeepSeek API base URL.
     *
     * @return string
     */
    protected function base_url(): string {
        return 'https://api.deepseek.com';
    }

    /**
     * DeepSeek V4 models think by default. Live roleplay turns switch thinking
     * off for latency; the evaluation keeps it on.
     *
     * @param string $model   Model id.
     * @param string $profile Inference profile.
     * @return array
     */
    protected function extra_body_params(string $model, string $profile): array {
        $enabled = ($profile === chat_provider::PROFILE_EVALUATION);
        return ['thinking' => ['type' => $enabled ? 'enabled' : 'disabled']];
    }

    /**
     * Reasoning effort only applies while thinking is enabled.
     *
     * @param string $model   Model id.
     * @param string $profile Inference profile.
     * @return string|null
     */
    protected function reasoning_effort(string $model, string $profile): ?string {
        return ($profile === chat_provider::PROFILE_EVALUATION) ? 'high' : null;
    }

    /**
     * Thinking tokens are only produced by the evaluation profile.
     *
     * @param string $model   Model id.
     * @param string $profile Inference profile.
     * @return bool
     */
    protected function thinks(string $model, string $profile): bool {
        return $profile === chat_provider::PROFILE_EVALUATION;
    }
}
