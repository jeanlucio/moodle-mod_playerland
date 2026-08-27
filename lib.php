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
 * @param string $feature The feature to check.
 * @return bool|null True if supported, false if unsupported, null if unknown.
 */
function playerland_supports(string $feature): bool|null {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return false;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return false;
        default:
            return null;
    }
}

/**
 * Creates or updates the grade item in the Moodle gradebook.
 *
 * @param stdClass $playerland Activity instance.
 * @param mixed $grades Grade object(s), null to update item only, or 'reset' to reset grades.
 * @return int GRADE_UPDATE_OK or error constant.
 */
function playerland_grade_item_update(stdClass $playerland, mixed $grades = null): int {
    global $CFG;

    require_once($CFG->libdir . '/gradelib.php');

    $params = [
        'itemname' => $playerland->name,
        'idnumber' => $playerland->cmidnumber ?? '',
    ];

    if ((int)$playerland->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = (float)$playerland->grade;
        $params['grademin'] = 0.0;
    } else if ((int)$playerland->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid'] = -(int)$playerland->grade;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    if (!empty($playerland->gradepass)) {
        $params['gradepass'] = (float)$playerland->gradepass;
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update(
        'mod/playerland',
        $playerland->course,
        'mod',
        'playerland',
        $playerland->id,
        0,
        $grades,
        $params
    );
}

/**
 * Deletes the grade item from the Moodle gradebook.
 *
 * @param stdClass $playerland Activity instance.
 * @return int GRADE_UPDATE_OK or error constant.
 */
function playerland_grade_item_delete(stdClass $playerland): int {
    global $CFG;

    require_once($CFG->libdir . '/gradelib.php');

    return grade_update(
        'mod/playerland',
        $playerland->course ?? 0,
        'mod',
        'playerland',
        $playerland->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Calculates the proportional grade for one progress count.
 *
 * @param stdClass $playerland Activity instance.
 * @param int $blocksresolved Number of distinct correct questions.
 * @return float The gradebook raw grade.
 */
function playerland_calculate_grade(stdClass $playerland, int $blocksresolved): float {
    $maxgrade = (float)max(0, (int)$playerland->grade);
    $targetquestions = max(1, (int)($playerland->targetquestions ?? 1));
    $ratio = min(1.0, max(0, $blocksresolved) / $targetquestions);

    return $maxgrade * $ratio;
}

/**
 * Updates gradebook grades for one or all users of a playerland instance.
 *
 * @param stdClass $playerland Activity instance.
 * @param int $userid User id, or 0 to update all users with attempts.
 * @return void
 */
function playerland_update_grades(stdClass $playerland, int $userid = 0): void {
    global $DB;

    if ((int)$playerland->grade <= 0) {
        playerland_grade_item_update($playerland);
        return;
    }

    $params = ['playerlandid' => $playerland->id];
    $where = 'playerlandid = :playerlandid';

    if ($userid > 0) {
        $where .= ' AND userid = :userid';
        $params['userid'] = $userid;
    }

    $attempts = $DB->get_records_select('playerland_atmpt', $where, $params);

    if (empty($attempts)) {
        if ($userid > 0) {
            $grade = new stdClass();
            $grade->userid = $userid;
            $grade->rawgrade = null;
            playerland_grade_item_update($playerland, [$userid => $grade]);
        } else {
            playerland_grade_item_update($playerland);
        }
        return;
    }

    $grades = [];
    foreach ($attempts as $attempt) {
        $grade = new stdClass();
        $grade->userid = $attempt->userid;
        $grade->rawgrade = playerland_calculate_grade($playerland, (int)$attempt->blocksresolved);
        $grades[$attempt->userid] = $grade;
    }

    playerland_grade_item_update($playerland, $grades);
}

/**
 * Adds a new playerland instance.
 *
 * @param stdClass $playerland Submitted data from the form.
 * @param mod_playerland_mod_form|null $mform The form instance.
 * @return int The new course module id.
 */
function playerland_add_instance(stdClass $playerland, ?mod_playerland_mod_form $mform = null): int {
    global $DB;

    $playerland->timecreated = time();
    $playerland->timemodified = $playerland->timecreated;

    $id = $DB->insert_record('playerland', $playerland);
    $playerland->id = $id;
    playerland_grade_item_update($playerland);

    return $id;
}

/**
 * Updates an existing playerland instance.
 *
 * @param stdClass $playerland Submitted data from the form.
 * @param mod_playerland_mod_form|null $mform The form instance.
 * @return bool True on success.
 */
function playerland_update_instance(stdClass $playerland, ?mod_playerland_mod_form $mform = null): bool {
    global $DB;

    $playerland->timemodified = time();
    $playerland->id = $playerland->instance;

    $result = $DB->update_record('playerland', $playerland);
    playerland_update_grades($playerland);

    return $result;
}

/**
 * Deletes a playerland instance.
 *
 * @param int $id ID of the module instance.
 * @return bool True on success.
 */
function playerland_delete_instance(int $id): bool {
    global $DB;

    if (!$playerland = $DB->get_record('playerland', ['id' => $id])) {
        return false;
    }

    $questionids = $DB->get_fieldset_select('playerland_q', 'id', 'playerlandid = :playerlandid', [
        'playerlandid' => $playerland->id,
    ]);

    if (!empty($questionids)) {
        [$insql, $inparams] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'qid');
        $DB->delete_records_select('playerland_opts', "questionid $insql", $inparams);
    }

    $DB->delete_records('playerland_ans', ['playerlandid' => $playerland->id]);
    $DB->delete_records('playerland_atmpt', ['playerlandid' => $playerland->id]);
    $DB->delete_records('playerland_q', ['playerlandid' => $playerland->id]);
    $DB->delete_records('playerland', ['id' => $playerland->id]);

    playerland_grade_item_delete($playerland);

    return true;
}
