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
 * Pre-uninstallation steps for mod_playerland.
 *
 * @package    mod_playerland
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Custom uninstallation steps for mod_playerland.
 *
 * Every table declared in db/install.xml is dropped automatically by core
 * (drop_plugin_tables()) and needs no attention here. The one thing core does
 * not clean up is per-user preferences, since they live in the core
 * user_preferences table rather than a plugin table.
 *
 * @return bool True on success.
 */
function xmldb_playerland_uninstall(): bool {
    global $DB;

    $DB->delete_records_select(
        'user_preferences',
        $DB->sql_like('name', ':prefix'),
        ['prefix' => 'mod_playerland_%']
    );

    return true;
}
