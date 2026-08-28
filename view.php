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

// We need an AMD module to load Phaser and start the game.
$config = [
    'id' => $playerland->id,
    'assetsurl' => (new moodle_url('/mod/playerland/assets'))->out(false),
    'levels' => $playerland->levels,
    'map' => $playerland->map,
    'targetquestions' => max(1, (int)($playerland->targetquestions ?? 1)),
    'blocksresolved' => 0,
    'lessons' => [
        (string)($playerland->lesson1 ?? ''),
        (string)($playerland->lesson2 ?? ''),
        (string)($playerland->lesson3 ?? ''),
    ],
];

$attempt = $DB->get_record('playerland_atmpt', ['playerlandid' => $playerland->id, 'userid' => $USER->id]);
if ($attempt) {
    $config['blocksresolved'] = (int)$attempt->blocksresolved;
}

echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($playerland->name));

if (has_capability('mod/playerland:manage', $context)) {
    $manageurl = new moodle_url('/mod/playerland/manage_questions.php', ['id' => $cm->id]);
    echo html_writer::div(
        html_writer::link($manageurl, get_string('managequestions', 'mod_playerland'), ['class' => 'btn btn-secondary']),
        'mod-playerland-actions'
    );
}

if (!empty($playerland->intro)) {
    echo $OUTPUT->box(
        format_module_intro('playerland', $playerland, $cm->id),
        'generalbox',
        'intro'
    );
}

$introseen = (bool)get_user_preferences('mod_playerland_introseen', false);

echo \html_writer::start_div('', ['id' => 'playerland-game-wrapper']);
echo \html_writer::div('', '', ['id' => 'playerland-game-container']);

if (!$introseen) {
    echo \html_writer::start_div('', [
        'id' => 'playerland-intro',
        'role' => 'dialog',
        'aria-modal' => 'true',
        'aria-labelledby' => 'playerland-intro-title',
    ]);
    echo \html_writer::tag('h2', get_string('introtitle', 'mod_playerland'), ['id' => 'playerland-intro-title']);
    echo \html_writer::start_tag('ul', ['class' => 'playerland-intro-list']);
    foreach (['controlmove', 'controljump', 'controlroll', 'controlcrouch', 'controlfullscreen'] as $key) {
        echo \html_writer::tag('li', get_string($key, 'mod_playerland'));
    }
    echo \html_writer::end_tag('ul');
    echo \html_writer::tag(
        'button',
        get_string('gotit', 'mod_playerland'),
        ['type' => 'button', 'class' => 'btn btn-primary', 'id' => 'playerland-intro-dismiss']
    );
    echo \html_writer::end_div();

    $PAGE->requires->js_call_amd('mod_playerland/intro', 'init', [$playerland->id]);
}

echo \html_writer::end_div();

// Pass config securely via json script tag as per rules.
echo \html_writer::tag(
    'script',
    json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP),
    ['type' => 'application/json', 'id' => 'mod-playerland-config']
);

// Phaser itself is loaded dynamically from inside game.js (mirrors
// filter_mathjaxloader's loadMathJax()), not queued here as a static <script> — a static
// tag would sit in the page's footer and race core_message/message_drawer.js.
$PAGE->requires->js_call_amd('mod_playerland/game', 'init');

echo $OUTPUT->footer();
