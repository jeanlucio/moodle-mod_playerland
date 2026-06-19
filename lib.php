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
 * Library of functions and constants for module playerland.
 *
 * @package    mod_playerland
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



/**
 * Indicates API features that the playerland module supports.
 *
 * @param string $feature
 * @return mixed True if yes (some features may use other values)
 */
function playerland_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * Adds a new playerland instance.
 *
 * @param stdClass $playerland
 * @param mod_playerland_mod_form $mform
 * @return int The new course module id
 */
function playerland_add_instance($playerland, $mform = null) {
    global $DB;

    $playerland->timecreated = time();
    $playerland->timemodified = $playerland->timecreated;

    $id = $DB->insert_record('playerland', $playerland);

    return $id;
}

/**
 * Updates an existing playerland instance.
 *
 * @param stdClass $playerland
 * @param mod_playerland_mod_form $mform
 * @return bool True on success
 */
function playerland_update_instance($playerland, $mform = null) {
    global $DB;

    $playerland->timemodified = time();
    $playerland->id = $playerland->instance;

    return $DB->update_record('playerland', $playerland);
}

/**
 * Deletes a playerland instance.
 *
 * @param int $id
 * @return bool True on success
 */
function playerland_delete_instance($id) {
    global $DB;

    if (!$playerland = $DB->get_record('playerland', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('playerland', ['id' => $playerland->id]);
    $DB->delete_records('playerland_atmpt', ['playerlandid' => $playerland->id]);

    return true;
}
