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
    'core/notification',
    'core/str'
], function(ajax, Modal, Notification, str) {
    'use strict';

    return function() {
        return class PlayScene extends Phaser.Scene {
            constructor() {
                super('PlayScene');
            }

            init(data) {
                this.gameConfig = data.gameConfig;
                this.isModalOpen = false;
                this.score = 0;
                this.levelComplete = false;
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

                // Capture SPACE so jumping does not scroll the page.
                this.input.keyboard.addCapture('SPACE');

                // ENTER restarts the level after completion.
                this.startKey = this.input.keyboard.addKey(Phaser.Input.Keyboard.KeyCodes.ENTER);

                // Groups populated from the "Objetos" layer below.
                this.questionBlocks = this.physics.add.staticGroup();
                this.collectibles = this.physics.add.group({allowGravity: false});

                // Read the "Objetos" layer from Tiled: cherries, gems, question blocks and exit.
                // The level designer places markers in Tiled; the behaviour lives here in code.
                const objects = map.getObjectLayer('Objetos');
                if (objects) {
                    objects.objects.forEach(obj => {
                        const kind = obj.type || obj.name;
                        if (kind === 'cherry' || kind === 'gem') {
                            const item = this.collectibles.create(obj.x, obj.y, 'atlas');
                            item.setData('points', kind === 'gem' ? 50 : 10);
                            item.anims.play(kind, true);
                        } else if (kind === 'question') {
                            const block = this.questionBlocks.create(obj.x, obj.y, 'question_block');
                            block.setScale(0.5);
                            block.refreshBody();
                        } else if (kind === 'exit') {
                            // Flag stands on the floor: anchor its bottom to the marker point.
                            this.exit = this.physics.add.staticImage(obj.x, obj.y, 'exit_flag');
                            this.exit.setOrigin(0.5, 1).refreshBody();
                        }
                    });
                }

                this.physics.add.collider(this.player, this.questionBlocks, this.hitQuestionBlock, null, this);
                this.physics.add.overlap(this.player, this.collectibles, this.collectItem, null, this);
                if (this.exit) {
                    this.physics.add.overlap(this.player, this.exit, this.reachExit, null, this);
                }

                // Score HUD, fixed to the camera (immune to scroll). Text is filled once the
                // language strings resolve.
                this.scoreText = this.add.text(6, 6, '', {
                    fontFamily: 'monospace',
                    fontSize: '10px',
                    color: '#ffffff',
                    stroke: '#000000',
                    strokeThickness: 3
                }).setScrollFactor(0).setDepth(20);

                str.get_strings([
                    {key: 'score', component: 'mod_playerland'},
                    {key: 'levelcomplete', component: 'mod_playerland'},
                    {key: 'pressenter', component: 'mod_playerland'}
                ]).then(([strScore, strLevelComplete, strPressEnter]) => {
                    this.strScore = strScore;
                    this.strLevelComplete = strLevelComplete;
                    this.strPressEnter = strPressEnter;
                    this.updateScore();
                    return null;
                }).catch(Notification.exception);
            }

            /**
             * Refreshes the score HUD using the localised template.
             */
            updateScore() {
                if (this.strScore) {
                    this.scoreText.setText(this.strScore.replace('{$a}', this.score));
                }
            }

            update() {
                // Level finished: wait for ENTER to restart, ignore all other input.
                if (this.levelComplete) {
                    if (Phaser.Input.Keyboard.JustDown(this.startKey)) {
                        this.scene.restart({gameConfig: this.gameConfig});
                    }
                    return;
                }

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
                const isJump = cursors.space.isDown;

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

                if (isJump && this.player.body.onFloor()) {
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

            /**
             * Collects a cherry or gem: adds points, plays a sparkle and removes the item.
             *
             * @param {Phaser.GameObjects.Sprite} player The player sprite.
             * @param {Phaser.GameObjects.Sprite} item The collectible touched.
             */
            collectItem(player, item) {
                this.score += item.getData('points');
                this.updateScore();

                const sparkle = this.add.sprite(item.x, item.y, 'atlas').setDepth(10);
                sparkle.anims.play('item-feedback');
                sparkle.once('animationcomplete', () => sparkle.destroy());

                item.destroy();
            }

            /**
             * Reached the exit flag: completes the level once.
             */
            reachExit() {
                if (this.levelComplete) {
                    return;
                }
                this.levelComplete = true;
                this.physics.pause();
                this.player.anims.play('player-idle', true);

                // Centre the message on whatever the camera is currently showing (world coords),
                // which is reliable regardless of camera zoom.
                const view = this.cameras.main.worldView;
                const message = this.strLevelComplete + '\n' +
                    this.strScore.replace('{$a}', this.score) + '\n' +
                    this.strPressEnter;
                this.add.text(
                    view.centerX,
                    view.centerY,
                    message,
                    {
                        fontFamily: 'monospace',
                        fontSize: '12px',
                        color: '#ffffff',
                        stroke: '#000000',
                        strokeThickness: 4,
                        align: 'center'
                    }
                ).setOrigin(0.5).setDepth(20);
            }

            async hitQuestionBlock(player, block) {
                // Only trigger when hit from below (the player is blocked upward against the
                // static block) and only once per block.
                if (!(player.body.blocked.up || player.body.touching.up)) {
                    return;
                }
                if (this.isModalOpen || block.getData('used')) {
                    return;
                }
                this.isModalOpen = true;
                player.setVelocityY(50); // Small bounce down.

                const self = this;
                const strings = await str.get_strings([
                    {key: 'question', component: 'mod_playerland'},
                    {key: 'noquestions', component: 'mod_playerland'},
                    {key: 'answercorrect', component: 'mod_playerland'},
                    {key: 'answerincorrect', component: 'mod_playerland'},
                    {key: 'continue', component: 'core'}
                ]);
                const [strQuestion, strNoQuestions, strCorrect, strIncorrect, strContinue] = strings;

                try {
                    const response = await ajax.call([{
                        methodname: 'mod_playerland_get_question',
                        args: {playerlandid: this.gameConfig.id}
                    }])[0];

                    let bodyHtml;
                    if (!response.hasquestion) {
                        bodyHtml = '<p>' + strNoQuestions + '</p>' +
                            '<button type="button" class="btn btn-secondary mt-2" id="pl-close">' +
                            strContinue + '</button>';
                    } else {
                        bodyHtml = '<p><strong>' + response.questiontext + '</strong></p>' +
                            '<div class="d-flex flex-column gap-2 mt-3" id="pl-answers">';
                        response.options.forEach(opt => {
                            bodyHtml += '<button type="button" class="btn btn-outline-primary btn-answer" ' +
                                'data-optid="' + opt.id + '">' + opt.optiontext + '</button>';
                        });
                        bodyHtml += '</div><div id="pl-feedback" class="mt-3"></div>';
                    }

                    const modal = await Modal.create({
                        title: strQuestion,
                        body: bodyHtml,
                        large: true,
                        removeOnClose: true,
                    });
                    modal.show();
                    const root = modal.getRoot();

                    // Spend the block (stays on the map, turns brown) and close the modal.
                    const finish = () => {
                        block.setData('used', true);
                        block.setTint(0x8a5a2b);
                        modal.hide();
                        modal.destroy();
                        self.isModalOpen = false;
                    };

                    // No questions configured: close without spending the block.
                    if (!response.hasquestion) {
                        root.on('click', '#pl-close', () => {
                            modal.hide();
                            modal.destroy();
                            self.isModalOpen = false;
                        });
                        root.on('modal:hidden', () => {
                            self.isModalOpen = false;
                        });
                        return;
                    }

                    root.on('click', '.btn-answer', async function(e) {
                        const optionId = e.currentTarget.getAttribute('data-optid');
                        // Lock answers so the question can only be attempted once.
                        root.find('.btn-answer').prop('disabled', true);

                        const checkResult = await ajax.call([{
                            methodname: 'mod_playerland_check_answer',
                            args: {
                                playerlandid: self.gameConfig.id,
                                questionid: response.questionid,
                                optionid: optionId
                            }
                        }])[0];

                        const chosen = root.find('[data-optid="' + optionId + '"]');
                        let feedbackHtml;
                        if (checkResult.correct) {
                            chosen.removeClass('btn-outline-primary').addClass('btn-success text-white');
                            feedbackHtml = '<div class="alert alert-success mb-0">' + strCorrect + '</div>';
                            ajax.call([{
                                methodname: 'mod_playerland_save_progress',
                                args: {playerlandid: self.gameConfig.id, blocksresolved: 1}
                            }]);
                        } else {
                            chosen.removeClass('btn-outline-primary').addClass('btn-danger text-white');
                            if (checkResult.correctoptionid) {
                                root.find('[data-optid="' + checkResult.correctoptionid + '"]')
                                    .removeClass('btn-outline-primary').addClass('btn-success text-white');
                            }
                            feedbackHtml = '<div class="alert alert-danger mb-0">' + strIncorrect + '</div>';
                        }

                        root.find('#pl-feedback').html(
                            feedbackHtml +
                            '<button type="button" class="btn btn-primary mt-3 w-100" id="pl-continue">' +
                            strContinue + '</button>'
                        );
                    });

                    root.on('click', '#pl-continue', finish);
                    root.on('modal:hidden', () => {
                        if (self.isModalOpen) {
                            self.isModalOpen = false;
                        }
                    });

                } catch (err) {
                    this.isModalOpen = false;
                    Notification.exception(err);
                }
            }
        };
    };
});
