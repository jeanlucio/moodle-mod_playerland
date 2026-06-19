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
        init() {
            const configEl = document.getElementById('mod-playerland-config');
            const config = configEl ? JSON.parse(configEl.textContent) : {};

            // Phaser is loaded globally via $PAGE->requires->js().
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
