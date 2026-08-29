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
            // Cache-busting suffix for the static assets below (tileset, backgrounds, atlases,
            // audio). It changes only when the plugin version is bumped, so browsers keep
            // caching these normally between releases instead of refetching on every page load
            // — unlike the map file just below, which intentionally always fetches fresh via
            // Date.now(). Without this, a browser that cached an older atlas-props.json can end
            // up paired with a newer play.js that requests frames the cached JSON doesn't have.
            const rev = '?rev=' + (this.gameConfig.assetrev || 0);

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

            // Load Tilemap based on config.
            const mapFile = this.gameConfig.map || 'map.json';
            this.load.image('tileset', assetsUrl + '/environment/tileset.png' + rev);
            this.load.tilemapTiledJSON('map', assetsUrl + '/maps/' + mapFile + '?cb=' + Date.now());

            // Load Backgrounds
            this.load.image('bg-back', assetsUrl + '/environment/back.png' + rev);
            this.load.image('bg-middle', assetsUrl + '/environment/middle.png' + rev);

            // Load Atlas (sprites)
            this.load.atlas('atlas', assetsUrl + '/atlas/atlas.png' + rev, assetsUrl + '/atlas/atlas.json' + rev);

            // Load the props atlas: spikes, crates, platforms, crank, door, decorative pieces.
            // Every frame here is a single static image referenced by name from PlayScene.
            this.load.atlas(
                'props',
                assetsUrl + '/atlas/atlas-props.png' + rev,
                assetsUrl + '/atlas/atlas-props.json' + rev
            );

            // Background music loop. Playback is opt-in and starts muted-friendly from PlayScene.
            this.load.audio('music', assetsUrl + '/sound/platformer_level03_loop.ogg' + rev);

            // Question block (temporarily generating an SVG for it since it's not in the atlas)
            const svgParts = [
                '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32">',
                '<rect width="32" height="32" fill="#ffcc00" stroke="#cc9900" stroke-width="4"/>',
                '<text x="16" y="24" font-family="monospace" font-size="24" ',
                'font-weight="bold" fill="#000" text-anchor="middle">?</text></svg>'
            ];
            this.load.svg('question_block', 'data:image/svg+xml;base64,' + btoa(svgParts.join('')));

            // Lesson block: blue "!" so the colour reads as "repeatable" (like the taxonomy)
            // and the symbol distinguishes "read" from the "?" of "answer".
            const lessonParts = [
                '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32">',
                '<rect width="32" height="32" fill="#2f7fd0" stroke="#1c4f86" stroke-width="4"/>',
                '<rect x="13" y="6" width="6" height="12" fill="#fff"/>',
                '<rect x="13" y="21" width="6" height="5" fill="#fff"/></svg>'
            ];
            this.load.svg('lesson_block', 'data:image/svg+xml;base64,' + btoa(lessonParts.join('')));

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

            // Crouch / crawl pose, also reused for the ground roll (dash).
            this.anims.create({
                key: 'player-crouch',
                frames: this.anims.generateFrameNames('atlas', {prefix: 'player/crouch/player-crouch-', start: 1, end: 2}),
                frameRate: 8,
                repeat: -1
            });

            // Ladder climb. Held still on a ladder shows the first frame only (handled in code).
            this.anims.create({
                key: 'player-climb',
                frames: this.anims.generateFrameNames('atlas', {prefix: 'player/climb/player-climb-', start: 1, end: 3}),
                frameRate: 9,
                repeat: -1
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

            this.anims.create({
                key: 'eagle-attack',
                frames: this.anims.generateFrameNames('atlas', {prefix: 'eagle/eagle-attack-', start: 1, end: 4}),
                frameRate: 10,
                repeat: -1
            });

            this.anims.create({
                key: 'frog-idle',
                frames: this.anims.generateFrameNames('atlas', {prefix: 'frog/idle/frog-idle-', start: 1, end: 4}),
                frameRate: 8,
                repeat: -1
            });

            this.anims.create({
                key: 'frog-jump',
                frames: this.anims.generateFrameNames('atlas', {prefix: 'frog/jump/frog-jump-', start: 1, end: 2}),
                frameRate: 10,
                repeat: 0
            });

            // Start the main play scene
            this.scene.start('PlayScene', {gameConfig: this.gameConfig});
        }
        };
    };
});
