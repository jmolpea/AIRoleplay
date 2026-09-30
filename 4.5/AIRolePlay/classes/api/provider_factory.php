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
 * AI provider factory for mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\api;

use mod_airoleplay\api\provider\anthropic_provider;
use mod_airoleplay\api\provider\chat_provider;
use mod_airoleplay\api\provider\deepseek_provider;
use mod_airoleplay\api\provider\gemini_provider;
use mod_airoleplay\api\provider\openai_provider;
use mod_airoleplay\api\provider\tts_provider;
use mod_airoleplay\form\mod_form_helper;

/**
 * Resolves model ids and site configuration to provider instances.
 *
 * The provider is inferred from the model id prefix, so activities only store
 * a model id (in the historical openai_model_* columns) and no extra provider
 * column is needed.
 */
class provider_factory {
    /** @var array Cached provider instances, keyed by class name. */
    private static array $instances = [];

    /**
     * Returns the chat provider responsible for a given model id.
     *
     * @param string $model Model id (e.g. 'gpt-6-sol', 'claude-sonnet-5', 'gemini-3.8-flash', 'deepseek-flash').
     * @return chat_provider
     */
    public static function chat_provider_for_model(string $model): chat_provider {
        switch (mod_form_helper::provider_for_model($model)) {
            case 'anthropic':
                return self::instance(anthropic_provider::class);
            case 'gemini':
                return self::instance(gemini_provider::class);
            case 'deepseek':
                return self::instance(deepseek_provider::class);
            default:
                // OpenAI: gpt-*, o* and any unknown legacy value.
                return self::instance(openai_provider::class);
        }
    }

    /**
     * Returns the site-wide server-side TTS provider selected in plugin settings.
     *
     * @return tts_provider|null Null when speech is synthesised in the browser
     *                           or disabled, so no server call is made.
     */
    public static function tts_provider(): ?tts_provider {
        switch (mod_form_helper::tts_provider()) {
            case 'gemini':
                return self::instance(gemini_provider::class);
            case 'openai':
                return self::instance(openai_provider::class);
            default:
                return null;
        }
    }

    /**
     * Resets cached instances (used by unit tests when config changes).
     */
    public static function reset(): void {
        self::$instances = [];
    }

    /**
     * Returns (and lazily creates) the singleton instance of a provider class.
     *
     * @param string $classname Fully qualified provider class name.
     * @return mixed Provider instance.
     */
    private static function instance(string $classname) {
        if (!isset(self::$instances[$classname])) {
            self::$instances[$classname] = new $classname();
        }
        return self::$instances[$classname];
    }
}
