<?php
namespace local_digieramedia\backup;

/**
 * Reads persisted Moodle activity content that can contain DIGIERA markers.
 */
interface content_adapter_interface {
    public function supports(string $modname): bool;

    /**
     * @return content_record[]
     */
    public function source_records(int $instanceid): array;
}
