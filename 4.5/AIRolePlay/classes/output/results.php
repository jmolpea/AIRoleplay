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
 * Results of a graded attempt, as the participant sees them.
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
 * Grade, feedback and rubric breakdown of a graded attempt, plus the
 * participant's next actions (new attempt, withdraw consent).
 */
class results implements named_templatable, renderable {
    /** @var \stdClass|null Graded attempt, or null when there are no results to show. */
    protected ?\stdClass $submission;

    /** @var \context_module Module context. */
    protected \context_module $context;

    /** @var \moodle_url|null Where to post "start a new attempt", or null when not allowed. */
    protected ?\moodle_url $newattempturl;

    /** @var string Rendered "withdraw consent" button, or '' when not allowed. */
    protected string $revokebutton;

    /**
     * Constructor.
     *
     * @param \stdClass|null   $submission    Graded attempt, or null.
     * @param \context_module  $context       Module context.
     * @param \moodle_url|null $newattempturl Where to post "start a new attempt", or null.
     * @param string           $revokebutton  Rendered "withdraw consent" button, or ''.
     */
    public function __construct(
        ?\stdClass $submission,
        \context_module $context,
        ?\moodle_url $newattempturl,
        string $revokebutton
    ) {
        $this->submission    = $submission;
        $this->context       = $context;
        $this->newattempturl = $newattempturl;
        $this->revokebutton  = $revokebutton;
    }

    /**
     * Exports the data for the template.
     *
     * @param renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $data = [
            'hasresults'    => false,
            'released'      => false,
            'newattempturl' => $this->newattempturl ? $this->newattempturl->out(false) : '',
            'sesskey'       => sesskey(),
            'revokebutton'  => $this->revokebutton,
        ];
        $submission = $this->submission;
        if (!$submission) {
            return $data;
        }
        $data['hasresults'] = true;
        if ($submission->workflow_state !== 'released') {
            return $data;
        }

        $data['released'] = true;
        $data['grade']    = format_float((float)$submission->final_grade, 2);
        // Feedback comes from the AI evaluator or a teacher; it is plain text
        // so model output can never inject markup.
        $data['feedback'] = !empty($submission->final_feedback)
            ? format_text($submission->final_feedback, FORMAT_PLAIN, ['context' => $this->context])
            : '';

        $analysis = json_decode((string)$submission->roleplay_analysis, true);
        $data['lists'] = [];
        foreach (['strengths', 'areas_for_improvement'] as $listkey) {
            $items = is_array($analysis[$listkey] ?? null) ? array_filter($analysis[$listkey], 'is_string') : [];
            if ($items) {
                $data['lists'][] = [
                    'heading' => get_string('results_' . $listkey, 'mod_airoleplay'),
                    'items'   => array_values($items),
                ];
            }
        }

        $data['breakdown'] = breakdown::export((string)$submission->grade_breakdown);
        $data['hasbreakdown'] = !empty($data['breakdown']);
        return $data;
    }

    /**
     * Template used to render this widget.
     *
     * @param renderer_base $renderer Renderer.
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'mod_airoleplay/results';
    }
}
