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
 * Privacy provider for mod_airoleplay (GDPR compliance).
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * GDPR privacy provider for mod_airoleplay.
 *
 * Data stored locally:
 *  - Submission records (roleplay transcript, grades, GDPR consent)
 *  - Roleplay message logs (turn-by-turn)
 *
 * Data sent to external service:
 *  - The configured AI provider (OpenAI, Anthropic, Google or DeepSeek): the
 *    participant's first name, transcribed replies and the conversation for evaluation
 *  - The browser's own speech recognition service (Web Speech API), which may
 *    receive the audio of spoken replies; the plugin itself never handles audio
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns the metadata describing what data this plugin stores.
     *
     * @param collection $collection The initialised metadata collection.
     * @return collection The populated collection.
     */
    public static function get_metadata(collection $collection): collection {

        // Local database tables.
        $collection->add_database_table(
            'airoleplay_submissions',
            [
                'userid'                 => 'privacy:metadata:airoleplay_submissions:userid',
                'groupid'                => 'privacy:metadata:airoleplay_submissions:groupid',
                'attempt'                => 'privacy:metadata:airoleplay_submissions:attempt',
                'status'                 => 'privacy:metadata:airoleplay_submissions:status',
                'workflow_state'         => 'privacy:metadata:airoleplay_submissions:workflow_state',
                'gdpr_consent'           => 'privacy:metadata:airoleplay_submissions:gdpr_consent',
                'gdpr_consent_time'      => 'privacy:metadata:airoleplay_submissions:gdpr_consent_time',
                'roleplay_transcript'    => 'privacy:metadata:airoleplay_submissions:roleplay_transcript',
                'roleplay_analysis'      => 'privacy:metadata:airoleplay_submissions:roleplay_analysis',
                'grade_breakdown'        => 'privacy:metadata:airoleplay_submissions:grade_breakdown',
                'final_grade'            => 'privacy:metadata:airoleplay_submissions:final_grade',
                'final_feedback'         => 'privacy:metadata:airoleplay_submissions:final_feedback',
                'grader_userid'          => 'privacy:metadata:airoleplay_submissions:grader_userid',
                'timecreated'            => 'privacy:metadata:airoleplay_submissions:timecreated',
                'timemodified'           => 'privacy:metadata:airoleplay_submissions:timemodified',
                'timestarted'            => 'privacy:metadata:airoleplay_submissions:timestarted',
                'timesubmitted'          => 'privacy:metadata:airoleplay_submissions:timesubmitted',
                'timegraded'             => 'privacy:metadata:airoleplay_submissions:timegraded',
            ],
            'privacy:metadata:airoleplay_submissions'
        );

        $collection->add_database_table(
            'airoleplay_messages',
            [
                'speaker'      => 'privacy:metadata:airoleplay_messages:speaker',
                'message_text' => 'privacy:metadata:airoleplay_messages:message_text',
                'timestamp'    => 'privacy:metadata:airoleplay_messages:timestamp',
            ],
            'privacy:metadata:airoleplay_messages'
        );

        $collection->add_database_table(
            'airoleplay_overrides',
            [
                'userid'       => 'privacy:metadata:airoleplay_overrides:userid',
                'groupid'      => 'privacy:metadata:airoleplay_overrides:groupid',
                'max_attempts' => 'privacy:metadata:airoleplay_overrides:max_attempts',
                'timeopen'     => 'privacy:metadata:airoleplay_overrides:timeopen',
                'timeclose'    => 'privacy:metadata:airoleplay_overrides:timeclose',
                'timecreated'  => 'privacy:metadata:airoleplay_overrides:timecreated',
                'timemodified' => 'privacy:metadata:airoleplay_overrides:timemodified',
            ],
            'privacy:metadata:airoleplay_overrides'
        );

        // External services: the site may be configured to use OpenAI,
        // Anthropic, Google Gemini or DeepSeek for chat/evaluation, and
        // OpenAI or Google Gemini for text-to-speech. Personal identifiers
        // are replaced with a STUDENT-<hash> token (see
        // mod_airoleplay\privacy\anonymizer) before any payload leaves the
        // LMS, but the redacted free text — which the student authored — is
        // still transmitted to the configured provider for inference. The
        // participant's first name is also transmitted: the avatar needs it
        // to greet the human naturally; last name, username and email remain
        // redacted. Only the provider(s) selected in the plugin settings
        // actually receive data.
        $aifields = [
            'firstname'           => 'privacy:metadata:aiprovider:firstname',
            'roleplay_transcript' => 'privacy:metadata:aiprovider:roleplay_transcript',
            'participant_turn'    => 'privacy:metadata:aiprovider:participant_turn',
            'scenario'            => 'privacy:metadata:aiprovider:scenario',
            'participant_role'    => 'privacy:metadata:aiprovider:participant_role',
            'evaluation_request'  => 'privacy:metadata:aiprovider:evaluation_request',
        ];
        $collection->add_external_location_link('openai', $aifields, 'privacy:metadata:openai');
        $collection->add_external_location_link('anthropic', $aifields, 'privacy:metadata:anthropic');
        $collection->add_external_location_link('gemini', $aifields, 'privacy:metadata:gemini');
        $collection->add_external_location_link('deepseek', $aifields, 'privacy:metadata:deepseek');

        // Voice input: the browser's Web Speech API transcribes the spoken
        // replies, and most browsers do it on their vendor's servers. The
        // plugin only receives the resulting text.
        $collection->add_external_location_link(
            'speechrecognition',
            ['audio' => 'privacy:metadata:speechrecognition:audio'],
            'privacy:metadata:speechrecognition'
        );

        return $collection;
    }

    /**
     * Returns the contexts where the given user has data.
     *
     * @param int $userid The Moodle user id.
     * @return contextlist The list of contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :ctxlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {airoleplay_submissions} s ON s.airoleplay = cm.instance
                 WHERE s.userid = :userid OR s.grader_userid = :grader";
        $contextlist->add_from_sql($sql, [
            'ctxlevel' => CONTEXT_MODULE,
            'modname'  => 'airoleplay',
            'userid'   => $userid,
            'grader'   => $userid,
        ]);

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :ctxlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {airoleplay_overrides} o ON o.airoleplay = cm.instance
                 WHERE o.userid = :userid";
        $contextlist->add_from_sql($sql, ['ctxlevel' => CONTEXT_MODULE, 'modname' => 'airoleplay', 'userid' => $userid]);
        return $contextlist;
    }

    /**
     * Returns the list of users with data in a context.
     *
     * @param userlist $userlist The userlist.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $params = ['cmid' => $context->instanceid, 'modname' => 'airoleplay'];
        $join   = "JOIN {course_modules} cm ON cm.instance = s.airoleplay
                   JOIN {modules} m ON m.id = cm.module AND m.name = :modname";
        $userlist->add_from_sql('userid', "SELECT s.userid FROM {airoleplay_submissions} s {$join} WHERE cm.id = :cmid", $params);
        $userlist->add_from_sql(
            'grader_userid',
            "SELECT s.grader_userid FROM {airoleplay_submissions} s {$join} WHERE cm.id = :cmid AND s.grader_userid IS NOT NULL",
            $params
        );
        $userlist->add_from_sql(
            'userid',
            "SELECT s.userid FROM {airoleplay_overrides} s {$join} WHERE cm.id = :cmid AND s.userid IS NOT NULL",
            $params
        );
    }

    /**
     * Exports all data for the given user in the given contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('airoleplay', $context->instanceid, 0, false, MUST_EXIST);

            $submissions = $DB->get_records('airoleplay_submissions', [
                'airoleplay' => $cm->instance,
                'userid'     => $userid,
            ]);

            foreach ($submissions as $submission) {
                $data = [
                    'attempt'             => $submission->attempt,
                    'status'              => $submission->status,
                    'workflow_state'      => $submission->workflow_state,
                    'groupid'             => $submission->groupid,
                    'gdpr_consent'        => transform::yesno($submission->gdpr_consent),
                    'gdpr_consent_time'   => $submission->gdpr_consent_time
                        ? transform::datetime($submission->gdpr_consent_time)
                        : '-',
                    'roleplay_transcript' => $submission->roleplay_transcript,
                    'roleplay_analysis'   => $submission->roleplay_analysis,
                    'grade_breakdown'     => $submission->grade_breakdown,
                    'final_grade'         => $submission->final_grade,
                    'final_feedback'      => $submission->final_feedback,
                    'grader_userid'       => $submission->grader_userid,
                    'timecreated'         => transform::datetime($submission->timecreated),
                    'timemodified'        => $submission->timemodified
                        ? transform::datetime($submission->timemodified)
                        : '-',
                    'timestarted'         => $submission->timestarted
                        ? transform::datetime($submission->timestarted)
                        : '-',
                    'timesubmitted'       => $submission->timesubmitted
                        ? transform::datetime($submission->timesubmitted)
                        : '-',
                    'timegraded'          => $submission->timegraded
                        ? transform::datetime($submission->timegraded)
                        : '-',
                ];

                $subcontextpath = [
                    get_string('pluginname', 'mod_airoleplay'),
                    get_string('submission', 'mod_airoleplay') . ' ' . $submission->attempt,
                ];

                writer::with_context($context)->export_data($subcontextpath, (object)$data);

                // Export roleplay messages.
                $messages = $DB->get_records(
                    'airoleplay_messages',
                    ['submission_id' => $submission->id],
                    'turn_number ASC, id ASC'
                );

                if ($messages) {
                    $msgdata = array_values(array_map(static function ($m) {
                        return [
                            'turn'    => $m->turn_number,
                            'speaker' => $m->speaker,
                            'text'    => $m->message_text,
                            'time'    => transform::datetime($m->timestamp),
                        ];
                    }, $messages));
                    writer::with_context($context)->export_data(
                        array_merge($subcontextpath, [get_string('conversation_log', 'mod_airoleplay')]),
                        (object)['messages' => $msgdata]
                    );
                }
            }

            // Attempts of other users that this user graded: what the grader
            // set, without the student's identity or conversation.
            $graded = $DB->get_records_select(
                'airoleplay_submissions',
                'airoleplay = :airoleplay AND grader_userid = :grader AND userid <> :userid',
                ['airoleplay' => $cm->instance, 'grader' => $userid, 'userid' => $userid],
                'id ASC'
            );
            foreach ($graded as $submission) {
                writer::with_context($context)->export_data(
                    [
                        get_string('pluginname', 'mod_airoleplay'),
                        get_string('privacy:gradedattempts', 'mod_airoleplay'),
                        get_string('submission', 'mod_airoleplay') . ' ' . $submission->id,
                    ],
                    (object)[
                        'attempt'        => $submission->attempt,
                        'workflow_state' => $submission->workflow_state,
                        'final_grade'    => $submission->final_grade,
                        'final_feedback' => $submission->final_feedback,
                        'timemodified'   => $submission->timemodified
                            ? transform::datetime($submission->timemodified)
                            : '-',
                        'timegraded'     => $submission->timegraded
                            ? transform::datetime($submission->timegraded)
                            : '-',
                    ]
                );
            }

            // Per-user overrides for this activity.
            $overrides = $DB->get_records('airoleplay_overrides', [
                'airoleplay' => $cm->instance,
                'userid'     => $userid,
            ]);
            if ($overrides) {
                $overridedata = array_values(array_map(static function ($o) {
                    return [
                        'max_attempts' => $o->max_attempts,
                        'timeopen'     => $o->timeopen ? transform::datetime($o->timeopen) : '-',
                        'timeclose'    => $o->timeclose ? transform::datetime($o->timeclose) : '-',
                        'timecreated'  => transform::datetime($o->timecreated),
                        'timemodified' => $o->timemodified ? transform::datetime($o->timemodified) : '-',
                    ];
                }, $overrides));
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'mod_airoleplay'), get_string('overrides_heading', 'mod_airoleplay')],
                    (object)['overrides' => $overridedata]
                );
            }
        }
    }

    /**
     * Deletes all data for all users in the given context.
     *
     * @param \context $context The context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('airoleplay', $context->instanceid);
        if (!$cm) {
            return;
        }

        $submissions = $DB->get_records('airoleplay_submissions', ['airoleplay' => $cm->instance]);
        foreach ($submissions as $submission) {
            $DB->delete_records('airoleplay_messages', ['submission_id' => $submission->id]);
        }
        $DB->delete_records('airoleplay_submissions', ['airoleplay' => $cm->instance]);
        $DB->delete_records('airoleplay_overrides', ['airoleplay' => $cm->instance]);
    }

    /**
     * Deletes all data for the given user in the given contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('airoleplay', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $submissions = $DB->get_records('airoleplay_submissions', [
                'airoleplay' => $cm->instance,
                'userid'     => $userid,
            ]);

            foreach ($submissions as $submission) {
                $DB->delete_records('airoleplay_messages', ['submission_id' => $submission->id]);
            }

            $DB->delete_records('airoleplay_submissions', ['airoleplay' => $cm->instance, 'userid' => $userid]);
            $DB->delete_records('airoleplay_overrides', ['airoleplay' => $cm->instance, 'userid' => $userid]);
            // Keep other students' grades but forget who graded them.
            $DB->set_field('airoleplay_submissions', 'grader_userid', null, [
                'airoleplay'    => $cm->instance,
                'grader_userid' => $userid,
            ]);
        }
    }

    /**
     * Deletes all data for all users in the given userlist.
     *
     * @param approved_userlist $userlist The approved userlist.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('airoleplay', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['airoleplay'] = $cm->instance;

        $submissions = $DB->get_records_sql(
            "SELECT * FROM {airoleplay_submissions} WHERE airoleplay = :airoleplay AND userid {$insql}",
            $params
        );

        foreach ($submissions as $submission) {
            $DB->delete_records('airoleplay_messages', ['submission_id' => $submission->id]);
        }

        $DB->delete_records_select(
            'airoleplay_submissions',
            "airoleplay = :airoleplay AND userid {$insql}",
            $params
        );
        $DB->delete_records_select(
            'airoleplay_overrides',
            "airoleplay = :airoleplay AND userid {$insql}",
            $params
        );
        $DB->set_field_select(
            'airoleplay_submissions',
            'grader_userid',
            null,
            "airoleplay = :airoleplay AND grader_userid {$insql}",
            $params
        );
    }
}
