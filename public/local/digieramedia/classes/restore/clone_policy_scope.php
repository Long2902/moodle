<?php
namespace local_digieramedia\restore;

/**
 * Process-local scope for DIGIERA clone policy during backup/restore operations.
 *
 * Normal Moodle backup/restore defaults to SHARED_FOLLOW. Course Publisher
 * sets the active scope through coursepublisher_bridge::run() before invoking
 * Moodle Core backup/restore. Scope is stack-safe: nested calls preserve and
 * restore prior state.
 */
final class clone_policy_scope {
    /** @var string[] Stack of previous modes for nesting. */
    private static array $modestack = [];
    /** @var string[] Stack of previous operation IDs. */
    private static array $opstack = [];
    /** @var int[] Stack of previous target course IDs. */
    private static array $coursestack = [];

    /** @var string Current active clone mode. */
    private static string $mode = clone_mode::SHARED_FOLLOW;
    /** @var string Current operation ID for idempotency. */
    private static string $operationid = '';
    /** @var int Current target course ID. */
    private static int $targetcourseid = 0;

    /**
     * Push a new scope onto the stack and set active values.
     */
    public static function push(string $mode, string $operationid, int $targetcourseid): void {
        clone_mode::assert_valid($mode);
        self::$modestack[] = self::$mode;
        self::$opstack[] = self::$operationid;
        self::$coursestack[] = self::$targetcourseid;
        self::$mode = $mode;
        self::$operationid = $operationid;
        self::$targetcourseid = $targetcourseid;
    }

    /**
     * Pop the scope stack, restoring the previous state.
     */
    public static function pop(): void {
        self::$mode = array_pop(self::$modestack) ?? clone_mode::SHARED_FOLLOW;
        self::$operationid = array_pop(self::$opstack) ?? '';
        self::$targetcourseid = array_pop(self::$coursestack) ?? 0;
    }

    /**
     * Return the currently active clone mode string (uppercase DIGIERA constant).
     */
    public static function current_mode(): string {
        return strtoupper(self::$mode);
    }

    /**
     * Return the currently active operation ID for idempotency.
     */
    public static function current_operation_id(): string {
        if (self::$operationid === '') {
            return 'default-' . getmypid();
        }
        return self::$operationid;
    }

    /**
     * Return the currently active target course ID.
     */
    public static function current_target_courseid(): int {
        return self::$targetcourseid;
    }

    /**
     * Reset all state. Only for testing.
     */
    public static function reset(): void {
        self::$modestack = [];
        self::$opstack = [];
        self::$coursestack = [];
        self::$mode = clone_mode::SHARED_FOLLOW;
        self::$operationid = '';
        self::$targetcourseid = 0;
    }
}
