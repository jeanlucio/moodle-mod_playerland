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
    'core/modal',
    'core/str'
], function(ajax, Modal, str) {
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
                // Tilemap — created first so the backgrounds can be sized to the real map.
                const map = this.make.tilemap({key: 'map'});
                const tileset = map.addTilesetImage('tileset', 'tileset');
                const layer = map.createLayer('Tile Layer 1', tileset, 0, 0);

                // Parallax backgrounds, stretched to the actual map size.
                this.bgBack = this.add.tileSprite(0, 0, map.widthInPixels, 240, 'bg-back')
                    .setOrigin(0, 0).setScrollFactor(0);
                this.bgBack.setScale(map.heightInPixels / 240);

                this.bgMiddle = this.add.tileSprite(0, 0, map.widthInPixels, 240, 'bg-middle')
                    .setOrigin(0, 0).setScrollFactor(0.3);
                this.bgMiddle.setScale(map.heightInPixels / 240);

                // Keep backgrounds behind the tilemap layer.
                this.bgBack.setDepth(-2);
                this.bgMiddle.setDepth(-1);

                // Collision now travels with the map: any tile flagged collides=true in Tiled is
                // solid. No more hardcoded index lists — add the property in Tiled and it just works.
                layer.setCollisionByProperty({collides: true});

                // Temporary collision debug overlay. Cyan is used (not orange) because the
                // tileset is brown/orange and the default orange overlay is invisible on it.
                const debugGraphics = this.add.graphics().setAlpha(0.7);
                layer.renderDebug(debugGraphics, {
                    tileColor: null,
                    collidingTileColor: new Phaser.Display.Color(0, 220, 255, 180),
                    faceColor: new Phaser.Display.Color(255, 0, 255, 255)
                });

                // Player spawn near the left of the starter map, above the floor (tile ~2, 8).
                // Stored so the player can respawn here.
                this.spawnX = 2 * 16;
                this.spawnY = 8 * 16;
                this.player = this.physics.add.sprite(this.spawnX, this.spawnY, 'atlas', 'player/idle/player-idle-1');

                // Match the original demo's player body: body.setSize(12, 16, 8, 16)
                // Phaser 3 setSize(width, height) + setOffset(x, y)
                this.player.body.setSize(12, 16);
                this.player.body.setOffset(8, 16);

                this.player.setCollideWorldBounds(true);
                this.physics.world.setBounds(0, 0, map.widthInPixels, map.heightInPixels);

                // Keep the left/right/top walls, but disable the bottom wall so the player
                // falls out through real pits (gaps in the floor) instead of resting on the
                // world edge. A death line below the map triggers an automatic respawn.
                this.physics.world.setBoundsCollision(true, true, true, false);
                this.deathY = map.heightInPixels + 64;

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

                // Manual respawn key (R) — escape hatch while testing if the player gets stuck.
                this.respawnKey = this.input.keyboard.addKey(Phaser.Input.Keyboard.KeyCodes.R);

                // Question Blocks
                this.questionBlocks = this.physics.add.staticGroup();

                // Question blocks placed above reachable platforms on the starter map.
                const blockPositions = [
                    {x: 8 * 16, y: 7 * 16},
                    {x: 16 * 16, y: 4 * 16},
                    {x: 24 * 16, y: 7 * 16}
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

                // Respawn on manual key press or after falling past the death line.
                if (Phaser.Input.Keyboard.JustDown(this.respawnKey) || this.player.y > this.deathY) {
                    this.respawn();
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
                    this.player.setVelocityY(-260); // Increased to allow jumping over 3-block walls comfortably
                }

                if (!this.player.body.onFloor()) {
                    this.player.anims.play('player-jump', true);
                }

                // Scroll parallax backgrounds
                this.bgBack.tilePositionX = this.cameras.main.scrollX * 0.1;
                this.bgMiddle.tilePositionX = this.cameras.main.scrollX * 0.3;
            }

            /**
             * Resets the player to the spawn point, clearing any momentum.
             */
            respawn() {
                this.player.setVelocity(0, 0);
                this.player.setPosition(this.spawnX, this.spawnY);
                this.player.anims.play('player-idle', true);
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
                        args: {playerlandid: this.gameConfig.id}
                    }])[0];

                    const strQuestion = await str.get_string('question', 'mod_playerland');
                    let bodyHtml = '';
                    if (!response.hasquestion) {
                        const strNoQuestions = await str.get_string('noquestions', 'mod_playerland');
                        const strClose = await str.get_string('closebuttontitle', 'core');
                        bodyHtml = '<p>' + strNoQuestions + '</p>';
                        bodyHtml += '<button class="btn btn-secondary mt-3" id="btn-close-modal">' + strClose + '</button>';
                    } else {
                        bodyHtml = '<p><strong>' + response.questiontext + '</strong></p>';
                        bodyHtml += '<div class="d-flex flex-column gap-2 mt-3">';
                        response.options.forEach(function(opt) {
                            bodyHtml += '<button class="btn btn-outline-primary btn-answer" data-optid="' + opt.id + '">' +
                                opt.optiontext + '</button>';
                        });
                        bodyHtml += '</div>';
                    }

                    const modal = await Modal.create({
                        title: strQuestion,
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
        };
    };
});
