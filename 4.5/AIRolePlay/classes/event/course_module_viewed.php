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
 * Event fired when a user views the airoleplay activity.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\event;

/**
 * Event: course module viewed.
 */
class course_module_viewed extends \core\event\course_module_viewed {
    /**
     * Initialises event data.
     */
    protected function init(): void {
        $this->data['crud']        = 'r';
        $this->data['edulevel']    = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'airoleplay';
    }

    /**
     * Factory: create from course module, course and airoleplay objects.
     *
     * @param \stdClass $cm         Course module record.
     * @param \stdClass $course     Course record.
     * @param \stdClass $airoleplay Airoleplay instance record.
     * @return self
     */
    public static function create_from_cm(\stdClass $cm, \stdClass $course, \stdClass $airoleplay): self {
        $event = self::create([
            'objectid' => $airoleplay->id,
            'context'  => \context_module::instance($cm->id),
        ]);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('airoleplay', $airoleplay);
        $event->add_record_snapshot('course_modules', $cm);
        return $event;
    }
}
