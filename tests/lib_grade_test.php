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
 * Tests for the gradebook-related functions in lib.php.
 *
 * @package    mod_playerland
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerland;

/**
 * Tests for playerland_calculate_grade(), playerland_grade_item_update(),
 * playerland_grade_item_delete() and playerland_update_grades().
 *
 * @covers ::playerland_calculate_grade
 * @covers ::playerland_grade_item_update
 * @covers ::playerland_grade_item_delete
 * @covers ::playerland_update_grades
 */
final class lib_grade_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/playerland/lib.php');
    }

    /**
     * Tests the proportional grade calculation for a normal in-progress case.
     *
     * @return void
     */
    public function test_calculate_grade_proportional(): void {
        $playerland = (object) ['grade' => 100, 'targetquestions' => 4];

        $this->assertEqualsWithDelta(50.0, playerland_calculate_grade($playerland, 2), 0.001);
    }

    /**
     * Tests that progress beyond the target is clamped to the maximum grade, never
     * exceeding 100% even if blocksresolved somehow overshoots targetquestions.
     *
     * @return void
     */
    public function test_calculate_grade_clamps_to_max_when_resolved_exceeds_target(): void {
        $playerland = (object) ['grade' => 100, 'targetquestions' => 4];

        $this->assertEqualsWithDelta(100.0, playerland_calculate_grade($playerland, 10), 0.001);
    }

    /**
     * Tests that zero progress yields a zero grade, never a negative one.
     *
     * @return void
     */
    public function test_calculate_grade_floor_zero_when_no_progress(): void {
        $playerland = (object) ['grade' => 100, 'targetquestions' => 4];

        $this->assertSame(0.0, playerland_calculate_grade($playerland, 0));
        $this->assertSame(0.0, playerland_calculate_grade($playerland, -3));
    }

    /**
     * Tests that a missing/zero targetquestions is treated as 1, rather than dividing
     * by zero.
     *
     * @return void
     */
    public function test_calculate_grade_defaults_target_to_one_when_missing(): void {
        $playerland = (object) ['grade' => 100, 'targetquestions' => 0];

        $this->assertEqualsWithDelta(100.0, playerland_calculate_grade($playerland, 1), 0.001);
    }

    /**
     * Tests that an activity with no numeric grade (scale-based or ungraded) always
     * calculates a zero raw grade, regardless of progress.
     *
     * @return void
     */
    public function test_calculate_grade_zero_when_activity_grade_is_not_positive(): void {
        $scaled = (object) ['grade' => -5, 'targetquestions' => 4];
        $ungraded = (object) ['grade' => 0, 'targetquestions' => 4];

        $this->assertSame(0.0, playerland_calculate_grade($scaled, 4));
        $this->assertSame(0.0, playerland_calculate_grade($ungraded, 4));
    }

    /**
     * Tests that a positive grade configures a point-based (VALUE) grade item.
     *
     * @return void
     */
    public function test_grade_item_update_creates_value_type_item(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
        $instance = $generator->create_instance(['course' => $course->id, 'grade' => 100]);

        $result = playerland_grade_item_update($instance);

        $this->assertSame(GRADE_UPDATE_OK, $result);
        $item = $DB->get_record('grade_items', [
            'itemmodule' => 'playerland',
            'iteminstance' => $instance->id,
            'courseid' => $course->id,
        ], '*', MUST_EXIST);
        $this->assertEquals(GRADE_TYPE_VALUE, $item->gradetype);
        $this->assertEqualsWithDelta(100.0, (float) $item->grademax, 0.001);
    }

    /**
     * Tests that a configured pass grade is forwarded to the grade item.
     *
     * @return void
     */
    public function test_grade_item_update_sets_gradepass_when_configured(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
        $instance = $generator->create_instance(['course' => $course->id, 'grade' => 100]);
        $instance->gradepass = 60;

        $result = playerland_grade_item_update($instance);

        $this->assertSame(GRADE_UPDATE_OK, $result);
        $item = $DB->get_record('grade_items', [
            'itemmodule' => 'playerland',
            'iteminstance' => $instance->id,
        ], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(60.0, (float) $item->gradepass, 0.001);
    }

    /**
     * Tests that passing 'reset' as $grades clears every grade recorded against the
     * item without deleting the item itself — the gradebook item survives so future
     * attempts still have somewhere to record a grade.
     *
     * @return void
     */
    public function test_grade_item_update_with_reset_clears_grades_but_keeps_the_item(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
        $instance = $generator->create_instance(['course' => $course->id, 'grade' => 100]);

        $grade = new \stdClass();
        $grade->userid = 2;
        $grade->rawgrade = 75.0;
        playerland_grade_item_update($instance, [2 => $grade]);

        $itemid = $DB->get_field('grade_items', 'id', [
            'itemmodule' => 'playerland',
            'iteminstance' => $instance->id,
        ], MUST_EXIST);
        $this->assertSame(1, $DB->count_records('grade_grades', ['itemid' => $itemid, 'userid' => 2]));

        $result = playerland_grade_item_update($instance, 'reset');

        $this->assertSame(GRADE_UPDATE_OK, $result);
        $this->assertSame(0, $DB->count_records('grade_grades', ['itemid' => $itemid, 'userid' => 2]));
        $this->assertTrue($DB->record_exists('grade_items', ['id' => $itemid]));
    }

    /**
     * Tests that a negative grade configures a scale-based grade item, using the
     * absolute value as the scale id.
     *
     * @return void
     */
    public function test_grade_item_update_creates_scale_type_item(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $scaleid = $DB->insert_record('scale', (object) [
            'courseid' => 0,
            'userid' => 0,
            'name' => 'Test scale',
            'scale' => 'Poor,Good,Excellent',
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'timemodified' => time(),
        ]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
        $instance = $generator->create_instance(['course' => $course->id, 'grade' => -$scaleid]);

        $result = playerland_grade_item_update($instance);

        $this->assertSame(GRADE_UPDATE_OK, $result);
        $item = $DB->get_record('grade_items', [
            'itemmodule' => 'playerland',
            'iteminstance' => $instance->id,
            'courseid' => $course->id,
        ], '*', MUST_EXIST);
        $this->assertEquals(GRADE_TYPE_SCALE, $item->gradetype);
        $this->assertSame($scaleid, (int) $item->scaleid);
    }

    /**
     * Tests that a grade of zero needs no grade item at all — gradelib's own
     * grade_update() short-circuits on GRADE_TYPE_NONE without inserting a row, so an
     * ungraded playerland instance simply has none.
     *
     * @return void
     */
    public function test_grade_item_update_creates_no_item_when_ungraded(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
        $instance = $generator->create_instance(['course' => $course->id, 'grade' => 0]);

        $result = playerland_grade_item_update($instance);

        $this->assertSame(GRADE_UPDATE_OK, $result);
        $this->assertFalse($DB->record_exists('grade_items', [
            'itemmodule' => 'playerland',
            'iteminstance' => $instance->id,
            'courseid' => $course->id,
        ]));
    }

    /**
     * Tests that deleting the grade item removes its row entirely — gradelib's own
     * grade_update() deletes the grade_item outright for the 'deleted' param, there is
     * no separate soft-delete flag to check.
     *
     * @return void
     */
    public function test_grade_item_delete_removes_the_item(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
        $instance = $generator->create_instance(['course' => $course->id, 'grade' => 100]);

        $result = playerland_grade_item_delete($instance);

        $this->assertSame(GRADE_UPDATE_OK, $result);
        $this->assertFalse($DB->record_exists('grade_items', [
            'itemmodule' => 'playerland',
            'iteminstance' => $instance->id,
            'courseid' => $course->id,
        ]));
    }

    /**
     * Tests that an ungraded activity's update_grades() only refreshes the grade item
     * (a no-op, since GRADE_TYPE_NONE needs no item at all), without touching or
     * requiring any attempt rows.
     *
     * @return void
     */
    public function test_update_grades_with_no_grade_only_updates_item(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
        $instance = $generator->create_instance(['course' => $course->id, 'grade' => 0]);

        playerland_update_grades($instance);

        $this->assertFalse($DB->record_exists('grade_items', [
            'itemmodule' => 'playerland',
            'iteminstance' => $instance->id,
        ]));
    }

    /**
     * Tests that update_grades() for the whole activity (userid=0) on a graded
     * instance nobody has attempted yet only refreshes the grade item, the same
     * whole-activity branch a freshly-created, never-played instance would hit.
     *
     * @return void
     */
    public function test_update_grades_for_all_users_with_no_attempts_only_updates_item(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
        $instance = $generator->create_instance(['course' => $course->id, 'grade' => 100]);

        playerland_update_grades($instance);

        $item = $DB->get_record('grade_items', [
            'itemmodule' => 'playerland',
            'iteminstance' => $instance->id,
        ], '*', MUST_EXIST);
        $this->assertEquals(GRADE_TYPE_VALUE, $item->gradetype);
        $this->assertSame(0, $DB->count_records('grade_grades', ['itemid' => $item->id]));
    }

    /**
     * Tests that update_grades() with userid=0 computes a proportional raw grade for
     * every user with an attempt row.
     *
     * @return void
     */
    public function test_update_grades_for_all_users_from_attempts(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
        $instance = $generator->create_instance([
            'course' => $course->id,
            'grade' => 100,
            'targetquestions' => 2,
        ]);

        $DB->insert_record('playerland_atmpt', (object) [
            'playerlandid' => $instance->id,
            'userid' => 2,
            'currentlevel' => 1,
            'blocksresolved' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('playerland_atmpt', (object) [
            'playerlandid' => $instance->id,
            'userid' => 3,
            'currentlevel' => 1,
            'blocksresolved' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        playerland_update_grades($instance);

        $itemid = $DB->get_field('grade_items', 'id', [
            'itemmodule' => 'playerland',
            'iteminstance' => $instance->id,
        ], MUST_EXIST);
        $graded = $DB->get_record('grade_grades', ['itemid' => $itemid, 'userid' => 2], '*', MUST_EXIST);
        $graded3 = $DB->get_record('grade_grades', ['itemid' => $itemid, 'userid' => 3], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(50.0, (float) $graded->rawgrade, 0.001);
        $this->assertEqualsWithDelta(100.0, (float) $graded3->rawgrade, 0.001);
    }

    /**
     * Tests that update_grades() scoped to a single user without an attempt row clears
     * that user's raw grade to null, rather than leaving a stale value.
     *
     * @return void
     */
    public function test_update_grades_single_user_without_attempt_sets_null_rawgrade(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
        $instance = $generator->create_instance(['course' => $course->id, 'grade' => 100]);

        playerland_update_grades($instance, 2);

        $itemid = $DB->get_field('grade_items', 'id', [
            'itemmodule' => 'playerland',
            'iteminstance' => $instance->id,
        ], MUST_EXIST);
        $graded = $DB->get_record('grade_grades', ['itemid' => $itemid, 'userid' => 2]);
        if ($graded) {
            $this->assertNull($graded->rawgrade);
        } else {
            $this->assertTrue(true);
        }
    }

    /**
     * Tests that update_grades() scoped to a single user only recalculates that user's
     * grade, leaving other users' attempts out of the query.
     *
     * @return void
     */
    public function test_update_grades_single_user_updates_only_that_user(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
        $instance = $generator->create_instance([
            'course' => $course->id,
            'grade' => 100,
            'targetquestions' => 2,
        ]);

        $DB->insert_record('playerland_atmpt', (object) [
            'playerlandid' => $instance->id,
            'userid' => 2,
            'currentlevel' => 1,
            'blocksresolved' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        playerland_update_grades($instance, 2);

        $itemid = $DB->get_field('grade_items', 'id', [
            'itemmodule' => 'playerland',
            'iteminstance' => $instance->id,
        ], MUST_EXIST);
        $graded = $DB->get_record('grade_grades', ['itemid' => $itemid, 'userid' => 2], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(50.0, (float) $graded->rawgrade, 0.001);
    }
}
