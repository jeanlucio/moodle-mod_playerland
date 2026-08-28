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
 * Unit tests for mod_playerland_mod_form's custom validation rules.
 *
 * @package    mod_playerland
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerland;

/**
 * Tests for mod_playerland_mod_form::validation().
 *
 * @covers \mod_playerland_mod_form
 */
final class mod_form_test extends \advanced_testcase {
    /** @var \stdClass Course used by every test. */
    private \stdClass $course;

    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/mod/playerland/mod_form.php');
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * Instantiates mod_playerland_mod_form for an existing instance, enough to run
     * validation() against.
     *
     * @return \mod_playerland_mod_form
     */
    private function build_form(): \mod_playerland_mod_form {
        global $PAGE;

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
        $instance = $generator->create_instance(['course' => $this->course->id]);
        $cm = get_coursemodule_from_instance('playerland', $instance->id);

        $PAGE->set_course($this->course);

        $data = (object) [
            'instance' => $instance->id,
            'id' => $cm->id,
            'course' => $this->course->id,
        ];

        return new \mod_playerland_mod_form($data, 0, $cm, $this->course);
    }

    /**
     * Builds a minimal, well-formed submission the parent moodleform_mod::validation()
     * can process without missing-key notices, overridable per test.
     *
     * @param array $overrides Fields to override on top of the base submission.
     * @return array The submitted data array.
     */
    private function base_submission(array $overrides = []): array {
        $data = [
            'name' => 'A Trilha',
            'modulename' => 'playerland',
            'instance' => 0,
            'coursemodule' => 0,
            'cmidnumber' => '',
            'availabilityconditionsjson' => '',
            'levels' => 1,
            'targetquestions' => 3,
            'lesson1' => '',
            'lesson2' => '',
            'lesson3' => '',
        ];

        return array_merge($data, $overrides);
    }

    /**
     * Tests that a non-positive levels value is rejected.
     *
     * @return void
     */
    public function test_rejects_non_positive_levels(): void {
        $form = $this->build_form();

        $errors = $form->validation($this->base_submission(['levels' => 0]), []);

        $this->assertArrayHasKey('levels', $errors);
        $this->assertSame(get_string('err_positiveint', 'mod_playerland'), $errors['levels']);
    }

    /**
     * Tests that a non-positive targetquestions value is rejected.
     *
     * @return void
     */
    public function test_rejects_non_positive_targetquestions(): void {
        $form = $this->build_form();

        $errors = $form->validation($this->base_submission(['targetquestions' => 0]), []);

        $this->assertArrayHasKey('targetquestions', $errors);
        $this->assertSame(get_string('err_positiveint', 'mod_playerland'), $errors['targetquestions']);
    }

    /**
     * Tests that valid positive values raise no error on either field.
     *
     * @return void
     */
    public function test_accepts_valid_positive_values(): void {
        $form = $this->build_form();

        $errors = $form->validation($this->base_submission(['levels' => 2, 'targetquestions' => 5]), []);

        $this->assertArrayNotHasKey('levels', $errors);
        $this->assertArrayNotHasKey('targetquestions', $errors);
    }

    /**
     * Tests that a mini-lesson longer than the configured maximum is rejected, and that
     * only the offending slot is flagged.
     *
     * @return void
     */
    public function test_rejects_lesson_over_max_length(): void {
        $form = $this->build_form();
        $toolong = str_repeat('a', \mod_playerland_mod_form::LESSON_MAXLENGTH + 1);

        $errors = $form->validation($this->base_submission(['lesson1' => $toolong]), []);

        $this->assertArrayHasKey('lesson1', $errors);
        $this->assertArrayNotHasKey('lesson2', $errors);
        $this->assertArrayNotHasKey('lesson3', $errors);
    }

    /**
     * Tests that a mini-lesson exactly at the maximum length is accepted (the limit is
     * inclusive).
     *
     * @return void
     */
    public function test_accepts_lesson_at_max_length(): void {
        $form = $this->build_form();
        $exact = str_repeat('a', \mod_playerland_mod_form::LESSON_MAXLENGTH);

        $errors = $form->validation($this->base_submission(['lesson1' => $exact]), []);

        $this->assertArrayNotHasKey('lesson1', $errors);
    }
}
