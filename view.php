<?php
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
 * Displays the playerland instance.
 *
 * @package    mod_playerland
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/completionlib.php');

use mod_playerland\local\view_page_service;

$id = required_param('id', PARAM_INT); // Course module ID.

$cm = get_coursemodule_from_id('playerland', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$playerland = $DB->get_record('playerland', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/playerland:view', $context);

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Trigger course_module_viewed event.
$event = \mod_playerland\event\course_module_viewed::create([
    'objectid' => $playerland->id,
    'context' => $context,
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('playerland', $playerland);
$event->trigger();

$PAGE->set_url('/mod/playerland/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($playerland->name));
$PAGE->set_heading(format_string($course->fullname));

$templatecontext = view_page_service::build_page_context($cm, $playerland, $context, (int)$USER->id);

if (!$templatecontext['introseen']) {
    $PAGE->requires->js_call_amd('mod_playerland/intro', 'init', [$playerland->id]);
}

// Phaser itself is loaded dynamically from inside game.js (mirrors
// filter_mathjaxloader's loadMathJax()), not queued here as a static <script> — a static
// tag would sit in the page's footer and race core_message/message_drawer.js.
$PAGE->requires->js_call_amd('mod_playerland/game', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_playerland/view', $templatecontext);
echo $OUTPUT->footer();
