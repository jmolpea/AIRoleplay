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
 * Event fired when a grade is released to the student.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\event;

/**
 * Event: grade issued.
 */
class grade_issued extends \core\event\base {
    /**
     * Initialises event data.
     */
    protected function init(): void {
        $this->data['crud']        = 'u';
        $this->data['edulevel']    = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'airoleplay_submissions';
    }

    /**
     * Returns the event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_grade_issued', 'mod_airoleplay');
    }

    /**
     * Returns a human-readable description.
     *
     * @return string
     */
    public function get_description(): string {
        return "Grade was issued for submission with id '{$this->objectid}' " .
               "to user with id '$this->relateduserid'.";
    }

    /**
     * Maps the object id when course logs are restored.
     *
     * @return array
     */
    public static function get_objectid_mapping(): array {
        return ['db' => 'airoleplay_submissions', 'restore' => 'airoleplay_submission'];
    }

    /**
     * Validates the event data.
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
    }

    /**
     * Returns the URL of the relevant page.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/airoleplay/view.php', ['id' => $this->contextinstanceid]);
    }
}
