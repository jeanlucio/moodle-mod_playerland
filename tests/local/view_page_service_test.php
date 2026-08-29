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
 * Tests for view_page_service.
 *
 * @package    mod_playerland
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerland\local;

/**
 * Tests for view_page_service::build_page_context().
 *
 * @covers \mod_playerland\local\view_page_service
 */
final class view_page_service_test extends \advanced_testcase {
    /** @var \mod_playerland_generator Plugin data generator. */
    private $generator;

    /** @var \stdClass Course used by every test. */
    private \stdClass $course;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_playerland');
    }

    /**
     * Builds the page context for the given instance and user.
     *
     * @param \stdClass $instance Activity instance (as returned by the generator).
     * @param int $userid Current user id.
     * @return array
     */
    private function build(\stdClass $instance, int $userid): array {
        $cm = get_coursemodule_from_id('playerland', $instance->cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        return view_page_service::build_page_context($cm, $instance, $context, $userid);
    }

    /**
     * A student (no manage capability) never sees the manage-questions button; a
     * teacher does.
     *
     * @return void
     */
    public function test_manage_capability_gates_the_button(): void {
        $instance = $this->generator->create_instance(['course' => $this->course->id]);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $studentcontext = $this->build($instance, (int)$student->id);
        $this->assertFalse($studentcontext['hasmanagecapability']);

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');
        $teachercontext = $this->build($instance, (int)$teacher->id);
        $this->assertTrue($teachercontext['hasmanagecapability']);
    }

    /**
     * An activity with no intro text omits the intro box; one with intro text
     * includes its formatted HTML.
     *
     * @return void
     */
    public function test_intro_box_only_shown_when_configured(): void {
        global $DB;

        $withoutintro = $this->generator->create_instance(['course' => $this->course->id]);
        // The generator backfills an empty intro with a "Test playerland N" default
        // (empty(), not isset(), core/lib/testing/generator/module_generator.php), so
        // force a genuinely empty one directly.
        $DB->set_field('playerland', 'intro', '', ['id' => $withoutintro->id]);
        $withoutintro->intro = '';

        $withintro = $this->generator->create_instance(['course' => $this->course->id, 'intro' => 'Explore the level.']);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, 'student');

        $contextwithout = $this->build($withoutintro, (int)$user->id);
        $this->assertFalse($contextwithout['hasintro']);
        $this->assertSame('', $contextwithout['introhtml']);

        $contextwith = $this->build($withintro, (int)$user->id);
        $this->assertTrue($contextwith['hasintro']);
        $this->assertStringContainsString('Explore the level.', $contextwith['introhtml']);
    }

    /**
     * The first-load controls overlay reflects the mod_playerland_introseen user
     * preference for the given user, not the globally logged-in $USER.
     *
     * @return void
     */
    public function test_introseen_reflects_the_users_preference(): void {
        $instance = $this->generator->create_instance(['course' => $this->course->id]);

        $seenuser = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($seenuser->id, $this->course->id, 'student');
        set_user_preference('mod_playerland_introseen', 1, $seenuser->id);

        $unseenuser = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($unseenuser->id, $this->course->id, 'student');

        $this->assertTrue($this->build($instance, (int)$seenuser->id)['introseen']);
        $this->assertFalse($this->build($instance, (int)$unseenuser->id)['introseen']);
    }

    /**
     * The embedded game config JSON carries the instance settings, and
     * blocksresolved is 0 with no attempt yet but reflects a real attempt once one
     * exists.
     *
     * @return void
     */
    public function test_config_json_reflects_instance_and_attempt(): void {
        global $DB;

        $instance = $this->generator->create_instance([
            'course' => $this->course->id,
            'map' => 'map_level001.json',
            'targetquestions' => 5,
        ]);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, 'student');

        $config = json_decode($this->build($instance, (int)$user->id)['configjson'], true);
        $this->assertSame((int)$instance->id, $config['id']);
        $this->assertSame('map_level001.json', $config['map']);
        $this->assertSame(5, $config['targetquestions']);
        $this->assertSame(0, $config['blocksresolved']);

        $DB->insert_record('playerland_atmpt', (object) [
            'playerlandid' => $instance->id,
            'userid' => $user->id,
            'currentlevel' => 1,
            'blocksresolved' => 3,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $config = json_decode($this->build($instance, (int)$user->id)['configjson'], true);
        $this->assertSame(3, $config['blocksresolved']);
    }
}
