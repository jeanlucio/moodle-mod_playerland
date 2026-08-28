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
 * External function tests for get_question, including the topic fallback chain.
 *
 * @package    mod_playerland
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerland\external;

use core_external\external_api;

/**
 * Tests for the mod_playerland_get_question web service, in particular
 * pick_question_pool()'s topic-then-fallback priority order: a lesson block should
 * almost always surface its own linked topic, but a block must never be left without a
 * question just because that topic is exhausted or empty.
 *
 * @covers \mod_playerland\external
 */
final class get_question_test extends \advanced_testcase {
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
     * Calls the mod_playerland_get_question web service through the real dispatch
     * path, which also casts the response according to get_question_returns() — a
     * direct static call to external::get_question() would hand back raw, uncast
     * database values instead.
     *
     * @param int $playerlandid The playerland instance id.
     * @param int $topic Mini-lesson to draw from (1-3), or 0 for the general pool.
     * @return array The cast response.
     */
    private function call_get_question(int $playerlandid, int $topic = 0): array {
        $_POST['sesskey'] = sesskey();
        $result = external_api::call_external_function('mod_playerland_get_question', [
            'playerlandid' => $playerlandid,
            'topic' => $topic,
        ]);
        $this->assertFalse($result['error']);

        return $result['data'];
    }

    /**
     * Tests that a block with no questions configured at all returns hasquestion=false
     * instead of erroring.
     *
     * @return void
     */
    public function test_no_questions_configured_returns_hasquestion_false(): void {
        $instance = $this->make_instance();

        $result = $this->call_get_question($instance->id, 0);

        $this->assertFalse($result['hasquestion']);
        $this->assertSame(0, $result['questionid']);
        $this->assertSame([], $result['options']);
    }

    /**
     * Tests that requesting a topic with an unanswered question of that topic always
     * returns one of that topic's questions, never a general-pool one.
     *
     * @return void
     */
    public function test_topic_specific_unanswered_question_is_preferred(): void {
        $instance = $this->make_instance();
        $this->generator->create_question($instance->id, ['topic' => 0]);
        $topical = $this->generator->create_question($instance->id, ['topic' => 1]);

        for ($i = 0; $i < 5; $i++) {
            $result = $this->call_get_question($instance->id, 1);
            $this->assertTrue($result['hasquestion']);
            $this->assertSame((int) $topical->id, $result['questionid']);
        }
    }

    /**
     * Tests that once the only question tied to a topic has already been answered
     * correctly, that same topic question is still returned (tier 2: topic-any) rather
     * than falling through to the unrelated general pool.
     *
     * @return void
     */
    public function test_falls_back_to_topic_any_before_the_general_pool(): void {
        global $DB;

        $instance = $this->make_instance();
        $this->generator->create_question($instance->id, ['topic' => 0]);
        $topical = $this->generator->create_question($instance->id, ['topic' => 1]);

        $DB->insert_record('playerland_ans', (object) [
            'playerlandid' => $instance->id,
            'userid' => $this->student->id,
            'questionid' => $topical->id,
            'timecreated' => time(),
        ]);

        $result = $this->call_get_question($instance->id, 1);

        $this->assertTrue($result['hasquestion']);
        $this->assertSame((int) $topical->id, $result['questionid']);
    }

    /**
     * Tests that a block requesting a topic with no questions at all falls back to the
     * general pool, so it is never left without a question.
     *
     * @return void
     */
    public function test_falls_back_to_general_pool_when_topic_has_no_questions(): void {
        $instance = $this->make_instance();
        $general = $this->generator->create_question($instance->id, ['topic' => 0]);

        $result = $this->call_get_question($instance->id, 2);

        $this->assertTrue($result['hasquestion']);
        $this->assertSame((int) $general->id, $result['questionid']);
    }

    /**
     * Tests that option text is formatted (escaped) rather than echoed raw, and that
     * every configured option is returned.
     *
     * @return void
     */
    public function test_options_are_formatted_and_complete(): void {
        $instance = $this->make_instance();
        $this->generator->create_question($instance->id, [
            'answers' => [
                '<script>alert(1)</script>' => false,
                'Correct answer' => true,
            ],
        ]);

        $result = $this->call_get_question($instance->id, 0);

        $this->assertTrue($result['hasquestion']);
        $this->assertCount(2, $result['options']);
        foreach ($result['options'] as $option) {
            $this->assertStringNotContainsString('<script>', $option['optiontext']);
        }
    }
}
