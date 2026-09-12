<?php

namespace local_coursepublisher\task;

use local_coursepublisher\local\batch_service;
use local_coursepublisher\local\job_service;

defined('MOODLE_INTERNAL') || die();

/** Short-lived fair dispatcher for fan-out child jobs. */
final class dispatch_batches_task extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('task_dispatch_batches', 'local_coursepublisher');
    }

    public function execute(): void {
        $active = batch_service::active_child_count();
        $slots = max(0, batch_service::GLOBAL_ACTIVE_LIMIT - $active);
        if ($slots <= 0) {
            return;
        }
        $batches = batch_service::dispatchable_batches();
        if (!$batches) {
            return;
        }

        // Round-robin one target per Batch per pass prevents one large Batch
        // from monopolising all newly available queue slots.
        while ($slots > 0) {
            $progress = false;
            foreach ($batches as $batch) {
                if ($slots <= 0) {
                    break;
                }
                if (batch_service::active_child_count((int)$batch->id) >= max(1, (int)$batch->dispatchlimit)) {
                    continue;
                }
                $job = batch_service::next_waiting_job((int)$batch->id);
                if (!$job) {
                    continue;
                }
                try {
                    job_service::queue_job((int)$job->id, false);
                    mtrace("Course Publisher Batch #{$batch->id}: queued child job #{$job->id}.");
                } catch (\Throwable $e) {
                    mtrace("Course Publisher Batch #{$batch->id}: failed to queue job #{$job->id}: {$e->getMessage()}");
                }
                $slots--;
                $progress = true;
            }
            if (!$progress) {
                break;
            }
        }
    }
}
