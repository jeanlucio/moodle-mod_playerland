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
 * Regression guard for view.php not duplicating the activity heading.
 *
 * @package    mod_playerland
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerland;

/**
 * The 'incourse' page layout (set implicitly by require_login($course, true, $cm), see
 * lib/moodlelib.php) already renders the activity icon, name and completion badge via
 * $PAGE->activityheader. A manual $OUTPUT->heading() call in view.php would duplicate the
 * title on every render — a rendering concern that only shows up in a real browser, not in
 * PHPUnit, so the only defensible automated guard is the structural invariant that prevents
 * it in the first place. Same technique as phaser_loading_test.php.
 *
 * @coversNothing
 */
final class view_page_rendering_test extends \basic_testcase {
    /**
     * view.php must never print the activity name manually.
     *
     * @return void
     */
    public function test_view_php_does_not_print_heading_manually(): void {
        global $CFG;
        $source = file_get_contents($CFG->dirroot . '/mod/playerland/view.php');

        $this->assertDoesNotMatchRegularExpression('/OUTPUT->heading\(/', $source);
    }
}
