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
 * Upgrade steps for mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the mod_airoleplay plugin.
 *
 * @param int $oldversion The version being upgraded from.
 * @return bool True on success.
 */
function xmldb_airoleplay_upgrade(int $oldversion): bool {
    global $DB;

    if ($oldversion < 2025050900) {
        // Migrate API keys from admin_setting_configpasswordunmask (plaintext)
        // to admin_setting_encryptedpassword. If the stored value cannot be
        // decrypted, assume it was plaintext and re-encrypt it in place.
        foreach (['openai_apikey', 'openai_apikey_secondary'] as $configkey) {
            $value = get_config('mod_airoleplay', $configkey);
            if (empty($value)) {
                continue;
            }
            $alreadyencrypted = false;
            try {
                $decrypted = \core\encryption::decrypt($value);
                $alreadyencrypted = ($decrypted !== false && $decrypted !== null && $decrypted !== '');
            } catch (\Throwable $e) {
                $alreadyencrypted = false;
            }
            if ($alreadyencrypted) {
                continue;
            }
            try {
                set_config($configkey, \core\encryption::encrypt($value), 'mod_airoleplay');
            } catch (\Throwable $e) {
                debugging(
                    'airoleplay upgrade: failed to encrypt legacy ' . $configkey . ': ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }
        upgrade_mod_savepoint(true, 2025050900, 'airoleplay');
    }

    if ($oldversion < 2025050901) {
        // Auto-generate a high-entropy anonymisation salt if the admin never set one.
        $salt = (string)get_config('mod_airoleplay', 'anonymize_salt');
        if (mb_strlen($salt) < 32) {
            try {
                set_config('anonymize_salt', bin2hex(random_bytes(32)), 'mod_airoleplay');
            } catch (\Throwable $e) {
                debugging(
                    'airoleplay upgrade: failed to seed anonymize_salt: ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }
        upgrade_mod_savepoint(true, 2025050901, 'airoleplay');
    }

    if ($oldversion < 2025070702) {
        // The default model for new activities moves to Claude Sonnet 5. Existing
        // activities keep whatever model their teacher picked; only the column
        // default changes, so records created from now on get the new value.

        $dbman = $DB->get_manager();
        $table = new xmldb_table('airoleplay');
        foreach (['openai_model_roleplay', 'openai_model_eval'] as $fieldname) {
            $field = new xmldb_field($fieldname, XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'claude-sonnet-5');
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_default($table, $field);
            }
        }

        // Surface the new models in the admin picker without discarding the
        // admin's existing selection.
        $enabled = array_filter(array_map(
            'trim',
            explode(',', (string)get_config('mod_airoleplay', 'openai_models'))
        ));
        if (!empty($enabled)) {
            foreach (['gpt-5.6-luna', 'gpt-5.6-terra'] as $newmodel) {
                if (!in_array($newmodel, $enabled, true)) {
                    $enabled[] = $newmodel;
                }
            }
            set_config('openai_models', implode(',', $enabled), 'mod_airoleplay');
        }

        upgrade_mod_savepoint(true, 2025070702, 'airoleplay');
    }

    if ($oldversion < 2026092800) {
        $dbman = $DB->get_manager();

        // Availability window.
        $table = new xmldb_table('airoleplay');
        $field = new xmldb_field('timeopen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'session_duration');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('timeclose', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timeopen');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Columns that were never read: a per-activity API key (never offered
        // in the form), per-activity safety knobs superseded by site settings,
        // completion columns covered by core grade completion, and the draft
        // item ids of the avatar file managers (files live in the file area).
        foreach (
            [
                'openai_apikey', 'safety_max_tokens', 'safety_content_filter',
                'completiongrade', 'completionmingradeval',
                'avatar_1_avatar_custom', 'avatar_2_avatar_custom', 'avatar_3_avatar_custom',
            ] as $fieldname
        ) {
            $field = new xmldb_field($fieldname);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }

        foreach (['openai_model_roleplay', 'openai_model_eval'] as $fieldname) {
            $field = new xmldb_field($fieldname, XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'gpt-6-sol');
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_default($table, $field);
            }
        }

        // Server-side session clock.
        $table = new xmldb_table('airoleplay_submissions');
        $field = new xmldb_field('timestarted', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'timemodified');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Activities pointing at models the vendors have retired.
        $retired = [
            'deepseek-chat'     => 'deepseek-flash',
            'deepseek-reasoner' => 'deepseek-flash',
            'deepseek-v4-flash' => 'deepseek-flash',
            'gemini-2.0-flash'  => 'gemini-3.8-flash',
        ];
        foreach ($retired as $old => $new) {
            foreach (['openai_model_roleplay', 'openai_model_eval'] as $fieldname) {
                $DB->set_field('airoleplay', $fieldname, $new, [$fieldname => $old]);
            }
        }

        // Surface the new model generations in the admin pickers without
        // discarding what the administrator already selected.
        $newmodels = [
            'openai_models'    => ['gpt-6-sol', 'gpt-6-luna'],
            'anthropic_models' => ['claude-sonnet-5', 'claude-haiku-4-5', 'claude-opus-5-5'],
            'gemini_models'    => ['gemini-3.8-flash', 'gemini-3.5-flash-lite'],
            'deepseek_models'  => ['deepseek-flash', 'deepseek-v4-pro'],
        ];
        foreach ($newmodels as $setting => $models) {
            $current = array_filter(array_map('trim', explode(',', (string)get_config('mod_airoleplay', $setting))));
            if (!$current) {
                continue;
            }
            $current = array_diff($current, array_keys($retired));
            set_config($setting, implode(',', array_unique(array_merge($models, $current))), 'mod_airoleplay');
        }

        // The old 60 calls/minute site cap throttled a single class of ~30
        // students (one chat + one voice call per turn each).
        if ((int)get_config('mod_airoleplay', 'api_rate_limit_global') === 60) {
            set_config('api_rate_limit_global', 300, 'mod_airoleplay');
        }

        // Settings left behind by the 0.1 per-model checkboxes.
        foreach (
            [
                'enable_gpt4o', 'enable_gpt4o_mini', 'enable_claude_sonnet', 'enable_claude_haiku',
                'enable_gemini_flash', 'enable_gemini_pro', 'enable_deepseek_chat',
            ] as $legacy
        ) {
            unset_config($legacy, 'mod_airoleplay');
        }

        upgrade_mod_savepoint(true, 2026092800, 'airoleplay');
    }

    return true;
}
