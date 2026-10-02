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

import Ajax from 'core/ajax';

/**
 * Calls one of the plugin's external services.
 *
 * Resolves with the service result plus success: true. A Moodle exception
 * (capability, state, provider error...) resolves with success: false, the
 * translated message and the error code, so callers can show it. Network
 * failures reject.
 *
 * @param {string} methodname Service name, e.g. mod_airoleplay_submit_turn.
 * @param {Object} args Service arguments.
 * @returns {Promise<Object>}
 */
export const callService = async(methodname, args) => {
    try {
        const data = await Ajax.call([{methodname: methodname, args: args}])[0];
        return Object.assign({success: true}, data);
    } catch (ex) {
        if (ex && ex.errorcode) {
            return {success: false, error: ex.message, errorcode: ex.errorcode};
        }
        throw ex;
    }
};

/**
 * Formats seconds as MM:SS.
 *
 * @param {number} totalSeconds Seconds.
 * @returns {string}
 */
export const formatTime = (totalSeconds) => {
    const abs = Math.max(0, Math.abs(totalSeconds));
    const minutes = Math.floor(abs / 60);
    const seconds = abs % 60;
    return String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
};

/**
 * Plays a short beep via the Web Audio API.
 *
 * @param {number} frequency Frequency in Hz.
 * @param {number} duration Duration in ms.
 * @param {number} volume Gain between 0 and 1.
 */
export const playBeep = (frequency = 440, duration = 200, volume = 0.3) => {
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        const ctx = new Ctx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.value = frequency;
        gain.gain.setValueAtTime(volume, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration / 1000);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + duration / 1000);
        osc.onended = () => ctx.close();
    } catch (e) {
        // Audio is a nicety only.
    }
};

/**
 * Shows a Bootstrap alert message in a container element.
 *
 * @param {HTMLElement|null} el Container.
 * @param {string} message Text (never HTML).
 * @param {string} type 'info'|'success'|'warning'|'danger'.
 */
export const showStatus = (el, message, type = 'info') => {
    if (!el) {
        return;
    }
    el.className = 'airoleplay-status-message alert alert-' + type;
    el.textContent = message;
    el.style.display = 'block';
};
