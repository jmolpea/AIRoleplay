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
 * Restore task for mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/airoleplay/backup/moodle2/restore_airoleplay_stepslib.php');

/**
 * Activity restore task for mod_airoleplay.
 */
class restore_airoleplay_activity_task extends restore_activity_task {
    /**
     * Defines settings specific to mod_airoleplay restore.
     */
    protected function define_my_settings(): void {
        // No custom settings beyond standards.
    }

    /**
     * Defines the restore steps.
     */
    protected function define_my_steps(): void {
        $this->add_step(new restore_airoleplay_activity_structure_step('airoleplay_structure', 'airoleplay.xml'));
    }

    /**
     * Decodes content links during restore.
     *
     * @return array Array of restore_decode_content objects.
     */
    public static function define_decode_contents(): array {
        return [
            new restore_decode_content('airoleplay', ['intro'], 'airoleplay'),
            new restore_decode_content('airoleplay', ['scenario_description'], 'airoleplay'),
        ];
    }

    /**
     * Defines the decode rules.
     *
     * @return array Array of decode rules.
     */
    public static function define_decode_rules(): array {
        return [
            new restore_decode_rule('AIROLEPLAYVIEWBYID', '/mod/airoleplay/view.php?id=$1', 'course_module'),
            new restore_decode_rule('AIROLEPLAYINDEX', '/mod/airoleplay/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Defines the restore log rules.
     *
     * @return array Array of log rules.
     */
    public static function define_restore_log_rules(): array {
        return [
            new restore_log_rule('airoleplay', 'add', 'view.php?id={course_module}', '{airoleplay}'),
            new restore_log_rule('airoleplay', 'view', 'view.php?id={course_module}', '{airoleplay}'),
        ];
    }
}
