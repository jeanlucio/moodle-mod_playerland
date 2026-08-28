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
 * External function tests for save_progress.
 *
 * @package    mod_playerland
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerland\external;

use context_module;
use core_external\external_api;
use mod_playerland\external;

/**
 * Tests for the mod_playerland_save_progress web service.
 *
 * @covers \mod_playerland\external
 */
final class save_progress_test extends \advanced_testcase {
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
     * @param array $overrides Instance field overrides.
     * @return \stdClass Instance record with the ->cmid field added.
     */
    private function make_instance(array $overrides = []): \stdClass {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
        $record = array_merge(['course' => $this->course->id], $overrides);

        return $generator->create_instance($record);
    }

    /**
     * Calls the mod_playerland_save_progress web service through the real dispatch
     * path, exercising sesskey, capability and parameter validation.
     *
     * @param array $args Web service arguments.
     * @return array Response shaped as ['error' => bool, 'data' => array|null, ...].
     */
    private function call_save_progress(array $args): array {
        $_POST['sesskey'] = sesskey();

        return external_api::call_external_function('mod_playerland_save_progress', $args);
    }

    /**
     * Tests that the very first call creates the attempt row and reports zero progress
     * when no question has actually been answered yet.
     *
     * @return void
     */
    public function test_creates_attempt_and_reports_zero_progress_initially(): void {
        global $DB;

        $instance = $this->make_instance(['targetquestions' => 3]);
        $this->setUser($this->student);

        $result = $this->call_save_progress(['playerlandid' => $instance->id, 'blocksresolved' => 0]);

        $this->assertFalse($result['error']);
        $this->assertSame(0, $result['data']['blocksresolved']);
        $this->assertSame(3, $result['data']['targetquestions']);
        $this->assertFalse($result['data']['complete']);
        $this->assertTrue($DB->record_exists('playerland_atmpt', [
            'playerlandid' => $instance->id,
            'userid' => $this->student->id,
        ]));
    }

    /**
     * Tests that the reported progress is always recomputed server-side from the
     * playerland_ans table, never trusting the client-supplied blocksresolved value —
     * a student calling this with a forged large number gets back the real count.
     *
     * @return void
     */
    public function test_client_supplied_blocksresolved_is_never_trusted(): void {
        $instance = $this->make_instance();
        $this->setUser($this->student);

        $result = $this->call_save_progress(['playerlandid' => $instance->id, 'blocksresolved' => 999]);

        $this->assertFalse($result['error']);
        $this->assertSame(0, $result['data']['blocksresolved']);
    }

    /**
     * Tests that progress is reported as complete once the recorded distinct correct
     * answers reach the configured target.
     *
     * @return void
     */
    public function test_reports_complete_once_target_is_reached(): void {
        global $DB;

        $instance = $this->make_instance(['targetquestions' => 1]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
        $question = $generator->create_question($instance->id);
        $this->setUser($this->student);

        $DB->insert_record('playerland_ans', (object) [
            'playerlandid' => $instance->id,
            'userid' => $this->student->id,
            'questionid' => $question->id,
            'timecreated' => time(),
        ]);

        $result = $this->call_save_progress(['playerlandid' => $instance->id, 'blocksresolved' => 0]);

        $this->assertFalse($result['error']);
        $this->assertSame(1, $result['data']['blocksresolved']);
        $this->assertTrue($result['data']['complete']);
    }

    /**
     * Tests that the mod/playerland:view capability is actually enforced, not just
     * declared — a role with it explicitly prohibited is denied.
     *
     * @return void
     */
    public function test_requires_view_capability(): void {
        $instance = $this->make_instance();
        $modcontext = context_module::instance($instance->cmid);

        $prohibitedrole = $this->getDataGenerator()->create_role();
        assign_capability('mod/playerland:view', CAP_PROHIBIT, $prohibitedrole, $modcontext);
        role_assign($prohibitedrole, $this->student->id, $modcontext);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($this->student);

        $this->expectException(\moodle_exception::class);
        external::save_progress($instance->id, 0);
    }

    /**
     * Tests that an unknown playerland instance id is rejected rather than silently
     * treated as valid.
     *
     * @return void
     */
    public function test_unknown_instance_is_rejected(): void {
        $this->setUser($this->student);

        $this->expectException(\dml_exception::class);
        external::save_progress(999999, 0);
    }
}
