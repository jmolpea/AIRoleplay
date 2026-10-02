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
 * External services of mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_airoleplay_start_session' => [
        'classname'    => \mod_airoleplay\external\start_session::class,
        'description'  => 'Starts the roleplay session of the current user, or resumes it.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/airoleplay:submit',
    ],
    'mod_airoleplay_submit_turn' => [
        'classname'    => \mod_airoleplay\external\submit_turn::class,
        'description'  => 'Sends one participant reply and returns the avatar answer.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/airoleplay:submit',
    ],
    'mod_airoleplay_close_session' => [
        'classname'    => \mod_airoleplay\external\close_session::class,
        'description'  => 'Closes the roleplay session and returns the closing line.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/airoleplay:submit',
    ],
    'mod_airoleplay_finalise_session' => [
        'classname'    => \mod_airoleplay\external\finalise_session::class,
        'description'  => 'Evaluates a closed roleplay session.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/airoleplay:submit',
    ],
    'mod_airoleplay_get_evaluation_status' => [
        'classname'    => \mod_airoleplay\external\get_evaluation_status::class,
        'description'  => 'Returns the status of an attempt of the current user.',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'mod/airoleplay:submit',
    ],
    'mod_airoleplay_regenerate_evaluation' => [
        'classname'    => \mod_airoleplay\external\regenerate_evaluation::class,
        'description'  => 'Regenerates the AI evaluation of an attempt.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/airoleplay:grade',
    ],
];
