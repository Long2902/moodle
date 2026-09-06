<?php
namespace local_digieramedia\backup;

final class reference_manifest {
    public static function from_reference(\stdClass $reference, \stdClass $media, \stdClass $version): array {
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
        $mode = strtoupper((string)($reference->versionmode ?? 'FOLLOW_CURRENT'));
        if (!in_array($mode, ['FOLLOW_CURRENT', 'PINNED_VERSION'], true)) {
            throw new \DomainException('Unsupported reference version mode');
        }
        return [
            'source_reference_uuid' => $referenceuuid,
            'media_uuid' => $mediauuid,
            'displayprofile' => (string)($reference->displayprofile ?? 'embedded'),
            'versionmode' => $mode,
            'pinned_version_no' => $mode === 'PINNED_VERSION' ? (int)($version->versionno ?? 0) : null,
        ];
    }
}
