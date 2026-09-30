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
 * Teacher view of one attempt: transcript, evaluation and grading controls.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\output;

use core\output\named_templatable;
use renderable;
use renderer_base;

/**
 * One attempt as the teacher reviews it.
 */
class submission_detail implements named_templatable, renderable {
    /** @var \stdClass Activity record. */
    protected \stdClass $airoleplay;

    /** @var \stdClass Participant. */
    protected \stdClass $student;

    /** @var \stdClass Attempt. */
    protected \stdClass $submission;

    /** @var \context_module Module context. */
    protected \context_module $context;

    /** @var bool Whether the viewer may grade. */
    protected bool $cangrade;

    /**
     * Constructor.
     *
     * @param \stdClass       $airoleplay Activity record.
     * @param \stdClass       $student    Participant.
     * @param \stdClass       $submission Attempt.
     * @param \context_module $context    Module context.
     * @param bool            $cangrade   Whether the viewer may grade.
     */
    public function __construct(
        \stdClass $airoleplay,
        \stdClass $student,
        \stdClass $submission,
        \context_module $context,
        bool $cangrade
    ) {
        $this->airoleplay = $airoleplay;
        $this->student    = $student;
        $this->submission = $submission;
        $this->context    = $context;
        $this->cangrade   = $cangrade;
    }

    /**
     * Exports the data for the template.
     *
     * @param renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $airoleplay = $this->airoleplay;
        $submission = $this->submission;
        $cmid       = (int)$this->context->instanceid;
        $baseurl    = new \moodle_url('/mod/airoleplay/submissions.php', ['id' => $cmid]);
        $strings    = get_string_manager();

        // Integrity flags first: they explain why an attempt is waiting for review.
        $flags = [];
        foreach (self::flags($submission) as $flag) {
            $flags[] = $strings->string_exists('flag_' . $flag, 'mod_airoleplay')
                ? get_string('flag_' . $flag, 'mod_airoleplay')
                : $flag;
        }

        // Transcript, with avatar names instead of internal ids.
        $transcript = [];
        $decoded = json_decode((string)$submission->roleplay_transcript, true);
        foreach (is_array($decoded) ? $decoded : [] as $turn) {
            if (!is_array($turn)) {
                continue;
            }
            $speaker = (string)($turn['speaker'] ?? '');
            if ($speaker === 'participant') {
                $name = fullname($this->student);
            } else {
                $index = (int)preg_replace('/\D/', '', $speaker);
                $name  = format_string(
                    trim((string)($airoleplay->{"avatar_{$index}_name"} ?? ''))
                        ?: get_string('avatar_default_name', 'mod_airoleplay', $index),
                    true,
                    ['context' => $this->context, 'escape' => false]
                );
            }
            $transcript[] = [
                'speaker'       => $name,
                'text'          => (string)($turn['text'] ?? ''),
                'isparticipant' => $speaker === 'participant',
            ];
        }

        $status = (string)$submission->status;
        $data = [
            'heading'     => fullname($this->student) . ' — ' .
                get_string('attempt_n', 'mod_airoleplay', (int)$submission->attempt),
            'backurl'     => $baseurl->out(false),
            'statuslabel' => get_string('status_' . $status, 'mod_airoleplay'),
            'flags'       => $flags,
            'hasflags'    => !empty($flags),
            'transcript'  => $transcript,
            'hastranscript' => !empty($transcript),
            'withdrawn'   => !$transcript && $status === 'graded' && !$submission->gdpr_consent,
            'breakdown'   => breakdown::export((string)$submission->grade_breakdown),
            'canregen'    => $this->cangrade && in_array($status, ['submitted', 'graded'], true),
            'submissionid' => (int)$submission->id,
            'cmid'        => $cmid,
            'gradeform'   => false,
        ];
        $data['hasbreakdown'] = !empty($data['breakdown']);

        if ($this->cangrade && $status === 'graded') {
            $released = $submission->workflow_state === 'released';
            $data['gradeform'] = [
                'actionurl'  => $baseurl->out(false),
                'userid'     => (int)$this->student->id,
                'sesskey'    => sesskey(),
                'gradelabel' => get_string('grade_label', 'mod_airoleplay', format_float($airoleplay->grade, 0)),
                'grade'      => format_float((float)$submission->final_grade, 2),
                'feedback'   => (string)$submission->final_feedback,
                'canpublish' => !$released,
                'canreturn'  => $released,
            ];
        }
        return $data;
    }

    /**
     * Integrity flags recorded by the evaluator for an attempt (formula mismatches are informational only).
     *
     * @param \stdClass $submission Attempt with roleplay_analysis.
     * @return string[]
     */
    public static function flags(\stdClass $submission): array {
        $analysis = json_decode((string)($submission->roleplay_analysis ?? ''), true);
        $flags    = is_array($analysis['academic_integrity_flags'] ?? null) ? $analysis['academic_integrity_flags'] : [];
        return array_values(array_filter($flags, fn($flag) => is_string($flag) && $flag !== 'formula_mismatch'));
    }

    /**
     * Template used to render this widget.
     *
     * @param renderer_base $renderer Renderer.
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'mod_airoleplay/submission_detail';
    }
}
