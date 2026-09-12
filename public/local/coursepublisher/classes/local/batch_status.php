<?php

namespace local_coursepublisher\local;

defined('MOODLE_INTERNAL') || die();

/** Durable Batch and Batch Target state constants. */
final class batch_status {
    public const DRAFT = 'draft';
    public const PREFLIGHT = 'preflight';
    public const QUEUED = 'queued';
    public const RUNNING = 'running';
    public const PAUSED = 'paused';
    public const PARTIAL = 'partial';
    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';

    public const TARGET_READY = 'ready';
    public const TARGET_BLOCKED = 'blocked';
    public const TARGET_WAITING = 'waiting';
    public const TARGET_QUEUED = 'queued';
    public const TARGET_RUNNING = 'running';
    public const TARGET_SUCCEEDED = 'succeeded';
    public const TARGET_FAILED = 'failed';
    public const TARGET_MANUAL_REVIEW = 'manual_review';
    public const TARGET_CANCELLED = 'cancelled';

    public static function is_dispatchable(string $status): bool {
        return in_array($status, [self::QUEUED, self::RUNNING, self::PARTIAL], true);
    }

    public static function is_terminal(string $status): bool {
        return in_array($status, [self::SUCCEEDED, self::FAILED, self::CANCELLED], true);
    }
}
