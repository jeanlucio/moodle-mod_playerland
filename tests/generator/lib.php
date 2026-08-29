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
 * Data generator for mod_playerland.
 *
 * @package    mod_playerland
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Data generator class for the playerland activity module.
 */
class mod_playerland_generator extends testing_module_generator {
    /**
     * Creates a new instance of the playerland activity.
     *
     * @param array|\stdClass|null $record Field values for the instance.
     * @param array|null $options Module options (e.g. idnumber, section).
     * @return \stdClass Created course-module record.
     */
    public function create_instance($record = null, ?array $options = null): \stdClass {
        $record = (object) (array) $record;

        $defaults = [
            'levels' => 1,
            'targetquestions' => 3,
            'map' => 'map_level001.json',
            'grade' => 100,
            'lesson1' => '',
            'lesson2' => '',
            'lesson3' => '',
        ];

        foreach ($defaults as $field => $value) {
            if (!isset($record->$field)) {
                $record->$field = $value;
            }
        }

        return parent::create_instance($record, $options);
    }

    /**
     * Creates a question with up to four options for a playerland instance.
     *
     * @param int $playerlandid The playerland instance id.
     * @param array $options Overrides: questiontext, topic, answers (text => iscorrect).
     * @return \stdClass The created question record.
     */
    public function create_question(int $playerlandid, array $options = []): \stdClass {
        global $DB;

        $now = time();

        $question = new \stdClass();
        $question->playerlandid = $playerlandid;
        $question->questiontext = $options['questiontext'] ?? 'What is 2 + 2?';
        $question->questionformat = FORMAT_PLAIN;
        $question->topic = $options['topic'] ?? 0;
        $question->timecreated = $now;
        $question->timemodified = $now;
        $question->id = $DB->insert_record('playerland_q', $question);

        $answers = $options['answers'] ?? ['4' => true, '5' => false];
        foreach ($answers as $text => $iscorrect) {
            $DB->insert_record('playerland_opts', (object) [
                'questionid' => $question->id,
                'optiontext' => (string) $text,
                'iscorrect' => $iscorrect ? 1 : 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        return $DB->get_record('playerland_q', ['id' => $question->id], '*', MUST_EXIST);
    }
}
