<?php

namespace local_coursepublisher\task;

use local_coursepublisher\local\job_service;
use local_coursepublisher\local\job_status;
use local_coursepublisher\local\batch_service;
use local_coursepublisher\local\batch_status;

defined('MOODLE_INTERNAL') || die();

/**
 * CASE 2 background publish worker.
 *
 * It runs foundation jobs and narrowly-scoped real publish pilots under the target-course lock.
 */
final class publish_job_task extends \core\task\adhoc_task {
    public function get_name(): string {
        return get_string('task_publish_job', 'local_coursepublisher');
    }

    public function execute(): void {
        $data = $this->get_custom_data();
        $jobid = isset($data->jobid) ? (int)$data->jobid : 0;
        if ($jobid <= 0) {
            throw new \moodle_exception('invalidparameter');
        }

        $job = job_service::get_job($jobid);
        if (job_status::is_nonretryable_terminal((string)$job->status)) {
            mtrace("Course Publisher job #{$jobid}: already terminal ({$job->status}); nothing to do.");
            return;
        }

        if ((int)($job->batchid ?? 0) > 0) {
            $batch = batch_service::get_batch((int)$job->batchid);
            if ((string)$batch->status === batch_status::CANCELLED) {
                job_service::transition($jobid, job_status::CANCELLED, '', ['batch_cancelled' => true]);
                mtrace("Course Publisher job #{$jobid}: Batch #{$batch->id} cancelled; nothing to do.");
                return;
            }
            if ((string)$batch->status === batch_status::PAUSED) {
                mtrace("Course Publisher job #{$jobid}: Batch #{$batch->id} paused; soft retry scheduled.");
                $this->set_soft_retry_delay(60);
                return;
            }
            if ((string)$job->status === job_status::WAITING) {
                // This can be an older adhoc task record from a paused Batch.
                // WAITING is deliberately non-runnable until the dispatcher
                // transitions the job back to QUEUED under the global slot cap.
                mtrace("Course Publisher job #{$jobid}: waiting for dispatcher slot; soft retry scheduled.");
                $this->set_soft_retry_delay(60);
                return;
            }
        }

        $factory = \core\lock\lock_config::get_lock_factory('local_coursepublisher_publish');
        $resource = 'target_' . (int)$job->targetcourseid;
        $lock = $factory->get_lock($resource, job_service::LOCK_TIMEOUT, job_service::LOCK_MAX_LIFETIME);
        if (!$lock) {
            mtrace("Course Publisher job #{$jobid}: target lock busy; retry scheduled.");
            job_service::mark_lock_wait($jobid);
            $this->set_soft_retry_delay(60);
            return;
        }

        try {
            mtrace("Course Publisher job #{$jobid}: target lock acquired for course #{$job->targetcourseid}.");
            job_service::log($jobid, 'lock_acquired', 'info', get_string('joblog_lockacquired', 'local_coursepublisher'), [
                'targetcourseid' => (int)$job->targetcourseid,
            ]);
            job_service::run_locked_job($jobid);
        } finally {
            $lock->release();
            job_service::log($jobid, 'lock_released', 'info', get_string('joblog_lockreleased', 'local_coursepublisher'), [
                'targetcourseid' => (int)$job->targetcourseid,
            ]);
            mtrace("Course Publisher job #{$jobid}: target lock released.");
        }
    }
}
