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
 * Restore structure step for mod_playerland.
 *
 * @package    mod_playerland
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Processes the XML tree produced by backup and rebuilds the database records.
 */
class restore_playerland_activity_structure_step extends restore_activity_structure_step {
    /**
     * Returns the path elements the restore engine should process.
     *
     * @return restore_path_element[]
     */
    protected function define_structure(): array {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('playerland', '/activity/playerland');
        $paths[] = new restore_path_element(
            'playerland_question',
            '/activity/playerland/questions/question'
        );
        $paths[] = new restore_path_element(
            'playerland_option',
            '/activity/playerland/questions/question/options/option'
        );

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'playerland_attempt',
                '/activity/playerland/attempts/attempt'
            );
            $paths[] = new restore_path_element(
                'playerland_answer',
                '/activity/playerland/answers/answer'
            );
        }

        // Wrap with the generic '/activity' path so the base class's process_activity()
        // runs: it registers the old-to-new context mapping and the old activity id.
        // Without this, restore_calendarevents_structure_step::after_execute() (a generic
        // step that runs for every activity) fails with unknown_context_mapping, and
        // course_format\local\cmactions::duplicate() never reaches its post-restore
        // cleanup (renaming to "(copy)", moving to the target section, rebuilding the
        // course cache) since the exception aborts the restore plan first.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores the root playerland instance record.
     *
     * @param array|object $data XML data for this element.
     * @return void
     */
    public function process_playerland(array|object $data): void {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('playerland', $data);
        $this->apply_activity_instance($newitemid);
        $this->set_mapping('playerland', $oldid, $newitemid);
    }

    /**
     * Restores a question belonging to the activity.
     *
     * @param array|object $data XML data for this element.
     * @return void
     */
    public function process_playerland_question(array|object $data): void {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->playerlandid = $this->get_new_parentid('playerland');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('playerland_q', $data);
        // Registered under the same name as the path element, so process_playerland_option()
        // can resolve it via get_new_parentid(), and process_playerland_answer() — a
        // same-instance sibling processed later in the same document — can resolve it via
        // get_mappingid(), which does not require the name to match a path element.
        $this->set_mapping('playerland_question', $oldid, $newitemid);
    }

    /**
     * Restores an option belonging to a restored question.
     *
     * @param array|object $data XML data for this element.
     * @return void
     */
    public function process_playerland_option(array|object $data): void {
        global $DB;

        $data = (object)$data;

        $data->questionid = $this->get_new_parentid('playerland_question');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('playerland_opts', $data);
    }

    /**
     * Restores a student progress record (only when userinfo is enabled).
     *
     * @param array|object $data XML data for this element.
     * @return void
     */
    public function process_playerland_attempt(array|object $data): void {
        global $DB;

        $data = (object)$data;

        $data->playerlandid = $this->get_new_parentid('playerland');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->userid = (int)$this->get_mappingid('user', $data->userid);

        // Skip an orphaned attempt (user not mapped).
        if (empty($data->userid)) {
            return;
        }

        $DB->insert_record('playerland_atmpt', $data);
    }

    /**
     * Restores a distinct correct answer record (only when userinfo is enabled).
     *
     * @param array|object $data XML data for this element.
     * @return void
     */
    public function process_playerland_answer(array|object $data): void {
        global $DB;

        $data = (object)$data;

        $data->playerlandid = $this->get_new_parentid('playerland');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->userid = (int)$this->get_mappingid('user', $data->userid);
        $data->questionid = (int)$this->get_mappingid('playerland_question', $data->questionid);

        // Skip an orphaned answer (user or question not mapped).
        if (empty($data->userid) || empty($data->questionid)) {
            return;
        }

        $DB->insert_record('playerland_ans', $data);
    }

    /**
     * Restores files embedded in the activity's intro editor field.
     *
     * The grade item itself is not touched here: restore_activity_grades_structure_step
     * (added generically by restore_activity_task for every gradable module) already
     * restores it. Calling playerland_grade_item_update() again here would race against
     * that generic step and leave two grade_items for the same instance.
     *
     * @return void
     */
    protected function after_execute(): void {
        $this->add_related_files('mod_playerland', 'intro', null);
    }
}
