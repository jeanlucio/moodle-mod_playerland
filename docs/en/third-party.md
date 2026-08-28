# 🔎 Third-party Service Disclosure

**Not applicable today.** PlayerLand does not call any external service — every question comes
from the activity's own internal question bank (see [Blocks & Mini-Lessons](#questions)), and no
network request leaves the server as part of gameplay. No AI feature exists in the plugin.

The one bundled third-party **library** (not a service) is [Phaser](https://phaser.io/), the
game engine — declared in `thirdpartylibs.xml` and loaded dynamically from the plugin's own
`javascript/` folder, never from a CDN.

## Planned

Drawing questions from Moodle's own Question Bank instead of the internal per-activity one is a
post-v1 idea — see [Features](#features). It would not introduce any external service either,
since the Question Bank is core Moodle data.
