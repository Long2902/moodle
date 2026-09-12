<?php
namespace local_digieramedia\backup;

/**
 * Immutable description of one persisted content field containing DIGIERA markers.
 */
final class content_record {
    public function __construct(
        public readonly string $adapter,
        public readonly int $sourceentityid,
        public readonly string $fieldname,
        public readonly string $content,
    ) {}
}
