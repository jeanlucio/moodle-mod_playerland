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
 * Restore task for mod_playerland.
 *
 * @package    mod_playerland
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/playerland/backup/moodle2/restore_playerland_stepslib.php');

/**
 * Provides the steps that perform one complete restore of a PlayerLand activity instance.
 */
class restore_playerland_activity_task extends restore_activity_task {
    /**
     * No specific settings for this activity.
     *
     * @return void
     */
    protected function define_my_settings(): void {
    }

    /**
     * Defines the restore steps for this activity.
     *
     * @return void
     */
    protected function define_my_steps(): void {
        $this->add_step(new restore_playerland_activity_structure_step(
            'playerland_structure',
            'playerland.xml'
        ));
    }

    /**
     * Returns the list of content areas that can hold encoded links.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents(): array {
        return [
            new restore_decode_content('playerland', ['intro'], 'playerland'),
        ];
    }

    /**
     * Returns the decode rules that rewrite encoded backup URLs back to live URLs.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules(): array {
        return [
            new restore_decode_rule(
                'PLAYERLANDVIEWBYID',
                '/mod/playerland/view.php?id=$1',
                'course_module'
            ),
            new restore_decode_rule(
                'PLAYERLANDINDEX',
                '/mod/playerland/index.php?id=$1',
                'course'
            ),
        ];
    }

    /**
     * Returns the restore log rules for this activity.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules(): array {
        return [];
    }

    /**
     * Returns the restore log rules for the course level.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules_for_course(): array {
        return [];
    }
}
