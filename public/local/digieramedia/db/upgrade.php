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

    if ($oldversion < 2026090901) {
        upgrade_plugin_savepoint(true, 2026090901, 'local', 'digieramedia');
    }

    if ($oldversion < 2026090902) {
        upgrade_plugin_savepoint(true, 2026090902, 'local', 'digieramedia');
    }

    if ($oldversion < 2026091001) {
        $table = new xmldb_table('local_digieramedia_recent');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('mediaid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastusedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usecount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field(
            'lastaction',
            XMLDB_TYPE_CHAR,
            '32',
            null,
            XMLDB_NOTNULL,
            null,
            'CREATE_REFERENCE'
        );
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key(
            'mediaid',
            XMLDB_KEY_FOREIGN,
            ['mediaid'],
            'local_digieramedia_media',
            ['id']
        );
        $table->add_index('user-media', XMLDB_INDEX_UNIQUE, ['userid', 'mediaid']);
        $table->add_index('user-lastused', XMLDB_INDEX_NOTUNIQUE, ['userid', 'lastusedat']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026091001, 'local', 'digieramedia');
    }

    return true;
}
