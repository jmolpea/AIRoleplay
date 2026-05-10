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
 * Backup structure step for mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Defines the XML structure for a mod_airoleplay backup.
 *
 * API keys are intentionally excluded from the backup for security reasons.
 */
class backup_airoleplay_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the backup structure.
     *
     * @return backup_nested_element The root element.
     */
    protected function define_structure(): backup_nested_element {
        $includesubmissions = $this->get_setting_value('userinfo');

        // Root element — activity settings (no API keys). Every column in
        // {airoleplay} is listed here so a course duplicate or a "Restore
        // into a new course" lands a fully-configured activity that does
        // not need to be re-set up by hand. Only the encrypted API key is
        // intentionally excluded.
        $airoleplay = new backup_nested_element('airoleplay', ['id'], [
            'name', 'intro', 'introformat',
            'num_avatars',
            'scenario_description', 'scenario_descriptionformat',
            'participant_role',
            'session_duration',
            'roleplay_prompt_eval',
            'openai_model_roleplay', 'openai_model_eval',
            'avatar_1_name', 'avatar_1_role', 'avatar_1_prompt',
            'avatar_1_voice', 'avatar_1_avatar', 'avatar_1_avatar_custom',
            'avatar_2_name', 'avatar_2_role', 'avatar_2_prompt',
            'avatar_2_voice', 'avatar_2_avatar', 'avatar_2_avatar_custom',
            'avatar_3_name', 'avatar_3_role', 'avatar_3_prompt',
            'avatar_3_voice', 'avatar_3_avatar', 'avatar_3_avatar_custom',
            'max_attempts',
            'grading_workflow', 'group_submission', 'groupingid',
            'notify_student',
            'safety_max_tokens', 'safety_content_filter', 'safety_extra_prompt',
            'grade', 'completionsubmit', 'completiongrade', 'completionmingradeval',
            'timecreated', 'timemodified',
        ]);

        // Per-user / per-group overrides are activity configuration, so we
        // back them up regardless of userinfo. Restore will skip rows whose
        // user/group cannot be mapped into the new course.
        $overrides = new backup_nested_element('overrides');
        $override  = new backup_nested_element('override', ['id'], [
            'userid', 'groupid', 'max_attempts',
            'timeopen', 'timeclose',
            'timecreated', 'timemodified',
        ]);

        // Student submissions (optional, requires userinfo setting).
        $submissions = new backup_nested_element('submissions');
        $submission  = new backup_nested_element('submission', ['id'], [
            'userid', 'groupid', 'status', 'attempt',
            'gdpr_consent', 'gdpr_consent_time',
            'roleplay_transcript', 'roleplay_analysis',
            'final_grade', 'final_feedback', 'grade_breakdown',
            'grader_userid', 'workflow_state',
            'timecreated', 'timemodified', 'timesubmitted', 'timegraded',
        ]);

        $messages = new backup_nested_element('roleplay_messages');
        $message  = new backup_nested_element('roleplay_message', ['id'], [
            'turn_number', 'speaker', 'message_text', 'timestamp',
        ]);

        // Build the tree.
        $airoleplay->add_child($overrides);
        $overrides->add_child($override);
        $airoleplay->add_child($submissions);
        $submissions->add_child($submission);
        $submission->add_child($messages);
        $messages->add_child($message);

        // Data sources.
        $airoleplay->set_source_table('airoleplay', ['id' => backup::VAR_ACTIVITYID]);
        $override->set_source_table('airoleplay_overrides', ['airoleplay' => backup::VAR_PARENTID]);

        if ($includesubmissions) {
            $submission->set_source_table('airoleplay_submissions', ['airoleplay' => backup::VAR_PARENTID]);
            $message->set_source_table('airoleplay_messages', ['submission_id' => backup::VAR_PARENTID]);
            $submission->annotate_ids('user', 'userid');
            $submission->annotate_ids('user', 'grader_userid');
        }

        // Annotate ids that need to be remapped on restore.
        $airoleplay->annotate_ids('grouping', 'groupingid');
        $override->annotate_ids('user', 'userid');
        $override->annotate_ids('group', 'groupid');

        // File annotations for the activity.
        $airoleplay->annotate_files('mod_airoleplay', 'intro', null);
        $airoleplay->annotate_files('mod_airoleplay', 'avatar_custom', null);

        return $this->prepare_activity_structure($airoleplay);
    }
}
