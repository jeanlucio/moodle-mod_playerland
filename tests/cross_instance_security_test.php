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
 * Instance-isolation security tests for the playerland external API.
 *
 * @package    mod_playerland
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerland;

use core_external\external_api;

/**
 * A question id is a bare primary key with no owner check of its own; every lookup
 * must be scoped by the already-validated playerlandid, not trust the id alone. These
 * tests forge a request that pairs one instance's playerlandid with another instance's
 * questionid/optionid and confirm the mismatch is rejected rather than silently
 * answered or leaked.
 *
 * @covers \mod_playerland\external
 */
final class cross_instance_security_test extends \advanced_testcase {
    /** @var \stdClass Enrolled student. */
    private \stdClass $student;

    /** @var \mod_playerland_generator Plugin data generator. */
    private $generator;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
        $this->student = $this->getDataGenerator()->create_user();
    }

    /**
     * Creates a playerland instance in its own new course, with the shared student
     * enrolled.
     *
     * @return \stdClass Instance record with the ->cmid field added.
     */
    private function make_instance(): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($this->student->id, $course->id, 'student');

        return $this->generator->create_instance(['course' => $course->id]);
    }

    /**
     * Tests that a question belonging to a different playerland instance cannot be
     * answered by pairing it with another instance's playerlandid.
     *
     * @return void
     */
    public function test_check_answer_rejects_a_question_from_another_instance(): void {
        global $DB;

        $instancea = $this->make_instance();
        $instanceb = $this->make_instance();
        $questionb = $this->generator->create_question($instanceb->id);
        $optionb = $DB->get_record('playerland_opts', ['questionid' => $questionb->id, 'iscorrect' => 1], '*', MUST_EXIST);

        $this->setUser($this->student);
        $_POST['sesskey'] = sesskey();

        $result = external_api::call_external_function('mod_playerland_check_answer', [
            'playerlandid' => $instancea->id,
            'questionid' => $questionb->id,
            'optionid' => $optionb->id,
        ]);

        $this->assertTrue($result['error']);
        $this->assertSame('invalidquestion', $result['exception']->errorcode);
    }

    /**
     * Tests that the topic-linked question pool for one instance never surfaces a
     * question that belongs to another instance, even when both share the same topic
     * number.
     *
     * @return void
     */
    public function test_get_question_never_crosses_instance_boundaries(): void {
        $instancea = $this->make_instance();
        $instanceb = $this->make_instance();
        $questiona = $this->generator->create_question($instancea->id, ['topic' => 1]);
        $this->generator->create_question($instanceb->id, ['topic' => 1]);

        $this->setUser($this->student);
        $_POST['sesskey'] = sesskey();

        for ($i = 0; $i < 5; $i++) {
            $response = external_api::call_external_function('mod_playerland_get_question', [
                'playerlandid' => $instancea->id,
                'topic' => 1,
            ]);
            $this->assertFalse($response['error']);
            $this->assertTrue($response['data']['hasquestion']);
            $this->assertSame((int) $questiona->id, $response['data']['questionid']);
        }
    }

    /**
     * Tests that recording a correct answer for one instance's question does not mark
     * a same-numbered question in another instance as answered.
     *
     * @return void
     */
    public function test_answering_one_instance_does_not_affect_another(): void {
        global $DB;

        $instancea = $this->make_instance();
        $instanceb = $this->make_instance();
        $questiona = $this->generator->create_question($instancea->id);
        $this->generator->create_question($instanceb->id);
        $optiona = $DB->get_record('playerland_opts', ['questionid' => $questiona->id, 'iscorrect' => 1], '*', MUST_EXIST);

        $this->setUser($this->student);
        external::check_answer($instancea->id, $questiona->id, $optiona->id);

        $this->assertSame(0, $DB->count_records('playerland_ans', ['playerlandid' => $instanceb->id]));
    }
}
