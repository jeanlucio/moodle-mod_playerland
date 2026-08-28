# 🦊 Gameplay & Movement

## Movement Feel

Every movement number lives in one `TUNING` object in `amd/src/play.js`, tuned and approved
against real playtesting before any level was built on top of it:

* **Run** at a fixed speed, with smooth camera follow.
* **Jump** is height-variable: releasing the key while still rising multiplies the upward
  velocity by a jump-cut factor, so a tap is roughly a half-height hop and holding gives the full
  ~4-tile jump. This applies to both the normal jump and the wall-jump.
* **Dash/roll** (`Shift`) is a fixed-duration horizontal burst with its own cooldown.
* **Crouch** (`Down`) lowers the hitbox and slows movement, needed for a handful of low
  passages.
* **Climb** works on vine/ladder tiles, moving the player up and down at a fixed climb speed
  independent of gravity.
* **Wall-slide and wall-jump** trigger against a solid wall while airborne: sliding down at a
  capped speed, and jumping away from the wall with a brief input lock so the jump actually
  clears the wall instead of re-grabbing it.

Advanced verbs (dash, crouch, climb, wall-jump) are **not** all taught at once — each is
introduced in the first level that actually needs it, with a sign at the exact point it becomes
relevant.

## Terrain, Hazards & Props

All of the following are placed as point markers on a level's Tiled object layer — adding one
never requires touching `play.js`:

| Marker | Behavior |
|--------|----------|
| Spikes (up/down-facing) | Hurts the player on contact |
| Moving platform (horizontal/vertical) | Carries the player along a fixed path |
| Crumble block | Breaks a short moment after being stood on |
| Crate | Pushable, blocks enemies and hazards like solid terrain |
| Ladder | Climbable vertical tile run |
| One-way ledge | Solid from above only, lets the player pass through from below |
| Crank + door | Pulling a crank opens its linked door |
| Checkpoint | Moves the respawn point forward |
| Sign | Shows a short hint line when the player is close, no interaction key needed |

Falling into a pit, or getting hit by a hazard/enemy, respawns the player automatically at the
last checkpoint; a manual respawn key is available in case the player gets stuck.

## Enemies

| Enemy | Behavior |
|-------|----------|
| 🦡 Opossum | Patrols back and forth within a leash range of its spawn point, turning at walls and platform edges. Stomp from above to defeat; a side touch hurts the player. |
| 🦅 Eagle | Patrols along a fixed line and dives at speed when the player passes directly below, within range and off cooldown. |
| 🐸 Frog | Idles until the player gets close, then hops toward them. |

## Level Authoring

A PlayerLand level is a Tiled JSON map (tile layers plus an `objects` layer of point markers)
generated from a small Python level-authoring toolchain kept in `tools/` (development-only, not
shipped in the release package, excluded from the plugin ZIP via `.gitattributes`):

* `tools/levelkit.py` — the shared builder: ASCII "rooms" (one string per row) stitched side by
  side, tile characters mapped to actual tile GIDs, marker characters mapped to game objects with
  their properties.
* `tools/levelNNN.py` — one small module per level, describing its rooms and markers in plain
  ASCII, then calling into `levelkit` to emit `assets/maps/map_levelNNN.json`.
* `tools/build_all.py` — regenerates every shipped map from its `levelNNN.py` module in one pass.

This keeps level design reviewable as plain text (a room is just a handful of short strings) and
removes the risk of hand-aligning a Tiled tile grid pixel by pixel. A teacher installing the
plugin only ever picks a finished map from the activity settings dropdown — no Tiled editor
needed for that. A third party wanting a fully custom map is still free to hand-author one
directly in [Tiled](https://www.mapeditor.org/), since the shipped format is plain Tiled JSON.
