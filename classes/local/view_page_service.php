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
 * Service to build the template context for the activity view page.
 *
 * @package    mod_playerland
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerland\local;

use context_module;
use moodle_url;
use stdClass;

/**
 * Builds the mod_playerland/view template context.
 */
class view_page_service {
    /** @var array Lang string keys for the first-load controls overlay, in display order. */
    const CONTROL_KEYS = ['controlmove', 'controljump', 'controlroll', 'controlcrouch', 'controlfullscreen'];

    /**
     * Builds the template context for the activity view page.
     *
     * @param stdClass $cm Course module.
     * @param stdClass $playerland Activity instance.
     * @param context_module $context Module context.
     * @param int $userid Current user id.
     * @return array Template context for mod_playerland/view.
     */
    public static function build_page_context(
        stdClass $cm,
        stdClass $playerland,
        context_module $context,
        int $userid
    ): array {
        global $DB;

        $hasmanagecapability = has_capability('mod/playerland:manage', $context, $userid);
        $introseen = (bool)get_user_preferences('mod_playerland_introseen', false, $userid);

        $introhtml = '';
        if (!empty($playerland->intro)) {
            $introhtml = format_module_intro('playerland', $playerland, $cm->id);
        }

        $controls = [];
        foreach (self::CONTROL_KEYS as $key) {
            $controls[] = ['label' => get_string($key, 'mod_playerland')];
        }

        $config = [
            'id' => (int)$playerland->id,
            'assetsurl' => (new moodle_url('/mod/playerland/assets'))->out(false),
            'assetrev' => (int)get_config('mod_playerland', 'version'),
            'levels' => (int)$playerland->levels,
            'map' => $playerland->map,
            'targetquestions' => max(1, (int)($playerland->targetquestions ?? 1)),
            'blocksresolved' => 0,
            'lessons' => [
                (string)($playerland->lesson1 ?? ''),
                (string)($playerland->lesson2 ?? ''),
                (string)($playerland->lesson3 ?? ''),
            ],
        ];

        $attempt = $DB->get_record('playerland_atmpt', ['playerlandid' => $playerland->id, 'userid' => $userid]);
        if ($attempt) {
            $config['blocksresolved'] = (int)$attempt->blocksresolved;
        }

        return [
            'hasmanagecapability' => $hasmanagecapability,
            'manageurl' => (new moodle_url('/mod/playerland/manage_questions.php', ['id' => $cm->id]))->out(false),
            'managequestionslabel' => get_string('managequestions', 'mod_playerland'),
            'hasintro' => ($introhtml !== ''),
            'introhtml' => $introhtml,
            'introseen' => $introseen,
            'introtitle' => get_string('introtitle', 'mod_playerland'),
            'controls' => $controls,
            'gotitlabel' => get_string('gotit', 'mod_playerland'),
            'configjson' => json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP),
        ];
    }
}
