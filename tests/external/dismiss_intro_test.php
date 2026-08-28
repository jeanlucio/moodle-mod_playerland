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
 * External function tests for dismiss_intro.
 *
 * @package    mod_playerland
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerland\external;

use mod_playerland\external;

/**
 * Tests for the mod_playerland_dismiss_intro web service.
 *
 * @covers \mod_playerland\external
 */
final class dismiss_intro_test extends \advanced_testcase {
    /** @var \stdClass Course used by every test. */
    private \stdClass $course;

    /** @var \stdClass Enrolled student. */
    private \stdClass $student;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, 'student');
    }

    /**
     * Creates a playerland instance in the shared course.
     *
     * @return \stdClass Instance record with the ->cmid field added.
     */
    private function make_instance(): \stdClass {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');

        return $generator->create_instance(['course' => $this->course->id]);
    }

    /**
     * Tests that dismissing the intro sets the current user's preference to seen.
     *
     * @return void
     */
    public function test_dismiss_sets_user_preference(): void {
        $instance = $this->make_instance();
        $this->setUser($this->student);

        $this->assertFalse((bool) get_user_preferences('mod_playerland_introseen', false, $this->student->id));

        $result = external::dismiss_intro($instance->id);

        $this->assertTrue($result['status']);
        $this->assertTrue((bool) get_user_preferences('mod_playerland_introseen', false, $this->student->id));
    }

    /**
     * Tests that the preference is scoped to the calling user only, not shared globally.
     *
     * @return void
     */
    public function test_dismiss_does_not_affect_other_users(): void {
        $instance = $this->make_instance();
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($other->id, $this->course->id, 'student');

        $this->setUser($this->student);
        external::dismiss_intro($instance->id);

        $this->assertFalse((bool) get_user_preferences('mod_playerland_introseen', false, $other->id));
    }

    /**
     * Tests that an unknown playerland instance id is rejected rather than silently
     * marking the preference as seen.
     *
     * @return void
     */
    public function test_unknown_instance_is_rejected(): void {
        $this->setUser($this->student);

        $this->expectException(\dml_exception::class);
        external::dismiss_intro(999999);
    }
}
