# 🧪 Automated Tests

PlayerLand ships a PHPUnit suite covering the gradebook logic, the full external (web service)
API, the Privacy API, the question-authoring form and the view-tracking event. Doc-comment
`@covers` annotations are used throughout (not PHP attributes), since the suite is verified
cross-version against both Moodle 5.1 (PHPUnit 11) and Moodle 4.5 (PHPUnit 9).

## PHPUnit — Unit & Integration Tests

| Test file | Cases | What is covered |
|-----------|------:|-----------------|
| `privacy/provider_test.php` | 23 | Metadata declaration plus a drift guard asserting every declared table column matches the real schema; contexts, userlist, export across single/multiple contexts, and all three deletion paths, each checked against a non-module context, an orphaned (deleted) course module, and an empty user list, plus a module-type-collision regression guard |
| `lib_grade_test.php` | 16 | Proportional grade calculation (clamped at the target, floored at zero, defaults a missing target to 1, zero for a non-positive activity grade); the VALUE/SCALE/NONE grade-item paths; a configured pass grade actually reaching the grade item; `'reset'` clearing recorded grades without deleting the item; `update_grades()` for all users (with and without any attempts yet), for a single user with no attempt, and for a single user with one |
| `external/check_answer_test.php` | 5 | Correct answers recorded exactly once (idempotent on a repeat), wrong answers recorded nowhere but still reveal the correct option id, an unknown question id rejected with the dedicated error code, the gradebook updated after a correct answer |
| `external/get_question_test.php` | 5 | The topic→mini-lesson fallback chain: topic-specific unanswered preferred, falling back to any question of that topic before the general pool, falling back to the general pool when the topic has none, option text formatted rather than echoed raw |
| `external/save_progress_test.php` | 5 | Attempt creation on first call, the client-supplied progress count never trusted (always recomputed from the database), completion reported once the target is reached, the `view` capability actually enforced, an unknown instance id rejected |
| `lib_crud_test.php` | 5 | `add_instance`/`update_instance` field persistence and the gradebook item they create; `delete_instance` cascades to questions, options, answers and attempts; deleting an unknown id returns `false` |
| `mod_form_test.php` | 5 | `levels`/`targetquestions` must be positive integers; a mini-lesson over the character limit is rejected while the others are left alone; the limit itself is inclusive |
| `event/course_module_viewed_test.php` | 4 | The declared crud/edulevel/objecttable/component, the inherited `get_url()` pointing at this module's own `view.php`, a non-empty name/description, and the event actually being observable once triggered |
| `cross_instance_security_test.php` | 3 | A question/option id from one instance is rejected when paired with another instance's `playerlandid`; the topic pool never crosses instance boundaries; answering one instance never marks a question in another as answered |
| `external/dismiss_intro_test.php` | 3 | The first-load overlay preference is set for the calling user, scoped to that user only, and rejects an unknown instance id |
| `form/question_form_test.php` | 3 | The one piece of server-side logic on this form — a missing or zero correct-option selection is rejected, any selected option passes |
| `uninstall_test.php` | 2 | The uninstall hook deletes only `mod_playerland_`-prefixed `user_preferences` rows, leaving every other plugin's preferences untouched; a no-op run doesn't error |
| `phaser_loading_test.php` | 2 | Structural regression guard: no static `<script>` queues Phaser, `game.js` loads it dynamically instead |
| `lib_supports_test.php` | 1 | Every declared feature flag, including an unrecognised feature returning `null` |
| **Total** | **82** | |

```bash
vendor/bin/phpunit --bootstrap lib/phpunit/bootstrap.php mod/playerland/tests
```

## Coverage

Measured locally with Xdebug (`moodle-coverage`, a bench tool — not part of CI). The tool's
default scope is `classes/` plus the plugin's top-level `lib.php`/`db/upgrade.php`:

| | Coverage |
|---|---|
| Classes | 75% (3/4 fully covered) |
| Methods | 96.30% (26/27) |
| Lines | 89.01% (486/546) |

* **`lib.php` is now at 100% lines and methods.** Closing it surfaced a real bug, not just a
  test gap: `playerland_grade_item_update()` was passing `gradepass` through to core's
  `grade_update()`, which silently ignores that key — its own internal allow-list
  (`lib/gradelib.php`) only lets `itemname`/`idnumber`/`gradetype`/`grademax`/`grademin`/
  `scaleid`/`multfactor`/`plusfactor`/`deleted`/`hidden` through. A configured pass grade was
  never actually reaching the gradebook. Fixed by applying it directly on the `grade_item`
  object afterwards (the same pattern `mod_workshop` uses), and covered by a test asserting the
  pass grade actually lands on the item, plus one for the `'reset'` pathway (clears recorded
  grades without deleting the item) and one for the whole-activity, zero-attempts path.
* **`classes/privacy/provider.php`** is at **100% lines and methods (7/7)**, including three
  non-module-context guards, an orphaned-course-module guard, and an empty-user-list guard that
  a plain happy-path test would never reach.
* **`classes/form/question_form.php`** and **`classes/event/course_module_viewed.php`** are also
  at **100%** — both are small enough that their entire real logic (one validation rule; one
  `init()` setting three properties) is covered outright.
* **`classes/external.php`** (the web services the game actually calls) sits at 98.46% lines,
  94.12% methods (16/17). The only gap is `dismiss_intro_returns()`, which stays untouched
  because the `dismiss_intro` tests call the method directly rather than through the full
  `call_external_function()` dispatch that `save_progress`/`get_question`/`check_answer` use —
  and which is what actually exercises each method's own `_returns()` casting step.
* **`db/upgrade.php`** (0/55 lines) is the historical schema-migration script. Per project
  convention it targets a specific pre-upgrade schema state rather than the fresh schema PHPUnit
  installs, so it is not unit-tested directly — the one remaining reason the aggregate line
  coverage above sits below the 100% every individual class now reaches.
