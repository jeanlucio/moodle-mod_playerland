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

namespace mod_playerland\event;

use advanced_testcase;

/**
 * Tests that restoring a backup with logs points the viewed event at the restored activity.
 *
 * Without a mapping the core prints a debugging message for every restored log record and copies
 * the id as it was, which may now name another activity. The check is end to end: a real event, a
 * real backup with logs and a real restore into a second course.
 *
 * @package    mod_playerland
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_playerland\event\course_module_viewed
 */
final class restore_mapping_test extends advanced_testcase {
    /**
     * Backs the course up with its logs and restores it into a new course.
     *
     * @param \stdClass $course Source course.
     * @return \stdClass The course the backup was restored into.
     */
    private function backup_with_logs_and_restore(\stdClass $course): \stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $admin = get_admin();
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $admin->id
        );
        $bc->get_plan()->get_setting('logs')->set_value(true);
        $bc->execute_plan();
        $backupfile = $bc->get_results()['backup_destination'];
        $bc->destroy();

        $newcourse = $this->getDataGenerator()->create_course();
        $tempdir = \restore_controller::get_tempdir_name($newcourse->id, $admin->id);
        $backupfile->extract_to_pathname(
            get_file_packer('application/vnd.moodle.backup'),
            make_backup_temp_directory($tempdir)
        );
        $rc = new \restore_controller(
            $tempdir,
            $newcourse->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $admin->id,
            \backup::TARGET_EXISTING_ADDING
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        return $newcourse;
    }

    /**
     * The restored log record points at the restored activity, not at the original id.
     */
    public function test_restored_viewed_log_points_at_the_restored_activity(): void {
        global $DB;

        $this->resetAfterTest();
        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);
        $this->setAdminUser();

        // An activity in another course first, so that the restored id cannot equal the original one.
        $other = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('playerland', ['course' => $other->id]);
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('playerland', ['course' => $course->id]);
        // Triggered by an enrolled teacher: a log record whose user is not in the backup is skipped on
        // restore, with or without a mapping.
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $context = \context_module::instance($module->cmid);

        $this->setUser($teacher);
        course_module_viewed::create(['objectid' => $module->id, 'context' => $context])->trigger();
        $this->setAdminUser();

        $newcourse = $this->backup_with_logs_and_restore($course);

        $newmodule = $DB->get_record('playerland', ['course' => $newcourse->id], '*', MUST_EXIST);
        $this->assertNotEquals($module->id, $newmodule->id);
        $restored = $DB->get_record('logstore_standard_log', [
            'eventname' => '\\mod_playerland\\event\\course_module_viewed',
            'courseid' => $newcourse->id,
        ], '*', MUST_EXIST);
        $this->assertEquals($newmodule->id, $restored->objectid);
    }
}
