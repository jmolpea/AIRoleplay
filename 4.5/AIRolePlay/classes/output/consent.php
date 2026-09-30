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
 * Data-processing consent card shown before the first attempt.
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
 * Consent notice with the form that records the participant's agreement.
 */
class consent implements named_templatable, renderable {
    /** @var \moodle_url Page the form posts to. */
    protected \moodle_url $actionurl;

    /**
     * Constructor.
     *
     * @param \moodle_url $actionurl Page the form posts to.
     */
    public function __construct(\moodle_url $actionurl) {
        $this->actionurl = $actionurl;
    }

    /**
     * Exports the data for the template.
     *
     * @param renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $notice = trim((string)get_config('mod_airoleplay', 'gdpr_notice_text'));
        if ($notice === '') {
            $notice = get_string('gdpr_default_notice', 'mod_airoleplay');
        }
        return [
            'notice'    => format_text($notice, FORMAT_HTML, ['context' => \context_system::instance()]),
            'actionurl' => $this->actionurl->out(false),
            'sesskey'   => sesskey(),
        ];
    }

    /**
     * Template used to render this widget.
     *
     * @param renderer_base $renderer Renderer.
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'mod_airoleplay/consent';
    }
}
