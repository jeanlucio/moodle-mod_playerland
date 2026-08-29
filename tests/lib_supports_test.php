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
 * Tests for the playerland_supports callback in lib.php.
 *
 * @package    mod_playerland
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerland;

/**
 * Tests for playerland_supports() — no database access needed.
 *
 * @covers ::playerland_supports
 */
final class lib_supports_test extends \basic_testcase {
    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/playerland/lib.php');
    }

    /**
     * Known features return their declared support value, and an unrecognised feature
     * returns null. PHP evaluates every branch of the function body regardless of
     * which key is actually being looked up, so this alone would fatal if any
     * referenced constant (e.g. FEATURE_MOD_OTHERPURPOSE, only defined from Moodle 5.1
     * onwards) were ever used unconditionally instead of behind a defined() guard —
     * see test_supports_secondary_purpose_when_available() for that constant
     * specifically.
     *
     * @return void
     */
    public function test_supports_known_features(): void {
        $this->assertTrue(playerland_supports(FEATURE_MOD_INTRO));
        $this->assertTrue(playerland_supports(FEATURE_SHOW_DESCRIPTION));
        $this->assertTrue(playerland_supports(FEATURE_COMPLETION_TRACKS_VIEWS));
        $this->assertFalse(playerland_supports(FEATURE_COMPLETION_HAS_RULES));
        $this->assertTrue(playerland_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertTrue(playerland_supports(FEATURE_GRADE_OUTCOMES));
        $this->assertSame(MOD_PURPOSE_INTERACTIVECONTENT, playerland_supports(FEATURE_MOD_PURPOSE));
        $this->assertFalse(playerland_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertNull(playerland_supports('unknown_feature'));
    }

    /**
     * FEATURE_MOD_OTHERPURPOSE (MDL-85598) lets the activity chooser list this
     * activity under a second category (assessment) alongside its primary one
     * (interactive content) — only exists from Moodle 5.1 onwards, so the value is
     * only checked when the constant is actually defined on whichever Moodle branch
     * this test happens to run against.
     *
     * @return void
     */
    public function test_supports_secondary_purpose_when_available(): void {
        if (!defined('FEATURE_MOD_OTHERPURPOSE')) {
            $this->markTestSkipped('FEATURE_MOD_OTHERPURPOSE does not exist on this Moodle branch.');
        }
        $this->assertSame(MOD_PURPOSE_ASSESSMENT, playerland_supports(FEATURE_MOD_OTHERPURPOSE));
    }
}
