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
 * Live character counter for the mini-lesson fields on the activity form.
 *
 * @module     mod_playerland/lessoncounter
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/str'], function(str) {
    'use strict';

    return {
        /**
         * Attaches a counter under each named textarea.
         *
         * @param {string[]} fieldnames The textarea element names.
         * @param {number} max The character limit.
         */
        async init(fieldnames, max) {
            let template;
            try {
                template = await str.get_string(
                    'lessoncharcount',
                    'mod_playerland',
                    {count: '{count}', max: max}
                );
            } catch (e) {
                return;
            }

            fieldnames.forEach(name => {
                const field = document.querySelector(`[name="${name}"]`);
                if (!field) {
                    return;
                }
                const counter = document.createElement('div');
                counter.className = 'mod-playerland-lessoncounter';
                field.insertAdjacentElement('afterend', counter);

                const update = () => {
                    const length = field.value.length;
                    counter.textContent = template.replace('{count}', length);
                    counter.classList.toggle('text-danger', length > max);
                };
                field.addEventListener('input', update);
                update();
            });
        }
    };
});
