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
 * Regression guard for the Phaser-loading race with core_message/message_drawer.js.
 *
 * @package    mod_playerland
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerland;

/**
 * A static <script src="phaser.min.js"> queued via $PAGE->requires->js() in view.php sits
 * in the page footer and can race core_message/message_drawer.js, which expects its own
 * drawer markup (rendered further down the same footer) to already be in the DOM by the
 * time its require() callback runs — confirmed live against mod_playerpuzzle, which has
 * the identical Phaser-loading pattern (see its SCOPE.md §17). The bug itself is a
 * client-side timing race, not something reproducible deterministically in PHPUnit or
 * reliably in Behat, so the only defensible automated guard is the structural invariant
 * that prevents it: view.php must never queue phaser.min.js as a static <script>, and
 * game.js must load it dynamically instead (mirrors filter_mathjaxloader's loadMathJax()).
 *
 * @coversNothing
 */
final class phaser_loading_test extends \basic_testcase {
    /**
     * view.php must never queue phaser.min.js as a static <script> tag.
     *
     * @return void
     */
    public function test_view_php_does_not_queue_phaser_as_static_script(): void {
        global $CFG;
        $source = file_get_contents($CFG->dirroot . '/mod/playerland/view.php');

        $this->assertDoesNotMatchRegularExpression(
            '/requires->js\(\s*new moodle_url\([\'"]\/mod\/playerland\/javascript\/phaser\.min\.js[\'"]\)/',
            $source
        );
    }

    /**
     * game.js must load Phaser dynamically (a <script> created and appended via JS,
     * resolved through its onload event) rather than assuming a PHP-queued <script> tag
     * already exists on the page by the time init() runs.
     *
     * @return void
     */
    public function test_game_js_loads_phaser_dynamically(): void {
        global $CFG;
        $source = file_get_contents($CFG->dirroot . '/mod/playerland/amd/src/game.js');

        $this->assertMatchesRegularExpression('/document\.createElement\([\'"]script[\'"]\)/', $source);
        $this->assertMatchesRegularExpression('/script\.onload\s*=/', $source);
    }
}
