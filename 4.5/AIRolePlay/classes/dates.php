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
 * Activity dates shown in the course page and activity header.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay;

use core\activity_dates;

/**
 * Opening and closing dates of an AI Roleplay activity, honouring overrides.
 */
class dates extends activity_dates {
    /**
     * Returns the dates to display for the current user.
     *
     * @return array
     */
    protected function get_dates(): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/airoleplay/lib.php');

        $airoleplay = $DB->get_record('airoleplay', ['id' => $this->cm->instance], '*', MUST_EXIST);
        $settings   = \airoleplay_get_effective_settings($airoleplay, $this->userid);
        $now        = time();
        $dates      = [];

        if ($settings->timeopen) {
            $key = $settings->timeopen > $now ? 'activitydate:opens' : 'activitydate:opened';
            $dates[] = [
                'dataid'    => 'timeopen',
                'label'     => get_string($key, 'course'),
                'timestamp' => $settings->timeopen,
            ];
        }
        if ($settings->timeclose) {
            $key = $settings->timeclose > $now ? 'activitydate:closes' : 'activitydate:closed';
            $dates[] = [
                'dataid'    => 'timeclose',
                'label'     => get_string($key, 'course'),
                'timestamp' => $settings->timeclose,
            ];
        }
        return $dates;
    }
}
