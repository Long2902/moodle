<?php

namespace local_coursepublisher\local;

defined('MOODLE_INTERNAL') || die();

/** Durable multi-target Batch orchestration. */
final class batch_service {
    public const GLOBAL_ACTIVE_LIMIT = 10;
    public const DEFAULT_DISPATCH_LIMIT = 10;

    /** Configured target bindings eligible for initial operator selection. */
    public static function eligible_targets(int $programid, string $gradekey): array {
        global $DB;
        service::resolve_target_group($programid, $gradekey, true);
        $sql = "SELECT t.id AS targetbindid, t.programid, t.schoolid, t.gradekey,
                       t.courseid AS targetcourseid, s.regionid,
                       s.code AS schoolcode, s.name AS schoolname,
                       r.code AS regioncode, r.name AS regionname,
                       c.fullname AS coursename, c.shortname AS shortname
                  FROM {local_cp_target} t
                  JOIN {local_cp_school} s ON s.id = t.schoolid AND s.enabled = 1
                  JOIN {local_cp_region} r ON r.id = s.regionid AND r.enabled = 1
                  JOIN {course} c ON c.id = t.courseid
                 WHERE t.programid = :programid
                   AND t.gradekey = :gradekey
                   AND t.enabled = 1
              ORDER BY r.name, s.name, c.fullname, t.id";
        return array_values($DB->get_records_sql($sql, ['programid' => $programid, 'gradekey' => $gradekey]));
    }

    /**
     * Read-only fan-out preflight.
     *
     * Request keys: programid, gradekey, sourcetype, sourceid, publishmode,
     * placementmode, targetbindids, optional manualplacement/logicalcontract.
     */
    public static function preflight(array $request): array {
        global $DB;
        $programid = (int)($request['programid'] ?? 0);
        $gradekey = (string)($request['gradekey'] ?? '');
        $sourcetype = (string)($request['sourcetype'] ?? '');
        $sourceid = (int)($request['sourceid'] ?? 0);
        $publishmode = (string)($request['publishmode'] ?? '');
        $placementmode = (string)($request['placementmode'] ?? 'auto');
        $targetids = array_values(array_unique(array_filter(array_map('intval', (array)($request['targetbindids'] ?? [])),
            static fn(int $id): bool => $id > 0)));

        $targetgroup = service::resolve_target_group($programid, $gradekey, true);
        if ($programid <= 0 || $sourceid <= 0 || !$targetids || !in_array($sourcetype, ['activity', 'section'], true) ||
                !in_array($placementmode, ['auto', 'override'], true)) {
            throw new \moodle_exception('invalidparameter');
        }
        self::validate_publish_mode($sourcetype, $publishmode, $sourceid, $programid, $gradekey);

        // Resolve source/master once. The resulting route rows are also a
        // single read-only health snapshot for all enabled schools.
        $common = service::resolve_preview($programid, $gradekey, $sourcetype, $sourceid, 0, 0);
        $sourcecourseid = (int)$common['mastercourse']->id;
        $manifest = job_service::build_manifest($sourcetype, $sourceid, $sourcecourseid);
        self::validate_manifest_for_mode($sourcetype, $sourceid, $sourcecourseid, $publishmode, $manifest);
        $sourcefingerprint = job_service::source_fingerprint($sourcetype, $sourceid, $sourcecourseid);

        if (!empty($request['logicalcontract']) && is_array($request['logicalcontract'])) {
            $logicalcontract = $request['logicalcontract'];
        } else if ($placementmode === 'override') {
            $logicalcontract = logical_placement_resolver::build_manual_contract(
                $sourcetype, $publishmode, (array)($request['manualplacement'] ?? [])
            );
        } else {
            $logicalcontract = logical_placement_resolver::build_source_contract(
                $sourcetype, $sourceid, $sourcecourseid, $publishmode
            );
        }

        $routemap = [];
        foreach ((array)$common['targets'] as $route) {
            if (!empty($route->targetbindid)) {
                $routemap[(int)$route->targetbindid] = $route;
            }
        }
        $snapshots = self::selected_target_rows($programid, $gradekey, $targetids);
        $rows = [];
        $ready = 0;
        $blocked = 0;
        foreach ($targetids as $targetbindid) {
            $snapshot = $snapshots[$targetbindid] ?? self::missing_target_snapshot($targetbindid);
            $row = self::preflight_one_target($common['program'], $gradekey, $sourcetype, $sourceid,
                $sourcecourseid, $sourcefingerprint, $publishmode, $logicalcontract, $snapshot,
                $routemap[$targetbindid] ?? null);
            $rows[] = $row;
            if ($row['status'] === 'ready') {
                $ready++;
            } else {
                $blocked++;
            }
        }

        return [
            'program' => $common['program'],
            'mastercourse' => $common['mastercourse'],
            'source' => $common['source'],
            'programid' => $programid,
            'gradekey' => $gradekey,
            'targetgroupid' => (int)$targetgroup->id,
            'sourcetype' => $sourcetype,
            'sourceid' => $sourceid,
            'sourcecourseid' => $sourcecourseid,
            'sourcefingerprint' => $sourcefingerprint,
            'publishmode' => $publishmode,
            'placementmode' => $placementmode,
            'logicalcontract' => $logicalcontract,
            'manifest' => $manifest,
            'targets' => $rows,
            'totaltargets' => count($rows),
            'readytargets' => $ready,
            'blockedtargets' => $blocked,
            'preflighttime' => time(),
        ];
    }

    /** Create durable Batch snapshot and waiting child jobs. */
    public static function create_from_preflight(array $preflight, int $userid): \stdClass {
        global $DB;
        if ((int)($preflight['readytargets'] ?? 0) <= 0) {
            throw new \moodle_exception('batchnoreadytargets', 'local_coursepublisher');
        }
        $transaction = $DB->start_delegated_transaction();
        $now = time();
        $batchid = (int)$DB->insert_record('local_cp_batch', (object)[
            'requestid' => bin2hex(random_bytes(16)),
            'userid' => $userid,
            'programid' => (int)$preflight['programid'],
            'gradekey' => (string)$preflight['gradekey'],
            'targetgroupid' => (int)$preflight['targetgroupid'],
            'sourcetype' => (string)$preflight['sourcetype'],
            'sourceid' => (int)$preflight['sourceid'],
            'sourcecourseid' => (int)$preflight['sourcecourseid'],
            'sourcefingerprint' => (string)$preflight['sourcefingerprint'],
            'publishmode' => (string)$preflight['publishmode'],
            'placementmode' => (string)$preflight['placementmode'],
            'placementjson' => self::encode($preflight['logicalcontract']),
            'resolverversion' => logical_placement_resolver::VERSION,
            'status' => batch_status::QUEUED,
            'dispatchlimit' => self::DEFAULT_DISPATCH_LIMIT,
            'pausereason' => null,
            'totaltargets' => (int)$preflight['totaltargets'],
            'readytargets' => (int)$preflight['readytargets'],
            'blockedtargets' => (int)$preflight['blockedtargets'],
            'waitingtargets' => 0,
            'queuedtargets' => 0,
            'runningtargets' => 0,
            'succeededtargets' => 0,
            'failedtargets' => 0,
            'manualreviewtargets' => 0,
            'cancelledtargets' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
            'timestarted' => 0,
            'timefinished' => 0,
        ]);

        foreach ($preflight['targets'] as $row) {
            $isready = $row['status'] === 'ready';
            $targetid = (int)$DB->insert_record('local_cp_batch_target', (object)[
                'batchid' => $batchid,
                'targetbindid' => (int)$row['targetbindid'],
                'regionid' => (int)$row['regionid'],
                'schoolid' => (int)$row['schoolid'],
                'targetcourseid' => (int)$row['targetcourseid'],
                'preflightstatus' => $isready ? batch_status::TARGET_READY : batch_status::TARGET_BLOCKED,
                'status' => $isready ? batch_status::TARGET_READY : batch_status::TARGET_BLOCKED,
                'blockcode' => $isready ? null : (string)$row['blockcode'],
                'blockreason' => $isready ? null : (string)$row['blockreason'],
                'preflightjson' => self::encode($row['snapshot']),
                'logicalplacementjson' => self::encode($preflight['logicalcontract']),
                'resolvedplacementjson' => $isready ? self::encode($row['resolved']) : null,
                'attempts' => 0,
                'latestjobid' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
                'timefinished' => $isready ? 0 : $now,
            ]);
            if ($isready) {
                job_service::create_for_batch_target($targetid, [
                    'placement' => $row['resolved']['placement'],
                    'skipreconcile' => true,
                ]);
            }
        }
        self::reconcile($batchid);
        service::audit('batch_create', 'batch', $batchid, [
            'targets' => (int)$preflight['totaltargets'],
            'ready' => (int)$preflight['readytargets'],
            'blocked' => (int)$preflight['blockedtargets'],
            'sourceid' => (int)$preflight['sourceid'],
            'sourcetype' => (string)$preflight['sourcetype'],
        ]);
        $transaction->allow_commit();
        return self::get_batch($batchid);
    }

    public static function get_batch(int $batchid): \stdClass {
        global $DB;
        return $DB->get_record('local_cp_batch', ['id' => $batchid], '*', MUST_EXIST);
    }

    public static function targets(int $batchid): array {
        global $DB;
        $sql = "SELECT bt.*, r.name AS regionname, r.code AS regioncode,
                       s.name AS schoolname, s.code AS schoolcode,
                       c.fullname AS coursename, c.shortname,
                       j.mutationstate AS latestmutationstate, j.status AS latestjobstatus
                  FROM {local_cp_batch_target} bt
             LEFT JOIN {local_cp_region} r ON r.id = bt.regionid
             LEFT JOIN {local_cp_school} s ON s.id = bt.schoolid
             LEFT JOIN {course} c ON c.id = bt.targetcourseid
             LEFT JOIN {local_cp_job} j ON j.id = bt.latestjobid
                 WHERE bt.batchid = :batchid
              ORDER BY r.name, s.name, bt.id";
        return array_values($DB->get_records_sql($sql, ['batchid' => $batchid]));
    }

    public static function recent_batches(int $limit = 50, array $filters = []): array {
        global $DB;

        $where = [];
        $params = [];
        $validstatuses = [
            batch_status::DRAFT,
            batch_status::PREFLIGHT,
            batch_status::QUEUED,
            batch_status::RUNNING,
            batch_status::PAUSED,
            batch_status::PARTIAL,
            batch_status::SUCCEEDED,
            batch_status::FAILED,
            batch_status::CANCELLED,
        ];

        $status = (string)($filters['status'] ?? '');
        if ($status !== '' && in_array($status, $validstatuses, true)) {
            $where[] = 'b.status = :status';
            $params['status'] = $status;
        }
        $programid = (int)($filters['programid'] ?? 0);
        if ($programid > 0) {
            $where[] = 'b.programid = :programid';
            $params['programid'] = $programid;
        }
        $gradekey = (string)($filters['gradekey'] ?? '');
        if ($gradekey !== '') {
            service::assert_grade($gradekey);
            $where[] = 'b.gradekey = :gradekey';
            $params['gradekey'] = $gradekey;
        }
        $timefrom = (int)($filters['timefrom'] ?? 0);
        if ($timefrom > 0) {
            $where[] = 'b.timecreated >= :timefrom';
            $params['timefrom'] = $timefrom;
        }
        $timeto = (int)($filters['timeto'] ?? 0);
        if ($timeto > 0) {
            $where[] = 'b.timecreated <= :timeto';
            $params['timeto'] = $timeto;
        }

        $wheresql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT b.*, p.name AS programname, p.code AS programcode,
                       c.fullname AS sourcecoursename
                  FROM {local_cp_batch} b
             LEFT JOIN {local_cp_program} p ON p.id = b.programid
             LEFT JOIN {course} c ON c.id = b.sourcecourseid" .
             $wheresql . "
              ORDER BY b.id DESC";
        return array_values($DB->get_records_sql($sql, $params, 0, max(1, $limit)));
    }

    /** Synchronize one Batch Target from durable child-job state. */
    public static function sync_from_job(\stdClass $job): void {
        global $DB;
        if ((int)($job->batchtargetid ?? 0) <= 0) {
            return;
        }
        $target = $DB->get_record('local_cp_batch_target', ['id' => (int)$job->batchtargetid], '*', MUST_EXIST);
        $map = [
            job_status::WAITING => batch_status::TARGET_WAITING,
            job_status::READY => batch_status::TARGET_WAITING,
            job_status::QUEUED => batch_status::TARGET_QUEUED,
            job_status::RUNNING => batch_status::TARGET_RUNNING,
            job_status::SUCCEEDED => batch_status::TARGET_SUCCEEDED,
            job_status::FAILED => batch_status::TARGET_FAILED,
            job_status::PREFLIGHT_FAILED => batch_status::TARGET_FAILED,
            job_status::PARTIAL => batch_status::TARGET_FAILED,
            job_status::MANUAL_REVIEW => batch_status::TARGET_MANUAL_REVIEW,
            job_status::CANCELLED => batch_status::TARGET_CANCELLED,
        ];
        $target->status = $map[(string)$job->status] ?? $target->status;
        $target->latestjobid = (int)$job->id;
        $target->timemodified = time();
        if (in_array((string)$target->status, [batch_status::TARGET_SUCCEEDED, batch_status::TARGET_FAILED,
                batch_status::TARGET_MANUAL_REVIEW, batch_status::TARGET_CANCELLED], true)) {
            $target->timefinished = time();
        } else {
            $target->timefinished = 0;
        }
        if ((string)$target->status === batch_status::TARGET_FAILED && !empty($job->lasterror)) {
            $target->blockcode = 'job_failed';
            $target->blockreason = (string)$job->lasterror;
        }
        $DB->update_record('local_cp_batch_target', $target);
        self::reconcile((int)$target->batchid);
    }

    /** Recalculate cached Batch counters from target source-of-truth rows. */
    public static function reconcile(int $batchid): \stdClass {
        global $DB;
        $batch = self::get_batch($batchid);
        $targets = array_values($DB->get_records('local_cp_batch_target', ['batchid' => $batchid]));
        $counts = [
            'totaltargets' => count($targets), 'readytargets' => 0, 'blockedtargets' => 0,
            'waitingtargets' => 0, 'queuedtargets' => 0, 'runningtargets' => 0,
            'succeededtargets' => 0, 'failedtargets' => 0, 'manualreviewtargets' => 0,
            'cancelledtargets' => 0,
        ];
        foreach ($targets as $target) {
            // preflightstatus is immutable audit evidence. A target that was
            // originally BLOCKED but later passed recheck is counted as ready
            // once it has a durable child-job lineage.
            if ((string)$target->preflightstatus === batch_status::TARGET_READY || (int)$target->latestjobid > 0) {
                $counts['readytargets']++;
            }
            $field = match ((string)$target->status) {
                batch_status::TARGET_BLOCKED => 'blockedtargets',
                batch_status::TARGET_READY, batch_status::TARGET_WAITING => 'waitingtargets',
                batch_status::TARGET_QUEUED => 'queuedtargets',
                batch_status::TARGET_RUNNING => 'runningtargets',
                batch_status::TARGET_SUCCEEDED => 'succeededtargets',
                batch_status::TARGET_FAILED => 'failedtargets',
                batch_status::TARGET_MANUAL_REVIEW => 'manualreviewtargets',
                batch_status::TARGET_CANCELLED => 'cancelledtargets',
                default => null,
            };
            if ($field) {
                $counts[$field]++;
            }
        }
        foreach ($counts as $field => $value) {
            $batch->{$field} = $value;
        }
        $active = $counts['waitingtargets'] + $counts['queuedtargets'] + $counts['runningtargets'];
        $problems = $counts['blockedtargets'] + $counts['failedtargets'] + $counts['manualreviewtargets'];
        if (!in_array((string)$batch->status, [batch_status::PAUSED, batch_status::CANCELLED], true)) {
            if ($counts['runningtargets'] > 0) {
                $batch->status = batch_status::RUNNING;
            } else if ($active > 0) {
                $batch->status = batch_status::QUEUED;
            } else if ($problems > 0 && $counts['succeededtargets'] > 0) {
                $batch->status = batch_status::PARTIAL;
            } else if ($problems > 0) {
                $batch->status = batch_status::FAILED;
            } else {
                $batch->status = batch_status::SUCCEEDED;
            }
        }
        if (!$batch->timestarted && ($counts['queuedtargets'] || $counts['runningtargets'] || $counts['succeededtargets'])) {
            $batch->timestarted = time();
        }
        $batch->timemodified = time();
        if ($active === 0 && in_array((string)$batch->status,
                [batch_status::SUCCEEDED, batch_status::FAILED, batch_status::PARTIAL, batch_status::CANCELLED], true)) {
            $batch->timefinished = $batch->timefinished ?: time();
        } else if ($active > 0 && (string)$batch->status !== batch_status::CANCELLED) {
            $batch->timefinished = 0;
        }
        $DB->update_record('local_cp_batch', $batch);
        return $batch;
    }

    public static function pause(int $batchid, string $reason = ''): void {
        global $DB;
        $batch = self::get_batch($batchid);
        if ((string)$batch->status === batch_status::CANCELLED) {
            return;
        }
        $batch->status = batch_status::PAUSED;
        $batch->pausereason = $reason !== '' ? $reason : null;
        $batch->timemodified = time();
        $DB->update_record('local_cp_batch', $batch);

        // Release dispatcher capacity held by child jobs that have been queued
        // but have not started. Their Moodle adhoc task record may still exist;
        // publish_job_task refuses to execute a WAITING Batch job until the
        // dispatcher grants it a slot again after Resume.
        $queuedjobs = $DB->get_records('local_cp_job', [
            'batchid' => $batchid,
            'status' => job_status::QUEUED,
        ], 'id ASC');
        foreach ($queuedjobs as $queuedjob) {
            job_service::transition((int)$queuedjob->id, job_status::WAITING, '', [
                'reason' => 'batch_paused',
            ]);
        }
        service::audit('batch_pause', 'batch', $batchid, [
            'reason' => $reason,
            'returned_to_waiting' => count($queuedjobs),
        ]);
    }

    public static function resume(int $batchid): void {
        global $DB;
        $batch = self::get_batch($batchid);
        if ((string)$batch->status !== batch_status::PAUSED) {
            throw new \moodle_exception('batchnotpaused', 'local_coursepublisher');
        }
        self::assert_source_unchanged($batch);

        // Normalize any QUEUED child left by a dispatcher/pause race while the
        // Batch is still PAUSED. Existing adhoc task records are harmless: the
        // worker refuses WAITING jobs until the dispatcher grants a slot.
        $queuedjobs = $DB->get_records('local_cp_job', [
            'batchid' => $batchid,
            'status' => job_status::QUEUED,
        ], 'id ASC');
        foreach ($queuedjobs as $queuedjob) {
            job_service::transition((int)$queuedjob->id, job_status::WAITING, '', [
                'reason' => 'batch_resume_normalize',
            ]);
        }

        self::refresh_waiting_targets($batch);
        $batch = self::get_batch($batchid);
        $batch->status = batch_status::QUEUED;
        $batch->pausereason = null;
        $batch->timemodified = time();
        $DB->update_record('local_cp_batch', $batch);
        self::reconcile($batchid);
        service::audit('batch_resume', 'batch', $batchid);
    }

    public static function cancel(int $batchid): void {
        global $DB;
        $batch = self::get_batch($batchid);
        if ((string)$batch->status === batch_status::CANCELLED) {
            return;
        }
        $targets = array_values($DB->get_records('local_cp_batch_target', ['batchid' => $batchid]));
        foreach ($targets as $target) {
            if (in_array((string)$target->status, [batch_status::TARGET_READY, batch_status::TARGET_WAITING,
                    batch_status::TARGET_QUEUED], true)) {
                if ((int)$target->latestjobid > 0) {
                    $job = job_service::get_job((int)$target->latestjobid);
                    if ((string)$job->status !== job_status::RUNNING) {
                        job_service::transition((int)$job->id, job_status::CANCELLED, '', ['batch_cancelled' => true]);
                    }
                } else {
                    $target->status = batch_status::TARGET_CANCELLED;
                    $target->timemodified = time();
                    $target->timefinished = time();
                    $DB->update_record('local_cp_batch_target', $target);
                }
            }
        }
        $batch = self::get_batch($batchid);
        $batch->status = batch_status::CANCELLED;
        $batch->pausereason = null;
        $batch->timemodified = time();
        $DB->update_record('local_cp_batch', $batch);
        self::reconcile($batchid);
        service::audit('batch_cancel', 'batch', $batchid);
    }

    public static function recheck_blocked(int $batchid): \stdClass {
        global $DB;
        $batch = self::get_batch($batchid);
        if ((string)$batch->status === batch_status::CANCELLED) {
            throw new \moodle_exception('batchcancelled', 'local_coursepublisher');
        }
        self::assert_source_unchanged($batch);
        $logicalcontract = self::decode((string)$batch->placementjson);
        $targets = array_values($DB->get_records('local_cp_batch_target', [
            'batchid' => $batchid, 'status' => batch_status::TARGET_BLOCKED,
        ], 'id ASC'));
        foreach ($targets as $target) {
            $result = self::revalidate_snapshot_target($batch, $target, $logicalcontract);
            self::apply_target_preflight($target, $result);
            if ($result['status'] === 'ready') {
                job_service::create_for_batch_target((int)$target->id, ['placement' => $result['resolved']['placement']]);
            }
        }
        service::audit('batch_recheck_blocked', 'batch', $batchid, ['targets' => count($targets)]);
        return self::reconcile($batchid);
    }

    public static function retry_safe_failures(int $batchid): \stdClass {
        global $DB;
        $batch = self::get_batch($batchid);
        if ((string)$batch->status === batch_status::CANCELLED) {
            throw new \moodle_exception('batchcancelled', 'local_coursepublisher');
        }
        self::assert_source_unchanged($batch);
        $logicalcontract = self::decode((string)$batch->placementjson);
        $targets = array_values($DB->get_records('local_cp_batch_target', [
            'batchid' => $batchid, 'status' => batch_status::TARGET_FAILED,
        ], 'id ASC'));
        $retried = 0;
        foreach ($targets as $target) {
            if ((int)$target->latestjobid <= 0) {
                continue;
            }
            $oldjob = job_service::get_job((int)$target->latestjobid);
            if ((string)$oldjob->mutationstate !== 'none') {
                continue;
            }
            $result = self::revalidate_snapshot_target($batch, $target, $logicalcontract);
            self::apply_target_preflight($target, $result);
            if ($result['status'] !== 'ready') {
                continue;
            }
            job_service::create_for_batch_target((int)$target->id, ['placement' => $result['resolved']['placement']]);
            $retried++;
        }
        service::audit('batch_retry_safe', 'batch', $batchid, ['retried' => $retried]);
        return self::reconcile($batchid);
    }

    /** Runtime preflight used by child worker immediately before mutation. */
    public static function revalidate_child_job(\stdClass $job): array {
        global $DB;
        if ((int)($job->batchid ?? 0) <= 0 || (int)($job->batchtargetid ?? 0) <= 0) {
            return [];
        }
        $batch = self::get_batch((int)$job->batchid);
        if (in_array((string)$batch->status, [batch_status::PAUSED, batch_status::CANCELLED], true)) {
            throw new \moodle_exception('batchnotrunnable', 'local_coursepublisher');
        }
        try {
            self::assert_source_unchanged($batch);
        } catch (\moodle_exception $e) {
            self::pause((int)$batch->id, 'source_changed');
            throw $e;
        }
        $target = $DB->get_record('local_cp_batch_target', ['id' => (int)$job->batchtargetid, 'batchid' => (int)$batch->id], '*', MUST_EXIST);
        $logicalcontract = self::decode((string)$target->logicalplacementjson);
        $result = self::revalidate_snapshot_target($batch, $target, $logicalcontract);
        if ($result['status'] !== 'ready') {
            throw new \moodle_exception('batchtargetruntimeblocked', 'local_coursepublisher', '', $result['blockreason']);
        }
        // Keep job's immutable source snapshot, but refresh the physical IDs
        // immediately before mutation so placement never relies on stale IDs.
        $snapshot = self::decode((string)$job->preflightjson);
        $snapshot['placement'] = $result['resolved']['placement'];
        $snapshot['runtime_resolver'] = $result['resolved'];
        $job->preflightjson = self::encode($snapshot);
        $job->timemodified = time();
        $DB->update_record('local_cp_job', $job);
        $target->resolvedplacementjson = self::encode($result['resolved']);
        $target->timemodified = time();
        $DB->update_record('local_cp_batch_target', $target);
        return $result;
    }

    /** Dispatcher helper: total queued+running Batch child jobs. */
    public static function active_child_count(?int $batchid = null): int {
        global $DB;
        $params = [
            'queued' => job_status::QUEUED,
            'running' => job_status::RUNNING,
            'paused' => batch_status::PAUSED,
            'cancelled' => batch_status::CANCELLED,
        ];
        // A running child continues safely when its Batch is paused/cancelled,
        // so it still consumes global capacity. Queued jobs on paused/cancelled
        // Batches are non-runnable and are normalized back to WAITING.
        $where = "j.batchid > 0
                  AND (
                        j.status = :running
                        OR (
                            j.status = :queued
                            AND b.status <> :paused
                            AND b.status <> :cancelled
                        )
                  )";
        if ($batchid !== null) {
            $where .= ' AND j.batchid = :batchid';
            $params['batchid'] = $batchid;
        }
        $sql = "SELECT COUNT(1)
                  FROM {local_cp_job} j
                  JOIN {local_cp_batch} b ON b.id = j.batchid
                 WHERE {$where}";
        return (int)$DB->count_records_sql($sql, $params);
    }

    /** Return oldest dispatchable Batches. */
    public static function dispatchable_batches(): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal([batch_status::QUEUED, batch_status::RUNNING, batch_status::PARTIAL], SQL_PARAMS_NAMED, 'bs');
        return array_values($DB->get_records_select('local_cp_batch', "status {$insql}", $params, 'id ASC'));
    }

    public static function next_waiting_job(int $batchid): ?\stdClass {
        global $DB;
        // A Batch Target may have an older WAITING attempt that was blocked by
        // a later re-preflight. Dispatch only the target's current latest
        // attempt while the target itself is still WAITING.
        $sql = "SELECT j.*
                  FROM {local_cp_job} j
                  JOIN {local_cp_batch_target} bt
                    ON bt.id = j.batchtargetid
                   AND bt.latestjobid = j.id
                 WHERE j.batchid = :batchid
                   AND j.status = :jobwaiting
                   AND bt.status = :targetwaiting
              ORDER BY j.id ASC";
        $records = $DB->get_records_sql($sql, [
            'batchid' => $batchid,
            'jobwaiting' => job_status::WAITING,
            'targetwaiting' => batch_status::TARGET_WAITING,
        ], 0, 1);
        return $records ? reset($records) : null;
    }

    private static function validate_publish_mode(string $sourcetype, string $mode, int $sourceid,
            int $programid, string $gradekey): void {
        $activitymodes = [job_service::MODE_ACTIVITY_GENERIC_PLACEMENT];
        $sectionmodes = [job_service::MODE_SECTION_GENERIC_PEER, job_service::MODE_SUBSECTION_TREE_PLACEMENT];
        if ($sourcetype === 'activity' && !in_array($mode, $activitymodes, true)) {
            throw new \moodle_exception('batchpublishmodeinvalid', 'local_coursepublisher');
        }
        if ($sourcetype === 'section' && !in_array($mode, $sectionmodes, true)) {
            throw new \moodle_exception('batchpublishmodeinvalid', 'local_coursepublisher');
        }
    }

    private static function validate_manifest_for_mode(string $sourcetype, int $sourceid, int $sourcecourseid,
            string $mode, array $manifest): void {
        if ($mode === job_service::MODE_ACTIVITY_GENERIC_PLACEMENT) {
            if (count($manifest) !== 1) {
                throw new \moodle_exception('invalidparameter');
            }
            $activity = content_publisher::validate_backup_capable_activity($sourceid, $sourcecourseid);
            if ((string)$activity['modname'] === 'subsection') {
                throw new \moodle_exception('genericactivitysubsectionuse_section', 'local_coursepublisher');
            }
            return;
        }
        if ($mode === job_service::MODE_SUBSECTION_TREE_PLACEMENT) {
            content_publisher::validate_source_delegated_subsection($sourceid, $sourcecourseid);
        } else if ($mode === job_service::MODE_SECTION_GENERIC_PEER) {
            content_publisher::validate_source_section_for_generic_copy($sourceid, $sourcecourseid);
        }
        foreach ($manifest as $row) {
            content_publisher::validate_backup_capable_activity((int)$row['sourcecmid'], $sourcecourseid);
        }
    }

    private static function selected_target_rows(int $programid, string $gradekey, array $targetids): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal($targetids, SQL_PARAMS_NAMED, 'tid');
        $params['programid'] = $programid;
        $params['gradekey'] = $gradekey;
        $sql = "SELECT t.id AS targetbindid, t.programid, t.schoolid, t.gradekey, t.courseid AS targetcourseid,
                       t.enabled AS targetenabled, s.regionid, s.enabled AS schoolenabled,
                       s.code AS schoolcode, s.name AS schoolname,
                       r.enabled AS regionenabled, r.code AS regioncode, r.name AS regionname,
                       c.id AS courseexists, c.fullname AS coursename, c.shortname
                  FROM {local_cp_target} t
             LEFT JOIN {local_cp_school} s ON s.id = t.schoolid
             LEFT JOIN {local_cp_region} r ON r.id = s.regionid
             LEFT JOIN {course} c ON c.id = t.courseid
                 WHERE t.id {$insql} AND t.programid = :programid AND t.gradekey = :gradekey";
        return $DB->get_records_sql($sql, $params);
    }

    private static function missing_target_snapshot(int $targetbindid): \stdClass {
        return (object)[
            'targetbindid' => $targetbindid, 'schoolid' => 0, 'regionid' => 0, 'targetcourseid' => 0,
            'targetenabled' => 0, 'schoolenabled' => 0, 'regionenabled' => 0, 'courseexists' => 0,
            'schoolcode' => '', 'schoolname' => '', 'regioncode' => '', 'regionname' => '',
            'coursename' => '', 'shortname' => '',
        ];
    }

    private static function preflight_one_target(\stdClass $program, string $gradekey, string $sourcetype, int $sourceid,
            int $sourcecourseid, string $sourcefingerprint, string $publishmode, array $logicalcontract,
            \stdClass $snapshot, ?\stdClass $route): array {
        $base = [
            'targetbindid' => (int)$snapshot->targetbindid,
            'regionid' => (int)($snapshot->regionid ?? 0),
            'schoolid' => (int)($snapshot->schoolid ?? 0),
            'targetcourseid' => (int)($snapshot->targetcourseid ?? 0),
            'regionname' => (string)($snapshot->regionname ?? ''),
            'regioncode' => (string)($snapshot->regioncode ?? ''),
            'schoolname' => (string)($snapshot->schoolname ?? ''),
            'schoolcode' => (string)($snapshot->schoolcode ?? ''),
            'coursename' => (string)($snapshot->coursename ?? ''),
            'shortname' => (string)($snapshot->shortname ?? ''),
        ];
        $snap = $base + [
            'targetenabled' => (int)($snapshot->targetenabled ?? 0),
            'schoolenabled' => (int)($snapshot->schoolenabled ?? 0),
            'regionenabled' => (int)($snapshot->regionenabled ?? 0),
            'preflighttime' => time(),
        ];
        if (empty($snapshot->targetenabled)) {
            return self::blocked_row($base, 'target_disabled', 'Target binding is missing or disabled.', $snap);
        }
        if (empty($snapshot->schoolenabled) || empty($snapshot->regionenabled)) {
            return self::blocked_row($base, 'topology_disabled', 'School or region is missing/disabled.', $snap);
        }
        if (empty($snapshot->courseexists) || (int)$snapshot->targetcourseid <= 0) {
            return self::blocked_row($base, 'target_course_missing', 'Target course no longer exists.', $snap);
        }
        if (!$route || (string)$route->status !== 'ready' || (int)$route->courseid !== (int)$snapshot->targetcourseid) {
            return self::blocked_row($base, 'route_not_ready', $route ? (string)$route->reason : 'Configured route is not ready.', $snap);
        }
        try {
            origin_map_service::assert_not_already_published($sourcecourseid, $sourcetype, $sourceid, (int)$snapshot->targetcourseid);
            $resolved = logical_placement_resolver::resolve_for_target($logicalcontract, (int)$snapshot->targetcourseid, $publishmode);
            if (($resolved['status'] ?? '') !== 'resolved') {
                return self::blocked_row($base, (string)($resolved['code'] ?? 'placement_blocked'),
                    (string)($resolved['reason'] ?? 'Logical placement is blocked.'), $snap, $resolved);
            }
        } catch (\moodle_exception $e) {
            return self::blocked_row($base, $e->errorcode ?: 'preflight_error', $e->getMessage(), $snap);
        }
        return $base + [
            'status' => 'ready', 'blockcode' => '', 'blockreason' => '', 'resolved' => $resolved, 'snapshot' => $snap,
        ];
    }

    private static function blocked_row(array $base, string $code, string $reason, array $snapshot,
            array $resolved = []): array {
        return $base + [
            'status' => 'blocked', 'blockcode' => $code, 'blockreason' => $reason,
            'resolved' => $resolved, 'snapshot' => $snapshot,
        ];
    }

    private static function assert_source_unchanged(\stdClass $batch): void {
        $current = job_service::source_fingerprint((string)$batch->sourcetype, (int)$batch->sourceid, (int)$batch->sourcecourseid);
        if (!hash_equals((string)$batch->sourcefingerprint, $current)) {
            throw new \moodle_exception('jobsourcechanged', 'local_coursepublisher');
        }
    }

    private static function revalidate_snapshot_target(\stdClass $batch, \stdClass $target, array $logicalcontract): array {
        global $DB;
        $snapshot = $DB->get_record('local_cp_target', ['id' => (int)$target->targetbindid], '*', IGNORE_MISSING);
        if (!$snapshot || (int)$snapshot->programid !== (int)$batch->programid || (string)$snapshot->gradekey !== (string)$batch->gradekey) {
            return self::blocked_row(self::target_base_from_record($target), 'target_binding_changed', 'Target binding is missing or changed.', []);
        }
        $school = $DB->get_record('local_cp_school', ['id' => (int)$snapshot->schoolid], '*', IGNORE_MISSING);
        $region = $school ? $DB->get_record('local_cp_region', ['id' => (int)$school->regionid], '*', IGNORE_MISSING) : false;
        $course = $DB->get_record('course', ['id' => (int)$snapshot->courseid], 'id,fullname,shortname', IGNORE_MISSING);
        $full = (object)[
            'targetbindid' => (int)$snapshot->id,
            'targetcourseid' => (int)$snapshot->courseid,
            'targetenabled' => (int)$snapshot->enabled,
            'schoolid' => $school ? (int)$school->id : 0,
            'schoolenabled' => $school ? (int)$school->enabled : 0,
            'regionid' => $region ? (int)$region->id : 0,
            'regionenabled' => $region ? (int)$region->enabled : 0,
            'courseexists' => $course ? 1 : 0,
            'schoolcode' => $school ? (string)$school->code : '', 'schoolname' => $school ? (string)$school->name : '',
            'regioncode' => $region ? (string)$region->code : '', 'regionname' => $region ? (string)$region->name : '',
            'coursename' => $course ? (string)$course->fullname : '', 'shortname' => $course ? (string)$course->shortname : '',
        ];
        $route = null;
        if ($school && $region && $snapshot->enabled && $school->enabled && $region->enabled) {
            $program = $DB->get_record('local_cp_program', ['id' => (int)$batch->programid, 'enabled' => 1], '*', MUST_EXIST);
            $health = service::evaluate_route($program, $school, (string)$batch->gradekey);
            $route = (object)[
                'status' => $health->status === 'ok' ? 'ready' : 'blocked',
                'reason' => $health->reason,
                'courseid' => $health->target ? (int)$health->target->courseid : 0,
            ];
        }
        return self::preflight_one_target(
            $DB->get_record('local_cp_program', ['id' => (int)$batch->programid], '*', MUST_EXIST),
            (string)$batch->gradekey, (string)$batch->sourcetype, (int)$batch->sourceid,
            (int)$batch->sourcecourseid, (string)$batch->sourcefingerprint, (string)$batch->publishmode,
            $logicalcontract, $full, $route
        );
    }

    private static function apply_target_preflight(\stdClass $target, array $result): void {
        global $DB;
        $target = $DB->get_record('local_cp_batch_target', ['id' => (int)$target->id], '*', MUST_EXIST);
        $ready = $result['status'] === 'ready';
        // Preserve the original preflightstatus forever; only execution status evolves.
        $target->status = $ready ? batch_status::TARGET_READY : batch_status::TARGET_BLOCKED;
        $target->blockcode = $ready ? null : (string)$result['blockcode'];
        $target->blockreason = $ready ? null : (string)$result['blockreason'];
        $target->preflightjson = self::encode($result['snapshot']);
        $target->resolvedplacementjson = $ready ? self::encode($result['resolved']) : null;
        $target->timemodified = time();
        $target->timefinished = $ready ? 0 : time();
        $DB->update_record('local_cp_batch_target', $target);
    }

    private static function refresh_waiting_targets(\stdClass $batch): void {
        global $DB;
        $logical = self::decode((string)$batch->placementjson);
        $targets = array_values($DB->get_records_select('local_cp_batch_target',
            'batchid = ? AND status IN (?, ?)', [(int)$batch->id, batch_status::TARGET_READY, batch_status::TARGET_WAITING], 'id ASC'));
        foreach ($targets as $target) {
            $result = self::revalidate_snapshot_target($batch, $target, $logical);
            if ($result['status'] !== 'ready') {
                $target->status = batch_status::TARGET_BLOCKED;
                $target->blockcode = $result['blockcode'];
                $target->blockreason = $result['blockreason'];
                $target->resolvedplacementjson = null;
                $target->timemodified = time();
                $target->timefinished = time();
                $DB->update_record('local_cp_batch_target', $target);
            } else {
                $target->resolvedplacementjson = self::encode($result['resolved']);
                $target->timemodified = time();
                $DB->update_record('local_cp_batch_target', $target);
            }
        }
    }

    private static function target_base_from_record(\stdClass $target): array {
        return [
            'targetbindid' => (int)$target->targetbindid, 'regionid' => (int)$target->regionid,
            'schoolid' => (int)$target->schoolid, 'targetcourseid' => (int)$target->targetcourseid,
            'regionname' => '', 'regioncode' => '', 'schoolname' => '', 'schoolcode' => '', 'coursename' => '', 'shortname' => '',
        ];
    }

    private static function encode($value): string {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function decode(string $value): array {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
