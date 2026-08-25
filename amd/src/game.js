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
 * Main game module for PlayerLand.
 *
 * @module     mod_playerland/game
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* global Phaser */

define([
    'mod_playerland/boot',
    'mod_playerland/play'
], function(BootScene, PlayScene) {
    'use strict';

    let phaserLoadPromise = null;

    // Loads Phaser as a dynamically-injected <script>, resolved via its onload event, instead
    // of a static <script> tag queued through $PAGE->requires->js(). A static tag there would
    // sit in the page's footer output and race core_message/message_drawer.js, which expects
    // its own drawer markup (rendered further down the same footer) to already be in the DOM
    // by the time its own require() callback runs — same pattern as filter_mathjaxloader's
    // loadMathJax() (filter/mathjaxloader/amd/src/loader.js).
    const loadPhaser = (url) => {
        if (!phaserLoadPromise) {
            phaserLoadPromise = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.type = 'text/javascript';
                script.onload = resolve;
                script.onerror = reject;
                script.src = url;
                document.getElementsByTagName('head')[0].appendChild(script);
            });
        }
        return phaserLoadPromise;
    };

    /**
     * Starts the Phaser game engine.
     *
     * @param {object} config The game configuration from PHP.
     */
    const startPhaser = function(config) {
        // Instantiate the classes using the factory functions now that Phaser is loaded
        // eslint-disable-next-line @babel/new-cap
        const BootSceneClass = BootScene();
        // eslint-disable-next-line @babel/new-cap
        const PlaySceneClass = PlayScene();

        const bootScene = new BootSceneClass();
        const playScene = new PlaySceneClass();

        const phaserConfig = {
            type: Phaser.AUTO,
            width: 800,
            height: 600,
            parent: 'playerland-game-container',
            backgroundColor: '#2d4a52', // Matches the dark background art, hides any camera gap.
            pixelArt: true, // Crucial for crisp pixel art!
            physics: {
                "default": 'arcade',
                arcade: {
                    gravity: {y: 500}, // Original demo value
                    debug: false
                }
            },
            scene: [bootScene, playScene]
        };

        const game = new Phaser.Game(phaserConfig);
        game.scene.start('BootScene', {gameConfig: config});
    };

    return {
        /**
         * Module entry point called by Moodle AMD loader.
         */
        async init() {
            const configEl = document.getElementById('mod-playerland-config');
            const config = configEl ? JSON.parse(configEl.textContent) : {};

            try {
                await loadPhaser(`${M.cfg.wwwroot}/mod/playerland/javascript/phaser.min.js`);
            } catch (err) {
                window.console.error('[PlayerLand] Phaser load error:', err);
                return;
            }

            // Phaser's UMD bundle self-registers as the AMD module "Phaser" the moment it
            // executes (checked above via its own onload), so this resolves immediately.
            require(['Phaser'], function(PhaserObj) {
                if (PhaserObj) {
                    window.Phaser = PhaserObj;
                }
                startPhaser(config);
            }, function() {
                if (window.Phaser) {
                    startPhaser(config);
                } else {
                    window.console.error('[PlayerLand] Phaser not found.');
                }
            });
        }
    };
});
