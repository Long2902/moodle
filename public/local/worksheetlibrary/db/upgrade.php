<?php
// This file is part of Moodle - http://moodle.org/.

defined('MOODLE_INTERNAL') || die();

/** Upgrade local_worksheetlibrary. */
function xmldb_local_worksheetlibrary_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026090701) {
        $table = new xmldb_table('wslib_version');
        $fields = [
            new xmldb_field('nativejson', XMLDB_TYPE_TEXT, null, null, null, null, null, 'contenthtml'),
            new xmldb_field('schemaversion', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'nativejson'),
            new xmldb_field('revision', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'schemaversion'),
            new xmldb_field('renderedhtml', XMLDB_TYPE_TEXT, null, null, null, null, null, 'revision'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026090701, 'local', 'worksheetlibrary');
    }

    return true;
}
