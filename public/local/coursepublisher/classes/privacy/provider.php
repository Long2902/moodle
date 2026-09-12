<?php

namespace local_coursepublisher\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;

class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_cp_audit', [
            'userid' => 'privacy:metadata:local_cp_audit:userid',
            'action' => 'privacy:metadata:local_cp_audit:action',
            'details' => 'privacy:metadata:local_cp_audit:details',
        ], 'privacy:metadata:local_cp_audit');
        $collection->add_database_table('local_cp_job', [
            'userid' => 'privacy:metadata:local_cp_job:userid',
            'preflightjson' => 'privacy:metadata:local_cp_job:preflightjson',
            'lasterror' => 'privacy:metadata:local_cp_job:lasterror',
        ], 'privacy:metadata:local_cp_job');
        $collection->add_database_table('local_cp_job_log', [
            'userid' => 'privacy:metadata:local_cp_job_log:userid',
            'message' => 'privacy:metadata:local_cp_job_log:message',
            'details' => 'privacy:metadata:local_cp_job_log:details',
        ], 'privacy:metadata:local_cp_job_log');
        $collection->add_database_table('local_cp_batch', [
            'userid' => 'privacy:metadata:local_cp_batch:userid',
            'placementjson' => 'privacy:metadata:local_cp_batch:placementjson',
            'pausereason' => 'privacy:metadata:local_cp_batch:pausereason',
        ], 'privacy:metadata:local_cp_batch');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('local_cp_audit', ['userid' => $userid]) ||
                $DB->record_exists('local_cp_job', ['userid' => $userid]) ||
                $DB->record_exists('local_cp_job_log', ['userid' => $userid]) ||
                $DB->record_exists('local_cp_batch', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        if (empty($contextlist->get_contextids())) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $data = (object)[
            'audit' => array_values($DB->get_records('local_cp_audit', ['userid' => $userid], 'timecreated ASC')),
            'jobs' => array_values($DB->get_records('local_cp_job', ['userid' => $userid], 'timecreated ASC')),
            'joblogs' => array_values($DB->get_records('local_cp_job_log', ['userid' => $userid], 'timecreated ASC')),
            'batches' => array_values($DB->get_records('local_cp_batch', ['userid' => $userid], 'timecreated ASC')),
        ];
        \core_privacy\local\request\writer::with_context(\context_system::instance())
            ->export_data([get_string('pluginname', 'local_coursepublisher')], $data);
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $DB->set_field('local_cp_audit', 'userid', 0);
            $DB->set_field('local_cp_job', 'userid', 0);
            $DB->set_field('local_cp_job_log', 'userid', 0);
            $DB->set_field('local_cp_batch', 'userid', 0);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        if (!in_array(\context_system::instance()->id, $contextlist->get_contextids(), true)) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        $DB->set_field('local_cp_audit', 'userid', 0, ['userid' => $userid]);
        $DB->set_field('local_cp_job', 'userid', 0, ['userid' => $userid]);
        $DB->set_field('local_cp_job_log', 'userid', 0, ['userid' => $userid]);
        $DB->set_field('local_cp_batch', 'userid', 0, ['userid' => $userid]);
    }
}
