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
 * Tests for the playerland_add_instance/update_instance/delete_instance callbacks.
 *
 * @package    mod_playerland
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerland;

/**
 * Tests for playerland_add_instance(), playerland_update_instance() and
 * playerland_delete_instance().
 *
 * @covers ::playerland_add_instance
 * @covers ::playerland_update_instance
 * @covers ::playerland_delete_instance
 */
final class lib_crud_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/playerland/lib.php');
    }

    /**
     * Tests that adding an instance persists the submitted fields, stamps timecreated
     * and creates the gradebook item.
     *
     * @return void
     */
    public function test_add_instance_persists_fields_and_creates_grade_item(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $data = (object) [
            'course' => $course->id,
            'name' => 'A Trilha',
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'levels' => 1,
            'targetquestions' => 3,
            'map' => 'map_level001.json',
            'grade' => 100,
            'lesson1' => '',
            'lesson2' => '',
            'lesson3' => '',
        ];

        $id = playerland_add_instance($data);

        $record = $DB->get_record('playerland', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('A Trilha', $record->name);
        $this->assertSame(3, (int) $record->targetquestions);
        $this->assertGreaterThan(0, (int) $record->timecreated);
        $this->assertSame((int) $record->timecreated, (int) $record->timemodified);

        $this->assertTrue($DB->record_exists('grade_items', [
            'itemmodule' => 'playerland',
            'iteminstance' => $id,
            'courseid' => $course->id,
        ]));
    }

    /**
     * Tests that updating an instance persists the new field values and stamps
     * timemodified.
     *
     * @return void
     */
    public function test_update_instance_persists_fields_and_stamps_timemodified(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
        $instance = $generator->create_instance(['course' => $course->id, 'targetquestions' => 3]);

        $before = time() - 10;
        $DB->set_field('playerland', 'timemodified', $before, ['id' => $instance->id]);

        $update = $DB->get_record('playerland', ['id' => $instance->id], '*', MUST_EXIST);
        $update->instance = $instance->id;
        $update->targetquestions = 7;

        $result = playerland_update_instance($update);

        $this->assertTrue($result);
        $record = $DB->get_record('playerland', ['id' => $instance->id], '*', MUST_EXIST);
        $this->assertSame(7, (int) $record->targetquestions);
        $this->assertGreaterThan($before, (int) $record->timemodified);
    }

    /**
     * Tests that deleting an instance cascades to every plugin table keyed by the
     * instance's own id, not just the instance row itself: questions, their options,
     * recorded answers and student attempts.
     *
     * @return void
     */
    public function test_delete_instance_cascades_questions_options_answers_attempts(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
        $instance = $generator->create_instance(['course' => $course->id]);
        $question = $generator->create_question($instance->id);

        $DB->insert_record('playerland_ans', (object) [
            'playerlandid' => $instance->id,
            'userid' => 2,
            'questionid' => $question->id,
            'timecreated' => time(),
        ]);
        $DB->insert_record('playerland_atmpt', (object) [
            'playerlandid' => $instance->id,
            'userid' => 2,
            'currentlevel' => 1,
            'blocksresolved' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $result = playerland_delete_instance($instance->id);

        $this->assertTrue($result);
        $this->assertFalse($DB->record_exists('playerland', ['id' => $instance->id]));
        $this->assertSame(0, $DB->count_records('playerland_q', ['playerlandid' => $instance->id]));
        $this->assertSame(0, $DB->count_records('playerland_opts', ['questionid' => $question->id]));
        $this->assertSame(0, $DB->count_records('playerland_ans', ['playerlandid' => $instance->id]));
        $this->assertSame(0, $DB->count_records('playerland_atmpt', ['playerlandid' => $instance->id]));
    }

    /**
     * Tests that deleting an instance without questions does not error on the
     * options-cascade step (empty question id list).
     *
     * @return void
     */
    public function test_delete_instance_without_questions_does_not_error(): void {
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
        $instance = $generator->create_instance(['course' => $course->id]);

        $this->assertTrue(playerland_delete_instance($instance->id));
    }

    /**
     * Tests that deleting a non-existent instance returns false without erroring.
     *
     * @return void
     */
    public function test_delete_instance_unknown_id_returns_false(): void {
        $this->assertFalse(playerland_delete_instance(999999));
    }
}
