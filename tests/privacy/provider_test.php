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
 * Privacy provider tests for mod_playerland.
 *
 * @package    mod_playerland
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerland\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Tests for the Privacy API provider.
 *
 * @covers \mod_playerland\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Creates a playerland course module and returns the cm record.
     *
     * @param \stdClass $course Course object.
     * @return \stdClass Course module record.
     */
    private function make_cm(\stdClass $course): \stdClass {
        return $this->getDataGenerator()->create_module('playerland', ['course' => $course->id]);
    }

    /**
     * Inserts one attempt record for the given user and activity.
     *
     * @param int $userid User ID.
     * @param int $playerlandid Activity instance ID.
     * @param int $blocksresolved Distinct correct answers so far.
     * @return int Inserted attempt ID.
     */
    private function make_attempt(int $userid, int $playerlandid, int $blocksresolved = 1): int {
        global $DB;

        return $DB->insert_record('playerland_atmpt', (object) [
            'playerlandid' => $playerlandid,
            'userid' => $userid,
            'currentlevel' => 1,
            'blocksresolved' => $blocksresolved,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Inserts one distinct-correct-answer record for the given user and activity.
     *
     * @param int $userid User ID.
     * @param int $playerlandid Activity instance ID.
     * @param int $questionid Question ID.
     * @return int Inserted answer ID.
     */
    private function make_answer(int $userid, int $playerlandid, int $questionid): int {
        global $DB;

        return $DB->insert_record('playerland_ans', (object) [
            'playerlandid' => $playerlandid,
            'userid' => $userid,
            'questionid' => $questionid,
            'timecreated' => time(),
        ]);
    }

    /**
     * Tests that get_metadata declares both personal-data tables.
     *
     * @return void
     */
    public function test_get_metadata_declares_expected_tables(): void {
        $collection = provider::get_metadata(new collection('mod_playerland'));
        $keys = array_map(fn($item) => $item->get_name(), $collection->get_collection());

        $this->assertContains('playerland_atmpt', $keys);
        $this->assertContains('playerland_ans', $keys);
    }

    /**
     * Tests that every real column of playerland_atmpt and playerland_ans (minus the
     * structural id and the playerlandid foreign key, neither of which is itself
     * personal data) is declared in get_metadata(). Asserted against the real schema
     * via $DB->get_columns() rather than a fixed key list, so a future column silently
     * added to install.xml without a privacy decision fails this test instead of just
     * going undeclared by omission.
     *
     * @return void
     */
    public function test_get_metadata_every_column_is_declared(): void {
        global $DB;

        $collection = provider::get_metadata(new collection('mod_playerland'));
        $items = [];
        foreach ($collection->get_collection() as $item) {
            $items[$item->get_name()] = $item;
        }

        foreach (['playerland_atmpt', 'playerland_ans'] as $table) {
            $this->assertArrayHasKey($table, $items);
            $declared = array_keys($items[$table]->get_privacy_fields());
            $real = array_diff(array_keys($DB->get_columns($table)), ['id', 'playerlandid']);
            $this->assertEmpty(array_diff($real, $declared), "Undeclared column in $table.");
        }
    }

    /**
     * Tests that get_metadata declares the first-load intro overlay preference.
     *
     * @return void
     */
    public function test_get_metadata_declares_intro_preference(): void {
        $collection = provider::get_metadata(new collection('mod_playerland'));
        $keys = array_map(fn($item) => $item->get_name(), $collection->get_collection());

        $this->assertContains(provider::INTROSEEN_PREFERENCE, $keys);
    }

    /**
     * A user who never had the intro preference set exports no preference data.
     *
     * @return void
     */
    public function test_export_user_preferences_no_pref(): void {
        $user = $this->getDataGenerator()->create_user();

        provider::export_user_preferences($user->id);

        $writer = writer::with_context(\context_system::instance());
        $this->assertFalse($writer->has_any_data());
    }

    /**
     * A user who has seen the intro exports exactly that one preference, under the
     * mod_playerland component.
     *
     * @return void
     */
    public function test_export_user_preferences_seen(): void {
        $user = $this->getDataGenerator()->create_user();
        set_user_preference(provider::INTROSEEN_PREFERENCE, 1, $user->id);

        provider::export_user_preferences($user->id);

        $writer = writer::with_context(\context_system::instance());
        $this->assertTrue($writer->has_any_data());

        $prefs = (array) $writer->get_user_preferences('mod_playerland');
        $this->assertCount(1, $prefs);
        $this->assertArrayHasKey(provider::INTROSEEN_PREFERENCE, $prefs);
    }

    /**
     * Tests that get_contexts_for_userid finds the context via an attempt row.
     *
     * @return void
     */
    public function test_get_contexts_for_userid_via_attempt(): void {
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_cm($course);
        $user = $this->getDataGenerator()->create_user();
        $this->make_attempt($user->id, (int) $cm->id);

        $contextlist = provider::get_contexts_for_userid($user->id);

        $expected = \context_module::instance($cm->cmid)->id;
        $this->assertContains((string) $expected, $contextlist->get_contextids());
    }

    /**
     * Tests that get_contexts_for_userid also finds the context via a standalone
     * answer row, independent of the attempt-row query.
     *
     * @return void
     */
    public function test_get_contexts_for_userid_via_answer_only(): void {
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_cm($course);
        $user = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
        $question = $generator->create_question((int) $cm->id);
        $this->make_answer($user->id, (int) $cm->id, $question->id);

        $contextlist = provider::get_contexts_for_userid($user->id);

        $expected = \context_module::instance($cm->cmid)->id;
        $this->assertContains((string) $expected, $contextlist->get_contextids());
    }

    /**
     * Tests that a user with no attempts or answers anywhere gets an empty contextlist.
     *
     * @return void
     */
    public function test_get_contexts_for_userid_empty_when_no_data(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->make_cm($course);
        $user = $this->getDataGenerator()->create_user();

        $contextlist = provider::get_contexts_for_userid($user->id);

        $this->assertEmpty($contextlist->get_contextids());
    }

    /**
     * Tests that get_users_in_context returns every user with an attempt in it.
     *
     * @return void
     */
    public function test_get_users_in_context(): void {
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_cm($course);
        $usera = $this->getDataGenerator()->create_user();
        $userb = $this->getDataGenerator()->create_user();
        $this->make_attempt($usera->id, (int) $cm->id);
        $this->make_attempt($userb->id, (int) $cm->id);

        $userlist = new userlist(\context_module::instance($cm->cmid), 'mod_playerland');
        provider::get_users_in_context($userlist);

        $this->assertContains((int) $usera->id, $userlist->get_userids());
        $this->assertContains((int) $userb->id, $userlist->get_userids());
    }

    /**
     * Tests that get_users_in_context is a silent no-op for a non-module context.
     *
     * @return void
     */
    public function test_get_users_in_context_ignores_non_module_context(): void {
        $userlist = new userlist(\context_system::instance(), 'mod_playerland');

        provider::get_users_in_context($userlist);

        $this->assertSame([], $userlist->get_userids());
    }

    /**
     * Regression guard: a page module whose course_modules row was made to carry the
     * same numeric instance id as a real playerland activity must never be mistaken
     * for it — every query in provider.php joins through {modules}.name =
     * 'playerland', not a bare instance lookup, precisely to prevent this.
     *
     * @return void
     */
    public function test_get_users_in_context_ignores_colliding_instance_id_from_other_module_type(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_cm($course);
        $student = $this->getDataGenerator()->create_user();
        $this->make_attempt($student->id, (int) $cm->id);

        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $DB->set_field('course_modules', 'instance', $cm->id, ['id' => $page->cmid]);

        $userlist = new userlist(\context_module::instance($page->cmid), 'mod_playerland');
        provider::get_users_in_context($userlist);

        $this->assertSame([], $userlist->get_userids());
    }

    /**
     * Tests that export_user_data writes both the attempt and the answer data for the
     * user's context.
     *
     * @return void
     */
    public function test_export_user_data_exports_attempts_and_answers(): void {
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_cm($course);
        $user = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
        $question = $generator->create_question((int) $cm->id);
        $this->make_attempt($user->id, (int) $cm->id, 3);
        $this->make_answer($user->id, (int) $cm->id, $question->id);

        $context = \context_module::instance($cm->cmid);
        provider::export_user_data(new approved_contextlist($user, 'mod_playerland', [$context->id]));

        $attemptdata = writer::with_context($context)->get_data([
            get_string('privacy:metadata:playerland_atmpt', 'mod_playerland'),
        ]);
        $answerdata = writer::with_context($context)->get_data([
            get_string('privacy:metadata:playerland_ans', 'mod_playerland'),
        ]);

        $this->assertCount(1, $attemptdata->attempts);
        $this->assertSame(3, (int) $attemptdata->attempts[0]->blocksresolved);
        $this->assertCount(1, $answerdata->answers);
        $this->assertSame((int) $question->id, (int) $answerdata->answers[0]->questionid);
    }

    /**
     * Tests that export_user_data keeps each context's data separate when the
     * approved list spans several activities.
     *
     * @return void
     */
    public function test_export_user_data_across_multiple_contexts(): void {
        $course = $this->getDataGenerator()->create_course();
        $cm1 = $this->make_cm($course);
        $cm2 = $this->make_cm($course);
        $user = $this->getDataGenerator()->create_user();
        $this->make_attempt($user->id, (int) $cm1->id);
        $this->make_attempt($user->id, (int) $cm2->id, 2);

        $context1 = \context_module::instance($cm1->cmid);
        $context2 = \context_module::instance($cm2->cmid);
        provider::export_user_data(new approved_contextlist($user, 'mod_playerland', [$context1->id, $context2->id]));

        $data1 = writer::with_context($context1)->get_data([
            get_string('privacy:metadata:playerland_atmpt', 'mod_playerland'),
        ]);
        $data2 = writer::with_context($context2)->get_data([
            get_string('privacy:metadata:playerland_atmpt', 'mod_playerland'),
        ]);

        $this->assertSame(1, (int) $data1->attempts[0]->blocksresolved);
        $this->assertSame(2, (int) $data2->attempts[0]->blocksresolved);
    }

    /**
     * Tests that a non-module context slipped into the approved contextlist is
     * silently skipped rather than erroring.
     *
     * @return void
     */
    public function test_export_user_data_ignores_non_module_contexts(): void {
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_cm($course);
        $user = $this->getDataGenerator()->create_user();
        $this->make_attempt($user->id, (int) $cm->id);

        $context = \context_module::instance($cm->cmid);
        $systemcontext = \context_system::instance();
        provider::export_user_data(
            new approved_contextlist($user, 'mod_playerland', [$context->id, $systemcontext->id])
        );

        $data = writer::with_context($context)->get_data([
            get_string('privacy:metadata:playerland_atmpt', 'mod_playerland'),
        ]);
        $this->assertCount(1, $data->attempts);
    }

    /**
     * Tests that an approved contextlist made up entirely of non-module contexts is
     * a silent no-op — the early empty-$contexts guard, not just the per-context skip
     * already covered above.
     *
     * @return void
     */
    public function test_export_user_data_with_only_non_module_contexts_is_a_noop(): void {
        $user = $this->getDataGenerator()->create_user();

        provider::export_user_data(
            new approved_contextlist($user, 'mod_playerland', [\context_system::instance()->id])
        );

        // No exception is the assertion: there is no module context to fetch exported
        // data from.
        $this->assertTrue(true);
    }

    /**
     * Tests that delete_data_for_user removes only that user's attempts and answers.
     *
     * @return void
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_cm($course);
        $usera = $this->getDataGenerator()->create_user();
        $userb = $this->getDataGenerator()->create_user();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
        $question = $generator->create_question((int) $cm->id);
        $this->make_attempt($usera->id, (int) $cm->id);
        $this->make_answer($usera->id, (int) $cm->id, $question->id);
        $this->make_attempt($userb->id, (int) $cm->id);

        $context = \context_module::instance($cm->cmid);
        provider::delete_data_for_user(new approved_contextlist($usera, 'mod_playerland', [$context->id]));

        $this->assertSame(0, $DB->count_records('playerland_atmpt', ['userid' => $usera->id]));
        $this->assertSame(0, $DB->count_records('playerland_ans', ['userid' => $usera->id]));
        $this->assertSame(1, $DB->count_records('playerland_atmpt', ['userid' => $userb->id]));
    }

    /**
     * Tests that delete_data_for_user only touches contexts present in the approved
     * list — a second instance the user also has data in, but that was not approved
     * for deletion, is left untouched.
     *
     * @return void
     */
    public function test_delete_data_for_user_respects_approved_contexts_only(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cmapproved = $this->make_cm($course);
        $cmother = $this->make_cm($course);
        $user = $this->getDataGenerator()->create_user();
        $this->make_attempt($user->id, (int) $cmapproved->id);
        $this->make_attempt($user->id, (int) $cmother->id);

        $context = \context_module::instance($cmapproved->cmid);
        provider::delete_data_for_user(new approved_contextlist($user, 'mod_playerland', [$context->id]));

        $this->assertSame(0, $DB->count_records('playerland_atmpt', ['playerlandid' => (int) $cmapproved->id]));
        $this->assertSame(1, $DB->count_records('playerland_atmpt', ['playerlandid' => (int) $cmother->id]));
    }

    /**
     * Tests that a non-module context in the approved contextlist is skipped rather
     * than erroring.
     *
     * @return void
     */
    public function test_delete_data_for_user_ignores_non_module_contexts(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_cm($course);
        $user = $this->getDataGenerator()->create_user();
        $this->make_attempt($user->id, (int) $cm->id);

        provider::delete_data_for_user(
            new approved_contextlist($user, 'mod_playerland', [\context_system::instance()->id])
        );

        $this->assertSame(1, $DB->count_records('playerland_atmpt', ['playerlandid' => (int) $cm->id]));
    }

    /**
     * Tests that a module context whose course_modules row no longer exists (an
     * orphaned context) is skipped rather than erroring.
     *
     * @return void
     */
    public function test_delete_data_for_user_ignores_a_stale_course_module(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_cm($course);
        $user = $this->getDataGenerator()->create_user();
        $this->make_attempt($user->id, (int) $cm->id);

        $context = \context_module::instance($cm->cmid);
        $DB->delete_records('course_modules', ['id' => $cm->cmid]);

        provider::delete_data_for_user(new approved_contextlist($user, 'mod_playerland', [$context->id]));

        $this->assertSame(1, $DB->count_records('playerland_atmpt', ['playerlandid' => (int) $cm->id]));
    }

    /**
     * Tests that delete_data_for_users removes data only for the listed users.
     *
     * @return void
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_cm($course);
        $usera = $this->getDataGenerator()->create_user();
        $userb = $this->getDataGenerator()->create_user();
        $this->make_attempt($usera->id, (int) $cm->id);
        $this->make_attempt($userb->id, (int) $cm->id);

        $context = \context_module::instance($cm->cmid);
        $approvedlist = new approved_userlist($context, 'mod_playerland', [$usera->id]);
        provider::delete_data_for_users($approvedlist);

        $this->assertSame(0, $DB->count_records('playerland_atmpt', ['userid' => $usera->id]));
        $this->assertSame(1, $DB->count_records('playerland_atmpt', ['userid' => $userb->id]));
    }

    /**
     * Tests that delete_data_for_users is a silent no-op for a non-module context.
     *
     * @return void
     */
    public function test_delete_data_for_users_ignores_non_module_context(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_cm($course);
        $user = $this->getDataGenerator()->create_user();
        $this->make_attempt($user->id, (int) $cm->id);

        $approvedlist = new approved_userlist(\context_system::instance(), 'mod_playerland', [$user->id]);
        provider::delete_data_for_users($approvedlist);

        $this->assertSame(1, $DB->count_records('playerland_atmpt', ['playerlandid' => (int) $cm->id]));
    }

    /**
     * Tests that delete_data_for_users is a silent no-op for a module context whose
     * course_modules row no longer exists.
     *
     * @return void
     */
    public function test_delete_data_for_users_ignores_a_stale_course_module(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_cm($course);
        $user = $this->getDataGenerator()->create_user();
        $this->make_attempt($user->id, (int) $cm->id);

        $context = \context_module::instance($cm->cmid);
        $DB->delete_records('course_modules', ['id' => $cm->cmid]);

        provider::delete_data_for_users(new approved_userlist($context, 'mod_playerland', [$user->id]));

        $this->assertSame(1, $DB->count_records('playerland_atmpt', ['playerlandid' => (int) $cm->id]));
    }

    /**
     * Tests that delete_data_for_users is a silent no-op for an empty user list.
     *
     * @return void
     */
    public function test_delete_data_for_users_with_empty_userids_is_a_noop(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_cm($course);
        $user = $this->getDataGenerator()->create_user();
        $this->make_attempt($user->id, (int) $cm->id);

        $context = \context_module::instance($cm->cmid);
        provider::delete_data_for_users(new approved_userlist($context, 'mod_playerland', []));

        $this->assertSame(1, $DB->count_records('playerland_atmpt', ['playerlandid' => (int) $cm->id]));
    }

    /**
     * Tests that delete_data_for_all_users_in_context clears every attempt/answer in
     * that context only, leaving another activity's data untouched.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cmtarget = $this->make_cm($course);
        $cmother = $this->make_cm($course);
        $user = $this->getDataGenerator()->create_user();
        $this->make_attempt($user->id, (int) $cmtarget->id);
        $this->make_attempt($user->id, (int) $cmother->id);

        provider::delete_data_for_all_users_in_context(\context_module::instance($cmtarget->cmid));

        $this->assertSame(0, $DB->count_records('playerland_atmpt', ['playerlandid' => (int) $cmtarget->id]));
        $this->assertSame(1, $DB->count_records('playerland_atmpt', ['playerlandid' => (int) $cmother->id]));
    }

    /**
     * Tests that delete_data_for_all_users_in_context is a silent no-op for a
     * non-module context.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_context_ignores_non_module_context(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_cm($course);
        $user = $this->getDataGenerator()->create_user();
        $this->make_attempt($user->id, (int) $cm->id);

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertSame(1, $DB->count_records('playerland_atmpt', ['playerlandid' => (int) $cm->id]));
    }

    /**
     * Tests that delete_data_for_all_users_in_context is a silent no-op for a module
     * context whose course_modules row no longer exists.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_context_ignores_a_stale_course_module(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_cm($course);
        $user = $this->getDataGenerator()->create_user();
        $this->make_attempt($user->id, (int) $cm->id);

        $context = \context_module::instance($cm->cmid);
        $DB->delete_records('course_modules', ['id' => $cm->cmid]);

        provider::delete_data_for_all_users_in_context($context);

        $this->assertSame(1, $DB->count_records('playerland_atmpt', ['playerlandid' => (int) $cm->id]));
    }
}
