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
 * Services definitions for playerland.
 *
 * @package    mod_playerland
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_playerland_save_progress' => [
        'classname'   => 'mod_playerland\external',
        'methodname'  => 'save_progress',
        'classpath'   => '',
        'description' => 'Saves playerland progress',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'mod_playerland_get_question' => [
        'classname'   => 'mod_playerland\external',
        'methodname'  => 'get_question',
        'classpath'   => '',
        'description' => 'Gets a random question for the playerland instance',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'mod_playerland_check_answer' => [
        'classname'   => 'mod_playerland\external',
        'methodname'  => 'check_answer',
        'classpath'   => '',
        'description' => 'Checks if the answer provided is correct',
        'type'        => 'write',
        'ajax'        => true,
    ]
];
