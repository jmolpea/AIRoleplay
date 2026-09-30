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
 * Roleplay session: scenario, avatars, transcript and participant controls.
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
 * The participant's side of an attempt: scenario card, "evaluating" panel
 * and, while a session can run, the roleplay room driven by mod_airoleplay/roleplay.
 */
class room implements named_templatable, renderable {
    /** @var \stdClass Activity record. */
    protected \stdClass $airoleplay;

    /** @var \context_module Module context. */
    protected \context_module $context;

    /** @var bool Whether the roleplay room is shown (a session can start or resume). */
    protected bool $canstart;

    /** @var bool Whether the attempt is being evaluated. */
    protected bool $evaluating;

    /**
     * Constructor.
     *
     * @param \stdClass       $airoleplay Activity record.
     * @param \context_module $context    Module context.
     * @param bool            $canstart   Whether a session can start or resume.
     * @param bool            $evaluating Whether the attempt is being evaluated.
     */
    public function __construct(\stdClass $airoleplay, \context_module $context, bool $canstart, bool $evaluating) {
        $this->airoleplay = $airoleplay;
        $this->context    = $context;
        $this->canstart   = $canstart;
        $this->evaluating = $evaluating;
    }

    /**
     * Exports the data for the template.
     *
     * @param renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/airoleplay/lib.php');

        $airoleplay = $this->airoleplay;
        $fmt = ['context' => $this->context];

        $scenario = '';
        if (trim((string)$airoleplay->scenario_description) !== '') {
            $scenario = format_text($airoleplay->scenario_description, $airoleplay->scenario_descriptionformat, $fmt);
        }
        $role = '';
        if (trim((string)$airoleplay->participant_role) !== '') {
            $role = format_text($airoleplay->participant_role, FORMAT_MOODLE, $fmt);
        }

        $avatars = [];
        if ($this->canstart) {
            $pixbase    = (new \moodle_url('/mod/airoleplay/pix/avatars/'))->out(false);
            $numavatars = max(1, min(3, (int)($airoleplay->num_avatars ?? 1)));
            for ($m = 1; $m <= $numavatars; $m++) {
                $name = trim((string)($airoleplay->{"avatar_{$m}_name"} ?? ''))
                    ?: get_string('avatar_default_name', 'mod_airoleplay', $m);
                $avatar = [
                    'index' => $m,
                    'name'  => format_string($name, true, $fmt),
                    'role'  => format_string((string)($airoleplay->{"avatar_{$m}_role"} ?? ''), true, $fmt),
                ];
                $visual    = (int)($airoleplay->{"avatar_{$m}_avatar"} ?? $m);
                $customurl = $visual === 0 ? \airoleplay_custom_avatar_url($this->context, $m) : null;
                if ($customurl) {
                    $avatar['image'] = ['src' => $customurl->out(false)];
                } else {
                    $visual = ($visual >= 1 && $visual <= 3) ? $visual : $m;
                    $avatar['video'] = [
                        'idle'    => $pixbase . "avatar_{$visual}_idle.mp4",
                        'talking' => $pixbase . "avatar_{$visual}_talking.mp4",
                        'poster'  => $pixbase . "avatar_{$visual}_poster.png",
                    ];
                }
                $avatars[] = $avatar;
            }
        }

        return [
            'scenario'   => $scenario,
            'role'       => $role,
            // The scenario stays visible before, during and right after the session.
            'hascard'    => ($this->canstart || $this->evaluating) && ($scenario !== '' || $role !== ''),
            'evaluating' => $this->evaluating,
            'canstart'   => $this->canstart,
            'title'      => format_string($airoleplay->name, true, $fmt),
            'avatars'    => $avatars,
        ];
    }

    /**
     * Template used to render this widget.
     *
     * @param renderer_base $renderer Renderer.
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'mod_airoleplay/room';
    }
}
