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
 * Restore structure step for mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the XML structure mapping for restoring a mod_airoleplay backup.
 */
class restore_airoleplay_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines the structure to be restored.
     *
     * @return array Array of restore_path_element objects.
     */
    protected function define_structure(): array {
        $paths    = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('airoleplay', '/activity/airoleplay');
        // Overrides are activity configuration; restored regardless of userinfo
        // because group overrides survive even when student data is dropped.
        $paths[] = new restore_path_element(
            'airoleplay_override',
            '/activity/airoleplay/overrides/override'
        );

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'airoleplay_submission',
                '/activity/airoleplay/submissions/submission'
            );
            $paths[] = new restore_path_element(
                'airoleplay_message',
                '/activity/airoleplay/submissions/submission/roleplay_messages/roleplay_message'
            );
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Processes a restored airoleplay element.
     *
     * @param array $data Data from the backup XML.
     */
    protected function process_airoleplay(array $data): void {
        global $DB;

        $data         = (object)$data;
        $oldid        = $data->id;
        $data->course = $this->get_courseid();

        // Remap grouping reference into the new course's grouping set.
        if (!empty($data->groupingid)) {
            $data->groupingid = (int)$this->get_mappingid('grouping', $data->groupingid) ?: 0;
        }

        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timecreated  = $this->apply_date_offset($data->timecreated);
        $data->timeopen     = !empty($data->timeopen) ? $this->apply_date_offset($data->timeopen) : 0;
        $data->timeclose    = !empty($data->timeclose) ? $this->apply_date_offset($data->timeclose) : 0;

        $newid = $DB->insert_record('airoleplay', $data);
        $this->apply_activity_instance($newid);
        $this->set_mapping('airoleplay', $oldid, $newid);
    }

    /**
     * Processes a restored override element.
     *
     * Skips the row when the user or group it pointed at does not exist in
     * the destination course (e.g. a course-import without users will lose
     * user overrides but keep group overrides if the group is also imported).
     *
     * @param array $data Data from the backup XML.
     */
    protected function process_airoleplay_override(array $data): void {
        global $DB;

        $data             = (object)$data;
        $data->airoleplay = $this->get_new_parentid('airoleplay');

        if (!empty($data->userid)) {
            $mappedid = $this->get_mappingid('user', $data->userid);
            if (!$mappedid) {
                return;
            }
            $data->userid = $mappedid;
        }
        if (!empty($data->groupid)) {
            $mappedid = $this->get_mappingid('group', $data->groupid);
            if (!$mappedid) {
                return;
            }
            $data->groupid = $mappedid;
        }
        if (empty($data->userid) && empty($data->groupid)) {
            // Neither anchor mapped — nothing to restore.
            return;
        }

        $data->timecreated  = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        if (!empty($data->timeopen)) {
            $data->timeopen = $this->apply_date_offset($data->timeopen);
        }
        if (!empty($data->timeclose)) {
            $data->timeclose = $this->apply_date_offset($data->timeclose);
        }

        $DB->insert_record('airoleplay_overrides', $data);
    }

    /**
     * Processes a restored submission element.
     *
     * @param array $data Data from the backup XML.
     */
    protected function process_airoleplay_submission(array $data): void {
        global $DB;

        $data              = (object)$data;
        $oldid             = $data->id;
        $data->airoleplay  = $this->get_new_parentid('airoleplay');
        $data->userid      = $this->get_mappingid('user', $data->userid);
        if (!$data->userid) {
            // The participant is not part of this restore: the attempt has no owner.
            return;
        }
        $data->grader_userid = $data->grader_userid
            ? $this->get_mappingid('user', $data->grader_userid)
            : null;

        $data->timecreated  = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->groupid = !empty($data->groupid) ? (int)$this->get_mappingid('group', $data->groupid) : 0;
        if (!empty($data->timestarted)) {
            $data->timestarted = $this->apply_date_offset($data->timestarted);
        }
        if ($data->timesubmitted) {
            $data->timesubmitted = $this->apply_date_offset($data->timesubmitted);
        }
        if ($data->timegraded) {
            $data->timegraded = $this->apply_date_offset($data->timegraded);
        }

        $newid = $DB->insert_record('airoleplay_submissions', $data);
        $this->set_mapping('airoleplay_submission', $oldid, $newid, true);
    }

    /**
     * Processes a restored roleplay message element.
     *
     * @param array $data Data from the backup XML.
     */
    protected function process_airoleplay_message(array $data): void {
        global $DB;

        $data                = (object)$data;
        $data->submission_id = $this->get_new_parentid('airoleplay_submission');
        $data->timestamp     = $this->apply_date_offset($data->timestamp);

        $DB->insert_record('airoleplay_messages', $data);
    }

    /**
     * Adds any required post-processing steps after the structure is restored.
     */
    protected function after_execute(): void {
        // Restore files for the activity (intro, avatars).
        $this->add_related_files('mod_airoleplay', 'intro', null);
        $this->add_related_files('mod_airoleplay', 'avatar_custom', null);
    }
}
