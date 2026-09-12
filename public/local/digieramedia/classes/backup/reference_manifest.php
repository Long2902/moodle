<?php
namespace local_digieramedia\backup;

use local_digieramedia\state\version_status;

/**
 * Builds one portable manifest row for a persisted DIGIERA marker occurrence.
 */
final class reference_manifest {
    public static function from_reference(
        \stdClass $reference,
        \stdClass $media,
        \stdClass $version,
        string $adapter,
        int $sourceentityid,
        string $fieldname,
        int $occurrence
    ): array {
        $referenceuuid = strtolower(trim((string)($reference->uuid ?? '')));
        $mediauuid = strtolower(trim((string)($media->uuid ?? '')));
        if ($referenceuuid === '' || $mediauuid === '') {
            throw new \InvalidArgumentException('Reference and Media UUIDs are required');
        }
        if ((int)($reference->mediaid ?? 0) !== (int)($media->id ?? 0)) {
            throw new \DomainException('Reference does not belong to Media');
        }
        if ((int)($version->mediaid ?? 0) !== (int)($media->id ?? 0)) {
            throw new \DomainException('Version does not belong to Media');
        }
        if (($version->status ?? version_status::READY) !== version_status::READY) {
            throw new \DomainException('Effective DIGIERA Media version is not READY');
        }

        $mode = strtoupper((string)($reference->versionmode ?? 'FOLLOW_CURRENT'));
        if (!in_array($mode, ['FOLLOW_CURRENT', 'PINNED_VERSION'], true)) {
            throw new \DomainException('Unsupported reference version mode');
        }
        $versionno = (int)($version->versionno ?? 0);
        if ($versionno <= 0) {
            throw new \DomainException('Effective DIGIERA Media version number is invalid');
        }
        if ($adapter === '' || $sourceentityid <= 0 || $fieldname === '' || $occurrence < 0) {
            throw new \InvalidArgumentException('Portable content identity is incomplete');
        }

        return [
            'source_reference_uuid' => $referenceuuid,
            'media_uuid' => $mediauuid,
            'displayprofile' => (string)($reference->displayprofile ?? 'embedded'),
            'source_versionmode' => $mode,
            'effective_version_no' => $versionno,
            'alttext' => $reference->alttext ?? null,
            'caption' => $reference->caption ?? null,
            'optionsjson' => $reference->optionsjson ?? null,
            'adapter' => $adapter,
            'source_entity_id' => $sourceentityid,
            'fieldname' => $fieldname,
            'occurrence' => $occurrence,
        ];
    }
}
