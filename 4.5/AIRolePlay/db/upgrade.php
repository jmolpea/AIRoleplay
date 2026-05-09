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

    return true;
}
