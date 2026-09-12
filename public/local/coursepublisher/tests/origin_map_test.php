<?php

namespace local_coursepublisher;

use local_coursepublisher\local\origin_map_service;

/** @covers \local_coursepublisher\local\origin_map_service */
final class origin_map_test extends \advanced_testcase {
    public function test_missing_mapping_returns_null(): void {
        $this->resetAfterTest(true);
        $this->assertNull(origin_map_service::find_current(1, 'activity', 2, 3));
    }
}
