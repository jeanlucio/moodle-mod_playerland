# 🔐 Security

## Server as the Source of Truth

Every web service call re-validates the activity instance, its context and the caller's
capability (`mod/playerland:view`) before doing anything else — a helper
(`get_validated_instance()`) is the single entry point every external function goes through, so
no code path skips it. Progress reported back to the client (distinct correct answers, whether
the exit quota is met) is **always** recomputed server-side from the `playerland_ans` table; a
client-supplied count is accepted as a parameter but never trusted or stored as-is.

## Instance Isolation

A question or option id is a bare primary key with no owner check of its own. Every lookup that
resolves one is scoped by the already-validated `playerlandid`, not the id alone — pairing one
instance's question with another instance's `playerlandid` is rejected, not silently answered.
This is enforced by a dedicated automated test file
(`cross_instance_security_test.php`, see [Automated Tests](#testing)), not just by code review.

## Output Escaping

Question text, option text and mini-lesson text are all formatted with `format_string()`/plain
rendering before reaching the page — none of it is echoed raw.

## Access Control

* `mod/playerland:view` — required to play the activity and to call any of its web services.
* `mod/playerland:manage` — required to reach the **Manage questions** screen.
* `mod/playerland:addinstance` — required to add the activity to a course.

`manage_questions.php`'s POST handling (adding/editing/deleting a question) verifies `sesskey` on
every destructive action.

## Privacy

`user_preferences` rows written by the plugin (the dismissed-intro flag) are cleaned up in
`db/uninstall.php` — the one thing core does not clean up automatically when the plugin is
removed, since every `install.xml` table is dropped by core on its own.
