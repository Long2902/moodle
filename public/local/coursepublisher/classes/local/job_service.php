<?php

namespace local_coursepublisher\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Durable CASE 2 job orchestration.
 *
 * 0.2.2 retains foundation + the first Page-copy pilot and adds an exact
 * target placement contract so a copied Page can be moved into a selected
 * target section and sequence position using Moodle Core APIs.
 */
final class job_service {
    public const MODE_FOUNDATION = 'foundation';
    public const MODE_PAGE_PILOT = 'page_pilot';
    public const MODE_PAGE_PLACEMENT = 'page_place';
    public const MODE_QUIZ_PLACEMENT = 'quiz_place';
    public const MODE_SECTION_PAGE_PLACEMENT = 'section_pages';
    public const MODE_SECTION_MIXED_PLACEMENT = 'section_mixed';
    public const MODE_SUBSECTION_TREE_PLACEMENT = 'subsection_tree';
    public const MODE_ACTIVITY_GENERIC_PLACEMENT = 'activity_generic';
    public const MODE_SECTION_GENERIC_PEER = 'section_peer';
    public const LOCK_TIMEOUT = 5;
    public const LOCK_MAX_LIFETIME = 900;

    /**
     * Create or safely reuse a durable job after a successful exact-route preflight.
     *
     * @return \stdClass job record with transient ->reused and ->queuednow flags.
     */
    public static function create_or_reuse(int $programid, string $gradekey, string $sourcetype,
            int $sourceid, int $schoolid, string $mode = self::MODE_FOUNDATION, array $placement = []): \stdClass {
        global $DB, $USER;

        service::assert_grade($gradekey);
        if (!in_array($sourcetype, ['activity', 'section'], true) || $sourceid <= 0 || $schoolid <= 0) {
            throw new \moodle_exception('invalidparameter');
        }
        if (!in_array($mode, [self::MODE_FOUNDATION, self::MODE_PAGE_PILOT, self::MODE_PAGE_PLACEMENT, self::MODE_QUIZ_PLACEMENT, self::MODE_SECTION_PAGE_PLACEMENT, self::MODE_SECTION_MIXED_PLACEMENT, self::MODE_SUBSECTION_TREE_PLACEMENT, self::MODE_ACTIVITY_GENERIC_PLACEMENT, self::MODE_SECTION_GENERIC_PEER], true)) {
            throw new \moodle_exception('invalidparameter');
        }
        if (in_array($mode, [self::MODE_PAGE_PILOT, self::MODE_PAGE_PLACEMENT, self::MODE_QUIZ_PLACEMENT, self::MODE_ACTIVITY_GENERIC_PLACEMENT], true) && $sourcetype !== 'activity') {
            throw new \moodle_exception('pilotactivityonly', 'local_coursepublisher');
        }
        if (in_array($mode, [self::MODE_SECTION_PAGE_PLACEMENT, self::MODE_SECTION_MIXED_PLACEMENT, self::MODE_SUBSECTION_TREE_PLACEMENT, self::MODE_SECTION_GENERIC_PEER], true) && $sourcetype !== 'section') {
            throw new \moodle_exception('sectionpilottypeonly', 'local_coursepublisher');
        }

        $school = $DB->get_record('local_cp_school', ['id' => $schoolid, 'enabled' => 1], '*', MUST_EXIST);
        $result = service::resolve_preview(
            $programid,
            $gradekey,
            $sourcetype,
            $sourceid,
            (int)$school->regionid,
            $schoolid
        );

        if (count($result['targets']) !== 1) {
            throw new \moodle_exception('jobexactroute', 'local_coursepublisher');
        }
        $route = reset($result['targets']);
        if (!$route || $route->status !== 'ready' || empty($route->courseid) || empty($route->targetbindid)) {
            throw new \moodle_exception('jobpreflightnotready', 'local_coursepublisher');
        }

        $sourcecourseid = (int)$result['mastercourse']->id;
        $sourcefingerprint = self::source_fingerprint($sourcetype, $sourceid, $sourcecourseid);
        $normalisedplacement = [];
        if (in_array($mode, [self::MODE_PAGE_PILOT, self::MODE_PAGE_PLACEMENT], true)) {
            $manifest = self::build_manifest($sourcetype, $sourceid, $sourcecourseid);
            if (count($manifest) !== 1 || (string)$manifest[0]['sourcemodule'] !== 'page') {
                throw new \moodle_exception('pilotpageonly', 'local_coursepublisher');
            }
        }
        if ($mode === self::MODE_QUIZ_PLACEMENT) {
            $manifest = self::build_manifest($sourcetype, $sourceid, $sourcecourseid);
            if (count($manifest) !== 1 || (string)$manifest[0]['sourcemodule'] !== 'quiz') {
                throw new \moodle_exception('pilotquizonly', 'local_coursepublisher');
            }
        }
        if ($mode === self::MODE_SECTION_PAGE_PLACEMENT) {
            content_publisher::validate_source_section_for_page_pilot($sourceid, $sourcecourseid);
            $manifest = self::build_manifest($sourcetype, $sourceid, $sourcecourseid);
            $unsupported = array_values(array_unique(array_filter(array_map(
                static fn(array $row): string => (string)$row['sourcemodule'] !== 'page'
                    ? (string)$row['sourcemodule']
                    : '',
                $manifest
            ))));
            if ($unsupported) {
                throw new \moodle_exception(
                    'sectionpilotunsupportedmodules',
                    'local_coursepublisher',
                    '',
                    implode(', ', $unsupported)
                );
            }
        }
        if ($mode === self::MODE_SECTION_MIXED_PLACEMENT) {
            content_publisher::validate_source_section_for_page_pilot($sourceid, $sourcecourseid);
            $manifest = self::build_manifest($sourcetype, $sourceid, $sourcecourseid);
            $unsupported = array_values(array_unique(array_filter(array_map(
                static fn(array $row): string => !in_array((string)$row['sourcemodule'], ['page', 'quiz'], true)
                    ? (string)$row['sourcemodule']
                    : '',
                $manifest
            ))));
            if ($unsupported) {
                throw new \moodle_exception(
                    'sectionmixedunsupportedmodules',
                    'local_coursepublisher',
                    '',
                    implode(', ', $unsupported)
                );
            }
        }
        if ($mode === self::MODE_SUBSECTION_TREE_PLACEMENT) {
            content_publisher::validate_source_delegated_subsection($sourceid, $sourcecourseid);
            $manifest = self::build_manifest($sourcetype, $sourceid, $sourcecourseid);
            foreach ($manifest as $row) {
                content_publisher::validate_backup_capable_activity((int)$row['sourcecmid'], $sourcecourseid);
            }
        }
        if ($mode === self::MODE_ACTIVITY_GENERIC_PLACEMENT) {
            $manifest = self::build_manifest($sourcetype, $sourceid, $sourcecourseid);
            if (count($manifest) !== 1) {
                throw new \moodle_exception('invalidparameter');
            }
            $activity = content_publisher::validate_backup_capable_activity(
                (int)$manifest[0]['sourcecmid'],
                $sourcecourseid
            );
            if ((string)$activity['modname'] === 'subsection') {
                throw new \moodle_exception('genericactivitysubsectionuse_section', 'local_coursepublisher');
            }
        }
        if ($mode === self::MODE_SECTION_GENERIC_PEER) {
            content_publisher::validate_source_section_for_generic_copy($sourceid, $sourcecourseid);
            $manifest = self::build_manifest($sourcetype, $sourceid, $sourcecourseid);
            foreach ($manifest as $row) {
                content_publisher::validate_backup_capable_activity((int)$row['sourcecmid'], $sourcecourseid);
            }
        }
        if (in_array($mode, [self::MODE_PAGE_PLACEMENT, self::MODE_QUIZ_PLACEMENT, self::MODE_SUBSECTION_TREE_PLACEMENT, self::MODE_ACTIVITY_GENERIC_PLACEMENT], true)) {
            $normalisedplacement = content_publisher::validate_placement((int)$route->courseid, $placement);
        } else if (in_array($mode, [self::MODE_SECTION_PAGE_PLACEMENT, self::MODE_SECTION_MIXED_PLACEMENT, self::MODE_SECTION_GENERIC_PEER], true)) {
            $normalisedplacement = content_publisher::validate_section_placement((int)$route->courseid, $placement);
        }
        $idempotencykey = self::idempotency_key(
            $programid,
            $schoolid,
            $gradekey,
            $sourcetype,
            $sourceid,
            (int)$route->courseid,
            $sourcefingerprint,
            $mode,
            $normalisedplacement
        );

        $existing = $DB->get_record('local_cp_job', ['idempotencykey' => $idempotencykey]);
        if ($existing) {
            $existing->reused = true;
            $existing->queuednow = false;
            if ($mode === self::MODE_FOUNDATION && in_array(
                    $existing->status,
                    [job_status::FAILED, job_status::PREFLIGHT_FAILED, job_status::CANCELLED],
                    true
            )) {
                self::transition((int)$existing->id, job_status::READY, '', [
                    'reason' => 'manual_requeue_same_idempotency_key',
                ]);
                self::queue_job((int)$existing->id, true);
                $existing = self::get_job((int)$existing->id);
                $existing->reused = true;
                $existing->queuednow = true;
            } else if ($mode !== self::MODE_FOUNDATION && $existing->status === job_status::FAILED) {
                // Real mutation may have partially happened before a failure was observed.
                // Never auto-requeue the same real-copy job; require operator review.
                $existing->manualreview = true;
            }
            return $existing;
        }

        $snapshot = [
            'mode' => $mode,
            'programid' => $programid,
            'programcode' => (string)$result['program']->code,
            'gradekey' => $gradekey,
            'schoolid' => $schoolid,
            'regionid' => (int)$school->regionid,
            'sourcetype' => $sourcetype,
            'sourceid' => $sourceid,
            'sourcecourseid' => $sourcecourseid,
            'sourcefingerprint' => $sourcefingerprint,
            'targetbindid' => (int)$route->targetbindid,
            'targetcourseid' => (int)$route->courseid,
            'targetshortname' => (string)$route->shortname,
            'route_reason' => (string)$route->reason,
            'placement' => $normalisedplacement,
            'preflighttime' => time(),
            'contentmutation' => $mode !== self::MODE_FOUNDATION,
        ];

        $now = time();
        $record = (object)[
            'requestid' => bin2hex(random_bytes(16)),
            'userid' => (int)$USER->id,
            'programid' => $programid,
            'schoolid' => $schoolid,
            'gradekey' => $gradekey,
            'sourcetype' => $sourcetype,
            'sourceid' => $sourceid,
            'sourcecourseid' => $sourcecourseid,
            'targetbindid' => (int)$route->targetbindid,
            'targetcourseid' => (int)$route->courseid,
            'targetsectionid' => 0,
            'batchid' => 0,
            'batchtargetid' => 0,
            'attemptno' => 1,
            'mutationstate' => 'none',
            'mode' => $mode,
            'status' => job_status::READY,
            'attempts' => 0,
            'idempotencykey' => $idempotencykey,
            'sourcefingerprint' => $sourcefingerprint,
            'preflightjson' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'lasterror' => null,
            'timecreated' => $now,
            'timemodified' => $now,
            'timestarted' => 0,
            'timefinished' => 0,
        ];

        try {
            $jobid = (int)$DB->insert_record('local_cp_job', $record);
        } catch (\dml_write_exception $e) {
            // A concurrent administrator may have created the same idempotent job.
            $existing = $DB->get_record('local_cp_job', ['idempotencykey' => $idempotencykey]);
            if ($existing) {
                $existing->reused = true;
                $existing->queuednow = false;
                return $existing;
            }
            throw $e;
        }

        self::log($jobid, 'job_created', 'info', get_string('joblog_created', 'local_coursepublisher'), $snapshot,
            (int)$USER->id);
        self::queue_job($jobid, false);

        $job = self::get_job($jobid);
        $job->reused = false;
        $job->queuednow = true;
        return $job;
    }

    /**
     * Create one durable WAITING child job for an already snapshotted Batch Target.
     * No adhoc task is queued here; the dispatcher owns queue exposure.
     */
    public static function create_for_batch_target(int $batchtargetid, array $context): \stdClass {
        global $DB;

        $target = $DB->get_record('local_cp_batch_target', ['id' => $batchtargetid], '*', MUST_EXIST);
        $batch = $DB->get_record('local_cp_batch', ['id' => (int)$target->batchid], '*', MUST_EXIST);
        // preflightstatus is immutable audit evidence. A target that was
        // originally BLOCKED may later pass an explicit recheck; child creation
        // is therefore gated by the current execution state, not the original
        // snapshot status.
        if ((string)$target->status !== batch_status::TARGET_READY) {
            throw new \moodle_exception('batchtargetnotready', 'local_coursepublisher');
        }

        $placement = (array)($context['placement'] ?? []);
        $attemptno = max(1, (int)$target->attempts + 1);
        $idempotencykey = hash('sha256', json_encode([
            'contract' => 'batch-child-v1',
            'batchtargetid' => $batchtargetid,
            'attemptno' => $attemptno,
            'sourcefingerprint' => (string)$batch->sourcefingerprint,
            'publishmode' => (string)$batch->publishmode,
            'placement' => $placement,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $existing = $DB->get_record('local_cp_job', ['idempotencykey' => $idempotencykey]);
        if ($existing) {
            return $existing;
        }

        $snapshot = [
            'mode' => (string)$batch->publishmode,
            'batchid' => (int)$batch->id,
            'batchtargetid' => $batchtargetid,
            'programid' => (int)$batch->programid,
            'gradekey' => (string)$batch->gradekey,
            'schoolid' => (int)$target->schoolid,
            'regionid' => (int)$target->regionid,
            'sourcetype' => (string)$batch->sourcetype,
            'sourceid' => (int)$batch->sourceid,
            'sourcecourseid' => (int)$batch->sourcecourseid,
            'sourcefingerprint' => (string)$batch->sourcefingerprint,
            'targetbindid' => (int)$target->targetbindid,
            'targetcourseid' => (int)$target->targetcourseid,
            'logicalplacement' => json_decode((string)$target->logicalplacementjson, true),
            'placement' => $placement,
            'resolverversion' => (int)$batch->resolverversion,
            'preflighttime' => time(),
            'contentmutation' => (string)$batch->publishmode !== self::MODE_FOUNDATION,
        ];
        $now = time();
        $record = (object)[
            'requestid' => bin2hex(random_bytes(16)),
            'userid' => (int)$batch->userid,
            'programid' => (int)$batch->programid,
            'schoolid' => (int)$target->schoolid,
            'gradekey' => (string)$batch->gradekey,
            'sourcetype' => (string)$batch->sourcetype,
            'sourceid' => (int)$batch->sourceid,
            'sourcecourseid' => (int)$batch->sourcecourseid,
            'targetbindid' => (int)$target->targetbindid,
            'targetcourseid' => (int)$target->targetcourseid,
            'targetsectionid' => 0,
            'batchid' => (int)$batch->id,
            'batchtargetid' => $batchtargetid,
            'attemptno' => $attemptno,
            'mutationstate' => 'none',
            'mode' => (string)$batch->publishmode,
            'status' => job_status::WAITING,
            'attempts' => 0,
            'idempotencykey' => $idempotencykey,
            'sourcefingerprint' => (string)$batch->sourcefingerprint,
            'preflightjson' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'lasterror' => null,
            'timecreated' => $now,
            'timemodified' => $now,
            'timestarted' => 0,
            'timefinished' => 0,
        ];
        $jobid = (int)$DB->insert_record('local_cp_job', $record);
        $target->attempts = $attemptno;
        $target->latestjobid = $jobid;
        $target->status = batch_status::TARGET_WAITING;
        $target->blockcode = null;
        $target->blockreason = null;
        $target->timemodified = $now;
        $target->timefinished = 0;
        $DB->update_record('local_cp_batch_target', $target);
        self::log($jobid, 'job_created', 'info', get_string('joblog_created', 'local_coursepublisher'), $snapshot,
            (int)$batch->userid);
        if (class_exists(batch_service::class) && empty($context['skipreconcile'])) {
            batch_service::reconcile((int)$batch->id);
        }
        return self::get_job($jobid);
    }

    /** Persist mutation state used by safe retry decisions. */
    public static function set_mutation_state(int $jobid, string $state): void {
        global $DB;
        if (!in_array($state, ['none', 'started', 'committed', 'uncertain', 'unknown'], true)) {
            throw new \moodle_exception('invalidparameter');
        }
        $DB->set_field('local_cp_job', 'mutationstate', $state, ['id' => $jobid]);
        $DB->set_field('local_cp_job', 'timemodified', time(), ['id' => $jobid]);
    }

    /** Mark the exact durable point immediately before the first target write. */
    private static function begin_mutation(\stdClass $job): void {
        $current = self::get_job((int)$job->id);
        if ((string)$current->mutationstate === 'none') {
            self::set_mutation_state((int)$job->id, 'started');
            self::log((int)$job->id, 'mutation_started', 'warning', 'Target mutation started.', [
                'targetcourseid' => (int)$job->targetcourseid,
                'mode' => (string)$job->mode,
            ]);
        }
    }

    /** Queue or requeue a job using Moodle's adhoc-task manager. */
    public static function queue_job(int $jobid, bool $requeue): void {
        global $DB;

        $job = self::get_job($jobid);
        $task = new \local_coursepublisher\task\publish_job_task();
        $task->set_component('local_coursepublisher');
        $task->set_custom_data(['jobid' => $jobid]);
        // One task record per job. Moodle's own retry system handles failures of that task record.
        $queued = \core\task\manager::queue_adhoc_task($task, true);

        if ($queued || self::task_is_already_queued($jobid)) {
            self::transition($jobid, job_status::QUEUED, '', [
                'requeue' => $requeue,
                'taskqueued' => (bool)$queued,
            ]);
            return;
        }

        $message = get_string('jobqueuefailed', 'local_coursepublisher');
        self::transition($jobid, job_status::FAILED, $message, ['stage' => 'queue']);
        throw new \moodle_exception('jobqueuefailed', 'local_coursepublisher');
    }

    /** Determine if a matching queued task already exists. */
    private static function task_is_already_queued(int $jobid): bool {
        $task = new \local_coursepublisher\task\publish_job_task();
        $task->set_component('local_coursepublisher');
        $task->set_custom_data(['jobid' => $jobid]);
        return (bool)\core\task\manager::get_queued_adhoc_task_record($task, false);
    }

    public static function get_job(int $jobid): \stdClass {
        global $DB;
        return $DB->get_record('local_cp_job', ['id' => $jobid], '*', MUST_EXIST);
    }

    public static function recent_jobs(int $limit = 50): array {
        global $DB;
        $sql = "SELECT j.*, p.name AS programname, p.code AS programcode,
                       s.name AS schoolname, s.code AS schoolcode,
                       c.fullname AS targetname, c.shortname AS targetshortname,
                       u.firstname, u.lastname
                  FROM {local_cp_job} j
             LEFT JOIN {local_cp_program} p ON p.id = j.programid
             LEFT JOIN {local_cp_school} s ON s.id = j.schoolid
             LEFT JOIN {course} c ON c.id = j.targetcourseid
             LEFT JOIN {user} u ON u.id = j.userid
              ORDER BY j.id DESC";
        return array_values($DB->get_records_sql($sql, [], 0, $limit));
    }

    public static function items(int $jobid): array {
        global $DB;
        return array_values($DB->get_records('local_cp_job_item', ['jobid' => $jobid], 'sortorder ASC, id ASC'));
    }

    public static function logs(int $jobid, int $limit = 100): array {
        global $DB;
        return array_values($DB->get_records('local_cp_job_log', ['jobid' => $jobid], 'id ASC', '*', 0, $limit));
    }

    /**
     * Called by the task when the target lock cannot be obtained yet.
     */
    public static function mark_lock_wait(int $jobid): void {
        self::transition($jobid, job_status::QUEUED, '', ['reason' => 'target_lock_busy']);
        self::log($jobid, 'retry_scheduled', 'warning', get_string('joblog_lockbusy', 'local_coursepublisher'), [
            'retry_after_seconds' => 60,
        ]);
    }

    /**
     * Run one job while the target lock is held.
     */
    public static function run_locked_job(int $jobid): void {
        global $DB;

        $job = self::get_job($jobid);
        if (job_status::is_nonretryable_terminal((string)$job->status)) {
            return;
        }

        $now = time();
        $job->attempts = (int)$job->attempts + 1;
        $job->timestarted = $job->timestarted ?: $now;
        $job->timemodified = $now;
        $job->lasterror = null;
        $DB->update_record('local_cp_job', $job);
        self::transition($jobid, job_status::RUNNING, '', ['attempt' => (int)$job->attempts]);
        $job = self::get_job($jobid);
        self::log($jobid, 'worker_started', 'info', get_string('joblog_workerstarted', 'local_coursepublisher'), [
            'attempt' => (int)$job->attempts,
            'targetcourseid' => (int)$job->targetcourseid,
            'mode' => (string)$job->mode,
        ]);

        try {
            $validated = self::revalidate($job);
            $job = self::get_job($jobid);
        } catch (\moodle_exception $e) {
            self::transition($jobid, job_status::PREFLIGHT_FAILED, $e->getMessage(), [
                'stage' => 'worker_revalidation',
            ]);
            self::log($jobid, 'preflight_failed', 'error', $e->getMessage(), ['stage' => 'worker_revalidation']);
            return;
        }

        try {
            $manifest = self::build_manifest((string)$job->sourcetype, (int)$job->sourceid, (int)$job->sourcecourseid);
            if ((string)$job->mode === self::MODE_FOUNDATION ||
                    !$DB->record_exists('local_cp_job_item', ['jobid' => $jobid])) {
                self::replace_item_scaffold($jobid, $manifest);
            } else {
                self::assert_existing_items_match_manifest($jobid, $manifest);
            }
            self::log($jobid, 'manifest_created', 'info', get_string('joblog_manifest', 'local_coursepublisher', count($manifest)), [
                'itemcount' => count($manifest),
                'sourcefingerprint' => $validated['sourcefingerprint'],
            ]);

            if ((string)$job->mode === self::MODE_FOUNDATION) {
                self::finish_foundation($jobid, count($manifest));
                return;
            }

            if ((string)$job->mode === self::MODE_PAGE_PILOT) {
                self::run_page_copy($job, $manifest, null);
                return;
            }

            if ((string)$job->mode === self::MODE_PAGE_PLACEMENT) {
                self::run_page_copy($job, $manifest, self::placement_from_job($job));
                return;
            }

            if ((string)$job->mode === self::MODE_QUIZ_PLACEMENT) {
                self::run_quiz_copy($job, $manifest, self::placement_from_job($job));
                return;
            }

            if ((string)$job->mode === self::MODE_SECTION_PAGE_PLACEMENT) {
                self::run_section_page_copy($job, $manifest, self::section_placement_from_job($job));
                return;
            }

            if ((string)$job->mode === self::MODE_SECTION_MIXED_PLACEMENT) {
                self::run_section_mixed_copy($job, $manifest, self::section_placement_from_job($job));
                return;
            }

            if ((string)$job->mode === self::MODE_SUBSECTION_TREE_PLACEMENT) {
                self::run_delegated_subsection_copy($job, $manifest, self::placement_from_job($job));
                return;
            }

            if ((string)$job->mode === self::MODE_ACTIVITY_GENERIC_PLACEMENT) {
                self::run_generic_activity_copy($job, $manifest, self::placement_from_job($job));
                return;
            }

            if ((string)$job->mode === self::MODE_SECTION_GENERIC_PEER) {
                self::run_generic_peer_section_copy($job, $manifest, self::section_placement_from_job($job));
                return;
            }

            throw new \moodle_exception('invalidparameter');
        } catch (\Throwable $e) {
            self::transition($jobid, job_status::FAILED, $e->getMessage(), [
                'stage' => (string)$job->mode . '_worker',
                'attempt' => (int)$job->attempts,
                'manualreview' => (string)$job->mode !== self::MODE_FOUNDATION,
            ]);
            self::log($jobid, 'job_failed', 'error', $e->getMessage(), [
                'attempt' => (int)$job->attempts,
                'mode' => (string)$job->mode,
                'manualreview' => (string)$job->mode !== self::MODE_FOUNDATION,
            ]);

            // Foundation remains retryable through Moodle task failure handling.
            // Once a real mutation attempt has started, do not automatically
            // retry because the external outcome may be uncertain.
            if ((string)$job->mode === self::MODE_FOUNDATION) {
                throw $e;
            }
        }
    }

    /** Complete a non-mutating foundation job. */
    private static function finish_foundation(int $jobid, int $itemcount): void {
        global $DB;

        $DB->set_field_select('local_cp_job_item', 'status', job_status::ITEM_SKIPPED, 'jobid = ?', [$jobid]);
        $DB->set_field_select(
            'local_cp_job_item',
            'errormessage',
            get_string('jobfoundationitemskip', 'local_coursepublisher'),
            'jobid = ?',
            [$jobid]
        );
        $DB->set_field_select('local_cp_job_item', 'timemodified', time(), 'jobid = ?', [$jobid]);

        self::transition($jobid, job_status::SUCCEEDED, '', [
            'mode' => self::MODE_FOUNDATION,
            'contentmutation' => false,
            'itemcount' => $itemcount,
        ]);
        self::log($jobid, 'job_completed', 'success', get_string('joblog_foundationcomplete', 'local_coursepublisher'), [
            'contentmutation' => false,
            'itemcount' => $itemcount,
        ]);
    }

    /**
     * First real mutation pilot: copy one mod_page activity to the target course.
     */
    private static function run_page_copy(\stdClass $job, array $manifest, ?array $placement): void {
        global $DB;

        if ((string)$job->sourcetype !== 'activity' || count($manifest) !== 1 ||
                (string)$manifest[0]['sourcemodule'] !== 'page') {
            throw new \moodle_exception('pilotpageonly', 'local_coursepublisher');
        }

        $item = $DB->get_record('local_cp_job_item', ['jobid' => $job->id, 'sourcecmid' => $job->sourceid], '*', MUST_EXIST);

        // If the copy result was durably recorded but the job-level transition
        // was interrupted, finish idempotently without creating another copy.
        if ((string)$item->status === job_status::ITEM_SUCCEEDED && (int)$item->targetcmid > 0) {
            self::transition((int)$job->id, job_status::SUCCEEDED, '', [
                'mode' => (string)$job->mode,
                'contentmutation' => true,
                'targetcmid' => (int)$item->targetcmid,
                'recovered_from_item_state' => true,
            ]);
            self::log((int)$job->id, 'job_completed', 'success', get_string('joblog_pagepilotcomplete', 'local_coursepublisher', $item->targetcmid), [
                'contentmutation' => true,
                'targetcmid' => (int)$item->targetcmid,
                'recovered_from_item_state' => true,
            ]);
            return;
        }

        // A prior RUNNING item without a recorded target is an uncertain
        // outcome. Fail closed instead of risking a duplicate copy.
        if ((string)$item->status === job_status::ITEM_RUNNING && (int)$item->attempts > 0 && (int)$item->targetcmid === 0) {
            throw new \moodle_exception('pilotuncertainoutcome', 'local_coursepublisher');
        }

        $now = time();
        $item->status = job_status::ITEM_RUNNING;
        $item->attempts = (int)$item->attempts + 1;
        $item->timestarted = $item->timestarted ?: $now;
        $item->timemodified = $now;
        $item->errorcode = null;
        $item->errormessage = null;
        $DB->update_record('local_cp_job_item', $item);
        self::log((int)$job->id, 'item_started', 'info', get_string('joblog_itemstarted', 'local_coursepublisher', $item->sourcecmid), [
            'sourcecmid' => (int)$item->sourcecmid,
            'module' => 'page',
            'attempt' => (int)$item->attempts,
        ]);

        try {
            self::begin_mutation($job);
            $copy = content_publisher::copy_page_activity(
                (int)$item->sourcecmid,
                (int)$job->targetcourseid,
                (int)$job->userid,
                $placement
            );

            $item = $DB->get_record('local_cp_job_item', ['id' => $item->id], '*', MUST_EXIST);
            $item->targetcmid = (int)$copy['targetcmid'];
            $item->status = job_status::ITEM_SUCCEEDED;
            $item->errorcode = null;
            $item->errormessage = get_string('jobpagepilotitemcopied', 'local_coursepublisher', $item->targetcmid);
            $item->timemodified = time();
            $item->timefinished = time();
            $DB->update_record('local_cp_job_item', $item);

            self::log((int)$job->id, 'item_succeeded', 'success', get_string('joblog_itemsucceeded', 'local_coursepublisher', (object)[
                'sourcecmid' => $item->sourcecmid,
                'targetcmid' => $item->targetcmid,
            ]), [
                'sourcecmid' => (int)$item->sourcecmid,
                'targetcmid' => (int)$item->targetcmid,
                'warnings' => $copy['warnings'],
            ]);

            if ($placement !== null) {
                self::log((int)$job->id, 'item_placed', 'success', get_string('joblog_itemplaced', 'local_coursepublisher', (object)[
                    'targetcmid' => $item->targetcmid,
                    'targetsectionid' => $copy['targetsectionid'],
                ]), [
                    'targetcmid' => (int)$item->targetcmid,
                    'targetsectionid' => (int)$copy['targetsectionid'],
                    'position' => (string)$copy['position'],
                    'beforecmid' => (int)$copy['beforecmid'],
                ]);
            }

            self::transition((int)$job->id, job_status::SUCCEEDED, '', [
                'mode' => (string)$job->mode,
                'contentmutation' => true,
                'targetcmid' => (int)$item->targetcmid,
            ]);
            self::log((int)$job->id, 'job_completed', 'success', get_string('joblog_pagepilotcomplete', 'local_coursepublisher', $item->targetcmid), [
                'contentmutation' => true,
                'sourcecmid' => (int)$item->sourcecmid,
                'targetcmid' => (int)$item->targetcmid,
            ]);
        } catch (\Throwable $e) {
            $item = $DB->get_record('local_cp_job_item', ['id' => $item->id], '*', MUST_EXIST);
            $item->status = job_status::ITEM_FAILED;
            $item->errorcode = substr(get_class($e), 0, 64);
            $item->errormessage = $e->getMessage();
            $item->timemodified = time();
            $item->timefinished = time();
            $DB->update_record('local_cp_job_item', $item);
            self::log((int)$job->id, 'item_failed', 'error', $e->getMessage(), [
                'sourcecmid' => (int)$item->sourcecmid,
                'manualreview' => true,
            ]);
            throw $e;
        }
    }


    /**
     * Narrow real-mutation pilot for one Quiz activity with exact placement.
     *
     * This is intended as a safety checkpoint before copying a mixed section.
     */
    private static function run_quiz_copy(\stdClass $job, array $manifest, array $placement): void {
        global $DB;

        if ((string)$job->sourcetype !== 'activity' || count($manifest) !== 1 ||
                (string)$manifest[0]['sourcemodule'] !== 'quiz') {
            throw new \moodle_exception('pilotquizonly', 'local_coursepublisher');
        }

        $item = $DB->get_record(
            'local_cp_job_item',
            ['jobid' => $job->id, 'sourcecmid' => $job->sourceid],
            '*',
            MUST_EXIST
        );

        if ((string)$item->status === job_status::ITEM_SUCCEEDED && (int)$item->targetcmid > 0) {
            self::transition((int)$job->id, job_status::SUCCEEDED, '', [
                'mode' => (string)$job->mode,
                'contentmutation' => true,
                'targetcmid' => (int)$item->targetcmid,
                'recovered_from_item_state' => true,
            ]);
            self::log((int)$job->id, 'job_completed', 'success',
                get_string('joblog_quizpilotcomplete', 'local_coursepublisher', $item->targetcmid),
                [
                    'contentmutation' => true,
                    'targetcmid' => (int)$item->targetcmid,
                    'recovered_from_item_state' => true,
                ]
            );
            return;
        }

        if ((string)$item->status === job_status::ITEM_RUNNING &&
                (int)$item->attempts > 0 && (int)$item->targetcmid === 0) {
            throw new \moodle_exception('pilotuncertainoutcome', 'local_coursepublisher');
        }

        $now = time();
        $item->status = job_status::ITEM_RUNNING;
        $item->attempts = (int)$item->attempts + 1;
        $item->timestarted = $item->timestarted ?: $now;
        $item->timemodified = $now;
        $item->errorcode = null;
        $item->errormessage = null;
        $DB->update_record('local_cp_job_item', $item);
        self::log((int)$job->id, 'item_started', 'info',
            get_string('joblog_quizitemstarted', 'local_coursepublisher', $item->sourcecmid),
            [
                'sourcecmid' => (int)$item->sourcecmid,
                'module' => 'quiz',
                'attempt' => (int)$item->attempts,
            ]
        );

        try {
            self::begin_mutation($job);
            $copy = content_publisher::copy_quiz_activity(
                (int)$item->sourcecmid,
                (int)$job->targetcourseid,
                (int)$job->userid,
                $placement
            );

            $item = $DB->get_record('local_cp_job_item', ['id' => $item->id], '*', MUST_EXIST);
            $item->targetcmid = (int)$copy['targetcmid'];
            $item->status = job_status::ITEM_SUCCEEDED;
            $item->errorcode = null;
            $item->errormessage = get_string(
                'jobquizpilotitemcopied',
                'local_coursepublisher',
                (object)[
                    'targetcmid' => (int)$item->targetcmid,
                    'slots' => (int)$copy['targetquizslots'],
                ]
            );
            $item->timemodified = time();
            $item->timefinished = time();
            $DB->update_record('local_cp_job_item', $item);

            self::log((int)$job->id, 'item_succeeded', 'success',
                get_string('joblog_itemsucceeded', 'local_coursepublisher', (object)[
                    'sourcecmid' => $item->sourcecmid,
                    'targetcmid' => $item->targetcmid,
                ]),
                [
                    'sourcecmid' => (int)$item->sourcecmid,
                    'targetcmid' => (int)$item->targetcmid,
                    'sourcequizslots' => (int)$copy['sourcequizslots'],
                    'targetquizslots' => (int)$copy['targetquizslots'],
                    'warnings' => $copy['warnings'],
                ]
            );
            self::log((int)$job->id, 'item_placed', 'success',
                get_string('joblog_itemplaced', 'local_coursepublisher', (object)[
                    'targetcmid' => $item->targetcmid,
                    'targetsectionid' => $copy['targetsectionid'],
                ]),
                [
                    'targetcmid' => (int)$item->targetcmid,
                    'targetsectionid' => (int)$copy['targetsectionid'],
                    'position' => (string)$copy['position'],
                    'beforecmid' => (int)$copy['beforecmid'],
                ]
            );

            self::transition((int)$job->id, job_status::SUCCEEDED, '', [
                'mode' => (string)$job->mode,
                'contentmutation' => true,
                'targetcmid' => (int)$item->targetcmid,
            ]);
            self::log((int)$job->id, 'job_completed', 'success',
                get_string('joblog_quizpilotcomplete', 'local_coursepublisher', $item->targetcmid),
                [
                    'contentmutation' => true,
                    'sourcecmid' => (int)$item->sourcecmid,
                    'targetcmid' => (int)$item->targetcmid,
                    'quizslots' => (int)$copy['targetquizslots'],
                ]
            );
        } catch (\Throwable $e) {
            $item = $DB->get_record('local_cp_job_item', ['id' => $item->id], '*', MUST_EXIST);
            $item->status = job_status::ITEM_FAILED;
            $item->errorcode = substr(get_class($e), 0, 64);
            $item->errormessage = $e->getMessage();
            $item->timemodified = time();
            $item->timefinished = time();
            $DB->update_record('local_cp_job_item', $item);
            self::log((int)$job->id, 'item_failed', 'error', $e->getMessage(), [
                'sourcecmid' => (int)$item->sourcecmid,
                'manualreview' => true,
            ]);
            throw $e;
        }
    }


    /**
     * Generic real-mutation path for one backup-capable Moodle module.
     */
    private static function run_generic_activity_copy(
        \stdClass $job,
        array $manifest,
        array $placement
    ): void {
        global $DB;

        if ((string)$job->sourcetype !== 'activity' || count($manifest) !== 1) {
            throw new \moodle_exception('invalidparameter');
        }
        $modname = (string)$manifest[0]['sourcemodule'];
        content_publisher::validate_backup_capable_activity(
            (int)$manifest[0]['sourcecmid'],
            (int)$job->sourcecourseid
        );
        if ($modname === 'subsection') {
            throw new \moodle_exception('genericactivitysubsectionuse_section', 'local_coursepublisher');
        }

        $item = $DB->get_record(
            'local_cp_job_item',
            ['jobid' => $job->id, 'sourcecmid' => $job->sourceid],
            '*',
            MUST_EXIST
        );

        if ((string)$item->status === job_status::ITEM_SUCCEEDED && (int)$item->targetcmid > 0) {
            self::transition((int)$job->id, job_status::SUCCEEDED, '', [
                'mode' => self::MODE_ACTIVITY_GENERIC_PLACEMENT,
                'contentmutation' => true,
                'targetcmid' => (int)$item->targetcmid,
                'recovered_from_item_state' => true,
            ]);
            return;
        }
        if ((string)$item->status === job_status::ITEM_RUNNING &&
                (int)$item->attempts > 0 && (int)$item->targetcmid === 0) {
            throw new \moodle_exception('pilotuncertainoutcome', 'local_coursepublisher');
        }

        $now = time();
        $item->status = job_status::ITEM_RUNNING;
        $item->attempts = (int)$item->attempts + 1;
        $item->timestarted = $item->timestarted ?: $now;
        $item->timemodified = $now;
        $item->errorcode = null;
        $item->errormessage = null;
        $DB->update_record('local_cp_job_item', $item);
        self::log((int)$job->id, 'item_started', 'info',
            get_string('joblog_genericitemstarted', 'local_coursepublisher', (object)[
                'sourcecmid' => (int)$item->sourcecmid,
                'modname' => $modname,
            ]),
            ['sourcecmid' => (int)$item->sourcecmid, 'sourcemodule' => $modname]
        );

        try {
            self::begin_mutation($job);
            $copy = content_publisher::copy_generic_activity(
                (int)$item->sourcecmid,
                (int)$job->targetcourseid,
                (int)$job->userid,
                $placement
            );

            $item = $DB->get_record('local_cp_job_item', ['id' => $item->id], '*', MUST_EXIST);
            $item->targetcmid = (int)$copy['targetcmid'];
            $item->status = job_status::ITEM_SUCCEEDED;
            $item->errorcode = null;
            $item->errormessage = get_string('jobgenericitemcopied', 'local_coursepublisher', (object)[
                'modname' => $modname,
                'targetcmid' => (int)$copy['targetcmid'],
            ]);
            $item->timemodified = time();
            $item->timefinished = time();
            $DB->update_record('local_cp_job_item', $item);

            self::log((int)$job->id, 'item_succeeded', 'success',
                get_string('joblog_itemsucceeded', 'local_coursepublisher', (object)[
                    'sourcecmid' => (int)$item->sourcecmid,
                    'targetcmid' => (int)$item->targetcmid,
                ]),
                [
                    'sourcecmid' => (int)$item->sourcecmid,
                    'sourcemodule' => $modname,
                    'targetcmid' => (int)$item->targetcmid,
                    'warnings' => $copy['warnings'],
                ]
            );
            self::log((int)$job->id, 'item_placed', 'success',
                get_string('joblog_itemplaced', 'local_coursepublisher', (object)[
                    'targetcmid' => (int)$item->targetcmid,
                    'targetsectionid' => (int)$copy['targetsectionid'],
                ]),
                [
                    'targetcmid' => (int)$item->targetcmid,
                    'targetsectionid' => (int)$copy['targetsectionid'],
                    'position' => (string)$copy['position'],
                    'beforecmid' => (int)$copy['beforecmid'],
                ]
            );

            self::transition((int)$job->id, job_status::SUCCEEDED, '', [
                'mode' => self::MODE_ACTIVITY_GENERIC_PLACEMENT,
                'contentmutation' => true,
                'modname' => $modname,
                'targetcmid' => (int)$item->targetcmid,
            ]);
            self::log((int)$job->id, 'job_completed', 'success',
                get_string('joblog_genericcomplete', 'local_coursepublisher', (object)[
                    'modname' => $modname,
                    'targetcmid' => (int)$item->targetcmid,
                ]),
                [
                    'contentmutation' => true,
                    'sourcemodule' => $modname,
                    'sourcecmid' => (int)$item->sourcecmid,
                    'targetcmid' => (int)$item->targetcmid,
                ]
            );
        } catch (\Throwable $e) {
            $item = $DB->get_record('local_cp_job_item', ['id' => $item->id], '*', MUST_EXIST);
            $item->status = job_status::ITEM_FAILED;
            $item->errorcode = substr(get_class($e), 0, 64);
            $item->errormessage = $e->getMessage();
            $item->timemodified = time();
            $item->timefinished = time();
            $DB->update_record('local_cp_job_item', $item);
            self::log((int)$job->id, 'item_failed', 'error', $e->getMessage(), [
                'sourcecmid' => (int)$item->sourcecmid,
                'sourcemodule' => $modname,
                'manualreview' => true,
            ]);
            throw $e;
        }
    }

    /**
     * 0.2.3 real-mutation pilot: create one new section at an exact section
     * position, then copy Page activities into it in source order.
     *
     * Any non-Page module is blocked before section creation.
     */
    private static function run_section_page_copy(\stdClass $job, array $manifest, array $placement): void {
        global $DB;

        if ((string)$job->sourcetype !== 'section') {
            throw new \moodle_exception('sectionpilottypeonly', 'local_coursepublisher');
        }

        content_publisher::validate_source_section_for_page_pilot(
            (int)$job->sourceid,
            (int)$job->sourcecourseid
        );

        foreach ($manifest as $row) {
            if ((string)$row['sourcemodule'] !== 'page') {
                throw new \moodle_exception(
                    'sectionpilotunsupportedmodules',
                    'local_coursepublisher',
                    '',
                    (string)$row['sourcemodule']
                );
            }
        }

        // A hard worker crash before targetsectionid was durably recorded has
        // an uncertain outcome. Never create another section on a later attempt.
        $targetsectionid = (int)($job->targetsectionid ?? 0);
        if ($targetsectionid <= 0 && (int)$job->attempts > 1) {
            throw new \moodle_exception('sectionpilotuncertainsection', 'local_coursepublisher');
        }

        if ($targetsectionid > 0) {
            content_publisher::validate_created_target_section(
                (int)$job->targetcourseid,
                $targetsectionid
            );
        } else {
            self::begin_mutation($job);
            $created = content_publisher::create_target_section_from_source(
                (int)$job->sourceid,
                (int)$job->sourcecourseid,
                (int)$job->targetcourseid,
                $placement
            );
            $targetsectionid = (int)$created['targetsectionid'];

            // Persist the created section identity immediately, before any
            // activity copy begins.
            $DB->set_field('local_cp_job', 'targetsectionid', $targetsectionid, ['id' => $job->id]);
            self::log((int)$job->id, 'section_created', 'success',
                get_string('joblog_sectioncreated', 'local_coursepublisher', (object)[
                    'targetsectionid' => $targetsectionid,
                    'targetsectionnum' => (int)$created['targetsectionnum'],
                ]),
                $created
            );
        }

        $items = array_values($DB->get_records(
            'local_cp_job_item',
            ['jobid' => $job->id],
            'sortorder ASC, id ASC'
        ));
        if (count($items) !== count($manifest)) {
            throw new \moodle_exception('jobmanifestchanged', 'local_coursepublisher');
        }

        $targetcmids = [];
        foreach ($items as $index => $item) {
            if ((string)$item->sourcemodule !== 'page') {
                throw new \moodle_exception(
                    'sectionpilotunsupportedmodules',
                    'local_coursepublisher',
                    '',
                    (string)$item->sourcemodule
                );
            }

            // Durable recovery: a completed item is not copied twice.
            if ((string)$item->status === job_status::ITEM_SUCCEEDED && (int)$item->targetcmid > 0) {
                $existingcm = $DB->get_record(
                    'course_modules',
                    [
                        'id' => (int)$item->targetcmid,
                        'course' => (int)$job->targetcourseid,
                        'section' => $targetsectionid,
                    ],
                    'id,course,section,deletioninprogress',
                    IGNORE_MISSING
                );
                if (!$existingcm || !empty($existingcm->deletioninprogress)) {
                    throw new \moodle_exception('sectionpilotitempostcondition', 'local_coursepublisher');
                }
                $targetcmids[] = (int)$item->targetcmid;
                continue;
            }

            // A RUNNING item with no target identity is an uncertain real-copy
            // outcome and must not be retried automatically.
            if ((string)$item->status === job_status::ITEM_RUNNING &&
                    (int)$item->attempts > 0 && (int)$item->targetcmid === 0) {
                throw new \moodle_exception('pilotuncertainoutcome', 'local_coursepublisher');
            }

            $now = time();
            $item->status = job_status::ITEM_RUNNING;
            $item->attempts = (int)$item->attempts + 1;
            $item->timestarted = $item->timestarted ?: $now;
            $item->timemodified = $now;
            $item->errorcode = null;
            $item->errormessage = null;
            $DB->update_record('local_cp_job_item', $item);

            self::log((int)$job->id, 'item_started', 'info',
                get_string('joblog_sectionitemstarted', 'local_coursepublisher', (object)[
                    'sourcecmid' => (int)$item->sourcecmid,
                    'index' => $index + 1,
                    'total' => count($items),
                ]),
                [
                    'sourcecmid' => (int)$item->sourcecmid,
                    'targetsectionid' => $targetsectionid,
                    'sortorder' => (int)$item->sortorder,
                    'attempt' => (int)$item->attempts,
                ]
            );

            try {
                // Appending each Page to the newly-created section preserves
                // source sequence order deterministically.
                self::begin_mutation($job);
            $copy = content_publisher::copy_page_activity(
                    (int)$item->sourcecmid,
                    (int)$job->targetcourseid,
                    (int)$job->userid,
                    [
                        'targetsectionid' => $targetsectionid,
                        'position' => 'end',
                        'beforecmid' => 0,
                    ]
                );

                $item = $DB->get_record('local_cp_job_item', ['id' => $item->id], '*', MUST_EXIST);
                $item->targetcmid = (int)$copy['targetcmid'];
                $item->status = job_status::ITEM_SUCCEEDED;
                $item->errorcode = null;
                $item->errormessage = get_string(
                    'jobsectionpageitemcopied',
                    'local_coursepublisher',
                    $item->targetcmid
                );
                $item->timemodified = time();
                $item->timefinished = time();
                $DB->update_record('local_cp_job_item', $item);
                $targetcmids[] = (int)$item->targetcmid;

                self::log((int)$job->id, 'item_succeeded', 'success',
                    get_string('joblog_itemsucceeded', 'local_coursepublisher', (object)[
                        'sourcecmid' => $item->sourcecmid,
                        'targetcmid' => $item->targetcmid,
                    ]),
                    [
                        'sourcecmid' => (int)$item->sourcecmid,
                        'targetcmid' => (int)$item->targetcmid,
                        'targetsectionid' => $targetsectionid,
                        'sortorder' => (int)$item->sortorder,
                        'warnings' => $copy['warnings'],
                    ]
                );
            } catch (\Throwable $e) {
                $item = $DB->get_record('local_cp_job_item', ['id' => $item->id], '*', MUST_EXIST);
                $item->status = job_status::ITEM_FAILED;
                $item->errorcode = substr(get_class($e), 0, 64);
                $item->errormessage = $e->getMessage();
                $item->timemodified = time();
                $item->timefinished = time();
                $DB->update_record('local_cp_job_item', $item);
                self::log((int)$job->id, 'item_failed', 'error', $e->getMessage(), [
                    'sourcecmid' => (int)$item->sourcecmid,
                    'targetsectionid' => $targetsectionid,
                    'manualreview' => true,
                ]);
                throw $e;
            }
        }

        // Strong postcondition: because the target section was created empty,
        // its full sequence must exactly match the copied target CMIDs.
        $targetsection = content_publisher::validate_created_target_section(
            (int)$job->targetcourseid,
            $targetsectionid
        );
        $sequence = array_values(array_filter(array_map(
            'intval',
            explode(',', trim((string)$targetsection->sequence))
        )));
        if ($sequence !== $targetcmids) {
            throw new \moodle_exception('sectionpilotorderpostcondition', 'local_coursepublisher');
        }

        self::transition((int)$job->id, job_status::SUCCEEDED, '', [
            'mode' => self::MODE_SECTION_PAGE_PLACEMENT,
            'contentmutation' => true,
            'targetsectionid' => $targetsectionid,
            'itemcount' => count($items),
            'targetcmids' => $targetcmids,
        ]);
        self::log((int)$job->id, 'job_completed', 'success',
            get_string('joblog_sectionpilotcomplete', 'local_coursepublisher', (object)[
                'targetsectionid' => $targetsectionid,
                'itemcount' => count($items),
            ]),
            [
                'contentmutation' => true,
                'targetsectionid' => $targetsectionid,
                'itemcount' => count($items),
                'targetcmids' => $targetcmids,
            ]
        );
    }


    /**
     * 0.2.4 real-mutation pilot: copy a standard section containing only
     * Moodle Page and Quiz activities into a new target section at the exact
     * selected section position, preserving source activity order.
     *
     * Quiz copy is delegated to Moodle Core backup/restore and validates the
     * restored quiz slot count before the job can succeed.
     */
    private static function run_section_mixed_copy(\stdClass $job, array $manifest, array $placement): void {
        global $DB;

        if ((string)$job->sourcetype !== 'section') {
            throw new \moodle_exception('sectionpilottypeonly', 'local_coursepublisher');
        }

        content_publisher::validate_source_section_for_page_pilot(
            (int)$job->sourceid,
            (int)$job->sourcecourseid
        );

        foreach ($manifest as $row) {
            if (!in_array((string)$row['sourcemodule'], ['page', 'quiz'], true)) {
                throw new \moodle_exception(
                    'sectionmixedunsupportedmodules',
                    'local_coursepublisher',
                    '',
                    (string)$row['sourcemodule']
                );
            }
        }

        $targetsectionid = (int)($job->targetsectionid ?? 0);
        if ($targetsectionid <= 0 && (int)$job->attempts > 1) {
            throw new \moodle_exception('sectionpilotuncertainsection', 'local_coursepublisher');
        }

        if ($targetsectionid > 0) {
            content_publisher::validate_created_target_section(
                (int)$job->targetcourseid,
                $targetsectionid
            );
        } else {
            self::begin_mutation($job);
            $created = content_publisher::create_target_section_from_source(
                (int)$job->sourceid,
                (int)$job->sourcecourseid,
                (int)$job->targetcourseid,
                $placement
            );
            $targetsectionid = (int)$created['targetsectionid'];

            $DB->set_field('local_cp_job', 'targetsectionid', $targetsectionid, ['id' => $job->id]);
            self::log((int)$job->id, 'section_created', 'success',
                get_string('joblog_sectioncreated', 'local_coursepublisher', (object)[
                    'targetsectionid' => $targetsectionid,
                    'targetsectionnum' => (int)$created['targetsectionnum'],
                ]),
                $created
            );
        }

        $items = array_values($DB->get_records(
            'local_cp_job_item',
            ['jobid' => $job->id],
            'sortorder ASC, id ASC'
        ));
        if (count($items) !== count($manifest)) {
            throw new \moodle_exception('jobmanifestchanged', 'local_coursepublisher');
        }

        $targetcmids = [];
        foreach ($items as $index => $item) {
            $modname = (string)$item->sourcemodule;
            if (!in_array($modname, ['page', 'quiz'], true)) {
                throw new \moodle_exception(
                    'sectionmixedunsupportedmodules',
                    'local_coursepublisher',
                    '',
                    $modname
                );
            }

            if ((string)$item->status === job_status::ITEM_SUCCEEDED && (int)$item->targetcmid > 0) {
                $existingcm = $DB->get_record(
                    'course_modules',
                    [
                        'id' => (int)$item->targetcmid,
                        'course' => (int)$job->targetcourseid,
                        'section' => $targetsectionid,
                    ],
                    'id,course,section,deletioninprogress',
                    IGNORE_MISSING
                );
                if (!$existingcm || !empty($existingcm->deletioninprogress)) {
                    throw new \moodle_exception('sectionpilotitempostcondition', 'local_coursepublisher');
                }
                $targetcmids[] = (int)$item->targetcmid;
                continue;
            }

            if ((string)$item->status === job_status::ITEM_RUNNING &&
                    (int)$item->attempts > 0 && (int)$item->targetcmid === 0) {
                throw new \moodle_exception('pilotuncertainoutcome', 'local_coursepublisher');
            }

            $now = time();
            $item->status = job_status::ITEM_RUNNING;
            $item->attempts = (int)$item->attempts + 1;
            $item->timestarted = $item->timestarted ?: $now;
            $item->timemodified = $now;
            $item->errorcode = null;
            $item->errormessage = null;
            $DB->update_record('local_cp_job_item', $item);

            self::log((int)$job->id, 'item_started', 'info',
                get_string('joblog_sectionmixeditemstarted', 'local_coursepublisher', (object)[
                    'sourcecmid' => (int)$item->sourcecmid,
                    'modname' => $modname,
                    'index' => $index + 1,
                    'total' => count($items),
                ]),
                [
                    'sourcecmid' => (int)$item->sourcecmid,
                    'sourcemodule' => $modname,
                    'targetsectionid' => $targetsectionid,
                    'sortorder' => (int)$item->sortorder,
                    'attempt' => (int)$item->attempts,
                ]
            );

            try {
                $exactplacement = [
                    'targetsectionid' => $targetsectionid,
                    'position' => 'end',
                    'beforecmid' => 0,
                ];
                if ($modname === 'page') {
                    self::begin_mutation($job);
            $copy = content_publisher::copy_page_activity(
                        (int)$item->sourcecmid,
                        (int)$job->targetcourseid,
                        (int)$job->userid,
                        $exactplacement
                    );
                } else {
                    self::begin_mutation($job);
            $copy = content_publisher::copy_quiz_activity(
                        (int)$item->sourcecmid,
                        (int)$job->targetcourseid,
                        (int)$job->userid,
                        $exactplacement
                    );
                }

                $item = $DB->get_record('local_cp_job_item', ['id' => $item->id], '*', MUST_EXIST);
                $item->targetcmid = (int)$copy['targetcmid'];
                $item->status = job_status::ITEM_SUCCEEDED;
                $item->errorcode = null;
                $item->errormessage = get_string(
                    'jobsectionmixeditemcopied',
                    'local_coursepublisher',
                    (object)[
                        'modname' => $modname,
                        'targetcmid' => (int)$item->targetcmid,
                    ]
                );
                $item->timemodified = time();
                $item->timefinished = time();
                $DB->update_record('local_cp_job_item', $item);
                $targetcmids[] = (int)$item->targetcmid;

                $details = [
                    'sourcecmid' => (int)$item->sourcecmid,
                    'sourcemodule' => $modname,
                    'targetcmid' => (int)$item->targetcmid,
                    'targetsectionid' => $targetsectionid,
                    'sortorder' => (int)$item->sortorder,
                    'warnings' => $copy['warnings'],
                ];
                if ($modname === 'quiz') {
                    $details['sourcequizslots'] = (int)$copy['sourcequizslots'];
                    $details['targetquizslots'] = (int)$copy['targetquizslots'];
                }
                self::log((int)$job->id, 'item_succeeded', 'success',
                    get_string('joblog_itemsucceeded', 'local_coursepublisher', (object)[
                        'sourcecmid' => $item->sourcecmid,
                        'targetcmid' => $item->targetcmid,
                    ]),
                    $details
                );
            } catch (\Throwable $e) {
                $item = $DB->get_record('local_cp_job_item', ['id' => $item->id], '*', MUST_EXIST);
                $item->status = job_status::ITEM_FAILED;
                $item->errorcode = substr(get_class($e), 0, 64);
                $item->errormessage = $e->getMessage();
                $item->timemodified = time();
                $item->timefinished = time();
                $DB->update_record('local_cp_job_item', $item);
                self::log((int)$job->id, 'item_failed', 'error', $e->getMessage(), [
                    'sourcecmid' => (int)$item->sourcecmid,
                    'sourcemodule' => $modname,
                    'targetsectionid' => $targetsectionid,
                    'manualreview' => true,
                ]);
                throw $e;
            }
        }

        $targetsection = content_publisher::validate_created_target_section(
            (int)$job->targetcourseid,
            $targetsectionid
        );
        $sequence = array_values(array_filter(array_map(
            'intval',
            explode(',', trim((string)$targetsection->sequence))
        )));
        if ($sequence !== $targetcmids) {
            throw new \moodle_exception('sectionpilotorderpostcondition', 'local_coursepublisher');
        }

        self::transition((int)$job->id, job_status::SUCCEEDED, '', [
            'mode' => self::MODE_SECTION_MIXED_PLACEMENT,
            'contentmutation' => true,
            'targetsectionid' => $targetsectionid,
            'itemcount' => count($items),
            'targetcmids' => $targetcmids,
        ]);
        self::log((int)$job->id, 'job_completed', 'success',
            get_string('joblog_sectionmixedcomplete', 'local_coursepublisher', (object)[
                'targetsectionid' => $targetsectionid,
                'itemcount' => count($items),
            ]),
            [
                'contentmutation' => true,
                'targetsectionid' => $targetsectionid,
                'itemcount' => count($items),
                'targetcmids' => $targetcmids,
            ]
        );
    }

    /**
     * 0.2.6 generic peer-section path.
     *
     * Creates a normal same-level target section before/after an existing
     * normal section (or at course start/end) and restores every immediate
     * source activity/resource that declares FEATURE_BACKUP_MOODLE2.
     * A nested mod_subsection is restored as its own delegated subtree.
     */
    private static function run_generic_peer_section_copy(
        \stdClass $job,
        array $manifest,
        array $placement
    ): void {
        global $DB;

        if ((string)$job->sourcetype !== 'section') {
            throw new \moodle_exception('sectionpilottypeonly', 'local_coursepublisher');
        }
        content_publisher::validate_source_section_for_generic_copy(
            (int)$job->sourceid,
            (int)$job->sourcecourseid
        );
        foreach ($manifest as $row) {
            content_publisher::validate_backup_capable_activity(
                (int)$row['sourcecmid'],
                (int)$job->sourcecourseid
            );
        }

        $targetsectionid = (int)($job->targetsectionid ?? 0);
        if ($targetsectionid <= 0 && (int)$job->attempts > 1) {
            throw new \moodle_exception('sectionpilotuncertainsection', 'local_coursepublisher');
        }

        if ($targetsectionid > 0) {
            content_publisher::validate_created_target_section(
                (int)$job->targetcourseid,
                $targetsectionid
            );
        } else {
            self::begin_mutation($job);
            $created = content_publisher::create_peer_target_section_from_source(
                (int)$job->sourceid,
                (int)$job->sourcecourseid,
                (int)$job->targetcourseid,
                $placement
            );
            $targetsectionid = (int)$created['targetsectionid'];
            $DB->set_field('local_cp_job', 'targetsectionid', $targetsectionid, ['id' => $job->id]);
            self::log((int)$job->id, 'section_created', 'success',
                get_string('joblog_peersectcreated', 'local_coursepublisher', (object)[
                    'targetsectionid' => $targetsectionid,
                    'targetsectionnum' => (int)$created['targetsectionnum'],
                ]),
                $created
            );
        }

        $items = array_values($DB->get_records(
            'local_cp_job_item',
            ['jobid' => $job->id],
            'sortorder ASC, id ASC'
        ));
        if (count($items) !== count($manifest)) {
            throw new \moodle_exception('jobmanifestchanged', 'local_coursepublisher');
        }

        $targetcmids = [];
        foreach ($items as $index => $item) {
            $modname = (string)$item->sourcemodule;
            content_publisher::validate_backup_capable_activity(
                (int)$item->sourcecmid,
                (int)$job->sourcecourseid
            );

            if ((string)$item->status === job_status::ITEM_SUCCEEDED && (int)$item->targetcmid > 0) {
                $existingcm = $DB->get_record(
                    'course_modules',
                    [
                        'id' => (int)$item->targetcmid,
                        'course' => (int)$job->targetcourseid,
                        'section' => $targetsectionid,
                    ],
                    'id,course,section,deletioninprogress',
                    IGNORE_MISSING
                );
                if (!$existingcm || !empty($existingcm->deletioninprogress)) {
                    throw new \moodle_exception('sectionpilotitempostcondition', 'local_coursepublisher');
                }
                $targetcmids[] = (int)$item->targetcmid;
                continue;
            }

            if ((string)$item->status === job_status::ITEM_RUNNING &&
                    (int)$item->attempts > 0 && (int)$item->targetcmid === 0) {
                throw new \moodle_exception('pilotuncertainoutcome', 'local_coursepublisher');
            }

            $now = time();
            $item->status = job_status::ITEM_RUNNING;
            $item->attempts = (int)$item->attempts + 1;
            $item->timestarted = $item->timestarted ?: $now;
            $item->timemodified = $now;
            $item->errorcode = null;
            $item->errormessage = null;
            $DB->update_record('local_cp_job_item', $item);

            self::log((int)$job->id, 'item_started', 'info',
                get_string('joblog_genericsectionitemstarted', 'local_coursepublisher', (object)[
                    'sourcecmid' => (int)$item->sourcecmid,
                    'modname' => $modname,
                    'index' => $index + 1,
                    'total' => count($items),
                ]),
                [
                    'sourcecmid' => (int)$item->sourcecmid,
                    'sourcemodule' => $modname,
                    'targetsectionid' => $targetsectionid,
                    'sortorder' => (int)$item->sortorder,
                    'attempt' => (int)$item->attempts,
                ]
            );

            try {
                $exactplacement = [
                    'targetsectionid' => $targetsectionid,
                    'position' => 'end',
                    'beforecmid' => 0,
                ];

                if ($modname === 'subsection') {
                    $delegated = content_publisher::delegated_section_from_subsection_cmid(
                        (int)$item->sourcecmid,
                        (int)$job->sourcecourseid
                    );
                    $nestedmanifest = self::build_manifest(
                        'section',
                        (int)$delegated->id,
                        (int)$job->sourcecourseid
                    );
                    foreach ($nestedmanifest as $nestedrow) {
                        content_publisher::validate_backup_capable_activity(
                            (int)$nestedrow['sourcecmid'],
                            (int)$job->sourcecourseid
                        );
                    }
                    self::begin_mutation($job);
            $copy = content_publisher::copy_delegated_subsection(
                        (int)$delegated->id,
                        (int)$job->sourcecourseid,
                        (int)$job->targetcourseid,
                        (int)$job->userid,
                        $nestedmanifest,
                        $exactplacement
                    );
                    $targetcmid = (int)$copy['targetdelegatecmid'];
                } else {
                    self::begin_mutation($job);
            $copy = content_publisher::copy_generic_activity(
                        (int)$item->sourcecmid,
                        (int)$job->targetcourseid,
                        (int)$job->userid,
                        $exactplacement
                    );
                    $targetcmid = (int)$copy['targetcmid'];
                }

                $item = $DB->get_record('local_cp_job_item', ['id' => $item->id], '*', MUST_EXIST);
                $item->targetcmid = $targetcmid;
                $item->status = job_status::ITEM_SUCCEEDED;
                $item->errorcode = null;
                $item->errormessage = get_string('jobgenericsectionitemcopied', 'local_coursepublisher', (object)[
                    'modname' => $modname,
                    'targetcmid' => $targetcmid,
                ]);
                $item->timemodified = time();
                $item->timefinished = time();
                $DB->update_record('local_cp_job_item', $item);
                $targetcmids[] = $targetcmid;

                self::log((int)$job->id, 'item_succeeded', 'success',
                    get_string('joblog_itemsucceeded', 'local_coursepublisher', (object)[
                        'sourcecmid' => (int)$item->sourcecmid,
                        'targetcmid' => $targetcmid,
                    ]),
                    [
                        'sourcecmid' => (int)$item->sourcecmid,
                        'sourcemodule' => $modname,
                        'targetcmid' => $targetcmid,
                        'targetsectionid' => $targetsectionid,
                        'sortorder' => (int)$item->sortorder,
                        'warnings' => $copy['warnings'] ?? [],
                    ]
                );
            } catch (\Throwable $e) {
                $item = $DB->get_record('local_cp_job_item', ['id' => $item->id], '*', MUST_EXIST);
                $item->status = job_status::ITEM_FAILED;
                $item->errorcode = substr(get_class($e), 0, 64);
                $item->errormessage = $e->getMessage();
                $item->timemodified = time();
                $item->timefinished = time();
                $DB->update_record('local_cp_job_item', $item);
                self::log((int)$job->id, 'item_failed', 'error', $e->getMessage(), [
                    'sourcecmid' => (int)$item->sourcecmid,
                    'sourcemodule' => $modname,
                    'targetsectionid' => $targetsectionid,
                    'manualreview' => true,
                ]);
                throw $e;
            }
        }

        $targetsection = content_publisher::validate_created_target_section(
            (int)$job->targetcourseid,
            $targetsectionid
        );
        $sequence = array_values(array_filter(array_map(
            'intval',
            explode(',', trim((string)$targetsection->sequence))
        )));
        if ($sequence !== $targetcmids) {
            throw new \moodle_exception('sectionpilotorderpostcondition', 'local_coursepublisher');
        }

        self::transition((int)$job->id, job_status::SUCCEEDED, '', [
            'mode' => self::MODE_SECTION_GENERIC_PEER,
            'contentmutation' => true,
            'targetsectionid' => $targetsectionid,
            'itemcount' => count($items),
            'targetcmids' => $targetcmids,
        ]);
        self::log((int)$job->id, 'job_completed', 'success',
            get_string('joblog_genericsectioncomplete', 'local_coursepublisher', (object)[
                'targetsectionid' => $targetsectionid,
                'itemcount' => count($items),
            ]),
            [
                'contentmutation' => true,
                'targetsectionid' => $targetsectionid,
                'itemcount' => count($items),
                'targetcmids' => $targetcmids,
            ]
        );
    }

    /**
     * 0.2.5 delegated-subsection pilot.
     *
     * The source is a Moodle mod_subsection delegated section. Instead of
     * flattening it into a normal course section, Core backs up/restores the
     * delegating subsection activity. Moodle's TYPE_1ACTIVITY plan then
     * includes the delegated section and its child Page/Quiz activities.
     */
    private static function run_delegated_subsection_copy(
        \stdClass $job,
        array $manifest,
        array $placement
    ): void {
        global $DB;

        if ((string)$job->sourcetype !== 'section') {
            throw new \moodle_exception('sectionpilottypeonly', 'local_coursepublisher');
        }
        content_publisher::validate_source_delegated_subsection(
            (int)$job->sourceid,
            (int)$job->sourcecourseid
        );
        foreach ($manifest as $row) {
            content_publisher::validate_backup_capable_activity(
                (int)$row['sourcecmid'],
                (int)$job->sourcecourseid
            );
        }

        // Real subtree restore is one Core mutation. If a previous worker
        // already entered mutation without a durable success record, never
        // repeat it automatically because that could duplicate the subtree.
        if ((int)$job->attempts > 1 && (int)($job->targetsectionid ?? 0) <= 0) {
            throw new \moodle_exception('subsectionpilotuncertainoutcome', 'local_coursepublisher');
        }

        $items = array_values($DB->get_records(
            'local_cp_job_item',
            ['jobid' => $job->id],
            'sortorder ASC, id ASC'
        ));
        if (count($items) !== count($manifest)) {
            throw new \moodle_exception('jobmanifestchanged', 'local_coursepublisher');
        }

        // If every child has already been recorded successfully, this is a
        // durable recovery path and no second restore is performed.
        $allcomplete = !empty($items);
        foreach ($items as $item) {
            if ((string)$item->status !== job_status::ITEM_SUCCEEDED || (int)$item->targetcmid <= 0) {
                $allcomplete = false;
                break;
            }
        }
        if ($allcomplete && (int)($job->targetsectionid ?? 0) > 0) {
            self::transition((int)$job->id, job_status::SUCCEEDED, '', [
                'mode' => self::MODE_SUBSECTION_TREE_PLACEMENT,
                'contentmutation' => true,
                'targetsectionid' => (int)$job->targetsectionid,
                'recovered_from_item_state' => true,
            ]);
            return;
        }

        $now = time();
        foreach ($items as $item) {
            if ((string)$item->status === job_status::ITEM_SUCCEEDED && (int)$item->targetcmid > 0) {
                // Mixed partial durable state is unsafe for a whole-subtree
                // restore because Core would recreate the entire subsection.
                throw new \moodle_exception('subsectionpilotpartialstate', 'local_coursepublisher');
            }
            if ((string)$item->status === job_status::ITEM_RUNNING && (int)$item->attempts > 0) {
                throw new \moodle_exception('subsectionpilotuncertainoutcome', 'local_coursepublisher');
            }
            $item->status = job_status::ITEM_RUNNING;
            $item->attempts = (int)$item->attempts + 1;
            $item->timestarted = $item->timestarted ?: $now;
            $item->timemodified = $now;
            $item->errorcode = null;
            $item->errormessage = null;
            $DB->update_record('local_cp_job_item', $item);
        }

        self::log((int)$job->id, 'subsection_restore_started', 'info',
            get_string('joblog_subsectionstarted', 'local_coursepublisher', count($items)), [
                'source_section_id' => (int)$job->sourceid,
                'target_course_id' => (int)$job->targetcourseid,
                'itemcount' => count($items),
            ]
        );

        try {
            self::begin_mutation($job);
            $copy = content_publisher::copy_delegated_subsection(
                (int)$job->sourceid,
                (int)$job->sourcecourseid,
                (int)$job->targetcourseid,
                (int)$job->userid,
                $manifest,
                $placement
            );

            // Persist the delegated target section immediately after Core
            // returns a fully validated subtree.
            $DB->set_field(
                'local_cp_job',
                'targetsectionid',
                (int)$copy['targetdelegatedsectionid'],
                ['id' => $job->id]
            );

            $mapbysource = [];
            foreach ($copy['childmap'] as $mapped) {
                $mapbysource[(int)$mapped['sourcecmid']] = $mapped;
            }

            foreach ($items as $item) {
                $sourcecmid = (int)$item->sourcecmid;
                if (empty($mapbysource[$sourcecmid])) {
                    throw new \moodle_exception('subsectionpilotmappingmissing', 'local_coursepublisher');
                }
                $mapped = $mapbysource[$sourcecmid];
                $item = $DB->get_record('local_cp_job_item', ['id' => $item->id], '*', MUST_EXIST);
                $item->targetcmid = (int)$mapped['targetcmid'];
                $item->status = job_status::ITEM_SUCCEEDED;
                $item->errorcode = null;
                $item->errormessage = get_string(
                    'jobsubsectionitemcopied',
                    'local_coursepublisher',
                    (object)[
                        'modname' => (string)$mapped['modname'],
                        'targetcmid' => (int)$mapped['targetcmid'],
                    ]
                );
                $item->timemodified = time();
                $item->timefinished = time();
                $DB->update_record('local_cp_job_item', $item);

                self::log((int)$job->id, 'item_succeeded', 'success',
                    get_string('joblog_itemsucceeded', 'local_coursepublisher', (object)[
                        'sourcecmid' => $sourcecmid,
                        'targetcmid' => (int)$mapped['targetcmid'],
                    ]),
                    [
                        'sourcecmid' => $sourcecmid,
                        'sourcemodule' => (string)$mapped['modname'],
                        'targetcmid' => (int)$mapped['targetcmid'],
                        'targetdelegatedsectionid' => (int)$copy['targetdelegatedsectionid'],
                    ]
                );
            }

            self::log((int)$job->id, 'subsection_placed', 'success',
                get_string('joblog_subsectionplaced', 'local_coursepublisher', (object)[
                    'delegatecmid' => (int)$copy['targetdelegatecmid'],
                    'delegatedsectionid' => (int)$copy['targetdelegatedsectionid'],
                    'parentsectionid' => (int)$copy['targetsectionid'],
                ]),
                $copy
            );

            self::transition((int)$job->id, job_status::SUCCEEDED, '', [
                'mode' => self::MODE_SUBSECTION_TREE_PLACEMENT,
                'contentmutation' => true,
                'targetdelegatecmid' => (int)$copy['targetdelegatecmid'],
                'targetsectionid' => (int)$copy['targetdelegatedsectionid'],
                'itemcount' => count($items),
            ]);
            self::log((int)$job->id, 'job_completed', 'success',
                get_string('joblog_subsectioncomplete', 'local_coursepublisher', (object)[
                    'targetsectionid' => (int)$copy['targetdelegatedsectionid'],
                    'itemcount' => count($items),
                ]),
                [
                    'contentmutation' => true,
                    'targetdelegatecmid' => (int)$copy['targetdelegatecmid'],
                    'targetdelegatedsectionid' => (int)$copy['targetdelegatedsectionid'],
                    'parentsectionid' => (int)$copy['targetsectionid'],
                    'itemcount' => count($items),
                    'warnings' => $copy['warnings'],
                ]
            );
        } catch (\Throwable $e) {
            // Every running child is marked failed for operator visibility.
            $currentitems = $DB->get_records('local_cp_job_item', ['jobid' => $job->id]);
            foreach ($currentitems as $item) {
                if ((string)$item->status === job_status::ITEM_RUNNING) {
                    $item->status = job_status::ITEM_FAILED;
                    $item->errorcode = substr(get_class($e), 0, 64);
                    $item->errormessage = $e->getMessage();
                    $item->timemodified = time();
                    $item->timefinished = time();
                    $DB->update_record('local_cp_job_item', $item);
                }
            }
            self::log((int)$job->id, 'subsection_restore_failed', 'error', $e->getMessage(), [
                'manualreview' => true,
                'source_section_id' => (int)$job->sourceid,
            ]);
            throw $e;
        }
    }

    /** Ensure a rerun cannot silently replace the durable item state. */
    private static function assert_existing_items_match_manifest(int $jobid, array $manifest): void {
        global $DB;

        $existing = array_values($DB->get_records('local_cp_job_item', ['jobid' => $jobid], 'sortorder ASC, id ASC'));
        if (count($existing) !== count($manifest)) {
            throw new \moodle_exception('jobmanifestchanged', 'local_coursepublisher');
        }
        foreach ($manifest as $i => $row) {
            if ((int)$existing[$i]->sourcecmid !== (int)$row['sourcecmid'] ||
                    !hash_equals((string)$existing[$i]->sourcefingerprint, (string)$row['sourcefingerprint'])) {
                throw new \moodle_exception('jobmanifestchanged', 'local_coursepublisher');
            }
        }
    }

    /** Re-run exact topology/source checks immediately before the worker proceeds. */
    private static function revalidate(\stdClass $job): array {
        global $DB;

        if (!has_capability('local/coursepublisher:publish', \context_system::instance(), (int)$job->userid)) {
            throw new \moodle_exception('jobpublishcaplost', 'local_coursepublisher');
        }

        if ((int)($job->batchid ?? 0) > 0) {
            batch_service::revalidate_child_job($job);
            $job = self::get_job((int)$job->id);
        }

        $school = $DB->get_record('local_cp_school', ['id' => $job->schoolid, 'enabled' => 1], '*', MUST_EXIST);
        $result = service::resolve_preview(
            (int)$job->programid,
            (string)$job->gradekey,
            (string)$job->sourcetype,
            (int)$job->sourceid,
            (int)$school->regionid,
            (int)$job->schoolid
        );
        if (count($result['targets']) !== 1) {
            throw new \moodle_exception('jobexactroute', 'local_coursepublisher');
        }
        $route = reset($result['targets']);
        if (!$route || $route->status !== 'ready') {
            throw new \moodle_exception('jobpreflightnotready', 'local_coursepublisher');
        }
        if ((int)$route->courseid !== (int)$job->targetcourseid || (int)$route->targetbindid !== (int)$job->targetbindid) {
            throw new \moodle_exception('jobtargetchanged', 'local_coursepublisher');
        }

        if (in_array((string)$job->mode, [self::MODE_PAGE_PLACEMENT, self::MODE_QUIZ_PLACEMENT, self::MODE_SUBSECTION_TREE_PLACEMENT, self::MODE_ACTIVITY_GENERIC_PLACEMENT], true)) {
            self::placement_from_job($job);
            if ((string)$job->mode === self::MODE_SUBSECTION_TREE_PLACEMENT) {
                content_publisher::validate_source_delegated_subsection(
                    (int)$job->sourceid,
                    (int)$job->sourcecourseid
                );
            } else if ((string)$job->mode === self::MODE_ACTIVITY_GENERIC_PLACEMENT) {
                $activity = content_publisher::validate_backup_capable_activity(
                    (int)$job->sourceid,
                    (int)$job->sourcecourseid
                );
                if ((string)$activity['modname'] === 'subsection') {
                    throw new \moodle_exception('genericactivitysubsectionuse_section', 'local_coursepublisher');
                }
            }
        } else if (in_array((string)$job->mode, [self::MODE_SECTION_PAGE_PLACEMENT, self::MODE_SECTION_MIXED_PLACEMENT, self::MODE_SECTION_GENERIC_PEER], true)) {
            self::section_placement_from_job($job);
            if ((string)$job->mode === self::MODE_SECTION_GENERIC_PEER) {
                content_publisher::validate_source_section_for_generic_copy(
                    (int)$job->sourceid,
                    (int)$job->sourcecourseid
                );
            } else {
                content_publisher::validate_source_section_for_page_pilot(
                    (int)$job->sourceid,
                    (int)$job->sourcecourseid
                );
            }
        }

        $fingerprint = self::source_fingerprint((string)$job->sourcetype, (int)$job->sourceid, (int)$job->sourcecourseid);
        if (!hash_equals((string)$job->sourcefingerprint, $fingerprint)) {
            throw new \moodle_exception('jobsourcechanged', 'local_coursepublisher');
        }

        return ['result' => $result, 'sourcefingerprint' => $fingerprint];
    }

    /**
     * Build a deterministic source fingerprint for idempotency and execution-time revalidation.
     */
    public static function source_fingerprint(string $sourcetype, int $sourceid, int $sourcecourseid): string {
        global $DB;

        $payload = [
            'type' => $sourcetype,
            'sourceid' => $sourceid,
            'sourcecourseid' => $sourcecourseid,
        ];
        if ($sourcetype === 'activity') {
            $cm = $DB->get_record('course_modules', ['id' => $sourceid, 'course' => $sourcecourseid], '*', MUST_EXIST);
            if (!empty($cm->deletioninprogress)) {
                throw new \moodle_exception('sourcependingdelete', 'local_coursepublisher');
            }
            $payload['activity'] = self::normalise_record($cm);
            $payload['module'] = self::module_identity((int)$cm->module, (int)$cm->instance);
        } else if ($sourcetype === 'section') {
            $section = $DB->get_record('course_sections', ['id' => $sourceid, 'course' => $sourcecourseid], '*', MUST_EXIST);
            $payload['section'] = self::normalise_record($section);
            $payload['manifest'] = self::build_manifest($sourcetype, $sourceid, $sourcecourseid);
        } else {
            throw new \moodle_exception('invalidparameter');
        }

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Build manifest rows but do not mutate any target course. */
    public static function build_manifest(string $sourcetype, int $sourceid, int $sourcecourseid): array {
        global $DB;

        if ($sourcetype === 'activity') {
            $cm = $DB->get_record('course_modules', ['id' => $sourceid, 'course' => $sourcecourseid], '*', MUST_EXIST);
            if (!empty($cm->deletioninprogress)) {
                throw new \moodle_exception('sourcependingdelete', 'local_coursepublisher');
            }
            $identity = self::module_identity((int)$cm->module, (int)$cm->instance);
            return [[
                'sortorder' => 0,
                'sourcecmid' => (int)$cm->id,
                'sourcemodule' => $identity['modname'],
                'sourceinstanceid' => (int)$cm->instance,
                'sourcefingerprint' => hash('sha256', json_encode([
                    self::normalise_record($cm),
                    $identity,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ]];
        }

        if ($sourcetype !== 'section') {
            throw new \moodle_exception('invalidparameter');
        }
        $section = $DB->get_record('course_sections', ['id' => $sourceid, 'course' => $sourcecourseid], '*', MUST_EXIST);
        $sequence = trim((string)$section->sequence);
        if ($sequence === '') {
            return [];
        }
        $ids = array_values(array_filter(array_map('intval', explode(',', $sequence))));
        if (!$ids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'cm');
        $params['courseid'] = $sourcecourseid;
        $cms = $DB->get_records_select('course_modules', "id {$insql} AND course = :courseid", $params);

        $manifest = [];
        foreach ($ids as $sortorder => $cmid) {
            if (!isset($cms[$cmid])) {
                throw new \moodle_exception('jobmanifestbroken', 'local_coursepublisher', '', $cmid);
            }
            $cm = $cms[$cmid];
            if (!empty($cm->deletioninprogress)) {
                throw new \moodle_exception('sourcependingdelete', 'local_coursepublisher');
            }
            $identity = self::module_identity((int)$cm->module, (int)$cm->instance);
            $manifest[] = [
                'sortorder' => $sortorder,
                'sourcecmid' => (int)$cm->id,
                'sourcemodule' => $identity['modname'],
                'sourceinstanceid' => (int)$cm->instance,
                'sourcefingerprint' => hash('sha256', json_encode([
                    self::normalise_record($cm),
                    $identity,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ];
        }
        return $manifest;
    }

    /** Resolve module name and a generic instance modification marker where available. */
    private static function module_identity(int $moduleid, int $instanceid): array {
        global $DB;

        $module = $DB->get_record('modules', ['id' => $moduleid], 'id,name', MUST_EXIST);
        $identity = [
            'moduleid' => $moduleid,
            'modname' => (string)$module->name,
            'instanceid' => $instanceid,
        ];
        try {
            $columns = $DB->get_columns((string)$module->name);
            if (isset($columns['timemodified'])) {
                $identity['instancetimemodified'] = (int)$DB->get_field((string)$module->name, 'timemodified', ['id' => $instanceid]);
            } else if (isset($columns['timecreated'])) {
                $identity['instancetimecreated'] = (int)$DB->get_field((string)$module->name, 'timecreated', ['id' => $instanceid]);
            }

            if ((string)$module->name === 'quiz') {
                $slots = array_values($DB->get_records(
                    'quiz_slots',
                    ['quizid' => $instanceid],
                    'slot ASC',
                    'id,slot,page,requireprevious,maxmark'
                ));
                $identity['quizslots'] = array_map(
                    static fn(\stdClass $slot): array => [
                        'slot' => (int)$slot->slot,
                        'page' => (int)$slot->page,
                        'requireprevious' => (int)$slot->requireprevious,
                        'maxmark' => (string)$slot->maxmark,
                    ],
                    $slots
                );
            }
        } catch (\Throwable $ignored) {
            // Module identity remains valid even when a third-party module has an unusual schema.
        }
        return $identity;
    }

    private static function normalise_record(\stdClass $record): array {
        $values = get_object_vars($record);
        ksort($values);
        return $values;
    }

    private static function idempotency_key(int $programid, int $schoolid, string $gradekey, string $sourcetype,
            int $sourceid, int $targetcourseid, string $sourcefingerprint, string $mode, array $placement = []): string {
        $contract = match ($mode) {
            self::MODE_PAGE_PILOT => 'case2-page-pilot-v1',
            self::MODE_PAGE_PLACEMENT => 'case2-page-placement-v1',
            self::MODE_QUIZ_PLACEMENT => 'case2-quiz-placement-v1',
            self::MODE_SECTION_PAGE_PLACEMENT => 'case2-section-page-placement-v1',
            self::MODE_SECTION_MIXED_PLACEMENT => 'case2-section-page-quiz-placement-v1',
            self::MODE_SUBSECTION_TREE_PLACEMENT => 'case2-delegated-subsection-tree-v2-generic-modules',
            self::MODE_ACTIVITY_GENERIC_PLACEMENT => 'case2-generic-activity-placement-v1',
            self::MODE_SECTION_GENERIC_PEER => 'case2-generic-peer-section-v1',
            default => 'case2-foundation-v1',
        };
        return hash('sha256', json_encode([
            'contract' => $contract,
            'mode' => $mode,
            'programid' => $programid,
            'schoolid' => $schoolid,
            'gradekey' => $gradekey,
            'sourcetype' => $sourcetype,
            'sourceid' => $sourceid,
            'targetcourseid' => $targetcourseid,
            'sourcefingerprint' => $sourcefingerprint,
            'placement' => $placement,
        ], JSON_UNESCAPED_SLASHES));
    }

    /** Read and revalidate exact placement stored in a durable job snapshot. */
    public static function placement_from_job(\stdClass $job): array {
        $snapshot = json_decode((string)$job->preflightjson, true);
        if (!is_array($snapshot) || empty($snapshot['placement']) || !is_array($snapshot['placement'])) {
            throw new \moodle_exception('placementinvalid', 'local_coursepublisher');
        }
        return content_publisher::validate_placement((int)$job->targetcourseid, $snapshot['placement']);
    }

    /** Read and revalidate section-level placement stored in a durable job snapshot. */
    public static function section_placement_from_job(\stdClass $job): array {
        $snapshot = json_decode((string)$job->preflightjson, true);
        if (!is_array($snapshot) || empty($snapshot['placement']) || !is_array($snapshot['placement'])) {
            throw new \moodle_exception('sectionplacementinvalid', 'local_coursepublisher');
        }
        return content_publisher::validate_section_placement((int)$job->targetcourseid, $snapshot['placement']);
    }

    private static function replace_item_scaffold(int $jobid, array $manifest): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_cp_job_item', ['jobid' => $jobid]);
        $now = time();
        foreach ($manifest as $item) {
            $DB->insert_record('local_cp_job_item', (object)[
                'jobid' => $jobid,
                'sortorder' => (int)$item['sortorder'],
                'sourcecmid' => (int)$item['sourcecmid'],
                'sourcemodule' => (string)$item['sourcemodule'],
                'sourceinstanceid' => (int)$item['sourceinstanceid'],
                'sourcefingerprint' => (string)$item['sourcefingerprint'],
                'targetcmid' => 0,
                'status' => job_status::ITEM_PENDING,
                'attempts' => 0,
                'errorcode' => null,
                'errormessage' => null,
                'timecreated' => $now,
                'timemodified' => $now,
                'timestarted' => 0,
                'timefinished' => 0,
            ]);
        }
        $transaction->allow_commit();
    }

    public static function transition(int $jobid, string $status, string $lasterror = '', array $details = []): void {
        global $DB;

        $job = self::get_job($jobid);
        $actualstatus = $status;
        if (in_array($status, [job_status::FAILED, job_status::PREFLIGHT_FAILED, job_status::PARTIAL], true) &&
                (string)($job->mutationstate ?? 'unknown') === 'started') {
            self::set_mutation_state($jobid, 'uncertain');
            $job = self::get_job($jobid);
            $actualstatus = job_status::MANUAL_REVIEW;
            $details['mutationstate'] = 'uncertain';
            $details['manualreview'] = true;
        }

        if ($status === job_status::SUCCEEDED && (string)$job->mode !== self::MODE_FOUNDATION) {
            // Origin mappings are part of the success postcondition. If this
            // fails, the caller observes an exception while mutation is still
            // STARTED, causing fail-closed manual review rather than false success.
            origin_map_service::record_job_success_from_db($jobid);
            self::set_mutation_state($jobid, 'committed');
            $job = self::get_job($jobid);
            $details['mutationstate'] = 'committed';
        }

        $job->status = $actualstatus;
        $job->timemodified = time();
        $job->lasterror = $lasterror !== '' ? $lasterror : null;
        if (in_array($actualstatus, [job_status::PREFLIGHT_FAILED, job_status::PARTIAL, job_status::SUCCEEDED,
                job_status::FAILED, job_status::MANUAL_REVIEW, job_status::CANCELLED], true)) {
            $job->timefinished = time();
        }
        if (in_array($actualstatus, [job_status::READY, job_status::WAITING, job_status::QUEUED, job_status::RUNNING], true)) {
            $job->timefinished = 0;
        }
        $DB->update_record('local_cp_job', $job);
        self::log($jobid, 'status_' . $actualstatus,
            in_array($actualstatus, [job_status::FAILED, job_status::MANUAL_REVIEW], true) ? 'error' : 'info',
            get_string('jobstatuschanged', 'local_coursepublisher', $actualstatus), $details);

        if ((int)($job->batchtargetid ?? 0) > 0 && class_exists(batch_service::class)) {
            batch_service::sync_from_job(self::get_job($jobid));
        }
    }

    public static function log(int $jobid, string $event, string $level, string $message, array $details = [],
            ?int $userid = null): void {
        global $DB;

        if ($userid === null) {
            $job = self::get_job($jobid);
            $userid = (int)$job->userid;
        }
        $DB->insert_record('local_cp_job_log', (object)[
            'jobid' => $jobid,
            'userid' => $userid,
            'event' => $event,
            'level' => $level,
            'message' => $message,
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'timecreated' => time(),
        ]);
    }
}
