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
 *  - OpenAI API: spoken responses (as text), conversation turns for evaluation
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
                'status'                 => 'privacy:metadata:airoleplay_submissions:status',
                'gdpr_consent'           => 'privacy:metadata:airoleplay_submissions:gdpr_consent',
                'gdpr_consent_time'      => 'privacy:metadata:airoleplay_submissions:gdpr_consent_time',
                'roleplay_transcript'    => 'privacy:metadata:airoleplay_submissions:roleplay_transcript',
                'final_grade'            => 'privacy:metadata:airoleplay_submissions:final_grade',
                'final_feedback'         => 'privacy:metadata:airoleplay_submissions:final_feedback',
                'timecreated'            => 'privacy:metadata:airoleplay_submissions:timecreated',
                'timesubmitted'          => 'privacy:metadata:airoleplay_submissions:timesubmitted',
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

        // External service: OpenAI.
        $collection->add_external_location_link(
            'openai',
            [
                'conversation_turns' => 'privacy:metadata:openai:conversation_turns',
            ],
            'privacy:metadata:openai'
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
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'airoleplay'
                  JOIN {airoleplay_submissions} s ON s.airoleplay = cm.instance AND s.userid = :userid";

        $contextlist->add_from_sql($sql, ['ctxlevel' => CONTEXT_MODULE, 'userid' => $userid]);
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
        $sql = "SELECT s.userid
                  FROM {airoleplay_submissions} s
                  JOIN {course_modules} cm ON cm.instance = s.airoleplay
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
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
                    'attempt'           => $submission->attempt,
                    'status'            => $submission->status,
                    'gdpr_consent'      => transform::yesno($submission->gdpr_consent),
                    'gdpr_consent_time' => $submission->gdpr_consent_time
                        ? transform::datetime($submission->gdpr_consent_time)
                        : '-',
                    'final_grade'       => $submission->final_grade,
                    'final_feedback'    => $submission->final_feedback,
                    'timecreated'       => transform::datetime($submission->timecreated),
                    'timesubmitted'     => $submission->timesubmitted
                        ? transform::datetime($submission->timesubmitted)
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
                    'turn_number ASC'
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
    }
}
