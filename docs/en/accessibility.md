# ♿ Accessibility

## What's Implemented Today

* ⌨️ **Keyboard-only play:** Every action — move, jump, dash, crouch, climb, fullscreen — has a
  keyboard binding. No mouse is required to play a level.
* 🗨️ **Accessible dialogs:** The question challenge and the mini-lesson text both use Moodle's
  own `core/modal` component rather than a hand-rolled overlay — focus trapping, `aria-modal` and
  ESC-to-close all come from core.
* 👋 **The first-load "How to play" overlay** is a real `role="dialog" aria-modal="true"
  aria-labelledby="..."` element, and its dismiss button receives programmatic focus when it
  appears.

## A Known, Honest Limitation

The platforming itself — player position, score, hazards, enemy movement — is rendered on a
`<canvas>` element and is **not** exposed to assistive technology today. This is a limitation
shared by most browser-based platformers, and a materially harder problem than mirroring a
turn-based board: a moving, real-time platformer has no established accessible-parallel-layer
design the way a static Match-3 board does. No such layer exists for PlayerLand yet, and it is
not currently designed — flagged here honestly rather than silently left unmentioned.

The in-game HUD (cherry/gem counters, the fullscreen button) is drawn as Phaser text/graphics
objects, not real DOM elements, so none of it carries an `aria-label` or is reachable via `Tab`.

## Planned

* ⏱️ An optional **practice mode** (no timer, reduced hazard pressure) is recommended internally
  for students affected by time pressure, anxiety or motor-skill demands — not implemented yet,
  see [Features](#features).

`speechSynthesis` is not used by the plugin.
