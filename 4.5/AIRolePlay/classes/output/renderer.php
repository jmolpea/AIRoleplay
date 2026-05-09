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
 * Output renderer for mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\output;

/**
 * Activity module renderer for mod_airoleplay.
 */
class renderer extends \plugin_renderer_base {
    /**
     * Renders the activity status badge.
     *
     * @param string $status Submission status string.
     * @return string HTML badge.
     */
    public function render_status_badge(string $status): string {
        $classmap = [
            'draft'     => 'secondary',
            'active'    => 'info',
            'submitted' => 'warning',
            'grading'   => 'warning',
            'graded'    => 'success',
        ];
        $class = $classmap[$status] ?? 'secondary';
        return \html_writer::span(
            s(ucfirst($status)),
            "badge bg-{$class}"
        );
    }

    /**
     * Renders a grade breakdown as a formatted HTML table.
     *
     * @param array $breakdown Decoded grade_breakdown JSON.
     * @return string HTML table.
     */
    public function render_grade_breakdown(array $breakdown): string {
        if (empty($breakdown)) {
            return '';
        }

        $table = new \html_table();
        $table->head = [
            get_string('col_grade', 'mod_airoleplay'),
            'Score',
            'Weight',
            get_string('feedback', 'mod_airoleplay'),
        ];
        $table->attributes['class'] = 'generaltable';

        foreach ($breakdown as $dimension => $data) {
            $label = get_string('dimension_' . $dimension, 'mod_airoleplay', $dimension);
            $table->data[] = [
                s($label),
                (int)($data['score'] ?? 0) . '/100',
                ((float)($data['weight'] ?? 0)) * 100 . '%',
                s($data['feedback'] ?? ''),
            ];
        }

        return \html_writer::table($table);
    }
}
