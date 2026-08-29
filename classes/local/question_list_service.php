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
 * Service to build the template context for the question management listing.
 *
 * @package    mod_playerland
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerland\local;

use moodle_url;
use renderer_base;
use stdClass;

/**
 * Builds the mod_playerland/manage_questions template context.
 */
class question_list_service {
    /**
     * Builds the template context for the question listing screen.
     *
     * @param stdClass $playerland The activity instance.
     * @param int $cmid The course module id.
     * @param renderer_base $output Used to render the edit/delete icons.
     * @return array Template context for mod_playerland/manage_questions.
     */
    public static function build_list_context(stdClass $playerland, int $cmid, renderer_base $output): array {
        global $DB;

        $questions = $DB->get_records('playerland_q', ['playerlandid' => $playerland->id], 'id ASC');

        $topiclabels = [
            0 => get_string('questiontopicgeneral', 'mod_playerland'),
            1 => get_string('lessonnum', 'mod_playerland', 1),
            2 => get_string('lessonnum', 'mod_playerland', 2),
            3 => get_string('lessonnum', 'mod_playerland', 3),
        ];

        $rows = [];
        foreach ($questions as $question) {
            $editurl = new moodle_url(
                '/mod/playerland/manage_questions.php',
                ['id' => $cmid, 'action' => 'edit', 'qid' => $question->id]
            );
            $deleteurl = new moodle_url(
                '/mod/playerland/manage_questions.php',
                ['id' => $cmid, 'action' => 'delete', 'qid' => $question->id, 'sesskey' => sesskey()]
            );

            $rows[] = [
                'questiontext' => format_text($question->questiontext, $question->questionformat),
                'topiclabel' => $topiclabels[$question->topic] ?? $topiclabels[0],
                'editurl' => $editurl->out(false),
                'deleteurl' => $deleteurl->out(false),
                'editicon' => $output->pix_icon('t/edit', get_string('edit')),
                'deleteicon' => $output->pix_icon('t/delete', get_string('delete')),
                'confirmdelete' => get_string('confirmdeletequestion', 'mod_playerland'),
            ];
        }

        return [
            'addurl' => (new moodle_url('/mod/playerland/manage_questions.php', ['id' => $cmid, 'action' => 'add']))->out(false),
            'addquestionlabel' => get_string('addquestion', 'mod_playerland'),
            'hasquestions' => !empty($rows),
            'noquestionslabel' => get_string('noquestions', 'mod_playerland'),
            'questioncolumnlabel' => get_string('question', 'mod_playerland'),
            'topiccolumnlabel' => get_string('questiontopic', 'mod_playerland'),
            'actionscolumnlabel' => get_string('actions'),
            'questions' => $rows,
            'backurl' => (new moodle_url('/mod/playerland/view.php', ['id' => $cmid]))->out(false),
            'backlabel' => get_string('back', 'core'),
        ];
    }
}
