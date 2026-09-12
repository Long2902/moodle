<?php
namespace local_coursepublisher\local;

/**
 * Optional DIGIERA Media integration for Course Publisher.
 *
 * This class wraps calls to \local_digieramedia\integration\coursepublisher_bridge
 * behind a class_exists() check. When DIGIERA Media is not installed, all methods
 * are no-ops and the Course Publisher operates exactly as 1.1.1 baseline.
 */
final class digiera_integration {

    /**
     * Check whether DIGIERA Media bridge is available.
     */
    public static function is_available(): bool {
        return class_exists(\local_digieramedia\integration\coursepublisher_bridge::class);
    }

    /**
     * Execute a callback within a DIGIERA clone policy scope.
     *
     * If DIGIERA Media is not installed, the callback executes without wrapping.
     *
     * @param string $digieramode DIGIERA mode string from job/batch record
     * @param string $operationid Stable operation ID for idempotency
     * @param int $targetcourseid Target course ID
     * @param callable $callback Callback to execute
     * @return mixed Return value from callback
     */
    public static function run_with_scope(
        string $digieramode,
        string $operationid,
        int $targetcourseid,
        callable $callback,
    ): mixed {
        if (!self::is_available()) {
            return $callback();
        }
        return \local_digieramedia\integration\coursepublisher_bridge::run(
            $digieramode,
            $operationid,
            $targetcourseid,
            $callback
        );
    }
}
