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
 * Event fired when a student starts a roleplay session.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\event;

/**
 * Event: submission created.
 */
class submission_created extends \core\event\base {
    /**
     * Initialises event data.
     */
    protected function init(): void {
        $this->data['crud']        = 'c';
        $this->data['edulevel']    = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'airoleplay_submissions';
    }

    /**
     * Returns the event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_submission_created', 'mod_airoleplay');
    }

    /**
     * Returns a human-readable description.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '$this->userid' started a roleplay session with id '{$this->objectid}' " .
               "for the airoleplay activity with course module id '{$this->contextinstanceid}'.";
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
     * Returns the URL of the relevant page.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/airoleplay/view.php', ['id' => $this->contextinstanceid]);
    }
}
