<?php

namespace local_coursepublisher;

use local_coursepublisher\local\batch_service;

/** @covers \local_coursepublisher\task\dispatch_batches_task */
final class dispatcher_test extends \advanced_testcase {
    public function test_global_limit_constant_is_conservative(): void {
        $this->assertSame(10, batch_service::GLOBAL_ACTIVE_LIMIT);
    }
}
