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
 * Boot scene for PlayerLand game.
 *
 * @module     mod_playerland/boot
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* global Phaser */

define([], function() {
    'use strict';

    return function() {
        return class BootScene extends Phaser.Scene {
        constructor() {
            super('BootScene');
        }

        init(data) {
            this.gameConfig = data.gameConfig;
        }

        preload() {
            const assetsUrl = this.gameConfig.assetsurl;

            // Display loading progress
            const width = this.cameras.main.width;
            const height = this.cameras.main.height;
            const loadingText = this.add.text(width / 2, height / 2, 'Loading...', {
                font: '20px monospace',
                fill: '#ffffff'
            });
            loadingText.setOrigin(0.5, 0.5);

            this.load.on('progress', function(value) {
                loadingText.setText('Loading... ' + Math.floor(value * 100) + '%');
            });

            this.load.on('complete', function() {
                loadingText.destroy();
            });

            // Load Tilemap. The map JSON is a static asset the browser caches aggressively;
            // a cache-buster guarantees level edits show up without a hard refresh.
            this.load.image('tileset', assetsUrl + '/environment/tileset.png');
            this.load.tilemapTiledJSON('map', assetsUrl + '/maps/map.json?cb=' + Date.now());

            // Load Backgrounds
            this.load.image('bg-back', assetsUrl + '/environment/back.png');
            this.load.image('bg-middle', assetsUrl + '/environment/middle.png');

            // Load Atlas (sprites)
            this.load.atlas('atlas', assetsUrl + '/atlas/atlas.png', assetsUrl + '/atlas/atlas.json');

            // Load Audio (optional, check if exists)
            // this.load.audio('music', assetsUrl + '/sound/platformer_level03_loop.ogg');

            // Question block (temporarily generating an SVG for it since it's not in the atlas)
            const svgParts = [
                '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32">',
                '<rect width="32" height="32" fill="#ffcc00" stroke="#cc9900" stroke-width="4"/>',
                '<text x="16" y="24" font-family="monospace" font-size="24" ',
                'font-weight="bold" fill="#000" text-anchor="middle">?</text></svg>'
            ];
            this.load.svg('question_block', 'data:image/svg+xml;base64,' + btoa(svgParts.join('')));

            // Exit flag (temporary placeholder art, generated as an SVG like the question block).
            const flagParts = [
                '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="24">',
                '<rect x="2" y="0" width="2" height="24" fill="#8a5a2b"/>',
                '<polygon points="4,2 14,6 4,10" fill="#e23b3b" stroke="#7a1f1f" stroke-width="1"/>',
                '</svg>'
            ];
            this.load.svg('exit_flag', 'data:image/svg+xml;base64,' + btoa(flagParts.join('')));
        }

        create() {
            // Create animations from the atlas
            this.anims.create({
                key: 'player-idle',
                frames: this.anims.generateFrameNames('atlas', {prefix: 'player/idle/player-idle-', start: 1, end: 4}),
                frameRate: 8,
                repeat: -1
            });

            this.anims.create({
                key: 'player-run',
                frames: this.anims.generateFrameNames('atlas', {prefix: 'player/run/player-run-', start: 1, end: 6}),
                frameRate: 12,
                repeat: -1
            });

            this.anims.create({
                key: 'player-jump',
                frames: this.anims.generateFrameNames('atlas', {prefix: 'player/jump/player-jump-', start: 1, end: 2}),
                frameRate: 10,
                repeat: 0
            });

            this.anims.create({
                key: 'player-hurt',
                frames: this.anims.generateFrameNames('atlas', {prefix: 'player/hurt/player-hurt-', start: 1, end: 2}),
                frameRate: 10,
                repeat: 0
            });

            // Collectible and feedback animations (sprites already present in the atlas).
            this.anims.create({
                key: 'cherry',
                frames: this.anims.generateFrameNames('atlas', {prefix: 'cherry/cherry-', start: 1, end: 7}),
                frameRate: 10,
                repeat: -1
            });

            this.anims.create({
                key: 'gem',
                frames: this.anims.generateFrameNames('atlas', {prefix: 'gem/gem-', start: 1, end: 5}),
                frameRate: 10,
                repeat: -1
            });

            this.anims.create({
                key: 'item-feedback',
                frames: this.anims.generateFrameNames('atlas', {prefix: 'item-feedback/item-feedback-', start: 1, end: 4}),
                frameRate: 12,
                repeat: 0
            });

            // Enemy animations.
            this.anims.create({
                key: 'opossum-walk',
                frames: this.anims.generateFrameNames('atlas', {prefix: 'opossum/opossum-', start: 1, end: 6}),
                frameRate: 10,
                repeat: -1
            });

            this.anims.create({
                key: 'enemy-death',
                frames: this.anims.generateFrameNames('atlas', {prefix: 'enemy-death/enemy-death-', start: 1, end: 6}),
                frameRate: 12,
                repeat: 0
            });

            // Start the main play scene
            this.scene.start('PlayScene', {gameConfig: this.gameConfig});
        }
        };
    };
});
