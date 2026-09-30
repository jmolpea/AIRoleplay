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
 * Helper utilities for mod_airoleplay's activity configuration form.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\form;

/**
 * Static helpers used by mod_form.php to build form option arrays.
 */
class mod_form_helper {
    /** @var string Site chat provider used until the administrator picks one. */
    public const DEFAULT_PROVIDER = 'openai';

    /** @var string Purpose key for the live roleplay conversation. */
    public const PURPOSE_ROLEPLAY = 'roleplay';

    /** @var string Purpose key for the final evaluation. */
    public const PURPOSE_EVALUATION = 'evaluation';

    /**
     * Recommended model per provider and purpose.
     *
     * This is the single source of truth for defaults: the activity form, the
     * runtime fallbacks and the admin model pickers all read it, so they can
     * never drift apart. Roleplay favours latency (the student is waiting for
     * a spoken reply); evaluation favours judgement (it runs once per attempt).
     */
    private const RECOMMENDED = [
        'openai'    => ['roleplay' => 'gpt-6-sol', 'evaluation' => 'gpt-6-sol'],
        'anthropic' => ['roleplay' => 'claude-sonnet-5', 'evaluation' => 'claude-sonnet-5'],
        'gemini'    => ['roleplay' => 'gemini-3.8-flash', 'evaluation' => 'gemini-3.8-flash'],
        'deepseek'  => ['roleplay' => 'deepseek-flash', 'evaluation' => 'deepseek-v4-pro'],
    ];

    /**
     * Retired model ids and the current model that replaces them at runtime.
     *
     * Vendors shut models down on their own schedule; without this map an
     * activity configured with a retired id would fail on every turn until a
     * teacher re-saved it.
     */
    private const RETIRED_MODELS = [
        'deepseek-chat'         => 'deepseek-flash',
        'deepseek-reasoner'     => 'deepseek-flash',
        'deepseek-v4-flash'     => 'deepseek-flash',
        'gemini-2.0-flash'      => 'gemini-3.8-flash',
        'gemini-2.0-flash-lite' => 'gemini-3.5-flash-lite',
        'gemini-3-pro-preview'  => 'gemini-3.1-pro-preview',
    ];

    /**
     * Full catalogue of models offered for new selections, grouped by provider.
     *
     * Each catalogue is ordered with its recommended model first. Used by the
     * admin settings page (per-provider model pickers) and by the activity form.
     *
     * @return array provider_id => [model_id => display_label].
     */
    public static function model_catalog(): array {
        $recommended = ' ' . get_string('model_recommended', 'mod_airoleplay');
        $economical  = ' ' . get_string('model_economical', 'mod_airoleplay');
        $premium     = ' ' . get_string('model_premium', 'mod_airoleplay');
        $preview     = ' ' . get_string('model_preview', 'mod_airoleplay');

        return [
            'openai' => [
                'gpt-6-sol'   => 'GPT-6 Sol' . $recommended,
                'gpt-6-luna'  => 'GPT-6 Luna' . $economical,
                'gpt-6-astra' => 'GPT-6 Astra' . $premium,
            ],
            'anthropic' => [
                'claude-sonnet-5'  => 'Claude Sonnet 5' . $recommended,
                'claude-haiku-4-5' => 'Claude Haiku 4.5' . $economical,
                'claude-opus-5-5'  => 'Claude Opus 5.5' . $premium,
                'claude-opus-5'    => 'Claude Opus 5',
            ],
            'gemini' => [
                'gemini-3.8-flash'       => 'Gemini 3.8 Flash' . $recommended,
                'gemini-3.5-flash-lite'  => 'Gemini 3.5 Flash-Lite' . $economical,
                'gemini-3.1-pro-preview' => 'Gemini 3.1 Pro' . $preview,
            ],
            'deepseek' => [
                'deepseek-flash'  => 'DeepSeek V4.1 Flash' . $recommended,
                'deepseek-v4-pro' => 'DeepSeek V4 Pro',
            ],
        ];
    }

    /**
     * Previous-generation models that are no longer offered for new
     * selections but keep working for activities that already use them.
     *
     * @return array provider_id => [model_id => display_label].
     */
    public static function legacy_catalog(): array {
        $legacy = ' ' . get_string('model_legacy', 'mod_airoleplay');
        return [
            'openai' => [
                'gpt-5.6-sol'   => 'GPT-5.6 Sol' . $legacy,
                'gpt-5.6-terra' => 'GPT-5.6 Terra' . $legacy,
                'gpt-5.6-luna'  => 'GPT-5.6 Luna' . $legacy,
                'gpt-5.1'       => 'GPT-5.1' . $legacy,
                'gpt-5'         => 'GPT-5' . $legacy,
                'gpt-5-mini'    => 'GPT-5 mini' . $legacy,
                'gpt-4.1'       => 'GPT-4.1' . $legacy,
                'gpt-4.1-mini'  => 'GPT-4.1 mini' . $legacy,
                'gpt-4o'        => 'GPT-4o' . $legacy,
                'gpt-4o-mini'   => 'GPT-4o mini' . $legacy,
            ],
            'anthropic' => [
                'claude-opus-4-8'   => 'Claude Opus 4.8' . $legacy,
                'claude-sonnet-4-6' => 'Claude Sonnet 4.6' . $legacy,
            ],
            'gemini' => [
                'gemini-2.5-flash'      => 'Gemini 2.5 Flash' . $legacy,
                'gemini-2.5-pro'        => 'Gemini 2.5 Pro' . $legacy,
                'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash-Lite' . $legacy,
            ],
            'deepseek' => [],
        ];
    }

    /**
     * Returns the provider id that serves a model, inferred from its prefix.
     *
     * @param string $model Model id.
     * @return string One of 'openai', 'anthropic', 'gemini', 'deepseek'.
     */
    public static function provider_for_model(string $model): string {
        $model = strtolower(trim($model));
        if (str_starts_with($model, 'claude')) {
            return 'anthropic';
        }
        if (str_starts_with($model, 'gemini')) {
            return 'gemini';
        }
        if (str_starts_with($model, 'deepseek')) {
            return 'deepseek';
        }
        return 'openai';
    }

    /**
     * Maps a retired model id to its current replacement; other ids pass through.
     *
     * @param string $model Model id as stored in the activity.
     * @return string Model id to send to the provider.
     */
    public static function resolve_model(string $model): string {
        $model = trim($model);
        return self::RETIRED_MODELS[strtolower($model)] ?? $model;
    }

    /**
     * Recommended model for a purpose.
     *
     * @param string      $purpose  PURPOSE_ROLEPLAY or PURPOSE_EVALUATION.
     * @param string|null $provider Provider id; null means the site chat provider.
     * @return string Model id.
     */
    public static function default_model(string $purpose, ?string $provider = null): string {
        $provider = $provider ?? self::chat_provider();
        $defaults = self::RECOMMENDED[$provider] ?? self::RECOMMENDED[self::DEFAULT_PROVIDER];
        return $defaults[$purpose] ?? $defaults[self::PURPOSE_ROLEPLAY];
    }

    /**
     * Model an activity should use for a purpose, falling back to the
     * recommended one when the activity record carries no model id.
     *
     * @param \stdClass $airoleplay Activity record.
     * @param string    $purpose    PURPOSE_ROLEPLAY or PURPOSE_EVALUATION.
     * @return string Model id, already resolved against retired ids.
     */
    public static function activity_model(\stdClass $airoleplay, string $purpose): string {
        $field = ($purpose === self::PURPOSE_EVALUATION) ? 'openai_model_eval' : 'openai_model_roleplay';
        $model = trim((string)($airoleplay->$field ?? ''));
        if ($model === '') {
            $model = self::default_model($purpose);
        }
        return self::resolve_model($model);
    }

    /**
     * Returns the configured site-wide chat provider id.
     *
     * @return string One of 'openai', 'anthropic', 'gemini', 'deepseek'.
     */
    public static function chat_provider(): string {
        $provider = (string)(get_config('mod_airoleplay', 'chat_provider') ?: self::DEFAULT_PROVIDER);
        return array_key_exists($provider, self::RECOMMENDED) ? $provider : self::DEFAULT_PROVIDER;
    }

    /**
     * Returns the AI model options offered to teachers in the activity form:
     * the models of the site-wide chat provider that the admin enabled.
     *
     * The model the activity currently uses is always kept in the list, even
     * when it belongs to another provider or to the legacy catalogue, so that
     * editing an unrelated setting never silently switches the model.
     *
     * @param string[] $current Model ids currently stored in the activity.
     * @return array Associative array of model_id => display_label.
     */
    public static function get_model_options(array $current = []): array {
        $provider = self::chat_provider();
        $catalog  = self::model_catalog()[$provider];

        // Multiselect settings are stored as a comma-separated list.
        $enabledsetting = (string)get_config('mod_airoleplay', $provider . '_models');
        $enabled = array_filter(array_map('trim', explode(',', $enabledsetting)));
        $options = array_intersect_key($catalog, array_flip($enabled));

        // Nothing enabled (or the setting was never saved): offer the whole
        // catalogue of the selected provider so the form never renders empty.
        $options = $options ?: $catalog;

        $known = [];
        foreach (array_merge_recursive(self::model_catalog(), self::legacy_catalog()) as $models) {
            $known += $models;
        }
        foreach ($current as $model) {
            $model = trim((string)$model);
            if ($model !== '' && !isset($options[$model])) {
                $options[$model] = $known[$model] ?? $model;
            }
        }
        return $options;
    }

    /**
     * Returns the available TTS voice options for the site TTS provider.
     *
     * @return array Associative array of voice_id => display_label.
     */
    public static function get_voice_options(): array {
        $tts = self::tts_provider();
        if ($tts === 'browser' || $tts === 'none') {
            // Server-side voices do not apply: the browser picks a voice for
            // the page language, or the avatars speak as on-screen text only.
            return ['auto' => get_string('voice_auto', 'mod_airoleplay')];
        }
        if ($tts === 'gemini') {
            return [
                'Aoede'  => get_string('voice_aoede', 'mod_airoleplay'),
                'Charon' => get_string('voice_charon', 'mod_airoleplay'),
                'Fenrir' => get_string('voice_fenrir', 'mod_airoleplay'),
                'Kore'   => get_string('voice_kore', 'mod_airoleplay'),
                'Leda'   => get_string('voice_leda', 'mod_airoleplay'),
                'Orus'   => get_string('voice_orus', 'mod_airoleplay'),
                'Puck'   => get_string('voice_puck', 'mod_airoleplay'),
                'Zephyr' => get_string('voice_zephyr', 'mod_airoleplay'),
            ];
        }

        return [
            'alloy'   => get_string('voice_alloy', 'mod_airoleplay'),
            'ash'     => get_string('voice_ash', 'mod_airoleplay'),
            'coral'   => get_string('voice_coral', 'mod_airoleplay'),
            'echo'    => get_string('voice_echo', 'mod_airoleplay'),
            'fable'   => get_string('voice_fable', 'mod_airoleplay'),
            'onyx'    => get_string('voice_onyx', 'mod_airoleplay'),
            'nova'    => get_string('voice_nova', 'mod_airoleplay'),
            'sage'    => get_string('voice_sage', 'mod_airoleplay'),
            'shimmer' => get_string('voice_shimmer', 'mod_airoleplay'),
        ];
    }

    /**
     * Returns the default voice per avatar index for the site TTS provider.
     *
     * @return array [avatar_index => voice_id] for avatars 1-3.
     */
    public static function get_default_voices(): array {
        $tts = self::tts_provider();
        if ($tts === 'browser' || $tts === 'none') {
            return [1 => 'auto', 2 => 'auto', 3 => 'auto'];
        }
        if ($tts === 'gemini') {
            return [1 => 'Charon', 2 => 'Kore', 3 => 'Puck'];
        }
        return [1 => 'onyx', 2 => 'nova', 3 => 'echo'];
    }

    /**
     * Returns the configured site-wide TTS provider id.
     *
     * @return string 'openai', 'gemini', 'browser' (Web Speech synthesis in the
     *                student's browser, no API cost) or 'none' (text only).
     */
    public static function tts_provider(): string {
        $provider = (string)(get_config('mod_airoleplay', 'tts_provider') ?: 'openai');
        return in_array($provider, ['openai', 'gemini', 'browser', 'none'], true) ? $provider : 'openai';
    }
}
