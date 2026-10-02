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

namespace mod_airoleplay\external;

use core_external\external_api;
use core_external\external_value;

/**
 * Shared set-up, attempt lookup and error handling for the session services.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends external_api {
    /**
     * Loads the activity and validates the context (login, course access).
     *
     * @param int $cmid Course module id.
     * @return array [cm, course, activity record, module context]
     * @throws \moodle_exception
     */
    protected static function load_activity(int $cmid): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/airoleplay/lib.php');

        $cm         = get_coursemodule_from_id('airoleplay', $cmid, 0, false, MUST_EXIST);
        $course     = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $airoleplay = $DB->get_record('airoleplay', ['id' => $cm->instance], '*', MUST_EXIST);

        $context = \context_module::instance($cm->id);
        self::validate_context($context);

        return [$cm, $course, $airoleplay, $context];
    }

    /**
     * Last step before the service works: the licence gate and the session lock.
     *
     * Called once the caller's permissions are checked, so a user without
     * access gets a permission error whatever the licence state.
     *
     * @throws \moodle_exception when no valid licence key is bound to this site.
     */
    protected static function begin_work(): void {
        \mod_airoleplay\license\validator::require_valid();

        // Nothing below writes to the session. Releasing its lock now keeps the
        // AI calls (seconds each) from blocking every other request of the same
        // user, such as Moodle's own notification polling or a second tab.
        \core\session\manager::write_close();
    }

    /**
     * Loads the current user's attempt for this activity.
     *
     * With an explicit id, the attempt must belong to the user and the activity;
     * otherwise the user's latest attempt is returned.
     *
     * @param \stdClass $airoleplay   Activity record.
     * @param int       $submissionid Attempt id sent by the browser (0 = latest).
     * @return \stdClass
     * @throws \moodle_exception when the user has no attempt yet (consent missing).
     */
    protected static function own_submission(\stdClass $airoleplay, int $submissionid): \stdClass {
        global $DB, $USER;

        if ($submissionid > 0) {
            $submission = $DB->get_record('airoleplay_submissions', [
                'id'         => $submissionid,
                'airoleplay' => $airoleplay->id,
                'userid'     => $USER->id,
            ]);
        } else {
            $submission = airoleplay_get_latest_attempt((int)$airoleplay->id, (int)$USER->id);
        }
        if (!$submission) {
            throw new \moodle_exception('gdpr_consent_required', 'mod_airoleplay');
        }
        return $submission;
    }

    /**
     * Runs a service body, logging what must not reach the browser.
     *
     * Moodle exceptions carry translated messages; provider errors keep the
     * vendor detail in debuginfo, which is logged here. Any other failure is
     * logged and replaced by a generic message.
     *
     * @param string   $action   Service name, for the log.
     * @param int      $cmid     Course module id, for the log.
     * @param callable $callback Service body.
     * @return array The service result.
     * @throws \moodle_exception
     */
    protected static function guarded(string $action, int $cmid, callable $callback): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/airoleplay/lib.php');

        try {
            return $callback();
        } catch (\moodle_exception $e) {
            if (!empty($e->debuginfo)) {
                airoleplay_log_internal_error('service_' . $action, $e, ['cmid' => $cmid]);
            }
            throw $e;
        } catch (\Throwable $e) {
            airoleplay_log_internal_error('service_' . $action, $e, ['cmid' => $cmid]);
            throw new \moodle_exception('unexpectederror', 'error');
        }
    }

    /**
     * The fields of one avatar line, shared by the services that return one.
     *
     * @return external_value[]
     */
    protected static function turn_fields(): array {
        return [
            'avatar'       => new external_value(PARAM_INT, 'Avatar that speaks (1-3), 0 when there is no line'),
            'text'         => new external_value(PARAM_RAW, 'The avatar line, plain text'),
            'audio_base64' => new external_value(PARAM_RAW, 'Synthesised speech, base64 encoded; empty for none'),
            'audio_mime'   => new external_value(PARAM_RAW, 'MIME type of the audio'),
            'turn'         => new external_value(PARAM_INT, 'Turn number of the line'),
        ];
    }

    /**
     * An empty avatar line.
     *
     * @return array
     */
    protected static function empty_turn(): array {
        return ['avatar' => 0, 'text' => '', 'audio_base64' => '', 'audio_mime' => '', 'turn' => 0];
    }
}
