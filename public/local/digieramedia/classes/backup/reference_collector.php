<?php
namespace local_digieramedia\backup;

use local_digieramedia\marker\reference_parser;
use local_digieramedia\repository\media_repository;
use local_digieramedia\repository\reference_repository;
use local_digieramedia\repository\version_repository;

final class reference_collector {
    public function __construct(
        private reference_repository $references,
        private media_repository $media,
        private version_repository $versions,
    ) {}

    public function collect_from_text(string $content): array {
        $result = [];
        foreach (reference_parser::extract($content) as $marker) {
            $reference = $this->references->get_by_uuid((string)$marker['uuid']);
            if (!$reference) {
                throw new \UnexpectedValueException('DIGIERA Media reference marker cannot be resolved');
            }
            $media = $this->media->get((int)$reference->mediaid);
            $versionid = ($reference->versionmode ?? 'FOLLOW_CURRENT') === 'PINNED_VERSION'
                ? (int)($reference->pinnedversionid ?? 0)
                : (int)($media->currentversionid ?? 0);
            if ($versionid <= 0) {
                throw new \UnexpectedValueException('DIGIERA Media reference has no effective version');
            }
            $version = $this->versions->get($versionid);
            $result[] = reference_manifest::from_reference($reference, $media, $version);
        }
        return $result;
    }
}
