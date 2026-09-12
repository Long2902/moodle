<?php
namespace local_digieramedia\restore;

use local_digieramedia\marker\reference_parser;
use local_digieramedia\repository\media_repository;
use local_digieramedia\repository\reference_repository;
use local_digieramedia\repository\version_repository;
use local_digieramedia\state\media_status;
use local_digieramedia\state\reference_status;
use local_digieramedia\state\version_status;

final class reference_remapper {
    private \Closure $uuidfactory;

    public function __construct(
        private reference_repository $references,
        private media_repository $media,
        private version_repository $versions,
        ?callable $uuidfactory = null,
    ) {
        $this->uuidfactory = $uuidfactory ? \Closure::fromCallable($uuidfactory) : fn(): string => $this->uuidv4();
    }

    public function remap_content(string $content, \context $targetcontext, string $mode, int $userid): array {
        clone_mode::assert_valid($mode);
        if ($mode === clone_mode::INDEPENDENT) {
            throw new \InvalidArgumentException('Independent mode requires independent_copy_service');
        }
        $metadata = $this->target_metadata($targetcontext);
        $mappings = [];
        $unresolved = [];
        $replacements = [];

        foreach (reference_parser::extract($content) as $occurrence) {
            $sourceuuid = (string)$occurrence['uuid'];
            $targetuuid = ($this->uuidfactory)();
            $source = $this->references->get_by_uuid($sourceuuid);
            if (!$source) {
                $unresolved[] = [
                    'source_reference_uuid' => $sourceuuid,
                    'target_reference_uuid' => $targetuuid,
                    'reason' => 'REFERENCE_NOT_FOUND',
                    'media_uuid' => null,
                ];
                $replacements[] = $this->replacement($occurrence, $targetuuid);
                continue;
            }

            try {
                $media = $this->media->get((int)$source->mediaid);
            } catch (\Throwable $e) {
                $unresolved[] = [
                    'source_reference_uuid' => $sourceuuid,
                    'target_reference_uuid' => $targetuuid,
                    'reason' => 'MEDIA_NOT_FOUND',
                    'media_uuid' => null,
                ];
                $replacements[] = $this->replacement($occurrence, $targetuuid);
                continue;
            }
            if (($media->status ?? '') === media_status::PURGED) {
                $unresolved[] = [
                    'source_reference_uuid' => $sourceuuid,
                    'target_reference_uuid' => $targetuuid,
                    'reason' => 'PURGED_MEDIA',
                    'media_uuid' => (string)($media->uuid ?? ''),
                ];
                $replacements[] = $this->replacement($occurrence, $targetuuid);
                continue;
            }

            $effective = $this->effective_version($source, $media);
            if (($effective->status ?? version_status::READY) !== version_status::READY) {
                $unresolved[] = [
                    'source_reference_uuid' => $sourceuuid,
                    'target_reference_uuid' => $targetuuid,
                    'reason' => 'VERSION_NOT_READY',
                    'media_uuid' => (string)($media->uuid ?? ''),
                ];
                $replacements[] = $this->replacement($occurrence, $targetuuid);
                continue;
            }

            $target = (object)[
                'uuid' => $targetuuid,
                'mediaid' => (int)$media->id,
                'contextid' => $metadata['contextid'],
                'courseid' => $metadata['courseid'],
                'cmid' => $metadata['cmid'],
                'component' => 'restore',
                'entitytype' => 'restore',
                'entityid' => 0,
                'fieldname' => '',
                'displayprofile' => (string)($source->displayprofile ?? 'embedded'),
                'versionmode' => $mode === clone_mode::SHARED_PINNED ? 'PINNED_VERSION' : 'FOLLOW_CURRENT',
                'pinnedversionid' => $mode === clone_mode::SHARED_PINNED ? (int)$effective->id : 0,
                'status' => reference_status::DRAFT,
                'createdby' => $userid,
                'timecreated' => time(),
                'timemodified' => time(),
            ];
            foreach (['alttext', 'caption', 'optionsjson'] as $field) {
                if (property_exists($source, $field)) {
                    $target->{$field} = $source->{$field};
                }
            }
            $target->id = $this->references->insert($target);
            $mappings[] = [
                'source_reference_uuid' => $sourceuuid,
                'target_reference_uuid' => $targetuuid,
                'media_uuid' => (string)($media->uuid ?? ''),
                'mode' => $mode,
                'effective_version_id' => (int)$effective->id,
                'effective_version_no' => (int)($effective->versionno ?? 0),
                'target_reference_id' => (int)$target->id,
            ];
            $replacements[] = $this->replacement($occurrence, $targetuuid);
        }

        usort($replacements, fn(array $a, array $b): int => $b['offset'] <=> $a['offset']);
        foreach ($replacements as $replacement) {
            $content = substr_replace($content, $replacement['marker'], $replacement['offset'], $replacement['length']);
        }
        return ['content' => $content, 'mappings' => $mappings, 'unresolved' => $unresolved];
    }


    /**
     * Manifest-driven remap: works from portable manifest data without requiring
     * the source Reference row to exist in the local database.
     *
     * This is the production method for Moodle backup/restore and Course Publisher
     * integration. It resolves Media by UUID from the manifest, creates new target
     * References with correct clone semantics, and rewrites markers in content.
     *
     * @param string $content Content containing [[digiera-ref:UUID]] markers
     * @param array $manifestentries Array of manifest entry arrays from reference_collector
     * @param \context $targetcontext Target context for new references
     * @param string $mode Clone mode: SHARED_FOLLOW|SHARED_PINNED|INDEPENDENT
     * @param string $operationid Stable operation ID for idempotency
     * @param int $userid User performing the restore
     * @return array {content: string, mappings: array, unresolved: array}
     * @throws \DomainException If source media is in PURGING/PURGED state or version not READY
     */
    public function remap_manifest_content(
        string $content,
        array $manifestentries,
        \context $targetcontext,
        string $mode,
        string $operationid,
        int $userid,
    ): array {
        // Normalize mode from uppercase constants.
        $modelower = strtolower($mode);
        clone_mode::assert_valid($modelower);

        if ($modelower === clone_mode::INDEPENDENT) {
            throw new \InvalidArgumentException('Independent mode requires independent_copy_service; use remap_independent_content');
        }

        $metadata = $this->target_metadata($targetcontext);

        // Index manifest by source reference UUID for marker matching.
        $manifestbyref = [];
        foreach ($manifestentries as $entry) {
            $key = strtolower($entry['source_reference_uuid'] ?? '');
            if ($key !== '') {
                $manifestbyref[$key] = $entry;
            }
        }

        $mappings = [];
        $unresolved = [];
        $replacements = [];

        foreach (reference_parser::extract($content) as $occurrence) {
            $sourceuuid = (string)$occurrence['uuid'];
            $targetuuid = ($this->uuidfactory)();

            // Look up manifest entry for this marker.
            $entry = $manifestbyref[$sourceuuid] ?? null;
            if (!$entry) {
                // Marker in content but no manifest entry — could be a non-DIGIERA marker or orphan.
                $unresolved[] = [
                    'source_reference_uuid' => $sourceuuid,
                    'target_reference_uuid' => $targetuuid,
                    'reason' => 'NO_MANIFEST_ENTRY',
                    'media_uuid' => null,
                ];
                $replacements[] = $this->replacement($occurrence, $targetuuid);
                continue;
            }

            $mediauuid = $entry['media_uuid'] ?? '';
            if ($mediauuid === '') {
                $unresolved[] = [
                    'source_reference_uuid' => $sourceuuid,
                    'target_reference_uuid' => $targetuuid,
                    'reason' => 'MISSING_MEDIA_UUID',
                    'media_uuid' => null,
                ];
                $replacements[] = $this->replacement($occurrence, $targetuuid);
                continue;
            }

            // Resolve media by UUID from local database.
            $media = $this->media->find_by_uuid($mediauuid);
            if (!$media) {
                $unresolved[] = [
                    'source_reference_uuid' => $sourceuuid,
                    'target_reference_uuid' => $targetuuid,
                    'reason' => 'MEDIA_NOT_FOUND',
                    'media_uuid' => $mediauuid,
                ];
                $replacements[] = $this->replacement($occurrence, $targetuuid);
                continue;
            }

            // Check media status — ACTIVE and TRASHED are allowed, PURGING/PURGED rejected.
            $mediastatus = $media->status ?? '';
            if ($mediastatus === media_status::PURGING || $mediastatus === media_status::PURGED) {
                throw new \DomainException(
                    "Cannot restore reference to {$mediastatus} media {$mediauuid}"
                );
            }

            // Resolve effective version.
            $effectiveVersionNo = (int)($entry['effective_version_no'] ?? 0);
            $currentVersionId = (int)($media->currentversionid ?? 0);

            if ($modelower === clone_mode::SHARED_PINNED) {
                // Must find the exact version number from manifest.
                $version = $this->versions->get_by_media_and_versionno(
                    (int)$media->id,
                    $effectiveVersionNo
                );
                if (!$version) {
                    throw new \DomainException(
                        "Pinned version {$effectiveVersionNo} not found for media {$mediauuid}; no fallback to current"
                    );
                }
            } else {
                // SHARED_FOLLOW: use current version.
                if ($currentVersionId <= 0) {
                    throw new \DomainException(
                        "Media {$mediauuid} has no current version"
                    );
                }
                $version = $this->versions->get($currentVersionId);
            }

            // Verify version is READY.
            if (($version->status ?? '') !== version_status::READY) {
                throw new \DomainException(
                    "Effective version is not READY for media {$mediauuid}"
                );
            }

            // Create target reference.
            $targetref = (object)[
                'uuid' => $targetuuid,
                'mediaid' => (int)$media->id,
                'contextid' => $metadata['contextid'],
                'courseid' => $metadata['courseid'],
                'cmid' => $metadata['cmid'],
                'component' => 'restore',
                'entitytype' => $entry['adapter'] ?? 'restore',
                'entityid' => 0,
                'fieldname' => $entry['fieldname'] ?? '',
                'displayprofile' => $entry['displayprofile'] ?? 'embedded',
                'versionmode' => $modelower === clone_mode::SHARED_PINNED
                    ? 'PINNED_VERSION'
                    : 'FOLLOW_CURRENT',
                'pinnedversionid' => $modelower === clone_mode::SHARED_PINNED
                    ? (int)$version->id
                    : 0,
                'alttext' => $entry['alttext'] ?? '',
                'caption' => $entry['caption'] ?? '',
                'optionsjson' => $entry['optionsjson'] ?? '{}',
                'status' => reference_status::DRAFT,
                'createdby' => $userid,
                'timecreated' => time(),
                'timemodified' => time(),
            ];
            $targetref->id = $this->references->insert($targetref);

            $mappings[] = [
                'source_reference_uuid' => $sourceuuid,
                'target_reference_uuid' => $targetuuid,
                'target_reference_id' => (int)$targetref->id,
                'media_uuid' => $mediauuid,
                'mode' => $modelower,
                'effective_version_id' => (int)$version->id,
                'effective_version_no' => (int)($version->versionno ?? 0),
            ];
            $replacements[] = $this->replacement($occurrence, $targetuuid);
        }

        // Apply replacements in reverse offset order to preserve positions.
        usort($replacements, fn(array $a, array $b): int => $b['offset'] <=> $a['offset']);
        foreach ($replacements as $replacement) {
            $content = substr_replace(
                $content,
                $replacement['marker'],
                $replacement['offset'],
                $replacement['length']
            );
        }

        return ['content' => $content, 'mappings' => $mappings, 'unresolved' => $unresolved];
    }

    private function effective_version(object $reference, object $media): \stdClass {
        $id = ($reference->versionmode ?? 'FOLLOW_CURRENT') === 'PINNED_VERSION'
            ? (int)($reference->pinnedversionid ?? 0)
            : (int)($media->currentversionid ?? 0);
        if ($id <= 0) {
            throw new \UnexpectedValueException('Source reference has no effective version');
        }
        $version = $this->versions->get($id);
        if ((int)($version->mediaid ?? 0) !== (int)($media->id ?? 0)) {
            throw new \DomainException('Effective version does not belong to source Media');
        }
        return $version;
    }

    private function target_metadata(\context $context): array {
        $courseid = 0;
        $cmid = 0;
        if (($context->contextlevel ?? 0) === (defined('CONTEXT_COURSE') ? CONTEXT_COURSE : 50)) {
            $courseid = (int)($context->instanceid ?? 0);
        } elseif (method_exists($context, 'get_course_context')) {
            $coursecontext = $context->get_course_context(false);
            if ($coursecontext) {
                $courseid = (int)($coursecontext->instanceid ?? 0);
            }
        }
        if (($context->contextlevel ?? 0) === (defined('CONTEXT_MODULE') ? CONTEXT_MODULE : 70)) {
            $cmid = (int)($context->instanceid ?? 0);
        }
        return ['contextid' => (int)$context->id, 'courseid' => $courseid, 'cmid' => $cmid];
    }

    private function replacement(array $occurrence, string $targetuuid): array {
        return [
            'offset' => (int)$occurrence['offset'],
            'length' => (int)$occurrence['length'],
            'marker' => reference_parser::marker($targetuuid),
        ];
    }

    private function uuidv4(): string {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }
}
