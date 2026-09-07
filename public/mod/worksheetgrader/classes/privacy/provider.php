<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_worksheetgrader\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\writer;

class provider implements \core_privacy\local\metadata\provider, plugin_provider, core_userlist_provider {
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('wsg_attempt', ['submitteruserid' => 'privacy:metadata:wsg_attempt:submitteruserid', 'answersjson' => 'privacy:metadata:wsg_attempt:answersjson'], 'privacy:metadata:wsg_attempt');
        $collection->add_database_table('wsg_grade', ['userid' => 'privacy:metadata:wsg_grade:userid', 'finalgrade' => 'privacy:metadata:wsg_grade:finalgrade'], 'privacy:metadata:wsg_grade');
        $collection->add_database_table('wsg_log', ['actorid' => 'privacy:metadata:wsg_log:actorid'], 'privacy:metadata:wsg_log');
        return $collection;
    }
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        $sql = "SELECT ctx.id FROM {context} ctx JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'worksheetgrader'
                  JOIN {worksheetgrader} w ON w.id = cm.instance
                 WHERE ctx.contextlevel = :contextmodule AND (EXISTS (SELECT 1 FROM {wsg_grade} g JOIN {wsg_attempt} a ON a.id=g.attemptid JOIN {wsg_session} s ON s.id=a.sessionid WHERE s.worksheetgraderid=w.id AND g.userid=:uid1)
                    OR EXISTS (SELECT 1 FROM {wsg_member} mem JOIN {wsg_team} t ON t.id=mem.teamid JOIN {wsg_session} s2 ON s2.id=t.sessionid WHERE s2.worksheetgraderid=w.id AND mem.userid=:uid2))";
        $list->add_from_sql($sql, ['contextmodule' => CONTEXT_MODULE, 'uid1' => $userid, 'uid2' => $userid]);
        return $list;
    }
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            $cm = get_coursemodule_from_id('worksheetgrader', $context->instanceid, 0, false, MUST_EXIST);
            $sessions = $DB->get_records('wsg_session', ['worksheetgraderid' => $cm->instance]);
            $sessionids = array_keys($sessions);
            if (!$sessionids) { continue; }
            [$insql, $params] = $DB->get_in_or_equal($sessionids, SQL_PARAMS_NAMED);
            $params['userid'] = $contextlist->get_user()->id;
            $grades = $DB->get_records_sql("SELECT g.* FROM {wsg_grade} g JOIN {wsg_attempt} a ON a.id=g.attemptid WHERE a.sessionid $insql AND g.userid=:userid", $params);
            writer::with_context($context)->export_data([get_string('pluginname', 'mod_worksheetgrader')], (object)['grades' => array_values($grades)]);
        }
    }
    public static function delete_data_for_all_users_in_context(\context $context): void { self::delete_context($context, 0); }
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) { self::delete_context($context, $contextlist->get_user()->id); }
    }
    private static function delete_context(\context $context, int $userid): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_MODULE) { return; }
        $cm = get_coursemodule_from_id('worksheetgrader', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) { return; }
        $sessions = $DB->get_fieldset_select('wsg_session', 'id', 'worksheetgraderid = ?', [$cm->instance]);
        if (!$sessions) { return; }
        [$insql, $params] = $DB->get_in_or_equal($sessions, SQL_PARAMS_QM);
        if ($userid) {
            $params[] = $userid;
            $DB->delete_records_select('wsg_grade', "userid = ? AND attemptid IN (SELECT id FROM {wsg_attempt} WHERE sessionid $insql)", array_merge([$userid], $params));
        }
    }
    public static function get_users_in_context(approved_userlist $userlist): void { }
    public static function delete_data_for_users(approved_userlist $userlist): void { }
}
