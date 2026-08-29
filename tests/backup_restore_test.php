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
 * Backup and restore tests for mod_playerland.
 *
 * @package    mod_playerland
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerland;

use core_courseformat\local\cmactions;

/**
 * Tests for backup_playerland_activity_structure_step and
 * restore_playerland_activity_structure_step.
 *
 * @covers \backup_playerland_activity_structure_step
 * @covers \restore_playerland_activity_structure_step
 */
final class backup_restore_test extends \advanced_testcase {
    /** @var \mod_playerland_generator Plugin data generator. */
    private $generator;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
    }

    /**
     * Duplicates an activity via the real course duplication flow — a regression test
     * for a missing prepare_activity_structure() call in the restore step, which leaves
     * the restore's old-to-new context mapping unset. That mapping is what the generic
     * post-restore duplicate flow (renaming to "(copy)", moving the module, rebuilding
     * the course cache) and the generic calendar-events restore step both depend on;
     * without it, duplicating throws unknown_context_mapping and leaves the copy
     * invisible until caches are purged.
     *
     * @return void
     */
    public function test_duplicate_activity(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/playerland/lib.php');

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->generator->create_instance(['course' => $course->id]);
        $this->generator->create_question($instance->id, [
            'questiontext' => 'What is 2 + 2?',
            'answers' => ['4' => true, '5' => false],
        ]);

        $cm = get_coursemodule_from_instance('playerland', $instance->id, $course->id, false, MUST_EXIST);

        // Core's duplicate_module() is deprecated since Moodle 5.2 (MDL-86858), replaced by
        // cmactions::duplicate() — but that method doesn't exist before 5.2, so this must
        // stay guarded rather than switched outright while the plugin supports 4.5+5.2.
        if (method_exists(cmactions::class, 'duplicate')) {
            $newcm = (new cmactions($course))->duplicate($cm->id);
        } else {
            $newcm = duplicate_module($course, $cm);
        }

        $this->assertNotNull($newcm);
        $this->assertNotSame($cm->id, $newcm->id);
        $this->assertStringContainsString('(copy)', $newcm->name);

        $newinstance = $DB->get_record('playerland', ['id' => $newcm->instance], '*', MUST_EXIST);
        $this->assertSame(1, $DB->count_records('playerland_q', ['playerlandid' => $newinstance->id]));

        // No explicit cache purge here: this proves the context mapping (and therefore
        // the whole post-restore cleanup) actually ran, since a stale course cache is
        // exactly the symptom the missing mapping used to cause.
        $modinfo = get_fast_modinfo($course->id);
        $this->assertNotNull($modinfo->get_cm($newcm->id));

        // Regression guard: a restore step that also called playerland_grade_item_update()
        // in after_execute() would race against the generic grades-restore step and leave
        // two grade_items for the same instance.
        $this->assertSame(1, $DB->count_records('grade_items', [
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'playerland',
            'iteminstance' => $newinstance->id,
        ]));
    }

    /**
     * A full course backup/restore into a brand new course must carry every question and
     * its options intact — a regression test for the backup/restore checklist rule that
     * every install.xml column must be mirrored into the matching backup_nested_element()
     * attribute list.
     *
     * @return void
     */
    public function test_backup_restore_preserves_questions_and_options(): void {
        global $DB;
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->generator->create_instance([
            'course' => $course->id,
            'targetquestions' => 5,
            'map' => 'map_level009.json',
            'lesson1' => 'Fractions are parts of a whole.',
        ]);
        $originaltimemodified = time() - 12345;
        $question = $this->generator->create_question($instance->id, [
            'questiontext' => 'What is the capital of France?',
            'topic' => 1,
            'answers' => ['Paris' => true, 'Lyon' => false],
        ]);
        $DB->set_field('playerland_q', 'timemodified', $originaltimemodified, ['id' => $question->id]);

        $newcourse = $this->backup_and_restore_into_new_course($course);

        $newinstance = $DB->get_record('playerland', ['course' => $newcourse->id], '*', MUST_EXIST);
        $this->assertSame(5, (int)$newinstance->targetquestions);
        $this->assertSame('map_level009.json', $newinstance->map);
        $this->assertSame('Fractions are parts of a whole.', $newinstance->lesson1);

        $newquestion = $DB->get_record('playerland_q', ['playerlandid' => $newinstance->id], '*', MUST_EXIST);
        $this->assertSame('What is the capital of France?', $newquestion->questiontext);
        $this->assertSame(1, (int)$newquestion->topic);
        $this->assertSame($originaltimemodified, (int)$newquestion->timemodified);

        $newoptions = $DB->get_records('playerland_opts', ['questionid' => $newquestion->id], 'id ASC');
        $this->assertCount(2, $newoptions);
        $texts = array_map(fn($o) => $o->optiontext, array_values($newoptions));
        $this->assertEqualsCanonicalizing(['Paris', 'Lyon'], $texts);
    }

    /**
     * A full course backup/restore must carry each student's progress (attempts) and
     * distinct correct answers, with userid and questionid remapped to the restored
     * user and question — never left pointing at the old, no-longer-existing ids.
     *
     * @return void
     */
    public function test_backup_restore_remaps_attempts_and_answers(): void {
        global $DB;
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $instance = $this->generator->create_instance(['course' => $course->id]);
        $question = $this->generator->create_question($instance->id);

        $DB->insert_record('playerland_atmpt', (object)[
            'playerlandid' => $instance->id,
            'userid' => $user->id,
            'currentlevel' => 2,
            'blocksresolved' => 3,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('playerland_ans', (object)[
            'playerlandid' => $instance->id,
            'userid' => $user->id,
            'questionid' => $question->id,
            'timecreated' => time(),
        ]);

        $newcourse = $this->backup_and_restore_into_new_course($course);

        $newinstance = $DB->get_record('playerland', ['course' => $newcourse->id], '*', MUST_EXIST);
        $newquestion = $DB->get_record('playerland_q', ['playerlandid' => $newinstance->id], '*', MUST_EXIST);

        $newattempt = $DB->get_record('playerland_atmpt', ['playerlandid' => $newinstance->id], '*', MUST_EXIST);
        $this->assertSame((int)$user->id, (int)$newattempt->userid);
        $this->assertSame(3, (int)$newattempt->blocksresolved);

        $newanswer = $DB->get_record('playerland_ans', ['playerlandid' => $newinstance->id], '*', MUST_EXIST);
        $this->assertSame((int)$user->id, (int)$newanswer->userid);
        // The critical remap: the restored answer must point at the NEW question id, not
        // the old (by-now-nonexistent, in a different course) one.
        $this->assertSame((int)$newquestion->id, (int)$newanswer->questionid);
        $this->assertNotSame((int)$question->id, (int)$newanswer->questionid);
    }

    /**
     * Backing up without user data (userinfo disabled) must omit attempts and answers
     * entirely, while still restoring the questions/options that belong to the activity
     * itself rather than to any one student.
     *
     * @return void
     */
    public function test_backup_without_userinfo_omits_attempts_and_answers(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $instance = $this->generator->create_instance(['course' => $course->id]);
        $this->generator->create_question($instance->id);
        $DB->insert_record('playerland_atmpt', (object)[
            'playerlandid' => $instance->id,
            'userid' => $user->id,
            'currentlevel' => 1,
            'blocksresolved' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $admin = get_admin();
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $admin->id
        );
        $bc->get_plan()->get_setting('users')->set_value(false);
        $bc->execute_plan();
        $backupfile = $bc->get_results()['backup_destination'];
        $bc->destroy();

        $newcourse = $this->getDataGenerator()->create_course();
        $tempdir = \restore_controller::get_tempdir_name($newcourse->id, $admin->id);
        $fp = get_file_packer('application/vnd.moodle.backup');
        $backupfile->extract_to_pathname($fp, make_backup_temp_directory($tempdir));

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

        $newinstance = $DB->get_record('playerland', ['course' => $newcourse->id], '*', MUST_EXIST);
        $this->assertSame(1, $DB->count_records('playerland_q', ['playerlandid' => $newinstance->id]));
        $this->assertSame(0, $DB->count_records('playerland_atmpt', ['playerlandid' => $newinstance->id]));
        $this->assertSame(0, $DB->count_records('playerland_ans', ['playerlandid' => $newinstance->id]));
    }

    /**
     * Backs up the given course and restores it into a brand new course, returning that
     * course. Mirrors block_playerhud's and mod_playerwords's own full-course
     * backup/restore test pattern.
     *
     * @param \stdClass $course Source course.
     * @return \stdClass The new course the backup was restored into.
     */
    private function backup_and_restore_into_new_course(\stdClass $course): \stdClass {
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
        $bc->execute_plan();
        $backupfile = $bc->get_results()['backup_destination'];
        $bc->destroy();

        $newcourse = $this->getDataGenerator()->create_course();
        $tempdir = \restore_controller::get_tempdir_name($newcourse->id, $admin->id);
        $fp = get_file_packer('application/vnd.moodle.backup');
        $backupfile->extract_to_pathname($fp, make_backup_temp_directory($tempdir));

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
}
