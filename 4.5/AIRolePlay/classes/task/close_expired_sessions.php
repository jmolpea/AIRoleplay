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
 * Scheduled task: closes roleplay sessions whose time ran out.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\task;

use mod_airoleplay\local\session_manager;

/**
 * Submits and queues the evaluation of sessions abandoned mid-way.
 *
 * The browser closes a session when its countdown ends. When the student
 * closes the tab or loses the connection first, the attempt would otherwise
 * stay 'active' forever with no grade; this task closes it once the
 * server-side deadline (plus grace) has passed.
 */
class close_expired_sessions extends \core\task\scheduled_task {
    /**
     * Returns the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_close_expired_sessions', 'mod_airoleplay');
    }

    /**
     * Executes the task.
     */
    public function execute(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');

        $sql = "SELECT s.id, s.userid, s.timestarted, a.id AS airoleplayid, a.course, a.session_duration,
                       a.completionsubmit
                  FROM {airoleplay_submissions} s
                  JOIN {airoleplay} a ON a.id = s.airoleplay
                 WHERE s.status = :status
                   AND s.timestarted > 0";
        $rs = $DB->get_recordset_sql($sql, ['status' => 'active']);
        $now = time();
        foreach ($rs as $row) {
            $airoleplay = (object)['session_duration' => $row->session_duration];
            if (!session_manager::is_expired($airoleplay, $row, $now)) {
                continue;
            }
            if (!session_manager::close((int)$row->id)) {
                continue;
            }
            $cm = get_coursemodule_from_instance('airoleplay', $row->airoleplayid, $row->course);
            if ($cm) {
                session_manager::queue_evaluation((int)$row->id, (int)$cm->id);
                // Same completion update as when the browser closes the session.
                if (!empty($row->completionsubmit)) {
                    $completion = new \completion_info(get_course($row->course));
                    if ($completion->is_enabled($cm)) {
                        $completion->update_state($cm, COMPLETION_COMPLETE, (int)$row->userid);
                    }
                }
            }
            mtrace('airoleplay: closed expired session ' . $row->id);
        }
        $rs->close();
    }
}
