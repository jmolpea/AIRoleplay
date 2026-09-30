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
 * Exception raised when an AI provider request fails.
 *
 * @package    mod_airoleplay
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_airoleplay\api\provider;

/**
 * Provider failure carrying the HTTP status, so callers can tell a request the
 * API rejected (4xx) from a transient outage (429/5xx/network, status 0).
 *
 * The vendor's error text goes into $debuginfo only: it can quote request
 * content, so it must never be shown to the student.
 */
class api_exception extends \moodle_exception {
    /** @var int HTTP status of the failed request (0 = network failure). */
    public int $httpstatus;

    /**
     * Constructor.
     *
     * @param int    $httpstatus HTTP status code (0 when no response was received).
     * @param string $debuginfo  Vendor error text, for the server log only.
     * @param string $errorcode  Language string key in mod_airoleplay.
     */
    public function __construct(int $httpstatus, string $debuginfo = '', string $errorcode = 'openai_api_error') {
        $this->httpstatus = $httpstatus;
        parent::__construct($errorcode, 'mod_airoleplay', '', null, $debuginfo);
    }
}
