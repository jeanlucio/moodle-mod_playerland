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
 * Play scene for PlayerLand game.
 *
 * @module     mod_playerland/play
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* global Phaser */

define([
    'core/ajax',
    'core/modal'
], function(ajax, Modal) {
    'use strict';

    return function() {
        return class PlayScene extends Phaser.Scene {
        constructor() {
            super('PlayScene');
        }

        init(data) {
            this.gameConfig = data.gameConfig;
            this.isModalOpen = false;
        }

        create() {
            // Parallax Backgrounds
            const mapWidth = 58 * 16;
            const mapHeight = 25 * 16;

            this.bgBack = this.add.tileSprite(0, 0, mapWidth, 240, 'bg-back').setOrigin(0, 0).setScrollFactor(0);
            // Scale the back background to fit height if needed, but 240 is default
            this.bgBack.setScale(mapHeight / 240);

            this.bgMiddle = this.add.tileSprite(0, 0, mapWidth, 240, 'bg-middle').setOrigin(0, 0).setScrollFactor(0.3);
            this.bgMiddle.setScale(mapHeight / 240);

            // Tilemap
            const map = this.make.tilemap({ key: 'map' });
            const tileset = map.addTilesetImage('tileset', 'tileset');
            const layer = map.createLayer('Tile Layer 1', tileset, 0, 0);

            // Exact collision tile IDs from the original Sunny Land demo (game.js line 225).
            // Only these specific tiles are solid — everything else is background/decoration.
            const solidTiles = [
                27, 29, 31, 33, 35, 37,
                77, 81, 86, 87,
                127, 129, 131, 133, 134, 135,
                83, 84,
                502, 504, 505, 529, 530,
                333, 335, 337, 339,
                366, 368,
                262,
                191, 193, 195,
                241, 245,
                291, 293, 295
            ];
            layer.setCollision(solidTiles);

            // One-way platforms (from original demo lines 227-236).
            // These tiles only collide on top so the player can jump through from below.
            const oneWayTiles = [35, 36, 84, 86, 134, 135, 366, 367, 368, 262];
            layer.forEachTile(tile => {
                if (oneWayTiles.includes(tile.index)) {
                    tile.setCollision(false, false, true, false);
                }
            });

            // Enable visual debug for the tilemap collisions (temporary)
            const debugGraphics = this.add.graphics().setAlpha(0.5);
            layer.renderDebug(debugGraphics, {
                tileColor: null,
                collidingTileColor: new Phaser.Display.Color(243, 134, 48, 200),
                faceColor: new Phaser.Display.Color(40, 39, 37, 255)
            });

            // Player — spawn at tile(54, 9) as per the original demo: createPlayer(54, 9)
            // In pixel coords: 54*16 = 864, 9*16 = 144
            this.player = this.physics.add.sprite(864, 144, 'atlas', 'player/idle/player-idle-1');

            // Match the original demo's player body: body.setSize(12, 16, 8, 16)
            // Phaser 3 setSize(width, height) + setOffset(x, y)
            this.player.body.setSize(12, 16);
            this.player.body.setOffset(8, 16);

            this.player.setCollideWorldBounds(true);
            this.physics.world.setBounds(0, 0, map.widthInPixels, map.heightInPixels);

            // Collisions
            this.physics.add.collider(this.player, layer);

            // Camera
            this.cameras.main.setBounds(0, 0, map.widthInPixels, map.heightInPixels);
            this.cameras.main.startFollow(this.player, true, 0.05, 0.05);
            this.cameras.main.setZoom(2);

            // Inputs
            this.cursors = this.input.keyboard.createCursorKeys();
            this.wasd = {
                up: this.input.keyboard.addKey(Phaser.Input.Keyboard.KeyCodes.W),
                left: this.input.keyboard.addKey(Phaser.Input.Keyboard.KeyCodes.A),
                down: this.input.keyboard.addKey(Phaser.Input.Keyboard.KeyCodes.S),
                right: this.input.keyboard.addKey(Phaser.Input.Keyboard.KeyCodes.D)
            };

            // Question Blocks
            this.questionBlocks = this.physics.add.staticGroup();

            // Place question blocks at meaningful positions on the map
            const blockPositions = [
                { x: 30 * 16, y: 5 * 16 },
                { x: 44 * 16, y: 12 * 16 },
                { x: 23 * 16, y: 17 * 16 }
            ];

            blockPositions.forEach(pos => {
                const block = this.questionBlocks.create(pos.x, pos.y, 'question_block');
                block.setScale(0.5);
                block.refreshBody();
            });

            this.physics.add.collider(this.player, this.questionBlocks, this.hitQuestionBlock, null, this);
        }

        update() {
            if (this.isModalOpen) {
                this.player.setVelocityX(0);
                this.player.anims.play('player-idle', true);
                return;
            }

            const cursors = this.cursors;
            const wasd = this.wasd;
            const isLeft = cursors.left.isDown || wasd.left.isDown;
            const isRight = cursors.right.isDown || wasd.right.isDown;
            const isUp = cursors.up.isDown || wasd.up.isDown || cursors.space.isDown;

            if (isLeft) {
                this.player.setVelocityX(-150);
                this.player.setFlipX(true);
                if (this.player.body.onFloor()) {
                    this.player.anims.play('player-run', true);
                }
            } else if (isRight) {
                this.player.setVelocityX(150);
                this.player.setFlipX(false);
                if (this.player.body.onFloor()) {
                    this.player.anims.play('player-run', true);
                }
            } else {
                this.player.setVelocityX(0);
                if (this.player.body.onFloor()) {
                    this.player.anims.play('player-idle', true);
                }
            }

            if (isUp && this.player.body.onFloor()) {
                this.player.setVelocityY(-170); // Original demo value
            }

            if (!this.player.body.onFloor()) {
                this.player.anims.play('player-jump', true);
            }

            // Scroll parallax backgrounds
            this.bgBack.tilePositionX = this.cameras.main.scrollX * 0.1;
            this.bgMiddle.tilePositionX = this.cameras.main.scrollX * 0.3;
        }

        async hitQuestionBlock(player, block) {
            // Check if player hit the block from below
            if (!(block.body.touching.down && player.body.touching.up)) {
                return;
            }
            if (this.isModalOpen) {
                return;
            }
            this.isModalOpen = true;

            // Player bounces back down slightly
            player.setVelocityY(50);
            
            const self = this;

            try {
                const response = await ajax.call([{
                    methodname: 'mod_playerland_get_question',
                    args: { playerlandid: this.gameConfig.id }
                }])[0];

                let bodyHtml = '';
                if (!response.hasquestion) {
                    bodyHtml = '<p>Nenhuma pergunta cadastrada ainda para esta fase!</p>';
                    bodyHtml += '<button class="btn btn-secondary mt-3" id="btn-close-modal">Fechar</button>';
                } else {
                    bodyHtml = '<p><strong>' + response.questiontext + '</strong></p>';
                    bodyHtml += '<div class="d-flex flex-column gap-2 mt-3">';
                    response.options.forEach(function(opt) {
                        bodyHtml += '<button class="btn btn-outline-primary btn-answer" data-optid="' + opt.id + '">' + opt.optiontext + '</button>';
                    });
                    bodyHtml += '</div>';
                }

                const modal = await Modal.create({
                    title: 'Pergunta',
                    body: bodyHtml,
                    large: true,
                    removeOnClose: true,
                });
                modal.show();

                if (!response.hasquestion) {
                    modal.getRoot().on('click', '#btn-close-modal', function() {
                        modal.hide();
                        modal.destroy();
                        self.isModalOpen = false;
                    });
                } else {
                    modal.getRoot().on('click', '.btn-answer', async function(e) {
                        const optionId = e.currentTarget.getAttribute('data-optid');
                        
                        // Check answer via webservice
                        try {
                            const checkResult = await ajax.call([{
                                methodname: 'mod_playerland_check_answer',
                                args: {
                                    playerlandid: self.gameConfig.id,
                                    questionid: response.questionid,
                                    optionid: optionId
                                }
                            }])[0];
                            
                            modal.hide();
                            modal.destroy();
                            
                            if (checkResult.correct) {
                                // Correct! Destroy block and save progress
                                block.destroy();
                                ajax.call([{
                                    methodname: 'mod_playerland_save_progress',
                                    args: {
                                        playerlandid: self.gameConfig.id,
                                        blocksresolved: 1 // In a real scenario, this would be total blocks resolved
                                    }
                                }]);
                            } else {
                                // Wrong! Just close modal and maybe play an error sound
                            }
                            
                            self.isModalOpen = false;
                        } catch (err) {
                            window.console.error(err);
                            self.isModalOpen = false;
                        }
                    });
                }

                modal.getRoot().on('modal:hidden', function() {
                    if (self.isModalOpen) {
                        self.isModalOpen = false;
                    }
                });

            } catch (err) {
                window.console.error(err);
                this.isModalOpen = false;
            }
        }
        }
    };
});
