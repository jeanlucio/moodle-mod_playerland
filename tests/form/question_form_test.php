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
 * Unit tests for mod_playerland\form\question_form's custom validation rule.
 *
 * @package    mod_playerland
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerland\form;

/**
 * Tests for question_form::validation(). Every other element on this form (the
 * question text editor, the topic select, the four option/iscorrect groups) relies
 * entirely on formslib's own client-side rules, with no server-side logic of its own
 * beyond this one check.
 *
 * @covers \mod_playerland\form\question_form
 */
final class question_form_test extends \advanced_testcase {
    /**
     * Builds a question_form instance, mirroring how manage_questions.php constructs it.
     *
     * @return question_form
     */
    private function build_form(): question_form {
        $url = new \moodle_url('/mod/playerland/manage_questions.php', ['id' => 1]);

        return new question_form($url, null, 'post', '', ['class' => 'ignoredirty']);
    }

    /**
     * Tests that omitting a correct option is rejected.
     *
     * @return void
     */
    public function test_validation_rejects_missing_correct_option(): void {
        $form = $this->build_form();

        $errors = $form->validation(['optiontext' => ['1' => 'A', '2' => 'B']], []);

        $this->assertArrayHasKey('iscorrect', $errors);
        $this->assertSame(get_string('error_no_correct_option', 'mod_playerland'), $errors['iscorrect']);
    }

    /**
     * Tests that a zero/empty iscorrect value (the PARAM_INT default for an unset
     * radio group) is treated the same as missing.
     *
     * @return void
     */
    public function test_validation_rejects_zero_correct_option(): void {
        $form = $this->build_form();

        $errors = $form->validation(['iscorrect' => 0], []);

        $this->assertArrayHasKey('iscorrect', $errors);
    }

    /**
     * Tests that selecting any option as correct passes validation.
     *
     * @return void
     */
    public function test_validation_accepts_a_selected_correct_option(): void {
        $form = $this->build_form();

        $errors = $form->validation(['iscorrect' => 2], []);

        $this->assertArrayNotHasKey('iscorrect', $errors);
    }
}
