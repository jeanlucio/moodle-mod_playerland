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

$id = required_param('id', PARAM_INT); // Course module ID.

$cm = get_coursemodule_from_id('playerland', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$playerland = $DB->get_record('playerland', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/playerland:view', $context);

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
];

echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($playerland->name));

if (has_capability('mod/playerland:manage', $context)) {
    $manageurl = new moodle_url('/mod/playerland/manage_questions.php', ['id' => $cm->id]);
    echo html_writer::div(
        html_writer::link($manageurl, get_string('managequestions', 'mod_playerland'), ['class' => 'btn btn-secondary mb-3']),
        'text-right'
    );
}

if (!empty($playerland->intro)) {
    echo $OUTPUT->box(
        format_module_intro('playerland', $playerland, $cm->id),
        'generalbox',
        'intro'
    );
}

// Optional inline styling for the game container.
echo \html_writer::tag('style', '
    #playerland-game-wrapper {
        width: 100%;
        max-width: 800px;
        margin: 0 auto;
        border: 4px solid #333;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    }
    #playerland-game-container canvas {
        display: block;
        width: 100%;
        height: auto;
    }
');

echo \html_writer::start_div('', ['id' => 'playerland-game-wrapper']);
echo \html_writer::div('', '', ['id' => 'playerland-game-container']);
echo \html_writer::end_div();

// Pass config securely via json script tag as per rules.
echo \html_writer::tag(
    'script',
    json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP),
    ['type' => 'application/json', 'id' => 'mod-playerland-config']
);

// Phaser must be loaded globally before the AMD module initialises.
$PAGE->requires->js(
    new moodle_url('/mod/playerland/javascript/phaser.min.js')
);
$PAGE->requires->js_call_amd('mod_playerland/game', 'init');

echo $OUTPUT->footer();
