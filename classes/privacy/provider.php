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
 * Privacy provider implementation for mod_playerland.
 *
 * @package    mod_playerland
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerland\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_playerland.
 *
 * Personal data is stored in playerland_atmpt and playerland_ans.
 *
 * @package    mod_playerland
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\user_preference_provider {
    /** @var string Name of the site-wide user preference for the first-load intro overlay. */
    const INTROSEEN_PREFERENCE = 'mod_playerland_introseen';

    /**
     * Returns metadata about personal data stored by this plugin.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('playerland_atmpt', [
            'userid'         => 'privacy:metadata:userid',
            'currentlevel'   => 'privacy:metadata:currentlevel',
            'blocksresolved' => 'privacy:metadata:blocksresolved',
            'timecreated'    => 'privacy:metadata:timecreated',
            'timemodified'   => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:playerland_atmpt');

        $collection->add_database_table('playerland_ans', [
            'userid'      => 'privacy:metadata:userid',
            'questionid'  => 'privacy:metadata:questionid',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:playerland_ans');

        $collection->add_user_preference(
            self::INTROSEEN_PREFERENCE,
            'privacy:metadata:preference:introseen'
        );

        return $collection;
    }

    /**
     * Export the site-wide "how to play" intro overlay preference for the given user.
     *
     * @param int $userid The user id to export preferences for.
     */
    #[\Override]
    public static function export_user_preferences(int $userid): void {
        $seen = get_user_preferences(self::INTROSEEN_PREFERENCE, null, $userid);
        if ($seen === null) {
            return;
        }

        writer::export_user_preference(
            'mod_playerland',
            self::INTROSEEN_PREFERENCE,
            transform::yesno($seen),
            get_string('privacy:metadata:preference:introseen', 'mod_playerland')
        );
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {playerland_atmpt} pa
                  JOIN {playerland} pl ON pl.id = pa.playerlandid
                  JOIN {modules} m ON m.name = :activityname
                  JOIN {course_modules} cm ON cm.instance = pl.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel
                 WHERE pa.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'activityname' => 'playerland',
            'modlevel'     => CONTEXT_MODULE,
            'userid'       => $userid,
        ]);

        $sql = "SELECT ctx.id
                  FROM {playerland_ans} pa
                  JOIN {playerland} pl ON pl.id = pa.playerlandid
                  JOIN {modules} m ON m.name = :activityname
                  JOIN {course_modules} cm ON cm.instance = pl.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel
                 WHERE pa.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'activityname' => 'playerland',
            'modlevel'     => CONTEXT_MODULE,
            'userid'       => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist to populate.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $sql = "SELECT pa.userid
                  FROM {playerland_atmpt} pa
                  JOIN {playerland} pl ON pl.id = pa.playerlandid
                  JOIN {modules} m ON m.name = :activityname
                  JOIN {course_modules} cm ON cm.instance = pl.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel
                 WHERE ctx.id = :contextid";
        $userlist->add_from_sql('userid', $sql, [
            'activityname' => 'playerland',
            'modlevel'     => CONTEXT_MODULE,
            'contextid'    => $context->id,
        ]);

        $sql = "SELECT pa.userid
                  FROM {playerland_ans} pa
                  JOIN {playerland} pl ON pl.id = pa.playerlandid
                  JOIN {modules} m ON m.name = :activityname
                  JOIN {course_modules} cm ON cm.instance = pl.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel
                 WHERE ctx.id = :contextid";
        $userlist->add_from_sql('userid', $sql, [
            'activityname' => 'playerland',
            'modlevel'     => CONTEXT_MODULE,
            'contextid'    => $context->id,
        ]);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        $contexts = array_reduce($contextlist->get_contexts(), function (array $carry, \context $context): array {
            if ($context->contextlevel == CONTEXT_MODULE) {
                $carry[$context->id] = $context;
            }
            return $carry;
        }, []);

        if (empty($contexts)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($contexts), SQL_PARAMS_NAMED, 'ctx');

        $sql = "SELECT pa.id, pa.currentlevel, pa.blocksresolved, pa.timecreated, pa.timemodified,
                       ctx.id AS contextid
                  FROM {playerland_atmpt} pa
                  JOIN {playerland} pl ON pl.id = pa.playerlandid
                  JOIN {modules} m ON m.name = 'playerland'
                  JOIN {course_modules} cm ON cm.instance = pl.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id
                 WHERE ctx.id $insql
                   AND pa.userid = :userid";
        $records = $DB->get_recordset_sql($sql, array_merge($inparams, ['userid' => $userid]));

        $allattempts = [];
        foreach ($records as $record) {
            $allattempts[$record->contextid][] = (object) [
                'currentlevel'   => $record->currentlevel,
                'blocksresolved' => $record->blocksresolved,
                'timecreated'    => transform::datetime($record->timecreated),
                'timemodified'   => transform::datetime($record->timemodified),
            ];
        }
        $records->close();

        foreach ($allattempts as $contextid => $attempts) {
            writer::with_context($contexts[$contextid])->export_data(
                [get_string('privacy:metadata:playerland_atmpt', 'mod_playerland')],
                (object) ['attempts' => $attempts]
            );
        }

        $sql = "SELECT pa.id, pa.questionid, pa.timecreated, ctx.id AS contextid
                  FROM {playerland_ans} pa
                  JOIN {playerland} pl ON pl.id = pa.playerlandid
                  JOIN {modules} m ON m.name = 'playerland'
                  JOIN {course_modules} cm ON cm.instance = pl.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id
                 WHERE ctx.id $insql
                   AND pa.userid = :userid";
        $records = $DB->get_recordset_sql($sql, array_merge($inparams, ['userid' => $userid]));

        $allanswers = [];
        foreach ($records as $record) {
            $allanswers[$record->contextid][] = (object) [
                'questionid' => $record->questionid,
                'timecreated' => transform::datetime($record->timecreated),
            ];
        }
        $records->close();

        foreach ($allanswers as $contextid => $answers) {
            writer::with_context($contexts[$contextid])->export_data(
                [get_string('privacy:metadata:playerland_ans', 'mod_playerland')],
                (object) ['answers' => $answers]
            );
        }
    }

    /**
     * Delete all user data for all users in the specified context.
     *
     * @param \context $context The context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('playerland', $context->instanceid);
        if (!$cm) {
            return;
        }

        $DB->delete_records('playerland_atmpt', ['playerlandid' => $cm->instance]);
        $DB->delete_records('playerland_ans', ['playerlandid' => $cm->instance]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        $instanceids = [];
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $cm = get_coursemodule_from_id('playerland', $context->instanceid);
            if ($cm) {
                $instanceids[] = (int) $cm->instance;
            }
        }

        if (empty($instanceids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED, 'pl');
        $DB->delete_records_select(
            'playerland_atmpt',
            "playerlandid $insql AND userid = :userid",
            array_merge($inparams, ['userid' => $userid])
        );
        $DB->delete_records_select(
            'playerland_ans',
            "playerlandid $insql AND userid = :userid",
            array_merge($inparams, ['userid' => $userid])
        );
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $cm = get_coursemodule_from_id('playerland', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $DB->delete_records_select(
            'playerland_atmpt',
            "playerlandid = :playerlandid AND userid $insql",
            array_merge(['playerlandid' => $cm->instance], $inparams)
        );
        $DB->delete_records_select(
            'playerland_ans',
            "playerlandid = :playerlandid AND userid $insql",
            array_merge(['playerlandid' => $cm->instance], $inparams)
        );
    }
}
