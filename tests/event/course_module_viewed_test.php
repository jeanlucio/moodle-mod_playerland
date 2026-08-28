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
 * Tests for the course_module_viewed event.
 *
 * @package    mod_playerland
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerland\event;

/**
 * Tests for mod_playerland\event\course_module_viewed. The base
 * \core\event\course_module_viewed class supplies get_name()/get_description()/
 * get_url() generically from the crud/edulevel/objecttable this class's init() sets,
 * so the properties those fields drive are what's actually worth asserting here.
 *
 * @covers \mod_playerland\event\course_module_viewed
 */
final class course_module_viewed_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Creates a playerland course module and returns the cm record.
     *
     * @return \stdClass Course module record.
     */
    private function make_cm(): \stdClass {
        $course = $this->getDataGenerator()->create_course();

        return $this->getDataGenerator()->create_module('playerland', ['course' => $course->id]);
    }

    /**
     * Builds the event exactly as view.php does.
     *
     * @param \stdClass $cm Course module record.
     * @return course_module_viewed
     */
    private function build_event(\stdClass $cm): course_module_viewed {
        $context = \context_module::instance($cm->cmid);

        return course_module_viewed::create([
            'objectid' => $cm->id,
            'context' => $context,
        ]);
    }

    /**
     * Tests that init() sets the expected crud, education level and object table.
     *
     * @return void
     */
    public function test_event_has_correct_properties(): void {
        $cm = $this->make_cm();
        $event = $this->build_event($cm);

        $this->assertSame('r', $event->crud);
        $this->assertSame(\core\event\base::LEVEL_PARTICIPATING, $event->edulevel);
        $this->assertSame('playerland', $event->objecttable);
        $this->assertSame('mod_playerland', $event->component);
    }

    /**
     * Tests that the inherited get_url() points at this module's own view.php, using
     * the objecttable this class declares.
     *
     * @return void
     */
    public function test_get_url_points_to_playerland_view(): void {
        $cm = $this->make_cm();
        $event = $this->build_event($cm);

        $expected = new \moodle_url('/mod/playerland/view.php', ['id' => $cm->cmid]);
        $this->assertTrue($expected->compare($event->get_url()));
    }

    /**
     * Tests that get_name() and get_description() are non-empty and mention the
     * right identifiers, guarding against a coding_exception from validate_data()
     * (missing objectid/objecttable, or a non-module context) going unnoticed.
     *
     * @return void
     */
    public function test_name_and_description(): void {
        $cm = $this->make_cm();
        $event = $this->build_event($cm);

        $this->assertNotEmpty(course_module_viewed::get_name());
        $this->assertStringContainsString('playerland', $event->get_description());
        $this->assertStringContainsString((string) $cm->cmid, $event->get_description());
    }

    /**
     * Tests that triggering the event is captured by the standard event sink, the way
     * view.php's real trigger() call would be observed in production.
     *
     * @return void
     */
    public function test_triggering_is_observable(): void {
        $cm = $this->make_cm();
        $sink = $this->redirectEvents();

        $this->build_event($cm)->trigger();

        $events = array_filter($sink->get_events(), fn($event) => $event instanceof course_module_viewed);
        $sink->close();

        $this->assertCount(1, $events);
    }
}
