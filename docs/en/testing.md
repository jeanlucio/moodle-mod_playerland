# 🧪 Automated Tests

PlayerLand ships a PHPUnit suite covering the gradebook logic and the full external (web
service) API. Doc-comment `@covers` annotations are used throughout (not PHP attributes), since
the suite is verified cross-version against both Moodle 5.1 (PHPUnit 11) and Moodle 4.5
(PHPUnit 9).

## PHPUnit — Unit & Integration Tests

| Test file | Cases | What is covered |
|-----------|------:|-----------------|
| `lib_grade_test.php` | 13 | Proportional grade calculation (clamped at the target, floored at zero, defaults a missing target to 1, zero for a non-positive activity grade); the VALUE/SCALE/NONE grade-item paths; `update_grades()` for all users, for a single user with no attempt, and for a single user with one |
| `external/check_answer_test.php` | 5 | Correct answers recorded exactly once (idempotent on a repeat), wrong answers recorded nowhere but still reveal the correct option id, an unknown question id rejected with the dedicated error code, the gradebook updated after a correct answer |
| `external/get_question_test.php` | 5 | The topic→mini-lesson fallback chain: topic-specific unanswered preferred, falling back to any question of that topic before the general pool, falling back to the general pool when the topic has none, option text formatted rather than echoed raw |
| `external/save_progress_test.php` | 5 | Attempt creation on first call, the client-supplied progress count never trusted (always recomputed from the database), completion reported once the target is reached, the `view` capability actually enforced, an unknown instance id rejected |
| `lib_crud_test.php` | 5 | `add_instance`/`update_instance` field persistence and the gradebook item they create; `delete_instance` cascades to questions, options, answers and attempts; deleting an unknown id returns `false` |
| `mod_form_test.php` | 5 | `levels`/`targetquestions` must be positive integers; a mini-lesson over the character limit is rejected while the others are left alone; the limit itself is inclusive |
| `cross_instance_security_test.php` | 3 | A question/option id from one instance is rejected when paired with another instance's `playerlandid`; the topic pool never crosses instance boundaries; answering one instance never marks a question in another as answered |
| `external/dismiss_intro_test.php` | 3 | The first-load overlay preference is set for the calling user, scoped to that user only, and rejects an unknown instance id |
| `uninstall_test.php` | 2 | The uninstall hook deletes only `mod_playerland_`-prefixed `user_preferences` rows, leaving every other plugin's preferences untouched; a no-op run doesn't error |
| `phaser_loading_test.php` | 2 | Structural regression guard: no static `<script>` queues Phaser, `game.js` loads it dynamically instead |
| `lib_supports_test.php` | 1 | Every declared feature flag, including an unrecognised feature returning `null` |
| **Total** | **49** | |

```bash
vendor/bin/phpunit --bootstrap lib/phpunit/bootstrap.php mod/playerland/tests
```

## Coverage

Measured locally with Xdebug (`moodle-coverage`, a bench tool — not part of CI). The tool's
default scope is `classes/` plus the plugin's top-level `lib.php`/`db/upgrade.php`:

| | Coverage |
|---|---|
| Classes | 0% (0/4 fully covered) |
| Methods | 61.11% (22/36) |
| Lines | 54.95% (294/535) |

The aggregate looks low mostly because of **what this round intentionally didn't touch yet**,
not because the tested code is weak:

* **`classes/external.php`** (the web services the game actually calls) is the strongest file:
  98.46% lines, 94.12% methods (16/17). The only gap is `dismiss_intro_returns()`, which stays
  untouched because the `dismiss_intro` tests call the method directly rather than through the
  full `call_external_function()` dispatch that `save_progress`/`get_question`/`check_answer`
  use — and which is what actually exercises each method's own `_returns()` casting step.
* **`lib.php`** sits at 96.23% lines. Its two methods below the strict 100% threshold are
  `grade_item_update()` (89.66% lines — the `'reset'` grades pathway and the optional
  `gradepass` parameter are the two branches no current test exercises) and `update_grades()`
  (96%, one line — the whole-activity, zero-attempts path for `userid=0` on a positively-graded
  instance nobody has played yet).
* **Three classes have no coverage at all today, flagged here rather than hidden:**
  `classes/form/question_form.php` (the question-authoring form), `classes/privacy/provider.php`
  (the entire Privacy API — export/deletion of personal data), and
  `classes/event/course_module_viewed.php` (the standard view-tracking event). None were in
  scope for this round, which focused on `lib.php` and the external API; closing
  `privacy/provider.php` in particular is the natural next target given it handles personal data.
* **`db/upgrade.php`** (0/55 lines) is the historical schema-migration script. Per project
  convention it targets a specific pre-upgrade schema state rather than the fresh schema PHPUnit
  installs, so it is not unit-tested directly.
