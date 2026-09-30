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
 * Teacher submissions page: regenerate an AI evaluation.
 *
 * @module     mod_airoleplay/submissions
 * @copyright  2025 Pluginia
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';
import {saveCancelPromise} from 'core/notification';
import * as utils from 'mod_airoleplay/utils';

/**
 * Wires the "regenerate evaluation" buttons.
 */
export const init = () => {
    document.querySelectorAll('.airoleplay-regen-btn').forEach((button) => {
        button.addEventListener('click', async() => {
            const statusEl = document.getElementById('airoleplay-regen-status');
            try {
                await saveCancelPromise(
                    getString('regen_heading', 'mod_airoleplay'),
                    getString('regen_confirm', 'mod_airoleplay'),
                    getString('regen_evaluation', 'mod_airoleplay')
                );
            } catch (e) {
                return;
            }
            button.disabled = true;
            utils.showStatus(statusEl, await getString('regen_running', 'mod_airoleplay'), 'info');
            try {
                const data = await utils.ajaxPost(
                    M.cfg.wwwroot + '/mod/airoleplay/ajax.php',
                    {
                        action: 'regen_evaluation',
                        cmid: parseInt(button.dataset.cmid, 10),
                        submissionid: parseInt(button.dataset.submissionid, 10),
                    },
                    M.cfg.sesskey
                );
                if (data.success) {
                    utils.showStatus(statusEl, await getString('regen_success', 'mod_airoleplay'), 'success');
                    setTimeout(() => window.location.reload(), 1000);
                    return;
                }
                utils.showStatus(statusEl, data.error || await getString('error_generic', 'mod_airoleplay'), 'danger');
            } catch (e) {
                utils.showStatus(statusEl, await getString('error_generic', 'mod_airoleplay'), 'danger');
            }
            button.disabled = false;
        });
    });
};
