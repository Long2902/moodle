<?php
// This file is part of Moodle - http://moodle.org/.

defined('MOODLE_INTERNAL') || die();

/** Upgrade mod_worksheetgrader. */
function xmldb_worksheetgrader_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026080601) {
        $sessiontable = new xmldb_table('wsg_session');
        $contentmode = new xmldb_field('contentmode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'html', 'teammode');
        if (!$dbman->field_exists($sessiontable, $contentmode)) {
            $dbman->add_field($sessiontable, $contentmode);
        }

        $table = new xmldb_table('wsg_docspace');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('worksheetgraderid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('teamid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('scope', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'attempt');
            $table->add_field('roomid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '');
            $table->add_field('fileid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '');
            $table->add_field('roomtitle', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('publiclink', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('requesttoken', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('access', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'edit');
            $table->add_field('status', XMLDB_TYPE_CHAR, '24', null, XMLDB_NOTNULL, null, 'ready');
            $table->add_field('lastsync', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('activity', XMLDB_KEY_FOREIGN, ['worksheetgraderid'], 'worksheetgrader', ['id']);
            $table->add_key('session', XMLDB_KEY_FOREIGN, ['sessionid'], 'wsg_session', ['id']);
            $table->add_index('sessionscope', XMLDB_INDEX_NOTUNIQUE, ['sessionid', 'scope']);
            $table->add_index('activitysessionteamscope', XMLDB_INDEX_UNIQUE, ['worksheetgraderid', 'sessionid', 'teamid', 'scope']);
            $table->add_index('attemptscope', XMLDB_INDEX_NOTUNIQUE, ['attemptid', 'scope']);
            $dbman->create_table($table);
        }

        // Existing sessions continue to use the HTML workflow until a teacher
        // explicitly creates a DocSpace template for them.
        $DB->set_field_select('wsg_session', 'contentmode', 'html', "contentmode IS NULL OR contentmode = ''");
        upgrade_mod_savepoint(true, 2026080601, 'worksheetgrader');
    }


    if ($oldversion < 2026080602) {
        $table = new xmldb_table('wsg_docspace');
        if ($dbman->table_exists($table)) {
            $oldindex = new xmldb_index('sessionteamscope', XMLDB_INDEX_UNIQUE, ['sessionid', 'teamid', 'scope']);
            if ($dbman->index_exists($table, $oldindex)) {
                $dbman->drop_index($table, $oldindex);
            }
            $newindex = new xmldb_index(
                'activitysessionteamscope',
                XMLDB_INDEX_UNIQUE,
                ['worksheetgraderid', 'sessionid', 'teamid', 'scope']
            );
            if (!$dbman->index_exists($table, $newindex)) {
                $dbman->add_index($table, $newindex);
            }
        }
        upgrade_mod_savepoint(true, 2026080602, 'worksheetgrader');
    }


    if ($oldversion < 2026080603) {
        // Migrate earlier fixed SDK settings to auto-probing. Current cloud tenants
        // are tried with 2.2.0 first and 2.0.0 is retained only as a legacy fallback.
        $sdkversion = trim((string)get_config('mod_worksheetgrader', 'docspacesdkversion'));
        if ($sdkversion === '' || $sdkversion === '2.2.0') {
            set_config('docspacesdkversion', 'auto', 'mod_worksheetgrader');
        }
        upgrade_mod_savepoint(true, 2026080603, 'worksheetgrader');
    }


    if ($oldversion < 2026080604) {
        $table = new xmldb_table('wsg_docspace');
        if ($dbman->table_exists($table)) {
            $folderid = new xmldb_field('folderid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '', 'roomid');
            if (!$dbman->field_exists($table, $folderid)) {
                $dbman->add_field($table, $folderid);
            }
        }
        upgrade_mod_savepoint(true, 2026080604, 'worksheetgrader');
    }

    if ($oldversion < 2026080701) {
        // V11.5.3 switches anonymous editing to the supported Public Room mode.
        // No database schema change is required.
        upgrade_mod_savepoint(true, 2026080701, 'worksheetgrader');
    }


    if ($oldversion < 2026090501) {
        $sessiontable = new xmldb_table('wsg_session');
        $sessionfields = [
            new xmldb_field('libraryitemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'),
            new xmldb_field('libraryversionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'),
            new xmldb_field('worksheetkind', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'html'),
            new xmldb_field('worksheethash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, ''),
            new xmldb_field('migrationstatus', XMLDB_TYPE_CHAR, '24', null, XMLDB_NOTNULL, null, 'not_required'),
            new xmldb_field('setupcomplete', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0'),
        ];
        foreach ($sessionfields as $field) {
            if (!$dbman->field_exists($sessiontable, $field)) {
                $dbman->add_field($sessiontable, $field);
            }
        }
        $attempttable = new xmldb_table('wsg_attempt');
        foreach ([
            new xmldb_field('officerevision', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1'),
            new xmldb_field('officefilename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, ''),
        ] as $field) {
            if (!$dbman->field_exists($attempttable, $field)) {
                $dbman->add_field($attempttable, $field);
            }
        }
        $savetable = new xmldb_table('wsg_office_save');
        if (!$dbman->table_exists($savetable)) {
            $savetable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $savetable->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $savetable->add_field('requestid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '');
            $savetable->add_field('intent', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'progress');
            $savetable->add_field('status', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'pending');
            $savetable->add_field('documentkey', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, '');
            $savetable->add_field('revision', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
            $savetable->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $savetable->add_field('errorcode', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '');
            $savetable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $savetable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $savetable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $savetable->add_key('attempt', XMLDB_KEY_FOREIGN, ['attemptid'], 'wsg_attempt', ['id']);
            $savetable->add_index('requestid', XMLDB_INDEX_UNIQUE, ['requestid']);
            $savetable->add_index('attemptstatus', XMLDB_INDEX_NOTUNIQUE, ['attemptid', 'status']);
            $dbman->create_table($savetable);
        }
        upgrade_mod_savepoint(true, 2026090501, 'worksheetgrader');
    }


    if ($oldversion < 2026090701) {
        // Preview v0.1 adds the Native worksheet runtime bridge without a schema change.
        upgrade_mod_savepoint(true, 2026090701, 'worksheetgrader');
    }

    return true;
}
