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
 * Manage questions for playerland.
 *
 * @package    mod_playerland
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/playerland/lib.php');
require_once($CFG->dirroot . '/mod/playerland/classes/form/question_form.php');

use mod_playerland\local\question_list_service;

$cmid = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$questionid = optional_param('qid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('playerland', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$playerland = $DB->get_record('playerland', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/playerland:manage', $context);

$url = new moodle_url('/mod/playerland/manage_questions.php', ['id' => $cmid]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($playerland->name) . ' - ' . get_string('managequestions', 'mod_playerland'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($action === 'delete' && $questionid) {
    require_sesskey();
    $DB->delete_records('playerland_opts', ['questionid' => $questionid]);
    $DB->delete_records('playerland_q', ['id' => $questionid]);
    redirect($url, get_string('questiondeleted', 'mod_playerland'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$mform = new \mod_playerland\form\question_form($url, null, 'post', '', ['class' => 'ignoredirty']);

if ($mform->is_cancelled()) {
    redirect($url);
} else if ($data = $mform->get_data()) {
    // Save data.
    $now = time();

    $qrecord = new stdClass();
    $qrecord->playerlandid = $playerland->id;
    $qrecord->questiontext = $data->questiontext['text'];
    $qrecord->questionformat = $data->questiontext['format'];
    $qrecord->topic = (int)($data->topic ?? 0);
    $qrecord->timemodified = $now;

    if (!empty($data->qid)) {
        $qrecord->id = $data->qid;
        $DB->update_record('playerland_q', $qrecord);
        $qid = $qrecord->id;
    } else {
        $qrecord->timecreated = $now;
        $qid = $DB->insert_record('playerland_q', $qrecord);
    }

    // Process options.
    $existingopts = $DB->get_records('playerland_opts', ['questionid' => $qid]);

    // We only support 4 options in the form.
    for ($i = 1; $i <= 4; $i++) {
        $opttext = $data->optiontext[$i] ?? '';
        $optid = $data->optionid[$i] ?? 0;
        $iscorrect = ($data->iscorrect == $i) ? 1 : 0;

        if (empty($opttext)) {
            if ($optid) {
                $DB->delete_records('playerland_opts', ['id' => $optid]);
            }
            continue;
        }

        $optrecord = new stdClass();
        $optrecord->questionid = $qid;
        $optrecord->optiontext = $opttext;
        $optrecord->iscorrect = $iscorrect;
        $optrecord->timemodified = $now;

        if ($optid && isset($existingopts[$optid])) {
            $optrecord->id = $optid;
            $DB->update_record('playerland_opts', $optrecord);
        } else {
            $optrecord->timecreated = $now;
            $DB->insert_record('playerland_opts', $optrecord);
        }
    }

    redirect($url, get_string('questionsaved', 'mod_playerland'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managequestions', 'mod_playerland'));

if ($action === 'add' || $action === 'edit') {
    if ($action === 'edit' && $questionid) {
        $question = $DB->get_record('playerland_q', ['id' => $questionid], '*', MUST_EXIST);
        $options = $DB->get_records('playerland_opts', ['questionid' => $questionid], 'id ASC');

        $formdata = new stdClass();
        $formdata->qid = $question->id;
        $formdata->cmid = $cm->id;
        $formdata->topic = $question->topic;
        $formdata->questiontext = [
            'text' => $question->questiontext,
            'format' => $question->questionformat,
        ];

        $i = 1;
        foreach ($options as $opt) {
            $formdata->{"optionid[$i]"} = $opt->id;
            $formdata->{"optiontext[$i]"} = $opt->optiontext;
            if ($opt->iscorrect) {
                $formdata->iscorrect = $i;
            }
            $i++;
        }

        $mform->set_data($formdata);
    } else {
        $mform->set_data(['cmid' => $cm->id]);
    }

    $mform->display();
} else {
    $listcontext = question_list_service::build_list_context($playerland, $cmid, $OUTPUT);
    echo $OUTPUT->render_from_template('mod_playerland/manage_questions', $listcontext);
}

echo $OUTPUT->footer();
