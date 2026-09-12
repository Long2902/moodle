<?php

namespace local_coursepublisher;

use local_coursepublisher\local\batch_status;
use local_coursepublisher\local\job_status;

/**
 * Batch service/schema tests for Course Publisher 1.1.0.
 *
 * @covers \local_coursepublisher\local\batch_service
 */
final class batch_service_test extends \advanced_testcase {
    public function test_batch_schema_and_status_constants_exist(): void {
        global $DB;
        $dbman = $DB->get_manager();

        $this->assertTrue($dbman->table_exists('local_cp_batch'));
        $this->assertTrue($dbman->table_exists('local_cp_batch_target'));
        $this->assertTrue($dbman->table_exists('local_cp_origin_map'));
        $this->assertSame('waiting', job_status::WAITING);
        $this->assertSame('manual_review', job_status::MANUAL_REVIEW);
        $this->assertSame('paused', batch_status::PAUSED);
    }
}
