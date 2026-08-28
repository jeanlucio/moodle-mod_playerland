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
 * First-load "how to play" overlay for PlayerLand.
 *
 * @module     mod_playerland/intro
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification'], function(ajax, Notification) {
    'use strict';

    return {
        /**
         * Wires the dismiss button: hides the overlay immediately and remembers
         * it was seen (as a user preference, so it follows the student across
         * devices) via a single web service call.
         *
         * @param {number} playerlandid The playerland instance id.
         */
        init(playerlandid) {
            const overlay = document.getElementById('playerland-intro');
            const dismiss = document.getElementById('playerland-intro-dismiss');
            if (!overlay || !dismiss) {
                return;
            }

            dismiss.focus();

            dismiss.addEventListener('click', async() => {
                overlay.remove();
                try {
                    await ajax.call([{
                        methodname: 'mod_playerland_dismiss_intro',
                        args: {playerlandid}
                    }])[0];
                } catch (err) {
                    Notification.exception(err);
                }
            }, {once: true});
        }
    };
});
