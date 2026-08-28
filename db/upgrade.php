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
 * Database upgrade steps for mod_playerland.
 *
 * @package    mod_playerland
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin from one version to the next.
 *
 * @param int $oldversion The old plugin version.
 * @return bool True on success.
 */
function xmldb_playerland_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026082700) {
        $table = new xmldb_table('playerland');
        $field = new xmldb_field(
            'targetquestions',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '3',
            'levels'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('playerland_atmpt');
        $index = new xmldb_index('userinstance', XMLDB_INDEX_UNIQUE, ['playerlandid', 'userid']);

        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $table = new xmldb_table('playerland_ans');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('playerlandid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('playerlandid', XMLDB_KEY_FOREIGN, ['playerlandid'], 'playerland', ['id']);
            $table->add_key('questionid', XMLDB_KEY_FOREIGN, ['questionid'], 'playerland_q', ['id']);
            $table->add_index('userquestion', XMLDB_INDEX_UNIQUE, ['playerlandid', 'userid', 'questionid']);
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026082700, 'playerland');
    }

    if ($oldversion < 2026082701) {
        $table = new xmldb_table('playerland');
        $field = new xmldb_field('map', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'map.json', 'targetquestions');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026082701, 'playerland');
    }

    if ($oldversion < 2026082803) {
        $table = new xmldb_table('playerland');
        $previous = 'grade';
        foreach (['lesson1', 'lesson2', 'lesson3'] as $name) {
            $field = new xmldb_field($name, XMLDB_TYPE_TEXT, null, null, null, null, null, $previous);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            $previous = $name;
        }
        upgrade_mod_savepoint(true, 2026082803, 'playerland');
    }

    if ($oldversion < 2026082804) {
        $table = new xmldb_table('playerland_q');
        $field = new xmldb_field('topic', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'questionformat');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026082804, 'playerland');
    }

    return true;
}
