<?php
namespace local_digieraoffice\privacy;

/**
 * Privacy provider for the shared ONLYOFFICE gateway.
 *
 * The gateway stores configuration only and does not persist user data.
 */
class provider implements \core_privacy\local\metadata\null_provider {
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
