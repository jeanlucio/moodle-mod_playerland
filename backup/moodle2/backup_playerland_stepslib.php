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
 * Backup structure step for mod_playerland.
 *
 * @package    mod_playerland
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the XML tree structure for a PlayerLand backup.
 */
class backup_playerland_activity_structure_step extends backup_activity_structure_step {
    /**
     * Returns the root backup element with all nested children.
     *
     * @return backup_nested_element
     */
    protected function define_structure(): backup_nested_element {
        $userinfo = $this->get_setting_value('userinfo');

        // Root element — mirrors all columns in {playerland}.
        $playerland = new backup_nested_element('playerland', ['id'], [
            'name',
            'intro',
            'introformat',
            'timecreated',
            'timemodified',
            'levels',
            'targetquestions',
            'map',
            'grade',
            'lesson1',
            'lesson2',
            'lesson3',
        ]);

        // Questions and their options belong to the activity and are always backed up.
        $questions = new backup_nested_element('questions');
        $question = new backup_nested_element('question', ['id'], [
            'questiontext',
            'questionformat',
            'topic',
            'timecreated',
            'timemodified',
        ]);

        $options = new backup_nested_element('options');
        $option = new backup_nested_element('option', ['id'], [
            'optiontext',
            'iscorrect',
            'timecreated',
            'timemodified',
        ]);

        // Attempts and answers are user data — only backed up when userinfo is enabled.
        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element('attempt', ['id'], [
            'userid',
            'currentlevel',
            'blocksresolved',
            'timecreated',
            'timemodified',
        ]);

        $answers = new backup_nested_element('answers');
        $answer = new backup_nested_element('answer', ['id'], [
            'userid',
            'questionid',
            'timecreated',
        ]);

        // Build the tree. Questions (with their options) are added before answers, so
        // the question id mapping already exists — via the same-name set_mapping() call
        // in the restore step — by the time an answer needs to remap questionid; both
        // are children of the same activity instance in the same document, so no
        // after_execute() deferral is needed here.
        $playerland->add_child($questions);
        $questions->add_child($question);
        $question->add_child($options);
        $options->add_child($option);

        if ($userinfo) {
            $playerland->add_child($attempts);
            $attempts->add_child($attempt);

            $playerland->add_child($answers);
            $answers->add_child($answer);
        }

        // Connect elements to database tables.
        $playerland->set_source_table('playerland', ['id' => backup::VAR_ACTIVITYID]);
        $question->set_source_table('playerland_q', ['playerlandid' => backup::VAR_ACTIVITYID]);
        $option->set_source_table('playerland_opts', ['questionid' => backup::VAR_PARENTID]);

        if ($userinfo) {
            $attempt->set_source_table('playerland_atmpt', ['playerlandid' => backup::VAR_ACTIVITYID]);
            $answer->set_source_table('playerland_ans', ['playerlandid' => backup::VAR_ACTIVITYID]);
        }

        // Annotate files embedded in the intro editor field, if any.
        $playerland->annotate_files('mod_playerland', 'intro', null);

        if ($userinfo) {
            $attempt->annotate_ids('user', 'userid');
            $answer->annotate_ids('user', 'userid');
            // Questionid is an intra-plugin reference; resolved via the question mapping.
            $answer->annotate_ids('playerland_question', 'questionid');
        }

        // Wrap the root in the standard activity envelope.
        return $this->prepare_activity_structure($playerland);
    }
}
