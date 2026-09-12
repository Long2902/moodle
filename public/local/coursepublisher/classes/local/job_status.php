<?php

namespace local_coursepublisher\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Durable state constants for CASE 2 publish jobs and items.
 */
final class job_status {
    public const DRAFT = 'draft';
    public const PREFLIGHT_FAILED = 'preflight_failed';
    public const READY = 'ready';
    public const WAITING = 'waiting';
    public const QUEUED = 'queued';
    public const RUNNING = 'running';
    public const PARTIAL = 'partial';
    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';
    public const MANUAL_REVIEW = 'manual_review';
    public const CANCELLED = 'cancelled';

    public const ITEM_PENDING = 'pending';
    public const ITEM_SKIPPED = 'skipped';
    public const ITEM_RUNNING = 'running';
    public const ITEM_SUCCEEDED = 'succeeded';
    public const ITEM_FAILED = 'failed';

    /**
     * States which must not be re-executed by a queued task.
     */
    public static function is_nonretryable_terminal(string $status): bool {
        return in_array($status, [self::PREFLIGHT_FAILED, self::SUCCEEDED, self::CANCELLED, self::MANUAL_REVIEW], true);
    }
}
