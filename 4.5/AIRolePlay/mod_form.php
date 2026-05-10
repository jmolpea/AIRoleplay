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
 * Activity module configuration form for mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Activity creation/editing form.
 */
class mod_airoleplay_mod_form extends moodleform_mod {
    /**
     * Defines the form fields.
     */
    public function definition(): void {
        global $CFG;

        $mform  = $this->_form;
        $config = get_config('mod_airoleplay');

        // Section: General.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('activityname', 'mod_airoleplay'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $attemptoptions = [
            1 => '1',
            2 => '2',
            3 => '3',
            0 => get_string('unlimited', 'mod_airoleplay'),
        ];
        $mform->addElement('select', 'max_attempts', get_string('maxattempts', 'mod_airoleplay'), $attemptoptions);
        $mform->setDefault('max_attempts', 2);
        $mform->addHelpButton('max_attempts', 'maxattempts', 'mod_airoleplay');

        $mform->addElement('advcheckbox', 'group_submission', get_string('groupsubmission', 'mod_airoleplay'));
        $mform->addHelpButton('group_submission', 'groupsubmission', 'mod_airoleplay');

        $groupings = groups_get_all_groupings($this->current->course ?? 0);
        $groupingoptions = [0 => get_string('none')];
        foreach ($groupings as $grouping) {
            $groupingoptions[$grouping->id] = format_string($grouping->name);
        }
        $mform->addElement('select', 'groupingid', get_string('grouping', 'group'), $groupingoptions);
        $mform->hideIf('groupingid', 'group_submission', 'notchecked');

        // Section: Scenario.
        $mform->addElement('header', 'scenario_header', get_string('scenario_header', 'mod_airoleplay'));

        $mform->addElement(
            'editor',
            'scenario_description_editor',
            get_string('scenario_description', 'mod_airoleplay'),
            null,
            $this->get_editor_options()
        );
        $mform->setType('scenario_description_editor', PARAM_RAW);
        $mform->addHelpButton('scenario_description_editor', 'scenario_description', 'mod_airoleplay');

        $mform->addElement(
            'textarea',
            'participant_role',
            get_string('participant_role', 'mod_airoleplay'),
            ['rows' => 4, 'cols' => 80]
        );
        $mform->setType('participant_role', PARAM_RAW);
        $mform->addHelpButton('participant_role', 'participant_role', 'mod_airoleplay');

        $durationoptions = [5 => '5 min', 10 => '10 min', 15 => '15 min', 20 => '20 min', 30 => '30 min'];
        $mform->addElement('select', 'session_duration', get_string('session_duration', 'mod_airoleplay'), $durationoptions);
        $mform->setDefault('session_duration', 10);
        $mform->addHelpButton('session_duration', 'session_duration', 'mod_airoleplay');

        $mform->addElement(
            'textarea',
            'roleplay_prompt_eval',
            get_string('roleplay_prompt_eval', 'mod_airoleplay'),
            ['rows' => 5, 'cols' => 80]
        );
        $mform->setType('roleplay_prompt_eval', PARAM_RAW);
        $mform->addHelpButton('roleplay_prompt_eval', 'roleplay_prompt_eval', 'mod_airoleplay');

        // Section: Avatars.
        $mform->addElement('header', 'avatars_header', get_string('avatars_header', 'mod_airoleplay'));

        $numavatarsoptions = [1 => '1', 2 => '2', 3 => '3'];
        $mform->addElement('select', 'num_avatars', get_string('num_avatars', 'mod_airoleplay'), $numavatarsoptions);
        $mform->setDefault('num_avatars', 1);
        $mform->addHelpButton('num_avatars', 'num_avatars', 'mod_airoleplay');

        // Avatar fields (1 always shown; 2 and 3 conditionally).
        for ($i = 1; $i <= 3; $i++) {
            $this->add_avatar_fields($mform, $i);
        }

        // Section: AI Models.
        $mform->addElement('header', 'models_header', get_string('models_header', 'mod_airoleplay'));

        $mform->addElement(
            'select',
            'openai_model_roleplay',
            get_string('openai_model_roleplay', 'mod_airoleplay'),
            $this->get_model_options()
        );
        $mform->setDefault('openai_model_roleplay', 'gpt-4o');

        $mform->addElement(
            'select',
            'openai_model_eval',
            get_string('openai_model_eval', 'mod_airoleplay'),
            $this->get_model_options()
        );
        $mform->setDefault('openai_model_eval', 'gpt-4o');

        // Section: Grading and Workflow.
        $mform->addElement('header', 'grading_header', get_string('grading_header', 'mod_airoleplay'));

        $mform->addElement('text', 'grade', get_string('maximumgrade', 'mod_airoleplay'));
        $mform->setType('grade', PARAM_INT);
        $mform->setDefault('grade', 100);

        $mform->addElement('advcheckbox', 'grading_workflow', get_string('grading_workflow', 'mod_airoleplay'));
        $mform->addHelpButton('grading_workflow', 'grading_workflow', 'mod_airoleplay');

        $mform->addElement('advcheckbox', 'notify_student', get_string('notify_student', 'mod_airoleplay'));
        $mform->setDefault('notify_student', 1);

        // Section: Security.
        $mform->addElement('header', 'security_header', get_string('security_header', 'mod_airoleplay'));

        $mform->addElement(
            'textarea',
            'safety_extra_prompt',
            get_string('safety_extra_prompt', 'mod_airoleplay'),
            ['rows' => 4, 'cols' => 80]
        );
        $mform->setType('safety_extra_prompt', PARAM_RAW);
        $mform->addHelpButton('safety_extra_prompt', 'safety_extra_prompt', 'mod_airoleplay');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Adds fields for a single avatar.
     *
     * @param MoodleQuickForm $mform  The form.
     * @param int             $index  Avatar index (1, 2 or 3).
     */
    private function add_avatar_fields(MoodleQuickForm $mform, int $index): void {
        $mform->addElement(
            'header',
            "avatar_{$index}_header",
            get_string('avatar_header', 'mod_airoleplay', $index)
        );
        $mform->setExpanded("avatar_{$index}_header", $index === 1);

        // Hide avatar 2 and 3 headers/fields when num_avatars is less.
        if ($index > 1) {
            $mform->hideIf("avatar_{$index}_header", 'num_avatars', 'lt', $index);
        }

        $mform->addElement('text', "avatar_{$index}_name", get_string('avatar_name', 'mod_airoleplay'));
        $mform->setType("avatar_{$index}_name", PARAM_TEXT);
        $defaultnames = [1 => 'Alex', 2 => 'Jordan', 3 => 'Morgan'];
        $mform->setDefault("avatar_{$index}_name", $defaultnames[$index]);
        if ($index > 1) {
            $mform->hideIf("avatar_{$index}_name", 'num_avatars', 'lt', $index);
        }

        $mform->addElement('text', "avatar_{$index}_role", get_string('avatar_role', 'mod_airoleplay'));
        $mform->setType("avatar_{$index}_role", PARAM_TEXT);
        $mform->setDefault("avatar_{$index}_role", 'Interlocutor');
        if ($index > 1) {
            $mform->hideIf("avatar_{$index}_role", 'num_avatars', 'lt', $index);
        }

        $mform->addElement(
            'textarea',
            "avatar_{$index}_prompt",
            get_string('avatar_prompt', 'mod_airoleplay'),
            ['rows' => 5, 'cols' => 80]
        );
        $mform->setType("avatar_{$index}_prompt", PARAM_RAW);
        $mform->addHelpButton("avatar_{$index}_prompt", 'avatar_prompt', 'mod_airoleplay');
        if ($index > 1) {
            $mform->hideIf("avatar_{$index}_prompt", 'num_avatars', 'lt', $index);
        }

        $mform->addElement(
            'select',
            "avatar_{$index}_voice",
            get_string('avatar_voice', 'mod_airoleplay'),
            $this->get_voice_options()
        );
        $defaultvoices = [1 => 'onyx', 2 => 'nova', 3 => 'echo'];
        $mform->setDefault("avatar_{$index}_voice", $defaultvoices[$index]);
        if ($index > 1) {
            $mform->hideIf("avatar_{$index}_voice", 'num_avatars', 'lt', $index);
        }

        // Avatar visual selection.
        $avatargroup = [];
        $avatargroup[] = $mform->createElement('radio', "avatar_{$index}_avatar", '', get_string('avatar_1', 'mod_airoleplay'), 1);
        $avatargroup[] = $mform->createElement('radio', "avatar_{$index}_avatar", '', get_string('avatar_2', 'mod_airoleplay'), 2);
        $avatargroup[] = $mform->createElement('radio', "avatar_{$index}_avatar", '', get_string('avatar_3', 'mod_airoleplay'), 3);
        $customlabel   = get_string('avatar_custom', 'mod_airoleplay');
        $avatargroup[] = $mform->createElement('radio', "avatar_{$index}_avatar", '', $customlabel, 0);
        $mform->addGroup(
            $avatargroup,
            "avatar_{$index}_avatar_group",
            get_string('avatar_visual', 'mod_airoleplay'),
            '<br/>',
            false
        );
        $defaultavatars = [1 => 1, 2 => 2, 3 => 3];
        $mform->setDefault("avatar_{$index}_avatar", $defaultavatars[$index]);
        if ($index > 1) {
            $mform->hideIf("avatar_{$index}_avatar_group", 'num_avatars', 'lt', $index);
        }

        $mform->addElement(
            'filemanager',
            "avatar_{$index}_avatar_custom",
            get_string('avatar_custom_upload', 'mod_airoleplay'),
            null,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']]
        );
        $mform->hideIf("avatar_{$index}_avatar_custom", "avatar_{$index}_avatar", 'neq', 0);
        if ($index > 1) {
            $mform->hideIf("avatar_{$index}_avatar_custom", 'num_avatars', 'lt', $index);
        }
    }

    /**
     * Returns available AI model options based on global config.
     *
     * @return array Associative array of model_id => label.
     */
    private function get_model_options(): array {
        $config  = get_config('mod_airoleplay');
        $options = [];

        if (!empty($config->enable_gpt4o)) {
            $options['gpt-4o'] = 'GPT-4o ' . get_string('model_recommended', 'mod_airoleplay');
        }
        if (!empty($config->enable_gpt4o_mini)) {
            $options['gpt-4o-mini'] = 'GPT-4o mini ' . get_string('model_economical', 'mod_airoleplay');
        }

        if (empty($options)) {
            $options['gpt-4o']      = 'GPT-4o';
            $options['gpt-4o-mini'] = 'GPT-4o mini';
        }

        return $options;
    }

    /**
     * Returns available TTS voice options.
     *
     * @return array Associative array of voice_id => label.
     */
    private function get_voice_options(): array {
        return [
            'alloy'   => get_string('voice_alloy', 'mod_airoleplay'),
            'echo'    => get_string('voice_echo', 'mod_airoleplay'),
            'fable'   => get_string('voice_fable', 'mod_airoleplay'),
            'onyx'    => get_string('voice_onyx', 'mod_airoleplay'),
            'nova'    => get_string('voice_nova', 'mod_airoleplay'),
            'shimmer' => get_string('voice_shimmer', 'mod_airoleplay'),
        ];
    }

    /**
     * Returns editor options.
     *
     * @return array Editor options array.
     */
    private function get_editor_options(): array {
        return [
            'subdirs'  => 0,
            'maxfiles' => 0,
            'context'  => $this->context ?? null,
        ];
    }

    /**
     * Prepares data for the form.
     *
     * @param array $defaultvalues Array of default values (modified in-place).
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        if (!empty($defaultvalues['openai_apikey'])) {
            try {
                $defaultvalues['openai_apikey'] = \core\encryption::decrypt($defaultvalues['openai_apikey']);
            } catch (\moodle_exception $e) {
                $defaultvalues['openai_apikey'] = '';
            }
        }

        if (isset($defaultvalues['scenario_description'])) {
            $defaultvalues['scenario_description_editor'] = [
                'text'   => $defaultvalues['scenario_description'] ?? '',
                'format' => $defaultvalues['scenario_descriptionformat'] ?? FORMAT_HTML,
            ];
        }
    }

    /**
     * Performs server-side validation.
     *
     * @param array $data  Form data.
     * @param array $files Uploaded files.
     * @return array Errors array (field => message).
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['session_duration']) && (int)$data['session_duration'] < 1) {
            $errors['session_duration'] = get_string('error_duration_invalid', 'mod_airoleplay');
        }

        return $errors;
    }
}
