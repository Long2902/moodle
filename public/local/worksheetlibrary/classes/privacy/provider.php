<?php
namespace local_worksheetlibrary\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for worksheet-library author attribution.
 *
 * Shared worksheet content is deliberately retained when an account is
 * deleted. Only the personal attribution (createdby) is anonymised.
 */
class provider implements \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('wslib_folder', [
            'createdby' => 'privacy:metadata:wslib_folder:createdby',
        ], 'privacy:metadata:wslib_folder');
        $collection->add_database_table('wslib_item', [
            'createdby' => 'privacy:metadata:wslib_item:createdby',
        ], 'privacy:metadata:wslib_item');
        $collection->add_database_table('wslib_version', [
            'createdby' => 'privacy:metadata:wslib_version:createdby',
        ], 'privacy:metadata:wslib_version');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        $hasdata = $DB->record_exists('wslib_folder', ['createdby' => $userid])
            || $DB->record_exists('wslib_item', ['createdby' => $userid])
            || $DB->record_exists('wslib_version', ['createdby' => $userid]);
        if ($hasdata) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }
            $data = (object)[
                'folders' => array_values($DB->get_records('wslib_folder', ['createdby' => $userid], 'id')),
                'items' => array_values($DB->get_records('wslib_item', ['createdby' => $userid], 'id')),
                'versions' => array_values($DB->get_records('wslib_version', ['createdby' => $userid], 'id')),
            ];
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_worksheetlibrary')],
                $data
            );
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        foreach (['wslib_folder', 'wslib_item', 'wslib_version'] as $table) {
            $DB->set_field_select($table, 'createdby', 0, 'createdby <> 0');
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }
            foreach (['wslib_folder', 'wslib_item', 'wslib_version'] as $table) {
                $DB->set_field($table, 'createdby', 0, ['createdby' => $userid]);
            }
        }
    }
}
