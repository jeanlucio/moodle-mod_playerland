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
 * Question form.
 *
 * @package    mod_playerland
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerland\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Question form class.
 */
class question_form extends \moodleform {
    /**
     * Define the form fields.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'cmid', 0);
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Question text.
        $mform->addElement('editor', 'questiontext', get_string('questiontext', 'mod_playerland'), null, ['maxfiles' => 0]);
        $mform->setType('questiontext', PARAM_RAW);
        $mform->addRule('questiontext', null, 'required', null, 'client');

        // Options.
        for ($i = 1; $i <= 4; $i++) {
            $mform->addElement('header', 'hdr_option_' . $i, get_string('option', 'mod_playerland') . ' ' . $i);

            $mform->addElement('hidden', 'optionid[' . $i . ']', 0);
            $mform->setType('optionid[' . $i . ']', PARAM_INT);

            $mform->addElement('text', 'optiontext[' . $i . ']', get_string('optiontext', 'mod_playerland'), ['size' => '50']);
            $mform->setType('optiontext[' . $i . ']', PARAM_TEXT);

            // Require at least the first two options.
            if ($i <= 2) {
                $mform->addRule('optiontext[' . $i . ']', null, 'required', null, 'client');
            }

            $mform->addElement('radio', 'iscorrect', get_string('iscorrect', 'mod_playerland'), '', $i);
        }

        // Set the first option as correct by default.
        $mform->setDefault('iscorrect', 1);

        $this->add_action_buttons(true, get_string('savechanges', 'core'));
    }

    /**
     * Validate the form data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['iscorrect'])) {
            $errors['iscorrect'] = get_string('error_no_correct_option', 'mod_playerland');
        }

        return $errors;
    }
}
