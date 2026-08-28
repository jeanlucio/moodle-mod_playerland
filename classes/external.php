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
use stdClass;

/**
 * External functions for playerland.
 */
class external extends external_api {
    /**
     * Loads an activity instance and validates the current request context.
     *
     * @param int $playerlandid The playerland instance id.
     * @return array The course module, context and activity instance.
     */
    private static function get_validated_instance(int $playerlandid): array {
        global $DB;

        $cm = get_coursemodule_from_instance('playerland', $playerlandid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/playerland:view', $context);

        $playerland = $DB->get_record('playerland', ['id' => $playerlandid], '*', MUST_EXIST);

        return [$cm, $context, $playerland];
    }

    /**
     * Gets or creates the current user's attempt row.
     *
     * @param int $playerlandid The playerland instance id.
     * @param int $userid The user id.
     * @return stdClass The attempt record.
     */
    private static function get_or_create_attempt(int $playerlandid, int $userid): stdClass {
        global $DB;

        $attempt = $DB->get_record('playerland_atmpt', [
            'playerlandid' => $playerlandid,
            'userid' => $userid,
        ]);

        if ($attempt) {
            return $attempt;
        }

        $attempt = new stdClass();
        $attempt->playerlandid = $playerlandid;
        $attempt->userid = $userid;
        $attempt->currentlevel = 1;
        $attempt->blocksresolved = 0;
        $attempt->timecreated = time();
        $attempt->timemodified = $attempt->timecreated;
        $attempt->id = $DB->insert_record('playerland_atmpt', $attempt);

        return $attempt;
    }

    /**
     * Recounts and stores the current user's distinct correct answer progress.
     *
     * @param stdClass $playerland The activity instance.
     * @param int $userid The user id.
     * @return stdClass The updated attempt.
     */
    private static function refresh_attempt_progress(stdClass $playerland, int $userid): stdClass {
        global $CFG, $DB;

        $attempt = self::get_or_create_attempt((int)$playerland->id, $userid);
        $attempt->blocksresolved = $DB->count_records('playerland_ans', [
            'playerlandid' => $playerland->id,
            'userid' => $userid,
        ]);
        $attempt->timemodified = time();
        $DB->update_record('playerland_atmpt', $attempt);

        require_once($CFG->dirroot . '/mod/playerland/lib.php');
        playerland_update_grades($playerland, $userid);

        return $attempt;
    }

    /**
     * Converts attempt progress to an external response fragment.
     *
     * @param stdClass $playerland The activity instance.
     * @param stdClass $attempt The attempt record.
     * @return array Progress values.
     */
    private static function get_progress_response(stdClass $playerland, stdClass $attempt): array {
        $targetquestions = max(1, (int)($playerland->targetquestions ?? 1));
        $blocksresolved = (int)$attempt->blocksresolved;

        return [
            'blocksresolved' => $blocksresolved,
            'targetquestions' => $targetquestions,
            'complete' => $blocksresolved >= $targetquestions,
        ];
    }

    /**
     * Parameters for save_progress.
     *
     * @return external_function_parameters
     */
    public static function save_progress_parameters(): external_function_parameters {
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
    public static function save_progress(int $playerlandid, int $blocksresolved): array {
        global $USER;

        $params = self::validate_parameters(self::save_progress_parameters(), [
            'playerlandid' => $playerlandid,
            'blocksresolved' => $blocksresolved,
        ]);

        [, , $playerland] = self::get_validated_instance((int)$params['playerlandid']);
        $attempt = self::refresh_attempt_progress($playerland, $USER->id);

        return ['status' => true] + self::get_progress_response($playerland, $attempt);
    }

    /**
     * Returns for save_progress.
     *
     * @return external_single_structure
     */
    public static function save_progress_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Status of the operation'),
            'blocksresolved' => new external_value(PARAM_INT, 'Number of distinct correct questions'),
            'targetquestions' => new external_value(PARAM_INT, 'Required correct questions'),
            'complete' => new external_value(PARAM_BOOL, 'Whether the required progress has been reached'),
        ]);
    }

    /**
     * Parameters for get_question.
     *
     * @return external_function_parameters
     */
    public static function get_question_parameters(): external_function_parameters {
        return new external_function_parameters([
            'playerlandid' => new external_value(PARAM_INT, 'The playerland instance id'),
            'topic' => new external_value(
                PARAM_INT,
                'Mini-lesson to draw from (1-3), or 0 for the general pool',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Chooses the pool of unanswered questions for a block, honouring the topic and
     * falling back so a block is never left without a question.
     *
     * @param int $playerlandid The instance id.
     * @param int $userid The user id.
     * @param int $topic Preferred mini-lesson (1-3), or 0 for any.
     * @return array Question records keyed by id.
     */
    private static function pick_question_pool(int $playerlandid, int $userid, int $topic): array {
        global $DB;

        $unanswered = "SELECT q.id, q.questiontext, q.questionformat
                         FROM {playerland_q} q
                    LEFT JOIN {playerland_ans} a ON a.questionid = q.id
                              AND a.playerlandid = q.playerlandid
                              AND a.userid = :userid
                        WHERE q.playerlandid = :playerlandid
                              AND a.id IS NULL";
        $any = "SELECT id, questiontext, questionformat
                  FROM {playerland_q}
                 WHERE playerlandid = :playerlandid";
        $base = ['userid' => $userid, 'playerlandid' => $playerlandid];

        $attempts = [];
        if ($topic > 0) {
            $attempts[] = [$unanswered . ' AND q.topic = :topic', $base + ['topic' => $topic]];
            $attempts[] = [$any . ' AND topic = :topic', ['playerlandid' => $playerlandid, 'topic' => $topic]];
        }
        $attempts[] = [$unanswered, $base];
        $attempts[] = [$any, ['playerlandid' => $playerlandid]];

        foreach ($attempts as [$sql, $sqlparams]) {
            $rows = $DB->get_records_sql($sql, $sqlparams);
            if (!empty($rows)) {
                return $rows;
            }
        }
        return [];
    }

    /**
     * Get a random question for the given playerland instance.
     *
     * @param int $playerlandid
     * @param int $topic Mini-lesson to draw from (1-3), or 0 for the general pool.
     * @return array
     */
    public static function get_question(int $playerlandid, int $topic = 0): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::get_question_parameters(), [
            'playerlandid' => $playerlandid,
            'topic' => $topic,
        ]);

        [, $context] = self::get_validated_instance((int)$params['playerlandid']);

        $questions = self::pick_question_pool(
            (int)$params['playerlandid'],
            (int)$USER->id,
            (int)$params['topic']
        );

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
    public static function get_question_returns(): external_single_structure {
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
    public static function check_answer_parameters(): external_function_parameters {
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
    public static function check_answer(int $playerlandid, int $questionid, int $optionid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::check_answer_parameters(), [
            'playerlandid' => $playerlandid,
            'questionid' => $questionid,
            'optionid' => $optionid,
        ]);

        [, , $playerland] = self::get_validated_instance((int)$params['playerlandid']);

        $question = $DB->get_record('playerland_q', [
            'id' => $params['questionid'],
            'playerlandid' => $params['playerlandid'],
        ]);
        if (!$question) {
            throw new \moodle_exception('invalidquestion', 'mod_playerland');
        }

        $option = $DB->get_record('playerland_opts', [
            'id' => $params['optionid'],
            'questionid' => $params['questionid'],
        ]);

        $correct = false;
        if ($option && $option->iscorrect) {
            $correct = true;
            if (
                !$DB->record_exists('playerland_ans', [
                    'playerlandid' => $params['playerlandid'],
                    'userid' => $USER->id,
                    'questionid' => $params['questionid'],
                ])
            ) {
                $answer = new stdClass();
                $answer->playerlandid = $params['playerlandid'];
                $answer->userid = $USER->id;
                $answer->questionid = $params['questionid'];
                $answer->timecreated = time();
                $DB->insert_record('playerland_ans', $answer);
            }
        }

        // The correct option for this question, so the client can highlight it on a wrong answer.
        $correctoption = $DB->get_record(
            'playerland_opts',
            ['questionid' => $params['questionid'], 'iscorrect' => 1],
            'id',
            IGNORE_MULTIPLE
        );

        $attempt = self::refresh_attempt_progress($playerland, $USER->id);

        return [
            'correct' => $correct,
            'correctoptionid' => $correctoption ? (int) $correctoption->id : 0,
        ] + self::get_progress_response($playerland, $attempt);
    }

    /**
     * Returns for check_answer.
     *
     * @return external_single_structure
     */
    public static function check_answer_returns(): external_single_structure {
        return new external_single_structure([
            'correct' => new external_value(PARAM_BOOL, 'Whether the answer is correct'),
            'correctoptionid' => new external_value(PARAM_INT, 'The id of the correct option'),
            'blocksresolved' => new external_value(PARAM_INT, 'Number of distinct correct questions'),
            'targetquestions' => new external_value(PARAM_INT, 'Required correct questions'),
            'complete' => new external_value(PARAM_BOOL, 'Whether the required progress has been reached'),
        ]);
    }
}
