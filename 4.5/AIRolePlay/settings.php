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
 * Global plugin settings for mod_airoleplay.
 *
 * Displayed under: Site administration > Plugins > Activity modules > AI Roleplay
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Section: License.
    $settings->add(new admin_setting_heading(
        'mod_airoleplay/license_heading',
        get_string('license_heading', 'mod_airoleplay'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'mod_airoleplay/license_key',
        get_string('license_key', 'mod_airoleplay'),
        get_string('license_key_desc', 'mod_airoleplay'),
        '',
        PARAM_RAW_TRIMMED
    ));

    // License status indicator — computed inline at render time (offline, no DB hit).
    $licenseresult = \mod_airoleplay\license\validator::get_settings_status();
    $settings->add(new admin_setting_heading(
        'mod_airoleplay/license_status_display',
        '',
        html_writer::tag('span', $licenseresult['text'], ['class' => $licenseresult['css']])
    ));

    // Section: API Keys.
    $settings->add(new admin_setting_heading(
        'mod_airoleplay/apikeys_heading',
        get_string('settings_apikeys_heading', 'mod_airoleplay'),
        get_string('settings_apikeys_heading_desc', 'mod_airoleplay')
    ));

    // Primary OpenAI API Key. Stored encrypted via \core\encryption.
    $settings->add(new admin_setting_encryptedpassword(
        'mod_airoleplay/openai_apikey',
        get_string('settings_openai_apikey', 'mod_airoleplay'),
        get_string('settings_openai_apikey_desc', 'mod_airoleplay')
    ));

    // Secondary API Key (optional, for TTS separation). Stored encrypted.
    $settings->add(new admin_setting_encryptedpassword(
        'mod_airoleplay/openai_apikey_secondary',
        get_string('settings_openai_apikey_secondary', 'mod_airoleplay'),
        get_string('settings_openai_apikey_secondary_desc', 'mod_airoleplay')
    ));

    // Anthropic (Claude) API Key. Stored encrypted.
    $settings->add(new admin_setting_encryptedpassword(
        'mod_airoleplay/anthropic_apikey',
        get_string('settings_anthropic_apikey', 'mod_airoleplay'),
        get_string('settings_anthropic_apikey_desc', 'mod_airoleplay')
    ));

    // Google Gemini API Key. Stored encrypted.
    $settings->add(new admin_setting_encryptedpassword(
        'mod_airoleplay/gemini_apikey',
        get_string('settings_gemini_apikey', 'mod_airoleplay'),
        get_string('settings_gemini_apikey_desc', 'mod_airoleplay')
    ));

    // DeepSeek API Key. Stored encrypted.
    $settings->add(new admin_setting_encryptedpassword(
        'mod_airoleplay/deepseek_apikey',
        get_string('settings_deepseek_apikey', 'mod_airoleplay'),
        get_string('settings_deepseek_apikey_desc', 'mod_airoleplay')
    ));

    // Connection test: calls the configured providers once with a tiny prompt.
    $settings->add(new admin_setting_description(
        'mod_airoleplay/testconnection',
        get_string('testconnection', 'mod_airoleplay'),
        html_writer::link(
            new moodle_url('/mod/airoleplay/testconnection.php'),
            get_string('testconnection_run', 'mod_airoleplay'),
            ['class' => 'btn btn-secondary']
        ) . html_writer::div(get_string('testconnection_desc', 'mod_airoleplay'), 'form-text text-muted mt-1')
    ));

    // Section: Text-to-speech.
    $settings->add(new admin_setting_heading(
        'mod_airoleplay/tts_heading',
        get_string('settings_tts_heading', 'mod_airoleplay'),
        get_string('settings_tts_heading_desc', 'mod_airoleplay')
    ));

    // Site-wide TTS provider (determines the avatar voice catalogue).
    $settings->add(new admin_setting_configselect(
        'mod_airoleplay/tts_provider',
        get_string('settings_tts_provider', 'mod_airoleplay'),
        get_string('settings_tts_provider_desc', 'mod_airoleplay'),
        'openai',
        [
            'openai'  => get_string('tts_provider_openai', 'mod_airoleplay'),
            'gemini'  => get_string('tts_provider_gemini', 'mod_airoleplay'),
            'browser' => get_string('tts_provider_browser', 'mod_airoleplay'),
            'none'    => get_string('tts_provider_none', 'mod_airoleplay'),
        ]
    ));

    // Section: Chat provider and available models.
    $settings->add(new admin_setting_heading(
        'mod_airoleplay/models_heading',
        get_string('settings_models_heading', 'mod_airoleplay'),
        get_string('settings_models_heading_desc', 'mod_airoleplay')
    ));

    // Site-wide chat provider. Determines which model picker is shown below
    // and which models teachers can choose in activities.
    $settings->add(new admin_setting_configselect(
        'mod_airoleplay/chat_provider',
        get_string('settings_chat_provider', 'mod_airoleplay'),
        get_string('settings_chat_provider_desc', 'mod_airoleplay'),
        'openai',
        [
            'openai'    => get_string('provider_openai', 'mod_airoleplay'),
            'anthropic' => get_string('provider_anthropic', 'mod_airoleplay'),
            'gemini'    => get_string('provider_gemini', 'mod_airoleplay'),
            'deepseek'  => get_string('provider_deepseek', 'mod_airoleplay'),
        ]
    ));

    // One model picker per provider; only the selected provider's picker is
    // visible thanks to the hide_if dependencies declared below.
    $catalog = \mod_airoleplay\form\mod_form_helper::model_catalog();
    foreach ($catalog as $providerid => $models) {
        $settings->add(new admin_setting_configmultiselect(
            "mod_airoleplay/{$providerid}_models",
            get_string("settings_{$providerid}_models", 'mod_airoleplay'),
            get_string('settings_provider_models_desc', 'mod_airoleplay'),
            array_keys($models),
            $models
        ));
        $settings->hide_if(
            "mod_airoleplay/{$providerid}_models",
            'mod_airoleplay/chat_provider',
            'neq',
            $providerid
        );
    }

    // Estimated cost notice.
    $settings->add(new admin_setting_heading(
        'mod_airoleplay/cost_estimate_heading',
        get_string('settings_cost_estimate_heading', 'mod_airoleplay'),
        get_string('settings_cost_estimate_desc', 'mod_airoleplay')
    ));

    // Section: Grading. Default (and optional site-wide lock) for teacher
    // review before AI grades are released.
    $settings->add(new admin_setting_heading(
        'mod_airoleplay/grading_heading',
        get_string('settings_grading_heading', 'mod_airoleplay'),
        ''
    ));
    $settings->add(new admin_setting_configcheckbox_with_lock(
        'mod_airoleplay/grading_workflow',
        get_string('settings_grading_workflow', 'mod_airoleplay'),
        get_string('settings_grading_workflow_desc', 'mod_airoleplay'),
        ['value' => 1, 'locked' => 0]
    ));

    // Section: Security.
    $settings->add(new admin_setting_heading(
        'mod_airoleplay/security_heading',
        get_string('settings_security_heading', 'mod_airoleplay'),
        get_string('settings_security_heading_desc', 'mod_airoleplay')
    ));

    // Enable OpenAI content filter (moderation endpoint).
    $settings->add(new admin_setting_configcheckbox(
        'mod_airoleplay/safety_content_filter',
        get_string('settings_safety_content_filter', 'mod_airoleplay'),
        get_string('settings_safety_content_filter_desc', 'mod_airoleplay'),
        1
    ));

    // Max tokens per API call.
    $settings->add(new admin_setting_configtext(
        'mod_airoleplay/safety_max_tokens',
        get_string('settings_safety_max_tokens', 'mod_airoleplay'),
        get_string('settings_safety_max_tokens_desc', 'mod_airoleplay'),
        4096,
        PARAM_INT
    ));

    // Anonymize student names in prompts (always on, display-only).
    $settings->add(new admin_setting_heading(
        'mod_airoleplay/anonymize_heading',
        get_string('settings_anonymize_heading', 'mod_airoleplay'),
        get_string('settings_anonymize_desc', 'mod_airoleplay')
    ));

    // Salt for anonymisation hash. Empty values are allowed and will be
    // auto-generated on upgrade with random_bytes(32). Manual values must
    // be at least 32 characters of [A-Za-z0-9_-] for predictability.
    $settings->add(new admin_setting_configtext(
        'mod_airoleplay/anonymize_salt',
        get_string('settings_anonymize_salt', 'mod_airoleplay'),
        get_string('settings_anonymize_salt_desc', 'mod_airoleplay'),
        '',
        '/^([A-Za-z0-9_\-]{32,128})?$/'
    ));

    // Section: GDPR notice text.
    $settings->add(new admin_setting_heading(
        'mod_airoleplay/gdpr_heading',
        get_string('settings_gdpr_heading', 'mod_airoleplay'),
        get_string('settings_gdpr_heading_desc', 'mod_airoleplay')
    ));

    $settings->add(new admin_setting_configtextarea(
        'mod_airoleplay/gdpr_notice_text',
        get_string('settings_gdpr_notice_text', 'mod_airoleplay'),
        get_string('settings_gdpr_notice_text_desc', 'mod_airoleplay'),
        get_string('gdpr_default_notice', 'mod_airoleplay'),
        PARAM_RAW
    ));

    // Section: Advanced / API timeouts.
    $settings->add(new admin_setting_heading(
        'mod_airoleplay/advanced_heading',
        get_string('settings_advanced_heading', 'mod_airoleplay'),
        ''
    ));

    // API request timeout (seconds).
    $settings->add(new admin_setting_configtext(
        'mod_airoleplay/api_timeout',
        get_string('settings_api_timeout', 'mod_airoleplay'),
        get_string('settings_api_timeout_desc', 'mod_airoleplay'),
        120,
        PARAM_INT
    ));

    // Max API calls per minute per user.
    $settings->add(new admin_setting_configtext(
        'mod_airoleplay/api_rate_limit',
        get_string('settings_api_rate_limit', 'mod_airoleplay'),
        get_string('settings_api_rate_limit_desc', 'mod_airoleplay'),
        10,
        PARAM_INT
    ));

    // Max API calls per minute across the whole installation (global backstop).
    $settings->add(new admin_setting_configtext(
        'mod_airoleplay/api_rate_limit_global',
        get_string('settings_api_rate_limit_global', 'mod_airoleplay'),
        get_string('settings_api_rate_limit_global_desc', 'mod_airoleplay'),
        300,
        PARAM_INT
    ));
}
