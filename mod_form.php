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
 * The mod_playerland mod_form.
 *
 * @package    mod_playerland
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Form for playerland instances.
 */
class mod_playerland_mod_form extends moodleform_mod {
    /**
     * Defines the form.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        // General settings.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // Game settings.
        $mform->addElement('header', 'gamesettings', get_string('gamesettings', 'mod_playerland'));

        $mform->addElement('text', 'levels', get_string('levels', 'mod_playerland'), ['size' => '4']);
        $mform->setType('levels', PARAM_INT);
        $mform->setDefault('levels', 1);
        $mform->addRule('levels', get_string('err_positiveint', 'mod_playerland'), 'numeric', null, 'client');

        $mform->addElement('text', 'targetquestions', get_string('targetquestions', 'mod_playerland'), ['size' => '4']);
        $mform->setType('targetquestions', PARAM_INT);
        $mform->setDefault('targetquestions', 3);
        $mform->addHelpButton('targetquestions', 'targetquestions', 'mod_playerland');
        $mform->addRule('targetquestions', get_string('err_positiveint', 'mod_playerland'), 'numeric', null, 'client');

        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Validates the submitted form data.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Validation errors keyed by element name.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if ((int)($data['levels'] ?? 0) < 1) {
            $errors['levels'] = get_string('err_positiveint', 'mod_playerland');
        }

        if ((int)($data['targetquestions'] ?? 0) < 1) {
            $errors['targetquestions'] = get_string('err_positiveint', 'mod_playerland');
        }

        return $errors;
    }
}
