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
