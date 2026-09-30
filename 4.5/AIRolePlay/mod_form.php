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
require_once($CFG->dirroot . '/mod/airoleplay/lib.php');

use mod_airoleplay\form\mod_form_helper;

/**
 * Activity creation/editing form.
 */
class mod_airoleplay_mod_form extends moodleform_mod {
    /**
     * Defines the form fields.
     */
    public function definition(): void {
        global $CFG;

        $mform = $this->_form;

        // Section: General.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('activityname', 'mod_airoleplay'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $attemptoptions = [1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 10 => '10'];
        $attemptoptions[0] = get_string('unlimited', 'mod_airoleplay');
        $mform->addElement('select', 'max_attempts', get_string('maxattempts', 'mod_airoleplay'), $attemptoptions);
        $mform->setDefault('max_attempts', 2);
        $mform->addHelpButton('max_attempts', 'maxattempts', 'mod_airoleplay');

        // The grouping itself is chosen in the standard "Common module settings".
        $mform->addElement('advcheckbox', 'group_submission', get_string('groupsubmission', 'mod_airoleplay'));
        $mform->addHelpButton('group_submission', 'groupsubmission', 'mod_airoleplay');

        // Section: Availability.
        $mform->addElement('header', 'availability_header', get_string('availability_header', 'mod_airoleplay'));
        $mform->addElement('date_time_selector', 'timeopen', get_string('timeopen', 'mod_airoleplay'), ['optional' => true]);
        $mform->addHelpButton('timeopen', 'timeopen', 'mod_airoleplay');
        $mform->addElement('date_time_selector', 'timeclose', get_string('timeclose', 'mod_airoleplay'), ['optional' => true]);
        $mform->addHelpButton('timeclose', 'timeclose', 'mod_airoleplay');

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

        $durationoptions = [];
        foreach ([5, 10, 15, 20, 30] as $minutes) {
            $durationoptions[$minutes] = get_string('numminutes', 'moodle', $minutes);
        }
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

        // Keep the models the activity already uses selectable, so editing an
        // unrelated setting can never silently switch the model.
        $current = [];
        if (!empty($this->current->openai_model_roleplay)) {
            $current[] = $this->current->openai_model_roleplay;
        }
        if (!empty($this->current->openai_model_eval)) {
            $current[] = $this->current->openai_model_eval;
        }
        $modeloptions = mod_form_helper::get_model_options($current);

        $mform->addElement(
            'select',
            'openai_model_roleplay',
            get_string('openai_model_roleplay', 'mod_airoleplay'),
            $modeloptions
        );
        $mform->setDefault('openai_model_roleplay', $this->default_model($modeloptions, mod_form_helper::PURPOSE_ROLEPLAY));
        $mform->addHelpButton('openai_model_roleplay', 'openai_model_roleplay', 'mod_airoleplay');

        $mform->addElement(
            'select',
            'openai_model_eval',
            get_string('openai_model_eval', 'mod_airoleplay'),
            $modeloptions
        );
        $mform->setDefault('openai_model_eval', $this->default_model($modeloptions, mod_form_helper::PURPOSE_EVALUATION));
        $mform->addHelpButton('openai_model_eval', 'openai_model_eval', 'mod_airoleplay');

        // Section: Grading and Workflow.
        $mform->addElement('header', 'grading_header', get_string('grading_header', 'mod_airoleplay'));

        $mform->addElement('text', 'grade', get_string('maximumgrade', 'mod_airoleplay'), ['size' => 6]);
        $mform->setType('grade', PARAM_INT);
        $mform->setDefault('grade', 100);
        $mform->addHelpButton('grade', 'maximumgrade', 'mod_airoleplay');

        $mform->addElement('advcheckbox', 'grading_workflow', get_string('grading_workflow', 'mod_airoleplay'));
        $mform->addHelpButton('grading_workflow', 'grading_workflow', 'mod_airoleplay');
        $mform->setDefault('grading_workflow', airoleplay_grading_workflow_default());
        if (airoleplay_grading_workflow_locked()) {
            // The administrator imposes this choice on every activity.
            $mform->setConstant('grading_workflow', airoleplay_grading_workflow_default());
            $mform->freeze('grading_workflow');
            $mform->addElement(
                'static',
                'grading_workflow_locked',
                '',
                get_string('grading_workflow_locked', 'mod_airoleplay')
            );
        }

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
     * Recommended model for a purpose when it is offered, else the first option.
     *
     * @param array  $options Model options.
     * @param string $purpose mod_form_helper::PURPOSE_* constant.
     * @return string
     */
    private function default_model(array $options, string $purpose): string {
        $preferred = mod_form_helper::default_model($purpose);
        return array_key_exists($preferred, $options) ? $preferred : (string)array_key_first($options);
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

        // Avatars 2 and 3 only apply when the activity uses that many.
        $fields = [];

        $mform->addElement('text', "avatar_{$index}_name", get_string('avatar_name', 'mod_airoleplay'));
        $mform->setType("avatar_{$index}_name", PARAM_TEXT);
        $defaultnames = [1 => 'Alex', 2 => 'Jordan', 3 => 'Morgan'];
        $mform->setDefault("avatar_{$index}_name", $defaultnames[$index]);
        $fields[] = "avatar_{$index}_name";

        $mform->addElement('text', "avatar_{$index}_role", get_string('avatar_role', 'mod_airoleplay'));
        $mform->setType("avatar_{$index}_role", PARAM_TEXT);
        $mform->setDefault("avatar_{$index}_role", get_string('avatar_role_default', 'mod_airoleplay'));
        $fields[] = "avatar_{$index}_role";

        $mform->addElement(
            'textarea',
            "avatar_{$index}_prompt",
            get_string('avatar_prompt', 'mod_airoleplay'),
            ['rows' => 5, 'cols' => 80]
        );
        $mform->setType("avatar_{$index}_prompt", PARAM_RAW);
        $mform->addHelpButton("avatar_{$index}_prompt", 'avatar_prompt', 'mod_airoleplay');
        $fields[] = "avatar_{$index}_prompt";

        $voices = mod_form_helper::get_voice_options();
        $currentvoice = (string)($this->current->{"avatar_{$index}_voice"} ?? '');
        if ($currentvoice !== '' && !isset($voices[$currentvoice])) {
            // Voice from a TTS provider the site no longer uses: keep it visible.
            $voices[$currentvoice] = $currentvoice;
        }
        $mform->addElement('select', "avatar_{$index}_voice", get_string('avatar_voice', 'mod_airoleplay'), $voices);
        $mform->setDefault("avatar_{$index}_voice", mod_form_helper::get_default_voices()[$index]);
        $fields[] = "avatar_{$index}_voice";

        // Avatar visual selection.
        $avatargroup = [];
        for ($visual = 1; $visual <= 3; $visual++) {
            $avatargroup[] = $mform->createElement(
                'radio',
                "avatar_{$index}_avatar",
                '',
                get_string('avatar_' . $visual, 'mod_airoleplay'),
                $visual
            );
        }
        $avatargroup[] = $mform->createElement(
            'radio',
            "avatar_{$index}_avatar",
            '',
            get_string('avatar_custom', 'mod_airoleplay'),
            0
        );
        $mform->addGroup($avatargroup, "avatar_{$index}_avatar_group", get_string('avatar_visual', 'mod_airoleplay'), ' ', false);
        $mform->setDefault("avatar_{$index}_avatar", $index);
        $fields[] = "avatar_{$index}_avatar_group";

        $mform->addElement(
            'filemanager',
            "avatar_{$index}_avatar_custom",
            get_string('avatar_custom_upload', 'mod_airoleplay'),
            null,
            airoleplay_avatar_file_options()
        );
        $mform->addHelpButton("avatar_{$index}_avatar_custom", 'avatar_custom_upload', 'mod_airoleplay');
        $mform->hideIf("avatar_{$index}_avatar_custom", "avatar_{$index}_avatar", 'neq', 0);
        $fields[] = "avatar_{$index}_avatar_custom";

        if ($index > 1) {
            $mform->hideIf("avatar_{$index}_header", 'num_avatars', 'lt', $index);
            foreach ($fields as $field) {
                $mform->hideIf($field, 'num_avatars', 'lt', $index);
            }
        }
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

        if (isset($defaultvalues['scenario_description'])) {
            $defaultvalues['scenario_description_editor'] = [
                'text'   => $defaultvalues['scenario_description'] ?? '',
                'format' => $defaultvalues['scenario_descriptionformat'] ?? FORMAT_HTML,
            ];
        }

        // Load the uploaded custom avatars into draft areas for the file managers.
        for ($i = 1; $i <= 3; $i++) {
            $draftitemid = file_get_submitted_draft_itemid("avatar_{$i}_avatar_custom");
            file_prepare_draft_area(
                $draftitemid,
                $this->context ? $this->context->id : null,
                'mod_airoleplay',
                'avatar_custom',
                $i,
                airoleplay_avatar_file_options()
            );
            $defaultvalues["avatar_{$i}_avatar_custom"] = $draftitemid;
        }
    }

    /**
     * Adds the custom completion rule.
     *
     * @return array Names of the elements that make up the rule.
     */
    public function add_completion_rules(): array {
        $mform  = $this->_form;
        $name   = 'completionsubmit' . $this->get_suffix();
        $mform->addElement('advcheckbox', $name, '', get_string('completionsubmit', 'mod_airoleplay'));
        $mform->addHelpButton($name, 'completionsubmit', 'mod_airoleplay');
        return [$name];
    }

    /**
     * Whether the custom completion rule is enabled.
     *
     * @param array $data Submitted data.
     * @return bool
     */
    public function completion_rule_enabled($data): bool {
        return !empty($data['completionsubmit' . $this->get_suffix()]);
    }

    /**
     * Clears the completion rule when automatic completion is off.
     *
     * @param stdClass $data Submitted data.
     */
    public function data_postprocessing($data): void {
        parent::data_postprocessing($data);
        if (!empty($data->completionunlocked)) {
            $suffix     = $this->get_suffix();
            $completion = $data->{'completion' . $suffix} ?? COMPLETION_TRACKING_NONE;
            if ($completion != COMPLETION_TRACKING_AUTOMATIC) {
                $data->{'completionsubmit' . $suffix} = 0;
            }
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
        if (!isset($data['grade']) || (int)$data['grade'] < 1 || (int)$data['grade'] > 10000) {
            $errors['grade'] = get_string('error_grade_invalid', 'mod_airoleplay');
        }
        if (!empty($data['timeopen']) && !empty($data['timeclose']) && $data['timeclose'] <= $data['timeopen']) {
            $errors['timeclose'] = get_string('error_close_before_open', 'mod_airoleplay');
        }
        $numavatars = max(1, min(3, (int)($data['num_avatars'] ?? 1)));
        for ($i = 1; $i <= $numavatars; $i++) {
            if (trim((string)($data["avatar_{$i}_name"] ?? '')) === '') {
                $errors["avatar_{$i}_name"] = get_string('required');
            }
        }
        return $errors;
    }
}
