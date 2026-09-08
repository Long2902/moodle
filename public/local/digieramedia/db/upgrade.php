<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_local_digieramedia_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026090804) {
        $table = new xmldb_table('local_digieramedia_upload');
        $field = new xmldb_field(
            'targetmediaid',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'expectedmimetype'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026090804, 'local', 'digieramedia');
    }

    return true;
}
