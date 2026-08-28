# 🕹️ PlayerGames Ecosystem

PlayerLand stars **Huddy** the fox and is part of the
**[PlayerGames](https://jeanlucio.github.io/playergames/)** gamification ecosystem for Moodle.
None of the plugins below are required — PlayerLand works entirely standalone today — but a
PlayerHUD economy integration is a planned direction, see [Features](#features).

* **PlayerGames:** Central hub of the ecosystem — site-wide XP, seasons, daily mini-games, and
  the Ecosystem Dashboard that ties every installed Player plugin together.
  👉 [github.com/jeanlucio/moodle-local_playergames](https://github.com/jeanlucio/moodle-local_playergames)

* **PlayerHUD Block:** XP, levels, inventory, drops, quests, RPG classes and ranking inside each
  course. A future PlayerLand release may grant coins/items through it instead of building its
  own economy — see [Features](#features).
  👉 [github.com/jeanlucio/moodle-block_playerhud](https://github.com/jeanlucio/moodle-block_playerhud)

* **PlayerPuzzle:** A sibling activity module (turn-based Match-3 RPG) sharing PlayerLand's
  dynamic-script-loading pattern for bundling a third-party game engine (Phaser) without a static
  `<script>` tag race.
  👉 [github.com/jeanlucio/moodle-mod_playerpuzzle](https://github.com/jeanlucio/moodle-mod_playerpuzzle)

* **PlayerWords:** A sibling activity module (word-guessing) in the same ecosystem, using the
  Moodle user-preferences pattern PlayerLand's own first-load overlay follows for cross-device
  state.
  👉 [github.com/jeanlucio/moodle-mod_playerwords](https://github.com/jeanlucio/moodle-mod_playerwords)

* **PlayerGroup:** Lets students autonomously form their own groups directly from the activity
  page — no teacher intervention needed.
  👉 [github.com/jeanlucio/moodle-mod_playergroup](https://github.com/jeanlucio/moodle-mod_playergroup)
