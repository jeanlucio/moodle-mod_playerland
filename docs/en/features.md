# ✨ Features

## ✅ Implemented

* 🦊 **Platformer Gameplay:** Huddy the fox runs, jumps, falls and collides with a tile-based
  level built in Phaser 3. Camera follows the player; terrain and collision come from a Tiled
  map, tile by tile.
* 🦘 **Variable Jump Height:** Tapping the jump key is a short hop; holding it is the full jump —
  releasing early while still rising cuts the upward velocity in half. See
  [Gameplay & Movement](#gameplay).
* 🏃 **Full Movement Set:** Dash/roll (`Shift`), crouch (`Down`), climbing on vines/ladders,
  wall-slide and wall-jump — each introduced in the specific level that first needs it, not all
  at once.
* 🧱 **Level Hazards & Props:** Spikes, moving platforms, crumble blocks, pushable crates,
  ladders, one-way ledges, cranked doors and checkpoints — all placed as Tiled object markers,
  no code changes required.
* 🦫 **Three Enemy Types:** A leashed, patrolling **opossum** (stomp from above to defeat, a side
  touch hurts); an **eagle** that patrols and dives at the player when directly below; a **frog**
  that hops toward the player once it gets close. Falling into a pit or touching a hazard
  respawns the player automatically.
* ❓ **Question Blocks (yellow "?"):** Hit from below to open a Moodle-native `core/modal` dialog
  with a random question from the activity's own question bank. Correct/incorrect feedback is
  immediate; answered blocks dim permanently and count toward the exit quota.
* 📘 **Mini-Lesson Blocks (blue "!"):** Up to three short, plain-text explanations per activity,
  each shown by its own in-game block. Unlike question blocks, a lesson block is never "spent" —
  the student can re-read it any time. See [Blocks & Mini-Lessons](#questions).
* 🔗 **Topic-Linked Questions:** A question can be tied to a specific mini-lesson (1–3) so the
  question block right after a lesson draws from that topic first, with a four-tier fallback
  chain that guarantees a block is never left without a question. See
  [Blocks & Mini-Lessons](#questions).
* 🍒 **Collectibles:** Cherries and gems, tracked with a live counter in the HUD.
* ⛶ **Fullscreen Support:** A button and the `F` key toggle fullscreen on the game container.
* 👋 **First-Load Controls Overlay:** A dismissible "How to play" dialog shown once per student,
  remembered through a Moodle user preference (not `localStorage`), so it follows the student
  across devices instead of reappearing on every new browser.
* 🧩 **Internal Question Bank:** Teachers manage multiple-choice questions per activity through a
  dedicated **Manage questions** screen — no Moodle Question Bank integration yet, see "Planned"
  below.
* 🎓 **Proportional Grading:** The activity grade scales with the number of distinct correct
  answers against a teacher-configured target, computed server-side and never trusted from the
  client.
* 🗺️ **Level Authoring Toolchain:** Levels are built from a small Python DSL
  (`tools/levelkit.py` + one `tools/levelNNN.py` module per level, dev-only, not shipped in the
  release package) that compiles ASCII room layouts into Tiled JSON — no hand-editing tile grids.
* 🌍 **Bilingual:** English and Brazilian Portuguese language packs.
* 🧪 **Automated Tests:** A PHPUnit suite covering the gradebook logic and the full external API,
  green on both Moodle 5.1 (PHPUnit 11) and Moodle 4.5 (PHPUnit 9) — see
  [Automated Tests](#testing).

## ⏳ In Development / Planned

* 🗺️ **Ten Playable Levels:** The release goal is **one activity per level** — the teacher picks
  a map from a dropdown and builds the sequence by adding activities to the course in order (no
  in-plugin campaign wrapper, no unlock state). Levels 1 and 9 exist today (the latter still
  labeled "draft"); levels 2–8 are still to be designed and built.
* 🐲 **Boss Level (Level 10):** An "Eagle Nest" boss reusing the existing eagle sprite at a
  larger scale — three stomps to defeat, summoning opossums when hit. Designed to need zero new
  art.
* 🎨 **Enemy Variant System:** Turning the three base enemies into roughly eight or nine by
  combining a tint with one behavior parameter (speed, leash range, stompability) — designed, not
  implemented in `play.js` yet.
* 🔄 **Practice Question Block (blue "?"):** A rechargeable question block on a cooldown timer,
  reusing the existing random-draw logic.
* 🟢 **Reward Question Block (green "?"):** Grants an item on a correct answer — depends on the
  economy decision below.
* ⏱️ **Optional Timer / Practice Mode:** An internal accessibility recommendation (no timer, no
  hazard pressure) for students affected by time pressure or motor-skill demands — not
  implemented yet, see [Accessibility](#accessibility).
* 💰 **PlayerHUD/PlayerCoins Integration:** Letting PlayerLand grant PlayerCoins/items through
  `local_playergames` instead of building its own economy — an architecture recommendation, not
  a committed decision yet.
* 📚 **Moodle Question Bank Integration:** Today's internal per-activity bank is a deliberate v1
  scope choice; drawing from the course's own Question Bank is a post-v1 idea.
* 💾 **Backup & Restore:** `FEATURE_BACKUP_MOODLE2` is currently declared `false`, honestly,
  until the real implementation lands.
* 🏔️ **A Second Visual Biome:** Alternating a grassland and a ruins look (already sketched in
  Level 9) across the ten levels, from the same base tileset.

<p class="page-hint">The plugin is Alpha-stage software: everything in "Implemented" above works
today; everything in "Planned" is designed (see the project's internal roadmap) but not yet
built.</p>
