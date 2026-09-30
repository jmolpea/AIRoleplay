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
 * Template data for the rubric breakdown of an evaluation.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\output;

/**
 * Turns the stored rubric breakdown JSON into template rows.
 */
class breakdown {
    /**
     * Rows for the rubric breakdown.
     *
     * @param string $json Stored grade_breakdown JSON.
     * @return array List of ['label', 'score', 'weight', 'feedback'] (plain text, escaped by the template).
     */
    public static function export(string $json): array {
        $breakdown = json_decode($json, true);
        if (!is_array($breakdown)) {
            return [];
        }
        $rows = [];
        $strings = get_string_manager();
        foreach ($breakdown as $dimension => $data) {
            if (!is_string($dimension) || !is_array($data)) {
                continue;
            }
            $rows[] = [
                'label'    => $strings->string_exists('dimension_' . $dimension, 'mod_airoleplay')
                    ? get_string('dimension_' . $dimension, 'mod_airoleplay')
                    : $dimension,
                'score'    => (int)round((float)($data['score'] ?? 0)),
                'weight'   => (int)round((float)($data['weight'] ?? 0) * 100),
                'feedback' => (string)($data['feedback'] ?? ''),
            ];
        }
        return $rows;
    }
}
