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
 * Tests for question_list_service.
 *
 * @package    mod_playerland
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerland\local;

/**
 * Tests for question_list_service::build_list_context().
 *
 * @covers \mod_playerland\local\question_list_service
 */
final class question_list_service_test extends \advanced_testcase {
    /** @var \mod_playerland_generator Plugin data generator. */
    private $generator;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
    }

    /**
     * With no questions configured, the context reports an empty list and still
     * includes the add/back links.
     *
     * @return void
     */
    public function test_empty_pool(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('core');

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->generator->create_instance(['course' => $course->id]);

        $context = question_list_service::build_list_context($instance, (int)$instance->cmid, $output);

        $this->assertFalse($context['hasquestions']);
        $this->assertSame([], $context['questions']);
        $this->assertStringContainsString('action=add', $context['addurl']);
        $this->assertStringContainsString('view.php', $context['backurl']);
    }

    /**
     * A configured question is returned with its formatted text, resolved topic
     * label, and edit/delete URLs carrying the right ids and a sesskey.
     *
     * @return void
     */
    public function test_question_row_shape(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('core');

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->generator->create_instance(['course' => $course->id]);
        $question = $this->generator->create_question($instance->id, [
            'questiontext' => '<script>alert(1)</script>',
            'topic' => 1,
        ]);

        $context = question_list_service::build_list_context($instance, (int)$instance->cmid, $output);

        $this->assertTrue($context['hasquestions']);
        $this->assertCount(1, $context['questions']);

        $row = $context['questions'][0];
        $this->assertStringNotContainsString('<script>', $row['questiontext']);
        $this->assertSame(get_string('lessonnum', 'mod_playerland', 1), $row['topiclabel']);
        $this->assertStringContainsString('qid=' . $question->id, $row['editurl']);
        $this->assertStringContainsString('action=edit', $row['editurl']);
        $this->assertStringContainsString('qid=' . $question->id, $row['deleteurl']);
        $this->assertStringContainsString('action=delete', $row['deleteurl']);
        $this->assertStringContainsString('sesskey=', $row['deleteurl']);
    }

    /**
     * A question left with the default topic (0) resolves to the general-pool label,
     * not one of the lesson labels.
     *
     * @return void
     */
    public function test_general_topic_label(): void {
        global $PAGE;
        $output = $PAGE->get_renderer('core');

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->generator->create_instance(['course' => $course->id]);
        $this->generator->create_question($instance->id, ['topic' => 0]);

        $context = question_list_service::build_list_context($instance, (int)$instance->cmid, $output);

        $this->assertSame(
            get_string('questiontopicgeneral', 'mod_playerland'),
            $context['questions'][0]['topiclabel']
        );
    }
}
