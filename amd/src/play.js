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

    // Movement and hazard tuning. Kept in one place so the game feel can be tweaked
    // without hunting through the scene body.
    const TUNING = {
        runSpeed: 150,
        jumpVelocity: -260,
        dashSpeed: 300,
        dashDuration: 220,
        dashCooldown: 550,
        climbSpeed: 90,
        wallSlideSpeed: 55,
        wallJumpX: 210,
        wallJumpY: -250,
        wallJumpLock: 160,
        crouchSpeed: 55,
        hardLandVelocity: 470,
        dizzyDuration: 750,
        invulnerableMs: 1300,
        hurtRecoverMs: 800,
        signRange: 44,
        eagleDiveRange: 110,
        eagleDiveSpeed: 300,
        eagleDiveCooldown: 2400
    };

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
                this.blocksResolved = Number(data.gameConfig.blocksresolved || 0);
                this.targetQuestions = Math.max(1, Number(data.gameConfig.targetquestions || 1));
                this.exitUnlocked = this.blocksResolved >= this.targetQuestions;
                this.exitNotice = null;
                this.invulnerableUntil = 0;
                this.isDying = false;
                this.isDizzy = false;
                this.isClimbing = false;
                this.isCrouching = false;
                this.isWallSliding = false;
                this.dashUntil = 0;
                this.dashReadyAt = 0;
                this.dashDir = 1;
                this.inputLockUntil = 0;
                this.prevVelocityY = 0;
                this.ladders = [];
                this.signs = [];
                this.activeSign = null;
            }

            create() {
                // Tilemap — created first so the backgrounds can be sized to the real map.
                const map = this.make.tilemap({key: 'map'});
                const tileset = map.addTilesetImage('tileset', 'tileset');
                const layer = map.createLayer('Tile Layer 1', tileset, 0, 0);
                this.layer = layer; // Stored for enemy edge detection in update().

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

                // Collision travels with the map: any tile flagged collides=true in Tiled is
                // solid. A second optional property, oneway=true, makes the tile block only
                // from above so the player can jump up through it (wooden beams).
                layer.setCollisionByProperty({collides: true});
                layer.forEachTile(tile => {
                    if (tile.properties && tile.properties.oneway) {
                        tile.setCollision(false, false, true, false);
                    }
                });

                // Player spawn near the left of the map, above the floor. Stored so the player
                // can respawn here; checkpoints move it forward during play.
                this.spawnX = 2 * 16;
                this.spawnY = 8 * 16;
                const spawnObj = this.findObject(map, obj => (obj.type || obj.name) === 'spawn');
                if (spawnObj) {
                    this.spawnX = spawnObj.x;
                    this.spawnY = spawnObj.y;
                }
                this.player = this.physics.add.sprite(this.spawnX, this.spawnY, 'atlas', 'player/idle/player-idle-1');

                // Standing body. Crouching swaps to a shorter body (see applyCrouchBody()).
                this.standBody = {width: 12, height: 16, offsetX: 8, offsetY: 16};
                this.crouchBody = {width: 12, height: 10, offsetX: 8, offsetY: 22};
                this.applyStandBody();

                this.player.setCollideWorldBounds(true);
                this.physics.world.setBounds(0, 0, map.widthInPixels, map.heightInPixels);

                // Keep the left/right/top walls, but disable the bottom wall so the player
                // falls out through real pits. A death line below the map respawns.
                this.physics.world.setBoundsCollision(true, true, true, false);
                this.deathY = map.heightInPixels + 64;

                this.physics.add.collider(this.player, layer);

                // Camera
                this.cameras.main.setBounds(0, 0, map.widthInPixels, map.heightInPixels);
                this.cameras.main.startFollow(this.player, true, 0.08, 0.08);
                this.cameras.main.setZoom(2);

                // Inputs
                this.cursors = this.input.keyboard.createCursorKeys();
                this.wasd = {
                    up: this.input.keyboard.addKey(Phaser.Input.Keyboard.KeyCodes.W),
                    left: this.input.keyboard.addKey(Phaser.Input.Keyboard.KeyCodes.A),
                    down: this.input.keyboard.addKey(Phaser.Input.Keyboard.KeyCodes.S),
                    right: this.input.keyboard.addKey(Phaser.Input.Keyboard.KeyCodes.D)
                };
                this.dashKey = this.input.keyboard.addKey(Phaser.Input.Keyboard.KeyCodes.SHIFT);
                this.respawnKey = this.input.keyboard.addKey(Phaser.Input.Keyboard.KeyCodes.R);
                this.input.keyboard.addCapture('SPACE,SHIFT');
                this.startKey = this.input.keyboard.addKey(Phaser.Input.Keyboard.KeyCodes.ENTER);

                // Groups populated from the "objects" layer.
                this.questionBlocks = this.physics.add.staticGroup();
                this.collectibles = this.physics.add.group({allowGravity: false});
                this.enemies = this.physics.add.group();
                this.hazards = this.physics.add.staticGroup();
                this.movingPlatforms = this.physics.add.group({allowGravity: false, immovable: true});
                this.crumbleBlocks = this.physics.add.staticGroup();
                this.crates = this.physics.add.group();
                this.doors = this.physics.add.staticGroup();
                this.cranks = [];
                this.checkpoints = this.physics.add.staticGroup();

                this.buildObjects(map);

                // Collisions and overlaps.
                this.physics.add.collider(this.player, this.questionBlocks, this.hitQuestionBlock, null, this);
                this.physics.add.collider(this.enemies, layer);
                this.physics.add.collider(this.enemies, this.crumbleBlocks);
                this.physics.add.collider(this.player, this.movingPlatforms);
                this.physics.add.collider(this.enemies, this.movingPlatforms);
                this.physics.add.collider(this.player, this.crumbleBlocks, this.touchCrumble, null, this);
                this.physics.add.collider(this.crates, layer);
                this.physics.add.collider(this.crates, this.movingPlatforms);
                this.physics.add.collider(this.crates, this.crates);
                this.physics.add.collider(this.player, this.crates);
                this.physics.add.collider(this.enemies, this.crates);
                this.physics.add.collider(this.player, this.doors);
                this.physics.add.overlap(this.player, this.collectibles, this.collectItem, null, this);
                this.physics.add.overlap(this.player, this.enemies, this.hitEnemy, null, this);
                this.physics.add.overlap(this.player, this.hazards, this.hitHazard, null, this);
                this.physics.add.overlap(this.player, this.checkpoints, this.reachCheckpoint, null, this);

                // Brief grace period so a nearby enemy cannot kill the idle player.
                this.invulnerableUntil = this.time.now + 1500;
                if (this.exit) {
                    this.physics.add.overlap(this.player, this.exit, this.reachExit, null, this);
                }

                this.buildHud();
                this.startMusic();
            }

            /**
             * Returns the first Tiled object across every object layer matching the predicate.
             *
             * @param {Phaser.Tilemaps.Tilemap} map The loaded tilemap.
             * @param {Function} predicate Test called with each object.
             * @return {object|null} The matching object or null.
             */
            findObject(map, predicate) {
                let found = null;
                map.objects.forEach(objectLayer => {
                    objectLayer.objects.forEach(obj => {
                        if (!found && predicate(obj)) {
                            found = obj;
                        }
                    });
                });
                return found;
            }

            /**
             * Reads a custom property from a Tiled object (Tiled 1.10 array form).
             *
             * @param {object} obj The Tiled object.
             * @param {string} name Property name.
             * @param {*} fallback Value returned when the property is absent.
             * @return {*} The property value or the fallback.
             */
            objectProp(obj, name, fallback) {
                if (obj.properties && Array.isArray(obj.properties)) {
                    const match = obj.properties.find(p => p.name === name);
                    if (match) {
                        return match.value;
                    }
                } else if (obj.properties && typeof obj.properties === 'object') {
                    if (obj.properties[name] !== undefined) {
                        return obj.properties[name];
                    }
                }
                return fallback;
            }

            /**
             * Instantiates every game element from the Tiled "objects" layer.
             *
             * @param {Phaser.Tilemaps.Tilemap} map The loaded tilemap.
             */
            buildObjects(map) {
                const objects = map.getObjectLayer('objects');
                if (!objects) {
                    return;
                }

                const handlers = {
                    cherry: o => this.createCollectible(o, 'cherry'),
                    gem: o => this.createCollectible(o, 'gem'),
                    question: o => this.createQuestionBlock(o),
                    opossum: o => this.spawnEnemy(o.x, o.y, 'opossum'),
                    eagle: o => this.spawnEnemy(o.x, o.y, 'eagle'),
                    frog: o => this.spawnEnemy(o.x, o.y, 'frog'),
                    exit: o => this.createExit(o),
                    spike: o => this.createSpike(o),
                    spikes: o => this.createSpike(o),
                    platform: o => this.createMovingPlatform(o),
                    crumble: o => this.createCrumbleBlock(o),
                    crate: o => this.createCrate(o),
                    ladder: o => this.createLadder(o),
                    door: o => this.createDoor(o),
                    crank: o => this.createCrank(o),
                    checkpoint: o => this.createCheckpoint(o),
                    sign: o => this.createSign(o),
                    decor: o => this.createDecor(o)
                };

                objects.objects.forEach(obj => {
                    const handler = handlers[obj.type || obj.name];
                    if (handler) {
                        handler(obj);
                    }
                });
            }

            /**
             * Animated cherry or gem collectible.
             *
             * @param {object} obj The Tiled object.
             * @param {string} kind Either 'cherry' or 'gem'.
             */
            createCollectible(obj, kind) {
                const item = this.collectibles.create(obj.x, obj.y, 'atlas');
                item.setData('points', kind === 'gem' ? 50 : 10);
                item.anims.play(kind, true);
            }

            /**
             * A question block, hit from below to open the Moodle question modal.
             *
             * @param {object} obj The Tiled object.
             */
            createQuestionBlock(obj) {
                const block = this.questionBlocks.create(obj.x, obj.y, 'question_block');
                block.setScale(0.5);
                block.refreshBody();
            }

            /**
             * The burrow / exit flag that completes the level.
             *
             * @param {object} obj The Tiled object.
             */
            createExit(obj) {
                this.exit = this.physics.add.staticImage(obj.x, obj.y, 'exit_flag');
                this.exit.setOrigin(0.5, 1).refreshBody();
            }

            /**
             * A purely decorative prop (tree, house, bush...).
             *
             * @param {object} obj The Tiled object.
             */
            createDecor(obj) {
                this.add.image(obj.x, obj.y, 'props', this.objectProp(obj, 'frame', 'bush'))
                    .setOrigin(0.5, 1).setDepth(-1);
            }

            /**
             * Spike hazard. Point marker in Tiled; dir=down hangs it from a ceiling.
             *
             * @param {object} obj The Tiled object.
             */
            createSpike(obj) {
                const down = this.objectProp(obj, 'dir', 'up') === 'down';
                const frame = down ? 'spikes-top' : 'spikes';
                const spike = this.hazards.create(obj.x, obj.y, 'props', frame);
                spike.setOrigin(0.5, down ? 0 : 1);
                spike.refreshBody();
            }

            /**
             * Horizontal or vertical moving platform driven by body velocity so the
             * arcade physics carries the rider.
             *
             * @param {object} obj The Tiled object.
             */
            createMovingPlatform(obj) {
                const platform = this.movingPlatforms.create(obj.x, obj.y, 'props', 'platform-long');
                // Top origin so the marker Y is the ride surface, flush with a tile edge.
                platform.setOrigin(0.5, 0);
                platform.body.setAllowGravity(false);
                platform.body.setImmovable(true);
                platform.setData('originX', obj.x);
                platform.setData('originY', obj.y);
                platform.setData('rangeX', Number(this.objectProp(obj, 'dx', 0)));
                platform.setData('rangeY', Number(this.objectProp(obj, 'dy', 0)));
                platform.setData('speed', Number(this.objectProp(obj, 'speed', 40)));
                platform.setData('phase', 1);
            }

            /**
             * Platform that shakes and drops shortly after the player stands on it,
             * then restores itself so the level stays playable after a respawn.
             *
             * @param {object} obj The Tiled object.
             */
            createCrumbleBlock(obj) {
                const block = this.crumbleBlocks.create(obj.x, obj.y, 'props', 'face-block');
                block.setOrigin(0.5, 0.5).refreshBody();
                block.setData('restX', obj.x);
                block.setData('restY', obj.y);
                block.setData('falling', false);
            }

            /**
             * Pushable crate. Uses the arcade "pushable" body so walking into it moves it.
             *
             * @param {object} obj The Tiled object.
             */
            createCrate(obj) {
                const big = this.objectProp(obj, 'size', 'small') === 'big';
                const crate = this.crates.create(obj.x, obj.y, 'props', big ? 'big-crate' : 'crate');
                crate.setOrigin(0.5, 0.5);
                crate.body.setAllowGravity(true);
                crate.setDragX(900);
                crate.setMaxVelocity(120, 600);
                crate.setPushable(true);
                crate.setCollideWorldBounds(true);
            }

            /**
             * Climbable ladder zone. Rectangle marker in Tiled.
             *
             * @param {object} obj The Tiled object.
             */
            createLadder(obj) {
                const width = obj.width || 16;
                const height = obj.height || 16;
                // The climbable zone. The vine tiles painted in the tile layer are the
                // visual; this rectangle is what handleClimb() tests against.
                this.ladders.push(new Phaser.Geom.Rectangle(obj.x, obj.y, width, height));
            }

            /**
             * A gate that blocks the path until its crank is pulled.
             *
             * @param {object} obj The Tiled object.
             */
            createDoor(obj) {
                const door = this.doors.create(obj.x, obj.y, 'props', 'door');
                door.setOrigin(0.5, 1).refreshBody();
                door.setData('name', this.objectProp(obj, 'name', 'door'));
                door.setData('open', false);
            }

            /**
             * A lever the player pulls (hold UP while overlapping) to open a target door.
             *
             * @param {object} obj The Tiled object.
             */
            createCrank(obj) {
                const crank = this.add.image(obj.x, obj.y, 'props', 'crank-down').setOrigin(0.5, 1);
                crank.setData('target', this.objectProp(obj, 'target', 'door'));
                crank.setData('pulled', false);
                crank.zone = new Phaser.Geom.Rectangle(obj.x - 14, obj.y - 24, 28, 26);
                this.cranks.push(crank);
            }

            /**
             * Moves the respawn point forward. Rendered as a sign post.
             *
             * @param {object} obj The Tiled object.
             */
            createCheckpoint(obj) {
                const flag = this.checkpoints.create(obj.x, obj.y, 'props', 'sign');
                flag.setOrigin(0.5, 1).refreshBody();
                flag.setData('claimed', false);
                flag.setData('spawnX', obj.x);
                flag.setData('spawnY', obj.y - 20);
            }

            /**
             * Readable sign. Shows its text while the player stands close.
             *
             * @param {object} obj The Tiled object.
             */
            createSign(obj) {
                const post = this.add.image(obj.x, obj.y, 'props', 'sign').setOrigin(0.5, 1).setDepth(1);
                this.signs.push({
                    x: obj.x,
                    y: obj.y,
                    post: post,
                    text: String(this.objectProp(obj, 'text', ''))
                });
            }

            /**
             * Builds the score / question HUD and resolves its localised strings.
             */
            buildHud() {
                const style = {
                    fontFamily: 'monospace',
                    fontSize: '10px',
                    color: '#ffffff',
                    stroke: '#000000',
                    strokeThickness: 3
                };
                this.scoreText = this.add.text(6, 6, '', style).setScrollFactor(0).setDepth(20);
                this.questionsText = this.add.text(6, 22, '', style).setScrollFactor(0).setDepth(20);

                str.get_strings([
                    {key: 'score', component: 'mod_playerland'},
                    {key: 'questionsprogress', component: 'mod_playerland'},
                    {key: 'exitlocked', component: 'mod_playerland'},
                    {key: 'exitunlocked', component: 'mod_playerland'},
                    {key: 'levelcomplete', component: 'mod_playerland'},
                    {key: 'pressenter', component: 'mod_playerland'}
                ]).then(([strScore, strProgress, strLocked, strUnlocked, strComplete, strEnter]) => {
                    this.strScore = strScore;
                    this.strQuestionsProgress = strProgress;
                    this.strExitLocked = strLocked;
                    this.strExitUnlocked = strUnlocked;
                    this.strLevelComplete = strComplete;
                    this.strPressEnter = strEnter;
                    this.updateScore();
                    this.updateQuestionProgress();
                    return null;
                }).catch(Notification.exception);
            }

            /**
             * Starts the background music loop, respecting the browser autoplay policy.
             */
            startMusic() {
                if (this.cache.audio.exists('music')) {
                    this.music = this.sound.add('music', {loop: true, volume: 0.35});
                    this.input.once('pointerdown', () => {
                        if (this.music && !this.music.isPlaying) {
                            this.music.play();
                        }
                    });
                    try {
                        this.music.play();
                    } catch (e) {
                        window.console.warn('[PlayerLand] music deferred to first interaction');
                    }
                }
            }

            /**
             * Refreshes the score HUD using the localised template.
             */
            updateScore() {
                if (this.strScore) {
                    this.scoreText.setText(this.strScore.replace('{$a}', this.score));
                }
            }

            /**
             * Refreshes the question progress HUD.
             */
            updateQuestionProgress() {
                if (!this.strQuestionsProgress) {
                    return;
                }
                const progress = this.strQuestionsProgress
                    .replace('{$a->resolved}', Math.min(this.blocksResolved, this.targetQuestions))
                    .replace('{$a->target}', this.targetQuestions);
                this.questionsText.setText(progress);
            }

            /**
             * Applies progress returned by the Moodle web service.
             *
             * @param {object} result The web service response.
             */
            applyProgress(result) {
                this.blocksResolved = Number(result.blocksresolved || this.blocksResolved);
                this.targetQuestions = Math.max(1, Number(result.targetquestions || this.targetQuestions));
                this.exitUnlocked = Boolean(result.complete);
                this.updateQuestionProgress();

                if (this.exitUnlocked && this.strExitUnlocked) {
                    this.showExitNotice(this.strExitUnlocked, 0x3d7a2a);
                }
            }

            /**
             * Shows a short world-space notice near the exit flag or the player.
             *
             * @param {string} message The message to display.
             * @param {number} color The Phaser text colour.
             */
            showExitNotice(message, color) {
                if (this.exitNotice) {
                    this.exitNotice.destroy();
                }
                const anchor = this.exit || this.player;
                this.exitNotice = this.add.text(anchor.x, anchor.y - 42, message, {
                    fontFamily: 'monospace',
                    fontSize: '10px',
                    color: '#ffffff',
                    backgroundColor: Phaser.Display.Color.IntegerToColor(color).rgba,
                    padding: {x: 6, y: 4},
                    align: 'center'
                }).setOrigin(0.5).setDepth(30);

                this.time.delayedCall(1800, () => {
                    if (this.exitNotice) {
                        this.exitNotice.destroy();
                        this.exitNotice = null;
                    }
                });
            }

            /**
             * Plays a celebratory bump and sparkle on a question block when answered correctly.
             *
             * @param {Phaser.GameObjects.Sprite} block The question block.
             */
            celebrateBlock(block) {
                const restY = block.y;
                this.tweens.add({
                    targets: block,
                    y: restY - 8,
                    duration: 110,
                    yoyo: true,
                    ease: 'Quad.easeOut',
                    onComplete: () => block.setY(restY)
                });
                const sparkle = this.add.sprite(block.x, restY - 12, 'atlas').setDepth(10);
                sparkle.anims.play('item-feedback');
                sparkle.once('animationcomplete', () => sparkle.destroy());
            }

            /**
             * Switches the player physics body to its standing size.
             */
            applyStandBody() {
                this.player.body.setSize(this.standBody.width, this.standBody.height);
                this.player.body.setOffset(this.standBody.offsetX, this.standBody.offsetY);
            }

            /**
             * Switches the player physics body to its shorter crouching size.
             */
            applyCrouchBody() {
                this.player.body.setSize(this.crouchBody.width, this.crouchBody.height);
                this.player.body.setOffset(this.crouchBody.offsetX, this.crouchBody.offsetY);
            }

            update(time) {
                // Level finished: wait for ENTER to restart, ignore all other input.
                if (this.levelComplete) {
                    if (Phaser.Input.Keyboard.JustDown(this.startKey)) {
                        this.scene.restart({gameConfig: this.gameConfig});
                    }
                    return;
                }

                this.updateMovingPlatforms();

                if (this.isModalOpen) {
                    this.player.setVelocityX(0);
                    this.player.anims.play('player-idle', true);
                    return;
                }

                this.updateEnemies();
                this.updateSigns();

                // During the hurt or dizzy reaction the player is not controllable.
                if (this.isDying || this.isDizzy) {
                    this.prevVelocityY = this.player.body.velocity.y;
                    return;
                }

                if (Phaser.Input.Keyboard.JustDown(this.respawnKey) || this.player.y > this.deathY) {
                    this.respawn();
                    return;
                }

                this.detectHardLanding();

                if (this.tryPullCrank()) {
                    this.player.setVelocityX(0);
                    this.player.anims.play('player-idle', true);
                    this.prevVelocityY = this.player.body.velocity.y;
                    return;
                }

                if (this.handleClimb()) {
                    this.prevVelocityY = this.player.body.velocity.y;
                    return;
                }

                if (this.handleDash(time)) {
                    this.prevVelocityY = this.player.body.velocity.y;
                    return;
                }

                this.handleGroundAndAir(time);

                // Scroll parallax backgrounds.
                this.bgBack.tilePositionX = this.cameras.main.scrollX * 0.1;
                this.bgMiddle.tilePositionX = this.cameras.main.scrollX * 0.3;
                this.prevVelocityY = this.player.body.velocity.y;
            }

            /**
             * Records a heavy fall so the next landing can trigger the dizzy stagger.
             */
            detectHardLanding() {
                const landed = this.player.body.onFloor() && this.prevVelocityY > TUNING.hardLandVelocity;
                if (landed) {
                    this.enterDizzy();
                }
            }

            /**
             * Brief loss of control after a hard landing: the fox spins and staggers.
             */
            enterDizzy() {
                if (this.isDizzy || this.isDying) {
                    return;
                }
                this.isDizzy = true;
                this.player.setVelocityX(0);
                this.player.anims.play('player-hurt', true);
                this.tweens.add({
                    targets: this.player,
                    angle: {from: -12, to: 12},
                    duration: 90,
                    yoyo: true,
                    repeat: Math.floor(TUNING.dizzyDuration / 180)
                });
                this.time.delayedCall(TUNING.dizzyDuration, () => {
                    this.isDizzy = false;
                    this.player.setAngle(0);
                });
            }

            /**
             * Advances every moving platform along its configured path.
             */
            updateMovingPlatforms() {
                this.movingPlatforms.getChildren().forEach(platform => {
                    const rangeX = platform.getData('rangeX');
                    const rangeY = platform.getData('rangeY');
                    const speed = platform.getData('speed');
                    let phase = platform.getData('phase');

                    if (rangeX !== 0) {
                        const min = platform.getData('originX');
                        const max = min + rangeX;
                        if (platform.x >= max) {
                            phase = -1;
                        } else if (platform.x <= min) {
                            phase = 1;
                        }
                        platform.setVelocityX(speed * phase);
                    }
                    if (rangeY !== 0) {
                        const min = platform.getData('originY');
                        const max = min + rangeY;
                        if (platform.y >= max) {
                            phase = -1;
                        } else if (platform.y <= min) {
                            phase = 1;
                        }
                        platform.setVelocityY(speed * phase);
                    }
                    platform.setData('phase', phase);
                });
            }

            /**
             * Shows the sign text while the player is close to a sign.
             */
            updateSigns() {
                let near = null;
                this.signs.forEach(sign => {
                    const dist = Phaser.Math.Distance.Between(sign.x, sign.y, this.player.x, this.player.y);
                    if (dist < TUNING.signRange) {
                        near = sign;
                    }
                });

                if (near === this.activeSign) {
                    return;
                }
                if (this.signBubble) {
                    this.signBubble.destroy();
                    this.signBubble = null;
                }
                this.activeSign = near;
                if (near && near.text) {
                    this.signBubble = this.add.text(near.x, near.y - 34, near.text, {
                        fontFamily: 'monospace',
                        fontSize: '9px',
                        color: '#ffffff',
                        backgroundColor: '#00000099',
                        padding: {x: 5, y: 3},
                        align: 'center',
                        wordWrap: {width: 160}
                    }).setOrigin(0.5, 1).setDepth(30);
                }
            }

            /**
             * Handles ladder climbing. Returns true while the player is on a ladder.
             *
             * @return {boolean} Whether climb movement took over this frame.
             */
            handleClimb() {
                const onLadder = this.ladders.some(rect =>
                    Phaser.Geom.Rectangle.Contains(rect, this.player.x, this.player.body.center.y));

                const up = this.cursors.up.isDown || this.wasd.up.isDown;
                const down = this.cursors.down.isDown || this.wasd.down.isDown;

                if (!this.isClimbing && onLadder && (up || down)) {
                    this.isClimbing = true;
                    this.player.body.setAllowGravity(false);
                }

                if (!this.isClimbing) {
                    return false;
                }

                if (!onLadder || this.cursors.space.isDown) {
                    this.exitClimb();
                    if (this.cursors.space.isDown) {
                        this.player.setVelocityY(TUNING.jumpVelocity * 0.7);
                    }
                    return false;
                }

                let vy = 0;
                if (up) {
                    vy = -TUNING.climbSpeed;
                } else if (down) {
                    vy = TUNING.climbSpeed;
                }
                this.player.setVelocityY(vy);

                let vx = 0;
                if (this.cursors.left.isDown || this.wasd.left.isDown) {
                    vx = -TUNING.climbSpeed * 0.6;
                    this.player.setFlipX(true);
                } else if (this.cursors.right.isDown || this.wasd.right.isDown) {
                    vx = TUNING.climbSpeed * 0.6;
                    this.player.setFlipX(false);
                }
                this.player.setVelocityX(vx);

                if (vy !== 0 || vx !== 0) {
                    this.player.anims.play('player-climb', true);
                } else {
                    this.player.anims.pause();
                }
                return true;
            }

            /**
             * Leaves the climbing state, restoring gravity.
             */
            exitClimb() {
                this.isClimbing = false;
                this.player.body.setAllowGravity(true);
                this.player.anims.resume();
            }

            /**
             * Handles the ground roll (dash). Returns true while the dash is active.
             *
             * @param {number} time The scene time in ms.
             * @return {boolean} Whether the dash took over this frame.
             */
            handleDash(time) {
                const canStart = time > this.dashReadyAt
                    && this.player.body.onFloor()
                    && Phaser.Input.Keyboard.JustDown(this.dashKey);

                if (canStart) {
                    this.dashDir = this.player.flipX ? -1 : 1;
                    this.dashUntil = time + TUNING.dashDuration;
                    this.dashReadyAt = time + TUNING.dashCooldown;
                    this.applyCrouchBody();
                }

                if (time > this.dashUntil) {
                    if (this.isCrouching === 'dash') {
                        this.isCrouching = false;
                        this.applyStandBody();
                    }
                    return false;
                }

                this.isCrouching = 'dash';
                this.player.setVelocityX(TUNING.dashSpeed * this.dashDir);
                this.player.setFlipX(this.dashDir < 0);
                this.player.anims.play('player-crouch', true);
                return true;
            }

            /**
             * Reads the current directional input into a small state object.
             *
             * @return {object} Flags for left, right, down and a fresh jump press.
             */
            readInput() {
                return {
                    left: this.cursors.left.isDown || this.wasd.left.isDown,
                    right: this.cursors.right.isDown || this.wasd.right.isDown,
                    down: this.cursors.down.isDown || this.wasd.down.isDown,
                    jump: Phaser.Input.Keyboard.JustDown(this.cursors.space)
                };
            }

            /**
             * Wall slide and wall jump. Returns true when a wall jump fired this frame.
             *
             * @param {object} input The directional input state.
             * @param {number} time The scene time in ms.
             * @return {boolean} Whether a wall jump consumed this frame.
             */
            handleWall(input, time) {
                const onFloor = this.player.body.onFloor();
                const againstLeft = this.player.body.blocked.left && input.left;
                const againstRight = this.player.body.blocked.right && input.right;
                this.isWallSliding = !onFloor && (againstLeft || againstRight)
                    && this.player.body.velocity.y > 0;

                if (!this.isWallSliding) {
                    return false;
                }
                this.player.setVelocityY(Math.min(this.player.body.velocity.y, TUNING.wallSlideSpeed));
                if (!input.jump) {
                    return false;
                }
                const away = againstLeft ? 1 : -1;
                this.player.setVelocity(TUNING.wallJumpX * away, TUNING.wallJumpY);
                this.player.setFlipX(away < 0);
                this.inputLockUntil = time + TUNING.wallJumpLock;
                return true;
            }

            /**
             * Updates the crouch state and swaps the physics body to match.
             *
             * @param {object} input The directional input state.
             */
            updateCrouch(input) {
                this.isCrouching = this.player.body.onFloor() && input.down;
                if (this.isCrouching) {
                    this.applyCrouchBody();
                } else if (this.player.body.height !== this.standBody.height) {
                    this.applyStandBody();
                }
            }

            /**
             * Standard ground/air control: run, jump, crouch, crawl, wall-slide, wall-jump.
             *
             * @param {number} time The scene time in ms.
             */
            handleGroundAndAir(time) {
                const input = this.readInput();
                const inputLocked = time < this.inputLockUntil;

                if (this.handleWall(input, time)) {
                    return;
                }
                this.updateCrouch(input);

                const onFloor = this.player.body.onFloor();
                const moveSpeed = this.isCrouching ? TUNING.crouchSpeed : TUNING.runSpeed;

                if (!inputLocked && input.left) {
                    this.player.setVelocityX(-moveSpeed);
                    this.player.setFlipX(true);
                } else if (!inputLocked && input.right) {
                    this.player.setVelocityX(moveSpeed);
                    this.player.setFlipX(false);
                } else if (!inputLocked) {
                    this.player.setVelocityX(0);
                }

                if (input.jump && onFloor && !this.isCrouching) {
                    this.player.setVelocityY(TUNING.jumpVelocity);
                }
                this.animatePlayer(onFloor, input.left || input.right);
            }

            /**
             * Chooses the player animation for the current movement state.
             *
             * @param {boolean} onFloor Whether the player is standing on ground.
             * @param {boolean} moving Whether a horizontal direction is held.
             */
            animatePlayer(onFloor, moving) {
                if (this.isWallSliding) {
                    this.player.anims.play('player-crouch', true);
                } else if (!onFloor) {
                    this.player.anims.play('player-jump', true);
                } else if (this.isCrouching) {
                    this.player.anims.play('player-crouch', true);
                } else if (moving) {
                    this.player.anims.play('player-run', true);
                } else {
                    this.player.anims.play('player-idle', true);
                }
            }

            /**
             * Resets the player to the current spawn point, clearing any momentum.
             */
            respawn() {
                if (this.isClimbing) {
                    this.exitClimb();
                }
                this.applyStandBody();
                this.player.setAngle(0);
                this.player.setVelocity(0, 0);
                this.player.setPosition(this.spawnX, this.spawnY);
                this.player.anims.play('player-idle', true);
                this.invulnerableUntil = this.time.now + TUNING.invulnerableMs;
                this.player.setAlpha(0.5);
                this.tweens.add({
                    targets: this.player,
                    alpha: 1,
                    duration: TUNING.invulnerableMs,
                    onComplete: () => this.player.setAlpha(1)
                });
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
             * Claims a checkpoint, moving the respawn point forward.
             *
             * @param {Phaser.GameObjects.Sprite} player The player sprite.
             * @param {Phaser.GameObjects.Sprite} flag The checkpoint sprite.
             */
            reachCheckpoint(player, flag) {
                if (flag.getData('claimed')) {
                    return;
                }
                flag.setData('claimed', true);
                flag.setTint(0x8ad06a);
                this.spawnX = flag.getData('spawnX');
                this.spawnY = flag.getData('spawnY');
                const sparkle = this.add.sprite(flag.x, flag.y - 16, 'atlas').setDepth(10);
                sparkle.anims.play('item-feedback');
                sparkle.once('animationcomplete', () => sparkle.destroy());
            }

            /**
             * Starts the crumble timer the first time the player lands on the block.
             *
             * @param {Phaser.GameObjects.Sprite} player The player sprite.
             * @param {Phaser.GameObjects.Sprite} block The crumble block.
             */
            touchCrumble(player, block) {
                const standingOn = player.body.bottom <= block.body.top + 4 && player.body.velocity.y >= 0;
                if (!standingOn || block.getData('falling')) {
                    return;
                }
                block.setData('falling', true);
                this.tweens.add({
                    targets: block,
                    x: block.getData('restX') + Phaser.Math.Between(-1, 1),
                    duration: 45,
                    yoyo: true,
                    repeat: 8
                });
                this.time.delayedCall(600, () => {
                    block.body.enable = false;
                    this.tweens.add({targets: block, alpha: 0, y: block.y + 24, duration: 250});
                });
                this.time.delayedCall(2600, () => {
                    block.setPosition(block.getData('restX'), block.getData('restY'));
                    block.setAlpha(1);
                    block.body.enable = true;
                    block.setData('falling', false);
                    block.refreshBody();
                });
            }

            /**
             * Reached the exit flag: completes the level once the quota is met.
             */
            reachExit() {
                if (this.levelComplete) {
                    return;
                }
                if (!this.exitUnlocked) {
                    const remaining = Math.max(0, this.targetQuestions - this.blocksResolved);
                    const message = this.strExitLocked ? this.strExitLocked.replace('{$a}', remaining) : '';
                    if (message) {
                        this.showExitNotice(message, 0x8a5a2b);
                    }
                    this.player.setVelocityX(-80);
                    return;
                }

                this.levelComplete = true;
                this.physics.pause();
                this.player.anims.play('player-idle', true);

                const view = this.cameras.main.worldView;
                const message = this.strLevelComplete + '\n' +
                    this.strScore.replace('{$a}', this.score) + '\n' +
                    this.strPressEnter;
                this.add.text(view.centerX, view.centerY, message, {
                    fontFamily: 'monospace',
                    fontSize: '12px',
                    color: '#ffffff',
                    stroke: '#000000',
                    strokeThickness: 4,
                    align: 'center'
                }).setOrigin(0.5).setDepth(20);
            }

            /**
             * Creates a patrolling or dynamic enemy at the given position.
             *
             * @param {number} x World x of the spawn marker.
             * @param {number} y World y of the spawn marker.
             * @param {string} kind Type of the enemy (opossum, eagle, frog).
             */
            spawnEnemy(x, y, kind = 'opossum') {
                const enemy = this.enemies.create(x, y, 'atlas', `${kind}/${kind}-1`);
                enemy.setData('kind', kind);
                enemy.setData('alive', true);
                enemy.setCollideWorldBounds(true);

                if (kind === 'eagle') {
                    enemy.setData('speed', 60);
                    enemy.setData('patrolY', y);
                    enemy.setData('diveReadyAt', 0);
                    enemy.setData('diving', false);
                    enemy.body.allowGravity = false;
                    enemy.anims.play('eagle-attack', true);
                } else if (kind === 'frog') {
                    enemy.setData('speed', 0);
                    enemy.setData('jumpTimer', this.time.now + Math.random() * 2000);
                    enemy.anims.play('frog-idle', true);
                } else {
                    enemy.setData('speed', 50);
                    enemy.setData('homeX', x);
                    enemy.setData('leash', 88);
                    enemy.anims.play('opossum-walk', true);
                }
                enemy.setVelocityX(-enemy.getData('speed'));
            }

            /**
             * Moves the enemies based on their kind.
             */
            updateEnemies() {
                this.enemies.getChildren().forEach(enemy => {
                    if (!enemy.getData('alive')) {
                        return;
                    }
                    const kind = enemy.getData('kind');
                    if (kind === 'eagle') {
                        this.updateEagle(enemy);
                    } else if (kind === 'frog') {
                        this.updateFrog(enemy);
                    } else {
                        this.updateGroundEnemy(enemy);
                    }
                });
            }

            /**
             * Eagle: patrols horizontally, then dives when the player passes underneath.
             *
             * @param {Phaser.GameObjects.Sprite} enemy The eagle sprite.
             */
            updateEagle(enemy) {
                const speed = enemy.getData('speed');
                const patrolY = enemy.getData('patrolY');

                if (enemy.getData('diving')) {
                    if (enemy.body.onFloor() || enemy.body.blocked.down || enemy.y > patrolY + 260) {
                        enemy.setData('diving', false);
                    } else {
                        return;
                    }
                }

                // Return to patrol height.
                if (Math.abs(enemy.y - patrolY) > 2) {
                    enemy.setVelocityY((patrolY - enemy.y) * 3);
                } else {
                    enemy.setVelocityY(0);
                    enemy.y = patrolY;
                }

                let dir = enemy.body.velocity.x < 0 ? -1 : 1;
                if (enemy.body.blocked.left) {
                    dir = 1;
                } else if (enemy.body.blocked.right) {
                    dir = -1;
                }
                enemy.setVelocityX(dir * speed);
                enemy.setFlipX(dir > 0);

                const dx = Math.abs(enemy.x - this.player.x);
                const below = this.player.y > enemy.y;
                if (dx < TUNING.eagleDiveRange && below && this.time.now > enemy.getData('diveReadyAt')) {
                    enemy.setData('diving', true);
                    enemy.setData('diveReadyAt', this.time.now + TUNING.eagleDiveCooldown);
                    enemy.setVelocity((this.player.x - enemy.x) * 0.6, TUNING.eagleDiveSpeed);
                }
            }

            /**
             * Frog: idle on the ground, hops toward the player when close.
             *
             * @param {Phaser.GameObjects.Sprite} enemy The frog sprite.
             */
            updateFrog(enemy) {
                if (!enemy.body.onFloor()) {
                    return;
                }
                enemy.setVelocityX(0);
                enemy.anims.play('frog-idle', true);
                const dist = Phaser.Math.Distance.Between(enemy.x, enemy.y, this.player.x, this.player.y);
                if (dist < 150 && this.time.now > enemy.getData('jumpTimer')) {
                    enemy.setVelocityY(-250);
                    const jumpDir = this.player.x < enemy.x ? -1 : 1;
                    enemy.setVelocityX(jumpDir * 80);
                    enemy.setFlipX(jumpDir > 0);
                    enemy.anims.play('frog-jump', true);
                    enemy.setData('jumpTimer', this.time.now + 1500 + Math.random() * 1000);
                }
            }

            /**
             * Opossum: walks, turns at walls and at platform/pit edges.
             *
             * @param {Phaser.GameObjects.Sprite} enemy The opossum sprite.
             */
            updateGroundEnemy(enemy) {
                const speed = enemy.getData('speed');
                const homeX = enemy.getData('homeX');
                const leash = enemy.getData('leash');
                let dir = enemy.body.velocity.x < 0 ? -1 : 1;

                if (enemy.body.blocked.left || (dir < 0 && enemy.x < homeX - leash)) {
                    dir = 1;
                } else if (enemy.body.blocked.right || (dir > 0 && enemy.x > homeX + leash)) {
                    dir = -1;
                } else {
                    const aheadX = enemy.x + dir * (enemy.body.halfWidth + 2);
                    const belowY = enemy.body.bottom + 2;
                    if (!this.layer.getTileAtWorldXY(aheadX, belowY)) {
                        dir = -dir;
                    }
                }
                enemy.setVelocityX(dir * speed);
                enemy.setFlipX(dir > 0);
            }

            /**
             * Resolves a player/enemy overlap: stomp from above kills the enemy, a side
             * touch sends the player into a hurt reaction.
             *
             * @param {Phaser.GameObjects.Sprite} player The player sprite.
             * @param {Phaser.GameObjects.Sprite} enemy The enemy sprite.
             */
            hitEnemy(player, enemy) {
                if (!enemy.getData('alive') || this.levelComplete || this.time.now < this.invulnerableUntil) {
                    return;
                }
                const stomped = player.body.velocity.y > 0 && player.body.bottom <= enemy.body.top + 10;
                if (stomped) {
                    this.killEnemy(enemy);
                    player.setVelocityY(-220);
                } else {
                    this.playerHurt(enemy.x);
                }
            }

            /**
             * Resolves a player/hazard overlap (spikes): always a hurt reaction.
             *
             * @param {Phaser.GameObjects.Sprite} player The player sprite.
             * @param {Phaser.GameObjects.Sprite} hazard The hazard sprite.
             */
            hitHazard(player, hazard) {
                if (this.levelComplete || this.time.now < this.invulnerableUntil) {
                    return;
                }
                this.playerHurt(hazard.x);
            }

            /**
             * Plays a short hurt reaction (knockback + animation + pause) before respawning.
             *
             * @param {number} sourceX World x of whatever hit the player.
             */
            playerHurt(sourceX) {
                if (this.isDying) {
                    return;
                }
                this.isDying = true;
                if (this.isClimbing) {
                    this.exitClimb();
                }
                const dir = this.player.x < sourceX ? -1 : 1;
                this.player.setVelocity(dir * 60, -160);
                this.player.setTint(0xff6666);
                this.player.anims.play('player-hurt', true);

                this.time.delayedCall(TUNING.hurtRecoverMs, () => {
                    this.player.clearTint();
                    this.respawn();
                    this.isDying = false;
                });
            }

            /**
             * Kills an enemy: stops it, plays the death animation and removes it.
             *
             * @param {Phaser.GameObjects.Sprite} enemy The enemy sprite.
             */
            killEnemy(enemy) {
                enemy.setData('alive', false);
                enemy.setVelocity(0, 0);
                enemy.body.enable = false;
                enemy.anims.play('enemy-death');
                enemy.once('animationcomplete', () => enemy.destroy());
            }

            async hitQuestionBlock(player, block) {
                if (!(player.body.blocked.up || player.body.touching.up)) {
                    return;
                }
                if (this.isModalOpen || this.isDying || block.getData('used')) {
                    return;
                }
                this.isModalOpen = true;
                player.setVelocityY(50);
                this.celebrateBlock(block);

                const self = this;
                const strings = await str.get_strings([
                    {key: 'question', component: 'mod_playerland'},
                    {key: 'noquestions', component: 'mod_playerland'},
                    {key: 'answercorrect', component: 'mod_playerland'},
                    {key: 'answerincorrect', component: 'mod_playerland'},
                    {key: 'continue', component: 'core'}
                ]);
                const [strQuestion, strNoQuestions, strCorrect, strIncorrect, strContinue] = strings;

                await new Promise(resolve => self.time.delayedCall(150, resolve));

                this.physics.pause();
                this.anims.pauseAll();

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
                    root.find('.modal-dialog').addClass('modal-dialog-centered');

                    let answeredCorrectly = false;

                    const finish = () => {
                        if (answeredCorrectly) {
                            block.setData('used', true);
                            block.setTint(0x8a5a2b);
                        }
                        self.physics.resume();
                        self.anims.resumeAll();
                        modal.hide();
                        modal.destroy();
                        self.isModalOpen = false;
                    };

                    if (!response.hasquestion) {
                        root.on('click', '#pl-close', () => {
                            self.physics.resume();
                            self.anims.resumeAll();
                            modal.hide();
                            modal.destroy();
                            self.isModalOpen = false;
                        });
                        root.on('modal:hidden', () => {
                            self.physics.resume();
                            self.anims.resumeAll();
                            self.isModalOpen = false;
                        });
                        return;
                    }

                    root.on('click', '.btn-answer', async function(e) {
                        const optionId = e.currentTarget.getAttribute('data-optid');
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
                            answeredCorrectly = true;
                            chosen.removeClass('btn-outline-primary').addClass('btn-success text-white');
                            feedbackHtml = '<div class="alert alert-success mb-0">' + strCorrect + '</div>';
                            self.applyProgress(checkResult);
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
                            self.physics.resume();
                            self.anims.resumeAll();
                            self.isModalOpen = false;
                        }
                    });

                } catch (err) {
                    this.physics.resume();
                    this.anims.resumeAll();
                    this.isModalOpen = false;
                    Notification.exception(err);
                }
            }

            /**
             * Pulls a crank the player is standing under while holding up, opening its door.
             *
             * @return {boolean} Whether a crank was pulled this frame.
             */
            tryPullCrank() {
                const up = this.cursors.up.isDown || this.wasd.up.isDown;
                if (!up) {
                    return false;
                }
                let pulledAny = false;
                this.cranks.forEach(crank => {
                    if (crank.getData('pulled')) {
                        return;
                    }
                    if (!Phaser.Geom.Rectangle.Contains(crank.zone, this.player.x, this.player.body.center.y)) {
                        return;
                    }
                    crank.setData('pulled', true);
                    crank.setTexture('props', 'crank-up');
                    this.openDoor(crank.getData('target'));
                    pulledAny = true;
                });
                return pulledAny;
            }

            /**
             * Opens every door matching the given name.
             *
             * @param {string} name The door name a crank targets.
             */
            openDoor(name) {
                this.doors.getChildren().forEach(door => {
                    if (door.getData('open') || door.getData('name') !== name) {
                        return;
                    }
                    door.setData('open', true);
                    this.tweens.add({
                        targets: door,
                        y: door.y - door.height - 4,
                        alpha: 0.15,
                        duration: 400,
                        ease: 'Quad.easeIn',
                        onComplete: () => {
                            door.body.enable = false;
                        }
                    });
                    const sparkle = this.add.sprite(door.x, door.y - 12, 'atlas').setDepth(10);
                    sparkle.anims.play('item-feedback');
                    sparkle.once('animationcomplete', () => sparkle.destroy());
                });
            }
        };
    };
});
