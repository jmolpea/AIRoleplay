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

    // Section: Available Models.
    $settings->add(new admin_setting_heading(
        'mod_airoleplay/models_heading',
        get_string('settings_models_heading', 'mod_airoleplay'),
        get_string('settings_models_heading_desc', 'mod_airoleplay')
    ));

    // Enable GPT-4o.
    $settings->add(new admin_setting_configcheckbox(
        'mod_airoleplay/enable_gpt4o',
        get_string('settings_enable_gpt4o', 'mod_airoleplay'),
        get_string('settings_enable_gpt4o_desc', 'mod_airoleplay'),
        1
    ));

    // Enable GPT-4o-mini.
    $settings->add(new admin_setting_configcheckbox(
        'mod_airoleplay/enable_gpt4o_mini',
        get_string('settings_enable_gpt4o_mini', 'mod_airoleplay'),
        get_string('settings_enable_gpt4o_mini_desc', 'mod_airoleplay'),
        1
    ));

    // Estimated cost notice.
    $settings->add(new admin_setting_heading(
        'mod_airoleplay/cost_estimate_heading',
        get_string('settings_cost_estimate_heading', 'mod_airoleplay'),
        get_string('settings_cost_estimate_desc', 'mod_airoleplay')
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

    // Salt for anonymisation hash.
    $settings->add(new admin_setting_configtext(
        'mod_airoleplay/anonymize_salt',
        get_string('settings_anonymize_salt', 'mod_airoleplay'),
        get_string('settings_anonymize_salt_desc', 'mod_airoleplay'),
        '',
        PARAM_TEXT
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
        60,
        PARAM_INT
    ));
}
