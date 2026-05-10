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
 * English language strings for mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activityname']       = 'Activity name';

$string['airoleplay:addinstance']         = 'Add an AI Roleplay activity';
$string['airoleplay:grade']               = 'Grade submissions';
$string['airoleplay:manageoverrides']     = 'Manage user and group overrides';
$string['airoleplay:manageplugin']        = 'Manage plugin settings';
$string['airoleplay:submit']              = 'Participate in a roleplay session';
$string['airoleplay:view']                = 'View AI Roleplay activity';
$string['airoleplay:viewallsubmissions']  = 'View all submissions';

$string['attemptsinfo']   = 'Attempts used: {$a->used} / {$a->max} ({$a->remaining} remaining)';

$string['avatar_1']           = 'Avatar 1 (neutral)';
$string['avatar_2']           = 'Avatar 2 (feminine)';
$string['avatar_3']           = 'Avatar 3 (masculine)';
$string['avatar_custom']      = 'Custom image';
$string['avatar_custom_upload'] = 'Upload custom avatar image';

$string['avatar_header']      = 'Avatar {$a}';
$string['avatar_name']        = 'Name';
$string['avatar_prompt']      = 'Personality & interaction style';
$string['avatar_prompt_help'] = 'Describe how this avatar behaves, their personality, tone, and role within the scenario.';
$string['avatar_role']        = 'Role / Title';
$string['avatar_visual']      = 'Avatar appearance';
$string['avatar_voice']       = 'TTS Voice';
$string['avatars_header']     = 'Avatars';

$string['badrequest'] = 'The request could not be processed.';

$string['col_actions']        = 'Actions';
$string['col_grade']          = 'Grade';
$string['col_status']         = 'Status';
$string['col_student']        = 'Student';
$string['col_submitted']      = 'Submitted';
$string['col_workflow']       = 'Workflow';

$string['completiongrade']    = 'Student must receive a grade';
$string['completionsubmit']   = 'Student must complete the roleplay session';

$string['confirm_delete_submission'] = 'Are you sure you want to delete this submission? This cannot be undone.';

$string['content_flagged']    = 'Content was flagged by the AI safety filter.';

$string['conversation_log']   = 'Session Log';

$string['delete_submission']  = 'Delete submission';

$string['dimension_communication']    = 'Communication';
$string['dimension_language_quality'] = 'Language quality';

$string['dimension_role_adherence']   = 'Role adherence';
$string['dimension_scenario_handling'] = 'Scenario handling';
$string['error_duration_invalid']  = 'Duration must be at least 1 minute.';

$string['evaluation_complete']  = '✅ Evaluation complete. Redirecting…';
$string['evaluation_pending']   = 'The AI is evaluating your performance. This may take a moment…';

$string['evaluator_invalid_response'] = 'The AI evaluator returned an invalid response. Please contact your instructor.';

$string['event_assessment_completed'] = 'AI assessment completed';
$string['event_grade_issued']         = 'Grade issued';
$string['event_submission_created']   = 'Roleplay session started';

$string['feedback']           = 'Feedback';

$string['gdpr_consent_label']   = 'I understand and agree that my spoken responses will be processed by OpenAI\'s API.';
$string['gdpr_consent_required'] = 'You must provide consent on the activity page before starting the session.';
$string['gdpr_default_notice']  = '<p>To complete this activity, your spoken responses during the roleplay session will be sent to <strong>OpenAI\'s API</strong> for AI-driven conversation and evaluation.</p><p>Data is not retained by OpenAI beyond the immediate request.</p><p>By proceeding, you consent to this processing in accordance with our privacy policy.</p>';
$string['gdpr_notice_title']    = 'Privacy Notice — AI Processing';
$string['gdpr_revoke_button']   = 'Withdraw consent and delete my data';
$string['gdpr_revoke_confirm']  = 'This will delete every roleplay submission, transcript and grade you have on this activity. This cannot be undone. Continue?';
$string['gdpr_revoked_notice']  = 'Your consent has been withdrawn and your roleplay data has been deleted from this activity.';

$string['grade_breakdown']       = 'Grade breakdown';
$string['grade_override_saved']  = 'Grade saved successfully.';
$string['grade_pending_review']  = 'Your grade is being reviewed by your instructor. You will be notified when it is released.';

$string['gradenotification_body']     = <<<'EOT'
Your grade for '{$a->activityname}' in '{$a->coursename}' has been released.

Grade: {$a->grade}

View your results: {$a->link}
EOT;
$string['gradenotification_bodyhtml'] = '<p>Your grade for <strong>{$a->activityname}</strong> in <em>{$a->coursename}</em> has been released.</p><p>Grade: <strong>{$a->grade}</strong></p><p><a href="{$a->link}">View your results</a></p>';
$string['gradenotification_small']    = 'Grade released for {$a->activityname}';
$string['gradenotification_subject']  = 'Your grade is ready: {$a->activityname}';

$string['grading_header']     = 'Grading & Workflow';
$string['grading_workflow']   = 'Enable grading workflow';
$string['grading_workflow_help'] = 'If enabled, grades are held for teacher review before being released to students.';

$string['groupsubmission']     = 'Group submission';
$string['groupsubmission_help'] = 'Allow groups to submit together. Requires groups to be configured in the course.';

$string['invalid_state_transition'] = 'Invalid submission state transition.';
$string['invalidsubmissionstatus'] = 'This action is not allowed in the current submission state.';
$string['maxattempts']         = 'Maximum attempts';
$string['maxattempts_help']    = 'Maximum number of times a student may attempt this activity. Set to 0 for unlimited.';
$string['maximumgrade']        = 'Maximum grade';

$string['model_economical']    = '(economical)';
$string['model_recommended']   = '(recommended)';
$string['models_header']       = 'AI Models';

$string['modulename']          = 'AI Roleplay';
$string['modulenameplural']    = 'AI Roleplays';

$string['no_overrides_yet']    = 'No overrides have been configured.';
$string['no_submissions_yet']  = 'No submissions yet.';
$string['noinstances']         = 'No AI Roleplay activities in this course.';
$string['notify_student']      = 'Notify student when grade is published';

$string['num_avatars']         = 'Number of avatars';
$string['num_avatars_help']    = 'Choose how many avatars participate in the roleplay (1, 2, or 3). When multiple avatars are active, they take turns responding, and the participant can address a specific avatar by name.';

$string['openai_api_error']    = 'AI service error: {$a}';
$string['openai_apikey_missing']      = 'The AI Roleplay plugin has no usable OpenAI API key configured. Ask the site administrator to set it under Site administration > Plugins > Activity modules > AI Roleplay.';
$string['openai_model_eval']   = 'AI model for final evaluation';
$string['openai_model_roleplay'] = 'AI model for roleplay conversation';

$string['override_add']            = 'Add override';
$string['override_confirm_delete'] = 'Are you sure you want to delete this override?';
$string['override_delete']         = 'Delete override';
$string['override_deleted']        = 'Override deleted.';
$string['override_edit']           = 'Edit override';
$string['override_group']          = 'Group';
$string['override_maxattempts']    = 'Maximum attempts';
$string['override_saved']          = 'Override saved.';
$string['override_timeclose']      = 'Close';
$string['override_timeopen']       = 'Open';
$string['override_type']           = 'Override type';
$string['override_type_group']     = 'Group override';
$string['override_type_user']      = 'User override';
$string['override_user']           = 'User';
$string['overrides_heading']       = 'User/Group Overrides';

$string['participant_role']        = 'Participant\'s role';
$string['participant_role_help']   = 'Describe the character or role the participant plays in this scenario. This is shown to the participant before the session starts.';

$string['pluginadministration']    = 'AI Roleplay administration';
$string['pluginname']              = 'AI Roleplay';

$string['privacy:metadata:airoleplay_messages']                          = 'Detailed log of each turn in the roleplay session.';
$string['privacy:metadata:airoleplay_messages:message_text']             = 'The text of what was said.';
$string['privacy:metadata:airoleplay_messages:speaker']                  = 'Who spoke in this turn (avatar or participant).';
$string['privacy:metadata:airoleplay_messages:timestamp']                = 'When this turn occurred.';
$string['privacy:metadata:airoleplay_overrides']                         = 'Per-user (or per-group) overrides that change the activity\'s availability or attempt limits for specific participants.';
$string['privacy:metadata:airoleplay_overrides:groupid']                 = 'The group the override applies to (null for user overrides).';
$string['privacy:metadata:airoleplay_overrides:max_attempts']            = 'Override for the maximum number of attempts.';
$string['privacy:metadata:airoleplay_overrides:timeclose']               = 'Override for the activity close time.';
$string['privacy:metadata:airoleplay_overrides:timecreated']             = 'When the override was created.';
$string['privacy:metadata:airoleplay_overrides:timemodified']            = 'When the override was last modified.';
$string['privacy:metadata:airoleplay_overrides:timeopen']                = 'Override for the activity open time.';
$string['privacy:metadata:airoleplay_overrides:userid']                  = 'The user the override applies to (null for group overrides).';
$string['privacy:metadata:airoleplay_submissions']                       = 'Information about each student\'s roleplay session, including conversation transcript, grades and review workflow state.';
$string['privacy:metadata:airoleplay_submissions:attempt']               = 'The attempt number for this submission.';
$string['privacy:metadata:airoleplay_submissions:final_feedback']        = 'The final feedback text provided to the student.';
$string['privacy:metadata:airoleplay_submissions:final_grade']           = 'The final grade awarded to the student.';
$string['privacy:metadata:airoleplay_submissions:gdpr_consent']          = 'Whether the student gave GDPR consent.';
$string['privacy:metadata:airoleplay_submissions:gdpr_consent_time']     = 'When the student gave GDPR consent.';
$string['privacy:metadata:airoleplay_submissions:grade_breakdown']       = 'JSON breakdown of how the rubric components contribute to the final grade.';
$string['privacy:metadata:airoleplay_submissions:grader_userid']         = 'The teacher who last graded or published this submission.';
$string['privacy:metadata:airoleplay_submissions:groupid']               = 'The group the student submitted on behalf of (only for group submissions).';
$string['privacy:metadata:airoleplay_submissions:roleplay_analysis']     = 'The full evaluation JSON returned by the AI evaluator (rubric scores, integrity flags, model output).';
$string['privacy:metadata:airoleplay_submissions:roleplay_transcript']   = 'Full transcript of the roleplay session.';
$string['privacy:metadata:airoleplay_submissions:status']                = 'Current status of the submission.';
$string['privacy:metadata:airoleplay_submissions:timecreated']           = 'When the submission was created.';
$string['privacy:metadata:airoleplay_submissions:timegraded']            = 'When the submission was last graded.';
$string['privacy:metadata:airoleplay_submissions:timemodified']          = 'When the submission was last modified.';
$string['privacy:metadata:airoleplay_submissions:timesubmitted']         = 'When the session was completed.';
$string['privacy:metadata:airoleplay_submissions:userid']                = 'The ID of the student who participated.';
$string['privacy:metadata:airoleplay_submissions:workflow_state']        = 'Where the submission sits in the review workflow (inreview, readyforrelease, released).';
$string['privacy:metadata:openai']                                       = 'Conversation and evaluation data are sent to OpenAI for inference. Last name, username and email are replaced with a STUDENT-<hash> placeholder before transmission. The participant\'s first name is sent so the AI avatars can address the human naturally. The redacted free text the student authored is still transmitted. According to OpenAI\'s API terms, content is not used to train models.';
$string['privacy:metadata:openai:evaluation_request']                    = 'The teacher-authored evaluation rubric and the request to score the submission, sent to the AI evaluator.';

$string['privacy:metadata:openai:firstname']                             = 'The participant\'s first name is included in the avatar\'s system prompt so the AI greets and addresses them by their real name.';
$string['privacy:metadata:openai:participant_role']                      = 'The teacher-defined role assigned to the participant, sent as context to the AI.';
$string['privacy:metadata:openai:participant_turn']                      = 'The redacted text of each participant turn, sent to the AI in real time during the roleplay.';
$string['privacy:metadata:openai:roleplay_transcript']                   = 'The redacted JSON transcript of the entire roleplay session, sent to the AI evaluator.';
$string['privacy:metadata:openai:scenario']                              = 'The teacher-authored scenario description, sent as context to the AI.';
$string['publish_grade']       = 'Publish grade';
$string['push_to_talk']        = 'Hold to respond';

$string['rate_limit_exceeded']        = 'You have made too many requests. Please wait a moment before trying again.';
$string['rate_limit_exceeded_global'] = 'The site-wide AI Roleplay request limit has been reached. Please try again shortly.';

$string['regen_confirm']       = 'This will replace the current evaluation with a new one. Continue?';
$string['regen_cooldown']      = 'Please wait before regenerating again. This operation has a 5-minute cooldown to prevent excessive API usage.';
$string['regen_daily_cap']     = 'You have reached the daily limit for regenerations on this submission. Try again tomorrow.';
$string['regen_evaluation']    = 'Recalculate final evaluation';
$string['regen_heading']       = 'Regenerate AI Evaluation';
$string['regen_running']       = 'Processing… please wait (may take 1–3 minutes)';
$string['regen_success']       = 'Done! Reloading…';

$string['results_title']       = 'Your Results';
$string['return_to_student']   = 'Return for revision';

$string['roleplay_ending']     = 'The session is concluding…';
$string['roleplay_finished']   = 'Session ended. Your evaluation is being prepared…';
$string['roleplay_loading']    = 'Starting the roleplay…';
$string['roleplay_prompt_eval'] = 'Evaluation instructions';
$string['roleplay_prompt_eval_help'] = 'Instructions to the AI for generating the final grade and feedback. Leave blank to use the default evaluation criteria.';
$string['roleplay_ready_notice'] = 'You are about to start the roleplay session. Once you press the button, the timer will start and the avatars will begin interacting with you. You will not be able to pause the session.';
$string['roleplay_ready_title'] = 'Ready to Begin?';
$string['roleplay_start_btn']   = 'Start Roleplay';
$string['roleplay_thinking']    = 'The avatar is responding…';

$string['safety_extra_prompt']      = 'Additional content restrictions (optional)';
$string['safety_extra_prompt_help'] = 'Any extra safety instructions appended to every API call for this activity.';
$string['scenario_description']       = 'Scenario description';
$string['scenario_description_help']  = 'Describe the situation for the roleplay. This context is given to the avatars and shown to the participant before the session starts.';
$string['scenario_header']            = 'Scenario & Participant Role';
$string['scenario_label']             = 'Scenario';

$string['security_header']          = 'Activity Security';

$string['session_duration']           = 'Session duration (minutes)';
$string['session_duration_help']      = 'Maximum duration of the roleplay session. When the timer runs out, the session ends and evaluation begins.';

$string['settings_advanced_heading']         = 'Advanced';
$string['settings_anonymize_desc']           = 'Student real names are <strong>always</strong> replaced by an anonymous identifier before sending to OpenAI.';
$string['settings_anonymize_heading']        = 'Student Anonymisation';
$string['settings_anonymize_salt']           = 'Anonymisation salt';
$string['settings_anonymize_salt_desc']      = 'Random string mixed into the SHA-256 hash that replaces student identifiers before transcripts leave the LMS. Leave empty to auto-generate (32 random bytes are seeded on the next plugin upgrade). Manual values must be 32–128 characters of letters, digits, hyphen or underscore.';
$string['settings_api_rate_limit']           = 'Max API calls per user per minute';
$string['settings_api_rate_limit_desc']      = 'Rate limit per Moodle user to prevent API abuse.';
$string['settings_api_rate_limit_global']    = 'Max API calls per minute (whole site)';
$string['settings_api_rate_limit_global_desc'] = 'Site-wide cap that backs up the per-user limit. Counts every OpenAI call (chat, TTS, evaluation, cron tasks). Lower this if cost runaway is a concern.';
$string['settings_api_timeout']              = 'API request timeout (seconds)';
$string['settings_api_timeout_desc']         = 'Maximum time to wait for a response from OpenAI.';
$string['settings_apikeys_heading']          = 'OpenAI API Keys';
$string['settings_apikeys_heading_desc']     = 'These keys are stored encrypted.';
$string['settings_cost_estimate_desc']       = 'Estimated cost per complete 10-minute session:<br/>GPT-4o: ~$0.05–$0.15 USD &nbsp;|&nbsp; GPT-4o mini: ~$0.01–$0.03 USD<br/>TTS: ~$0.01 per avatar response';
$string['settings_cost_estimate_heading']    = 'Cost Estimates';
$string['settings_enable_gpt4o']             = 'Enable GPT-4o';
$string['settings_enable_gpt4o_desc']        = 'GPT-4o — highest quality, higher cost.';
$string['settings_enable_gpt4o_mini']        = 'Enable GPT-4o mini';
$string['settings_enable_gpt4o_mini_desc']   = 'GPT-4o mini — good quality, lower cost.';
$string['settings_gdpr_heading']             = 'GDPR Notice';
$string['settings_gdpr_heading_desc']        = 'This notice is shown to participants before they begin.';
$string['settings_gdpr_notice_text']         = 'GDPR notice text';
$string['settings_gdpr_notice_text_desc']    = 'HTML text shown to participants.';
$string['settings_models_heading']           = 'Available AI Models';
$string['settings_models_heading_desc']      = 'Select which models teachers can choose from.';
$string['settings_openai_apikey']            = 'Primary OpenAI API Key';
$string['settings_openai_apikey_desc']       = 'Your OpenAI API key. Used for GPT-4o and TTS.';
$string['settings_openai_apikey_secondary']  = 'Secondary OpenAI API Key (optional)';
$string['settings_openai_apikey_secondary_desc'] = 'If set, TTS calls will use this key.';
$string['settings_safety_content_filter']    = 'Enable OpenAI content moderation';
$string['settings_safety_content_filter_desc'] = 'Runs all user content through the OpenAI Moderation API before sending to GPT.';
$string['settings_safety_max_tokens']        = 'Maximum tokens per API call';
$string['settings_safety_max_tokens_desc']   = 'Hard limit on output tokens for all API calls.';
$string['settings_security_heading']         = 'Security & Safety';
$string['settings_security_heading_desc']    = 'Configure safety filters applied to all AI calls.';
$string['settings_storage_heading']          = 'Storage & Retention';
$string['settings_storage_heading_desc']     = 'Configure file storage limits.';

$string['start_activity']      = 'I\'m ready to begin';

$string['submission']          = 'Submission';
$string['submission_deleted']  = 'Submission deleted.';

$string['submissionnotification_body']     = <<<'EOT'
A student ({$a->studentname}) has completed '{$a->activityname}' in '{$a->coursename}' and their session is ready for your review.

View submissions: {$a->link}
EOT;
$string['submissionnotification_bodyhtml'] = '<p>Student <strong>{$a->studentname}</strong> has completed <em>{$a->activityname}</em> and their session is ready for review.</p><p><a href="{$a->link}">View submissions</a></p>';
$string['submissionnotification_small']    = 'New submission: {$a->activityname}';
$string['submissionnotification_subject']  = 'New submission for review: {$a->activityname}';
$string['submissions_heading']             = 'Submissions';

$string['task_evaluate_submission'] = 'AI Roleplay: Generate final evaluation';

$string['unlimited']           = 'Unlimited';

$string['voice_alloy']   = 'Alloy — versatile, neutral';
$string['voice_echo']    = 'Echo — resonant, male';
$string['voice_fable']   = 'Fable — expressive, British';
$string['voice_nova']    = 'Nova — warm, female';
$string['voice_onyx']    = 'Onyx — deep, authoritative';
$string['voice_shimmer'] = 'Shimmer — soft, clear';

$string['warning_1min']  = '⚠️ 1 minute remaining';
$string['warning_2min']  = '⚠️ 2 minutes remaining';

$string['workflow_inreview']        = 'In review';
$string['workflow_readyforrelease'] = 'Ready for release';
$string['workflow_released']        = 'Released';

$string['your_grade']       = 'Your grade:';
$string['your_role_label']  = 'Your role in this scenario:';
