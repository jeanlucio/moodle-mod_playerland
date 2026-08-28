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
 * External function tests for check_answer.
 *
 * @package    mod_playerland
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerland\external;

use core_external\external_api;
use mod_playerland\external;

/**
 * Tests for the mod_playerland_check_answer web service.
 *
 * @covers \mod_playerland\external
 */
final class check_answer_test extends \advanced_testcase {
    /** @var \stdClass Course used by every test. */
    private \stdClass $course;

    /** @var \stdClass Enrolled student. */
    private \stdClass $student;

    /** @var \mod_playerland_generator Plugin data generator. */
    private $generator;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, 'student');
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
        $this->setUser($this->student);
    }

    /**
     * Creates a playerland instance in the shared course.
     *
     * @param array $overrides Instance field overrides.
     * @return \stdClass Instance record with the ->cmid field added.
     */
    private function make_instance(array $overrides = []): \stdClass {
        $record = array_merge(['course' => $this->course->id], $overrides);

        return $this->generator->create_instance($record);
    }

    /**
     * Calls the mod_playerland_check_answer web service through the real dispatch
     * path, exercising sesskey, capability and parameter validation.
     *
     * @param array $args Web service arguments.
     * @return array Response shaped as ['error' => bool, 'data' => array|null, ...].
     */
    private function call_check_answer(array $args): array {
        $_POST['sesskey'] = sesskey();

        return external_api::call_external_function('mod_playerland_check_answer', $args);
    }

    /**
     * Tests that a correct answer is reported as correct and recorded exactly once.
     *
     * @return void
     */
    public function test_correct_answer_is_recorded(): void {
        global $DB;

        $instance = $this->make_instance(['targetquestions' => 1]);
        $question = $this->generator->create_question($instance->id, ['answers' => ['4' => true, '5' => false]]);
        $correctopt = $DB->get_record('playerland_opts', ['questionid' => $question->id, 'iscorrect' => 1], '*', MUST_EXIST);

        $result = $this->call_check_answer([
            'playerlandid' => $instance->id,
            'questionid' => $question->id,
            'optionid' => $correctopt->id,
        ]);

        $this->assertFalse($result['error']);
        $this->assertTrue($result['data']['correct']);
        $this->assertSame((int) $correctopt->id, $result['data']['correctoptionid']);
        $this->assertSame(1, $result['data']['blocksresolved']);
        $this->assertTrue($result['data']['complete']);
        $this->assertSame(1, $DB->count_records('playerland_ans', [
            'playerlandid' => $instance->id,
            'userid' => $this->student->id,
            'questionid' => $question->id,
        ]));
    }

    /**
     * Tests that answering the same question correctly a second time does not record a
     * second distinct answer row.
     *
     * @return void
     */
    public function test_correct_answer_is_idempotent(): void {
        global $DB;

        $instance = $this->make_instance();
        $question = $this->generator->create_question($instance->id, ['answers' => ['4' => true, '5' => false]]);
        $correctopt = $DB->get_record('playerland_opts', ['questionid' => $question->id, 'iscorrect' => 1], '*', MUST_EXIST);
        $args = [
            'playerlandid' => $instance->id,
            'questionid' => $question->id,
            'optionid' => $correctopt->id,
        ];

        $this->call_check_answer($args);
        $second = $this->call_check_answer($args);

        $this->assertFalse($second['error']);
        $this->assertSame(1, $second['data']['blocksresolved']);
        $this->assertSame(1, $DB->count_records('playerland_ans', [
            'playerlandid' => $instance->id,
            'userid' => $this->student->id,
            'questionid' => $question->id,
        ]));
    }

    /**
     * Tests that a wrong answer is reported as incorrect, records nothing, and still
     * reveals the correct option id so the client can highlight it.
     *
     * @return void
     */
    public function test_wrong_answer_is_not_recorded_but_reveals_correct_option(): void {
        global $DB;

        $instance = $this->make_instance();
        $question = $this->generator->create_question($instance->id, ['answers' => ['4' => true, '5' => false]]);
        $wrongopt = $DB->get_record('playerland_opts', ['questionid' => $question->id, 'iscorrect' => 0], '*', MUST_EXIST);
        $correctopt = $DB->get_record('playerland_opts', ['questionid' => $question->id, 'iscorrect' => 1], '*', MUST_EXIST);

        $result = $this->call_check_answer([
            'playerlandid' => $instance->id,
            'questionid' => $question->id,
            'optionid' => $wrongopt->id,
        ]);

        $this->assertFalse($result['error']);
        $this->assertFalse($result['data']['correct']);
        $this->assertSame((int) $correctopt->id, $result['data']['correctoptionid']);
        $this->assertSame(0, $result['data']['blocksresolved']);
        $this->assertSame(0, $DB->count_records('playerland_ans', [
            'playerlandid' => $instance->id,
            'userid' => $this->student->id,
        ]));
    }

    /**
     * Tests that a nonexistent question id throws the dedicated exception rather than
     * a generic database error.
     *
     * @return void
     */
    public function test_unknown_question_is_rejected(): void {
        $instance = $this->make_instance();

        $result = $this->call_check_answer([
            'playerlandid' => $instance->id,
            'questionid' => 999999,
            'optionid' => 0,
        ]);

        $this->assertTrue($result['error']);
        $this->assertSame('invalidquestion', $result['exception']->errorcode);
    }

    /**
     * Tests that answering triggers a gradebook update reflecting the new proportional
     * progress.
     *
     * @return void
     */
    public function test_correct_answer_updates_the_gradebook(): void {
        global $DB;

        $instance = $this->make_instance(['grade' => 100, 'targetquestions' => 2]);
        $question = $this->generator->create_question($instance->id, ['answers' => ['4' => true, '5' => false]]);
        $correctopt = $DB->get_record('playerland_opts', ['questionid' => $question->id, 'iscorrect' => 1], '*', MUST_EXIST);

        $this->call_check_answer([
            'playerlandid' => $instance->id,
            'questionid' => $question->id,
            'optionid' => $correctopt->id,
        ]);

        $itemid = $DB->get_field('grade_items', 'id', [
            'itemmodule' => 'playerland',
            'iteminstance' => $instance->id,
        ], MUST_EXIST);
        $graded = $DB->get_record('grade_grades', ['itemid' => $itemid, 'userid' => $this->student->id], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(50.0, (float) $graded->rawgrade, 0.001);
    }
}
