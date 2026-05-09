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
 * Shared utility functions for mod_airoleplay AMD modules.
 *
 * @module     mod_airoleplay/utils
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    /**
     * Makes an AJAX POST request.
     * @param {string} url
     * @param {Object} data
     * @param {string} sesskey
     * @returns {Promise}
     */
    var ajaxPost = function(url, data, sesskey) {
        var body = Object.assign({}, data, {sesskey: sesskey});
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(body)
        }).then(function(res) {
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            return res.json();
        });
    };

    /**
     * Formats seconds as MM:SS.
     * @param {number} totalSeconds
     * @returns {string}
     */
    var formatTime = function(totalSeconds) {
        var abs = Math.abs(totalSeconds);
        var minutes = Math.floor(abs / 60);
        var seconds = abs % 60;
        return String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
    };

    /**
     * Plays a short beep via Web Audio API.
     * @param {number} frequency
     * @param {number} duration
     * @param {number} volume
     */
    var playBeep = function(frequency, duration, volume) {
        frequency = frequency || 440;
        duration  = duration  || 200;
        volume    = volume    || 0.3;
        try {
            var ctx  = new (window.AudioContext || window.webkitAudioContext)();
            var osc  = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = frequency;
            gain.gain.setValueAtTime(volume, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration / 1000);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + duration / 1000);
        } catch (e) {
            // ignore
        }
    };

    /**
     * Shows a Bootstrap alert message in a container element.
     * @param {HTMLElement} el
     * @param {string}      message
     * @param {string}      type   'info'|'success'|'warning'|'danger'
     */
    var showStatus = function(el, message, type) {
        if (!el) {
            return;
        }
        type = type || 'info';
        el.className    = 'airoleplay-status-message alert alert-' + type;
        el.textContent  = message;
        el.style.display = 'block';
    };

    return {
        ajaxPost:   ajaxPost,
        formatTime: formatTime,
        playBeep:   playBeep,
        showStatus: showStatus
    };
});
