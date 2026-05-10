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
 * Cache definitions for mod_airoleplay.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    // Holds three families of counters keyed under a single cache:
    //   user_<userid>_<minute>            per-user per-minute call quota
    //   global_<minute>                   site-wide per-minute call quota
    //   regen_<op>_<userid>_<sub>         last regen timestamp (cooldown)
    //   regen_day_<op>_<userid>_<sub>_<d> regen daily cap (UTC day bucket)
    //
    // TTL must exceed the longest window we care about (the 24h daily cap)
    // because we read the prior value before deciding whether to throttle.
    // Per-minute bucketing happens via the key suffix, not via TTL, so
    // a long TTL only adds a small idle memory cost.
    'ratelimit' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'ttl'        => 86400,
        'simplekeys' => true,
        'simpledata' => true,
    ],
];
