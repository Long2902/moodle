<?php
namespace local_coursepublisher;

/**
 * @covers \local_coursepublisher\local\digiera_integration
 */
final class digiera_mode_schema_test extends \advanced_testcase {
    public function test_job_and_batch_store_digiera_mode(): void {
        global $DB;
        $dbman = $DB->get_manager();
        foreach (['local_cp_job', 'local_cp_batch'] as $tablename) {
            $table = new \xmldb_table($tablename);
            $field = new \xmldb_field('digieramode');
            $this->assertTrue(
                $dbman->field_exists($table, $field),
                "Field digieramode must exist in {$tablename}"
            );
        }
    }

    public function test_digiera_mode_defaults_to_shared_follow(): void {
        global $DB;
        $this->resetAfterTest();

        $now = time();
        $jobid = (int)$DB->insert_record('local_cp_job', (object)[
            'requestid' => 'test-' . uniqid(),
            'userid' => 0,
            'programid' => 0,
            'schoolid' => 0,
            'gradekey' => 'test',
            'sourcetype' => 'course',
            'sourceid' => 1,
            'sourcecourseid' => 1,
            'targetbindid' => 0,
            'targetcourseid' => 2,
            'targetsectionid' => 0,
            'batchid' => 0,
            'batchtargetid' => 0,
            'attemptno' => 1,
            'mutationstate' => 'unknown',
            'mode' => 'foundation',
            'status' => 'draft',
            'attempts' => 0,
            'idempotencykey' => 'test-' . uniqid(),
            'sourcefingerprint' => str_repeat('0', 64),
            'timecreated' => $now,
            'timemodified' => $now,
            'timestarted' => 0,
            'timefinished' => 0,
        ]);

        $job = $DB->get_record('local_cp_job', ['id' => $jobid]);
        $this->assertSame('shared_follow', $job->digieramode);
    }

    public function test_digiera_integration_is_available(): void {
        $this->assertTrue(
            \local_coursepublisher\local\digiera_integration::is_available(),
            'DIGIERA Media bridge must be available when local_digieramedia is installed'
        );
    }

    public function test_digiera_integration_executes_callback_with_scope(): void {
        $result = \local_coursepublisher\local\digiera_integration::run_with_scope(
            'shared_follow',
            'test-op-1',
            999,
            fn() => 'executed'
        );
        $this->assertSame('executed', $result);
    }
}
