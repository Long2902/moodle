<?php
namespace local_digieramedia\integration;

use local_digieramedia\restore\clone_mode;
use local_digieramedia\restore\clone_policy_scope;

/**
 * Optional bridge between Course Publisher and DIGIERA Media clone policy.
 *
 * Course Publisher checks class_exists() before using this. If DIGIERA Media
 * is not installed, Course Publisher operates exactly as 1.1.1 baseline.
 *
 * The bridge maps Course Publisher mode strings (shared_follow, shared_pinned,
 * independent) to DIGIERA clone policy scope and executes the callback within
 * that scope. Moodle backup and restore in Course Publisher execute synchronously
 * inside the same worker PHP process, so process-local scope is sufficient.
 */
final class coursepublisher_bridge {

    /** @var string[] Valid Course Publisher mode strings. */
    private const VALID_MODES = ['shared_follow', 'shared_pinned', 'independent'];

    /**
     * Execute a callback within a DIGIERA clone policy scope.
     *
     * @param string $cpmode Course Publisher mode string: shared_follow|shared_pinned|independent
     * @param string $operationid Stable operation ID for idempotency
     * @param int $targetcourseid Target course ID
     * @param callable $callback Callback to execute within scope
     * @return mixed Return value from callback
     * @throws \InvalidArgumentException If mode is not valid
     */
    public static function run(string $cpmode, string $operationid, int $targetcourseid, callable $callback): mixed {
        if (!in_array($cpmode, self::VALID_MODES, true)) {
            throw new \InvalidArgumentException(
                "Invalid Course Publisher DIGIERA mode: {$cpmode}"
            );
        }
        clone_policy_scope::push($cpmode, $operationid, $targetcourseid);
        try {
            return $callback();
        } finally {
            clone_policy_scope::pop();
        }
    }

    /**
     * Check whether DIGIERA Media integration is available.
     */
    public static function is_available(): bool {
        return class_exists(clone_policy_scope::class);
    }
}
