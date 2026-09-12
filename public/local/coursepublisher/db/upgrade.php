<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade local_coursepublisher.
 */
function xmldb_local_coursepublisher_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026082601) {
        $table = new xmldb_table('local_cp_job');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('requestid', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('programid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('schoolid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('gradekey', XMLDB_TYPE_CHAR, '2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sourcetype', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sourceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sourcecourseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('targetbindid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('targetcourseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('mode', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'foundation');
        $table->add_field('status', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'draft');
        $table->add_field('attempts', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('idempotencykey', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sourcefingerprint', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('preflightjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('lasterror', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timestarted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timefinished', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('requestid_uix', XMLDB_INDEX_UNIQUE, ['requestid']);
        $table->add_index('idempotency_uix', XMLDB_INDEX_UNIQUE, ['idempotencykey']);
        $table->add_index('status_ix', XMLDB_INDEX_NOTUNIQUE, ['status']);
        $table->add_index('target_ix', XMLDB_INDEX_NOTUNIQUE, ['targetcourseid']);
        $table->add_index('route_ix', XMLDB_INDEX_NOTUNIQUE, ['programid', 'schoolid', 'gradekey']);
        $table->add_index('userid_ix', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_cp_job_item');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('jobid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sourcecmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sourcemodule', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sourceinstanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sourcefingerprint', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('targetcmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('attempts', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('errorcode', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('errormessage', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timestarted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timefinished', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('job_fk', XMLDB_KEY_FOREIGN, ['jobid'], 'local_cp_job', ['id']);
        $table->add_index('job_source_uix', XMLDB_INDEX_UNIQUE, ['jobid', 'sourcecmid']);
        $table->add_index('status_ix', XMLDB_INDEX_NOTUNIQUE, ['status']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_cp_job_log');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('jobid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('event', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('level', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'info');
        $table->add_field('message', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('details', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('job_fk', XMLDB_KEY_FOREIGN, ['jobid'], 'local_cp_job', ['id']);
        $table->add_index('job_time_ix', XMLDB_INDEX_NOTUNIQUE, ['jobid', 'timecreated']);
        $table->add_index('userid_ix', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026082601, 'local', 'coursepublisher');
    }

    if ($oldversion < 2026082602) {
        // 0.2.1 adds the real Page-copy pilot using existing CASE 2 tables only.
        upgrade_plugin_savepoint(true, 2026082602, 'local', 'coursepublisher');
    }

    if ($oldversion < 2026082701) {
        // 0.2.2 adds exact target-section/activity placement metadata in preflightjson only.
        // No Moodle DB schema change is required.
        upgrade_plugin_savepoint(true, 2026082701, 'local', 'coursepublisher');
    }

    if ($oldversion < 2026082702) {
        // 0.2.3 records the created target section immediately for recoverability
        // of the page-only section placement pilot.
        $table = new xmldb_table('local_cp_job');
        $field = new xmldb_field(
            'targetsectionid',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'targetcourseid'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026082702, 'local', 'coursepublisher');
    }

    if ($oldversion < 2026082801) {
        // 0.2.4 expands section-copy support from Page-only to Page + Quiz.
        // Existing CASE 2 tables already contain all required durable state.
        upgrade_plugin_savepoint(true, 2026082801, 'local', 'coursepublisher');
    }

    if ($oldversion < 2026082802) {
        // 0.2.5 adds Moodle 5.1 delegated-subsection copy using Core's
        // TYPE_1ACTIVITY backup plan for mod_subsection. No schema change.
        upgrade_plugin_savepoint(true, 2026082802, 'local', 'coursepublisher');
    }

    if ($oldversion < 2026082803) {
        // 0.2.6 adds capability-driven generic activity publishing and
        // peer-section placement. No schema change.
        upgrade_plugin_savepoint(true, 2026082803, 'local', 'coursepublisher');
    }

    if ($oldversion < 2026082804) {
        // 1.0.0 production cleanup. No schema change.
        upgrade_plugin_savepoint(true, 2026082804, 'local', 'coursepublisher');
    }


    if ($oldversion < 2026082901) {
        // 1.1.0 adds durable multi-target Batch orchestration and origin mappings.
        $table = new xmldb_table('local_cp_batch');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('requestid', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('programid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('gradekey', XMLDB_TYPE_CHAR, '2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sourcetype', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sourceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sourcecourseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sourcefingerprint', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('publishmode', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('placementmode', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'auto');
        $table->add_field('placementjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('resolverversion', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('status', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'draft');
        $table->add_field('dispatchlimit', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '10');
        $table->add_field('pausereason', XMLDB_TYPE_TEXT, null, null, null, null, null);
        foreach (['totaltargets','readytargets','blockedtargets','waitingtargets','queuedtargets','runningtargets',
                'succeededtargets','failedtargets','manualreviewtargets','cancelledtargets','timecreated','timemodified',
                'timestarted','timefinished'] as $fieldname) {
            $table->add_field($fieldname, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        }
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('requestid_uix', XMLDB_INDEX_UNIQUE, ['requestid']);
        $table->add_index('status_ix', XMLDB_INDEX_NOTUNIQUE, ['status']);
        $table->add_index('program_grade_ix', XMLDB_INDEX_NOTUNIQUE, ['programid', 'gradekey']);
        $table->add_index('source_ix', XMLDB_INDEX_NOTUNIQUE, ['sourcecourseid', 'sourcetype', 'sourceid']);
        $table->add_index('userid_ix', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('timecreated_ix', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_cp_batch_target');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('batchid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        foreach (['targetbindid','regionid','schoolid','targetcourseid'] as $fieldname) {
            $table->add_field($fieldname, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        }
        $table->add_field('preflightstatus', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'blocked');
        $table->add_field('status', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'blocked');
        $table->add_field('blockcode', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('blockreason', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('preflightjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('logicalplacementjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('resolvedplacementjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('attempts', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('latestjobid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        foreach (['timecreated','timemodified','timefinished'] as $fieldname) {
            $table->add_field($fieldname, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        }
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('batch_fk', XMLDB_KEY_FOREIGN, ['batchid'], 'local_cp_batch', ['id']);
        $table->add_index('batch_target_uix', XMLDB_INDEX_UNIQUE, ['batchid', 'targetbindid']);
        $table->add_index('batch_status_ix', XMLDB_INDEX_NOTUNIQUE, ['batchid', 'status']);
        $table->add_index('targetcourse_ix', XMLDB_INDEX_NOTUNIQUE, ['targetcourseid']);
        $table->add_index('latestjob_ix', XMLDB_INDEX_NOTUNIQUE, ['latestjobid']);
        $table->add_index('school_ix', XMLDB_INDEX_NOTUNIQUE, ['schoolid']);
        $table->add_index('region_ix', XMLDB_INDEX_NOTUNIQUE, ['regionid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_cp_origin_map');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('sourcecourseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sourcetype', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sourceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('targetcourseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('targettype', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, null);
        $table->add_field('targetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('batchid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('jobid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sourcefingerprint', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('source_target_uix', XMLDB_INDEX_UNIQUE, ['sourcecourseid','sourcetype','sourceid','targetcourseid']);
        $table->add_index('target_ix', XMLDB_INDEX_NOTUNIQUE, ['targetcourseid','targettype','targetid']);
        $table->add_index('job_ix', XMLDB_INDEX_NOTUNIQUE, ['jobid']);
        $table->add_index('batch_ix', XMLDB_INDEX_NOTUNIQUE, ['batchid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_cp_job');
        $fields = [
            new xmldb_field('batchid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'targetsectionid'),
            new xmldb_field('batchtargetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'batchid'),
            new xmldb_field('attemptno', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'batchtargetid'),
            new xmldb_field('mutationstate', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'unknown', 'attemptno'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        $index = new xmldb_index('batch_ix', XMLDB_INDEX_NOTUNIQUE, ['batchid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        $index = new xmldb_index('batchtarget_ix', XMLDB_INDEX_NOTUNIQUE, ['batchtargetid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026082901, 'local', 'coursepublisher');
    }


    if ($oldversion < 2026082902) {
        // 1.1.1 generalises Grade into Target Groups and adds topology auto-discovery configuration.
        $legacygroups = ['10' => 'Lớp 10', '11' => 'Lớp 11', '12' => 'Lớp 12'];

        // Fail before any 1.1.1 schema mutation when active configured routes use an unsupported legacy key.
        foreach (['local_cp_master', 'local_cp_target'] as $tablename) {
            $records = $DB->get_records_select($tablename, 'enabled = 1', [], '', 'id,gradekey');
            foreach ($records as $record) {
                if (!array_key_exists((string)$record->gradekey, $legacygroups)) {
                    throw new moodle_exception('upgradeunsupportedgroupkey', 'local_coursepublisher', '', (string)$record->gradekey);
                }
            }
        }

        $table = new xmldb_table('local_cp_target_group');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('programid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('groupkey', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('program_fk', XMLDB_KEY_FOREIGN, ['programid'], 'local_cp_program', ['id']);
        $table->add_index('program_group_uix', XMLDB_INDEX_UNIQUE, ['programid', 'groupkey']);
        $table->add_index('enabled_ix', XMLDB_INDEX_NOTUNIQUE, ['enabled']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_cp_discovery_rule');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('targetgroupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('fieldname', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'fullname');
        $table->add_field('matchtype', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'contains');
        $table->add_field('pattern', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('priority', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '100');
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('targetgroup_fk', XMLDB_KEY_FOREIGN, ['targetgroupid'], 'local_cp_target_group', ['id']);
        $table->add_index('group_enabled_ix', XMLDB_INDEX_NOTUNIQUE, ['targetgroupid', 'enabled']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_cp_discovery_container');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('matchtype', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'contains');
        $table->add_field('pattern', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('enabled_ix', XMLDB_INDEX_NOTUNIQUE, ['enabled']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $programtable = new xmldb_table('local_cp_program');
        $rootfield = new xmldb_field('discoveryrootcategoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'enabled');
        if (!$dbman->field_exists($programtable, $rootfield)) {
            $dbman->add_field($programtable, $rootfield);
        }

        // Moodle DDL refuses to change a field while an index depends on it. Drop and recreate
        // each known gradekey index around the precision change. This is deliberately per-table
        // so a retry after a partial failed upgrade remains safe and deterministic.
        $gradekeyindexes = [
            'local_cp_master' => ['program_grade_uix', XMLDB_INDEX_UNIQUE, ['programid', 'gradekey']],
            'local_cp_target' => ['route_uix', XMLDB_INDEX_UNIQUE, ['programid', 'schoolid', 'gradekey']],
            'local_cp_job' => ['route_ix', XMLDB_INDEX_NOTUNIQUE, ['programid', 'schoolid', 'gradekey']],
            'local_cp_batch' => ['program_grade_ix', XMLDB_INDEX_NOTUNIQUE, ['programid', 'gradekey']],
        ];
        foreach ($gradekeyindexes as $tablename => [$indexname, $indextype, $indexfields]) {
            $t = new xmldb_table($tablename);
            $index = new xmldb_index($indexname, $indextype, $indexfields);
            if ($dbman->index_exists($t, $index)) {
                $dbman->drop_index($t, $index);
            }

            $field = new xmldb_field('gradekey', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $dbman->change_field_precision($t, $field);

            if (!$dbman->index_exists($t, $index)) {
                $dbman->add_index($t, $index);
            }
        }

        foreach (['local_cp_master', 'local_cp_target', 'local_cp_batch'] as $tablename) {
            $t = new xmldb_table($tablename);
            $field = new xmldb_field('targetgroupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'gradekey');
            if (!$dbman->field_exists($t, $field)) {
                $dbman->add_field($t, $field);
            }
        }

        $now = time();
        $programs = $DB->get_records('local_cp_program');
        foreach ($programs as $program) {
            $sortorder = 10;
            foreach ($legacygroups as $groupkey => $name) {
                $group = $DB->get_record('local_cp_target_group', ['programid' => $program->id, 'groupkey' => $groupkey]);
                if (!$group) {
                    $group = (object)[
                        'programid' => (int)$program->id,
                        'groupkey' => $groupkey,
                        'name' => $name,
                        'enabled' => 1,
                        'sortorder' => $sortorder,
                        'timecreated' => $now,
                        'timemodified' => $now,
                    ];
                    $group->id = $DB->insert_record('local_cp_target_group', $group);
                }
                $DB->set_field_select('local_cp_master', 'targetgroupid', $group->id, 'programid = ? AND gradekey = ?', [$program->id, $groupkey]);
                $DB->set_field_select('local_cp_target', 'targetgroupid', $group->id, 'programid = ? AND gradekey = ?', [$program->id, $groupkey]);
                $DB->set_field_select('local_cp_batch', 'targetgroupid', $group->id, 'programid = ? AND gradekey = ?', [$program->id, $groupkey]);

                $defaults = [
                    ['fullname', 'contains', 'LỚP ' . $groupkey, 'Tên khóa chứa LỚP ' . $groupkey],
                    ['shortname', 'contains', '_L' . $groupkey . '_', 'Shortname chứa _L' . $groupkey . '_'],
                ];
                foreach ($defaults as [$fieldname, $matchtype, $pattern, $rulename]) {
                    $ruleexists = false;
                    $existingrules = $DB->get_records('local_cp_discovery_rule', [
                        'targetgroupid' => (int)$group->id,
                        'fieldname' => $fieldname,
                        'matchtype' => $matchtype,
                    ], '', 'id,pattern');
                    foreach ($existingrules as $existingrule) {
                        if ((string)$existingrule->pattern === $pattern) {
                            $ruleexists = true;
                            break;
                        }
                    }
                    if (!$ruleexists) {
                        $DB->insert_record('local_cp_discovery_rule', (object)[
                            'targetgroupid' => (int)$group->id,
                            'name' => $rulename,
                            'fieldname' => $fieldname,
                            'matchtype' => $matchtype,
                            'pattern' => $pattern,
                            'priority' => 100,
                            'enabled' => 1,
                            'sortorder' => 0,
                            'timecreated' => $now,
                            'timemodified' => $now,
                        ]);
                    }
                }
                $sortorder += 10;
            }
        }

        foreach ([
            ['Khối THPT', 'KHỐI THPT', 10],
            ['Khối Liên Cấp', 'KHỐI LIÊN CẤP', 20],
            ['Trung tâm', 'TRUNG TÂM', 30],
        ] as [$name, $pattern, $sortorder]) {
            $containerexists = false;
            $existingcontainers = $DB->get_records('local_cp_discovery_container', [
                'matchtype' => 'contains',
            ], '', 'id,pattern');
            foreach ($existingcontainers as $existingcontainer) {
                if ((string)$existingcontainer->pattern === $pattern) {
                    $containerexists = true;
                    break;
                }
            }
            if (!$containerexists) {
                $DB->insert_record('local_cp_discovery_container', (object)[
                    'name' => $name,
                    'matchtype' => 'contains',
                    'pattern' => $pattern,
                    'enabled' => 1,
                    'sortorder' => $sortorder,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
            }
        }

        // Validate deterministic mappings before savepoint.
        foreach (['local_cp_master', 'local_cp_target'] as $tablename) {
            $unmapped = $DB->count_records_select($tablename, 'enabled = 1 AND targetgroupid = 0');
            if ($unmapped) {
                throw new moodle_exception('upgradeunmappedtargetgroups', 'local_coursepublisher', '', $unmapped);
            }
        }

        upgrade_plugin_savepoint(true, 2026082902, 'local', 'coursepublisher');
    }

    return true;
}
