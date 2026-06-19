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
 * External functions for playerland.
 *
 * @package    mod_playerland
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerland;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External functions for playerland.
 */
class external extends external_api {
    /**
     * Parameters for save_progress.
     *
     * @return external_function_parameters
     */
    public static function save_progress_parameters() {
        return new external_function_parameters([
            'playerlandid' => new external_value(PARAM_INT, 'The playerland instance id'),
            'blocksresolved' => new external_value(PARAM_INT, 'Number of blocks resolved'),
        ]);
    }

    /**
     * Save progress for the current user.
     *
     * @param int $playerlandid
     * @param int $blocksresolved
     * @return array
     */
    public static function save_progress($playerlandid, $blocksresolved) {
        global $DB, $USER;

        $params = self::validate_parameters(self::save_progress_parameters(), [
            'playerlandid' => $playerlandid,
            'blocksresolved' => $blocksresolved,
        ]);

        $cm = get_coursemodule_from_instance('playerland', $params['playerlandid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/playerland:view', $context);

        $attempt = $DB->get_record('playerland_atmpt', ['playerlandid' => $params['playerlandid'], 'userid' => $USER->id]);

        if ($attempt) {
            $attempt->blocksresolved = $params['blocksresolved'];
            $attempt->timemodified = time();
            $DB->update_record('playerland_atmpt', $attempt);
        } else {
            $attempt = new \stdClass();
            $attempt->playerlandid = $params['playerlandid'];
            $attempt->userid = $USER->id;
            $attempt->currentlevel = 1;
            $attempt->blocksresolved = $params['blocksresolved'];
            $attempt->timecreated = time();
            $attempt->timemodified = time();
            $DB->insert_record('playerland_atmpt', $attempt);
        }

        return ['status' => true];
    }

    /**
     * Returns for save_progress.
     *
     * @return external_single_structure
     */
    public static function save_progress_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Status of the operation'),
        ]);
    }

    /**
     * Parameters for get_question.
     *
     * @return external_function_parameters
     */
    public static function get_question_parameters() {
        return new external_function_parameters([
            'playerlandid' => new external_value(PARAM_INT, 'The playerland instance id'),
        ]);
    }

    /**
     * Get a random question for the given playerland instance.
     *
     * @param int $playerlandid
     * @return array
     */
    public static function get_question($playerlandid) {
        global $DB;

        $params = self::validate_parameters(self::get_question_parameters(), [
            'playerlandid' => $playerlandid,
        ]);

        $cm = get_coursemodule_from_instance('playerland', $params['playerlandid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/playerland:view', $context);

        // Fetch a random question for this instance.
        $sql = "SELECT id, questiontext FROM {playerland_q} WHERE playerlandid = ? ORDER BY RANDOM()";
        // Wait, Moodle DB abstraction doesn't support ORDER BY RANDOM() universally. Let's fetch all and pick one.
        $questions = $DB->get_records('playerland_q', ['playerlandid' => $params['playerlandid']]);

        if (empty($questions)) {
            // No questions configured yet.
            return [
                'hasquestion' => false,
                'questionid' => 0,
                'questiontext' => '',
                'options' => [],
            ];
        }

        $randomq = $questions[array_rand($questions)];

        // Fetch options.
        $options = $DB->get_records('playerland_opts', ['questionid' => $randomq->id], 'id ASC');
        $optsarray = [];
        foreach ($options as $opt) {
            $optsarray[] = [
                'id' => $opt->id,
                'optiontext' => format_string($opt->optiontext, true, ['context' => $context]),
            ];
        }

        // Shuffle options so the correct one is not always in the same place if they were inserted sequentially.
        shuffle($optsarray);

        return [
            'hasquestion' => true,
            'questionid' => $randomq->id,
            'questiontext' => format_string($randomq->questiontext, true, ['context' => $context]),
            'options' => $optsarray,
        ];
    }

    /**
     * Returns for get_question.
     *
     * @return external_single_structure
     */
    public static function get_question_returns() {
        return new external_single_structure([
            'hasquestion' => new external_value(PARAM_BOOL, 'Whether a question was found'),
            'questionid' => new external_value(PARAM_INT, 'Question ID'),
            'questiontext' => new external_value(PARAM_RAW, 'Question text'),
            'options' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Option ID'),
                    'optiontext' => new external_value(PARAM_RAW, 'Option text'),
                ]),
            ),
        ]);
    }

    /**
     * Parameters for check_answer.
     *
     * @return external_function_parameters
     */
    public static function check_answer_parameters() {
        return new external_function_parameters([
            'playerlandid' => new external_value(PARAM_INT, 'The playerland instance id'),
            'questionid' => new external_value(PARAM_INT, 'The question id'),
            'optionid' => new external_value(PARAM_INT, 'The chosen option id'),
        ]);
    }

    /**
     * Check the answer for a question.
     *
     * @param int $playerlandid
     * @param int $questionid
     * @param int $optionid
     * @return array
     */
    public static function check_answer($playerlandid, $questionid, $optionid) {
        global $DB;

        $params = self::validate_parameters(self::check_answer_parameters(), [
            'playerlandid' => $playerlandid,
            'questionid' => $questionid,
            'optionid' => $optionid,
        ]);

        $cm = get_coursemodule_from_instance('playerland', $params['playerlandid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/playerland:view', $context);

        $option = $DB->get_record('playerland_opts', ['id' => $params['optionid'], 'questionid' => $params['questionid']]);

        $correct = false;
        if ($option && $option->iscorrect) {
            $correct = true;
        }

        // The correct option for this question, so the client can highlight it on a wrong answer.
        $correctoption = $DB->get_record(
            'playerland_opts',
            ['questionid' => $params['questionid'], 'iscorrect' => 1],
            'id',
            IGNORE_MULTIPLE
        );

        return [
            'correct' => $correct,
            'correctoptionid' => $correctoption ? (int) $correctoption->id : 0,
        ];
    }

    /**
     * Returns for check_answer.
     *
     * @return external_single_structure
     */
    public static function check_answer_returns() {
        return new external_single_structure([
            'correct' => new external_value(PARAM_BOOL, 'Whether the answer is correct'),
            'correctoptionid' => new external_value(PARAM_INT, 'The id of the correct option'),
        ]);
    }
}
