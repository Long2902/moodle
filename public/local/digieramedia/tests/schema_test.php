<?php
namespace local_digieramedia;

/** @coversNothing */
final class schema_test extends \advanced_testcase {
    public function test_required_tables_exist(): void {
        global $DB;
        $dbman = $DB->get_manager();
        foreach ([
            'local_digieramedia_media',
            'local_digieramedia_version',
            'local_digieramedia_reference',
            'local_digieramedia_upload',
            'local_digieramedia_upart',
            'local_digieramedia_trash',
            'local_digieramedia_audit',
            'local_digieramedia_migration',
            'local_digieramedia_migitem',
        ] as $tablename) {
            $this->assertTrue($dbman->table_exists(new \xmldb_table($tablename)), $tablename);
        }
    }

    public function test_canonical_unique_indexes_exist(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $checks = [
            ['local_digieramedia_media', 'uuid'],
            ['local_digieramedia_reference', 'uuid'],
            ['local_digieramedia_upload', 'sessionuuid'],
            ['local_digieramedia_version', 'media-version'],
            ['local_digieramedia_upart', 'upload-part'],
            ['local_digieramedia_migration', 'jobuuid'],
            ['local_digieramedia_migitem', 'migration-fingerprint'],
        ];
        foreach ($checks as [$table, $index]) {
            $this->assertTrue(
                $dbman->index_exists(new \xmldb_table($table), new \xmldb_index($index)),
                $table . ':' . $index
            );
        }
    }
}
