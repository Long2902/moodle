<?php
namespace local_digieramedia\restore;

use local_digieramedia\db\transaction_runner_interface;
use local_digieramedia\lock\lock_manager_interface;
use local_digieramedia\repository\media_repository;
use local_digieramedia\repository\reference_repository;
use local_digieramedia\repository\version_repository;
use local_digieramedia\r2\client_interface;
use local_digieramedia\state\media_status;
use local_digieramedia\state\reference_status;
use local_digieramedia\state\version_status;

final class independent_copy_service {
    public function __construct(
        private media_repository $media,
        private version_repository $versions,
        private reference_repository $references,
        private client_interface $r2,
        private lock_manager_interface $locks,
        private transaction_runner_interface $tx,
    ) {}

    public function copy_reference(\stdClass $sourceReference, \context $targetcontext, string $restoreoperation, int $userid, int $occurrence = 0): array {
        if ($restoreoperation === '') {
            throw new \InvalidArgumentException('Restore operation id is required');
        }
        $sourceMedia = $this->media->get((int)$sourceReference->mediaid);
        if (($sourceMedia->status ?? '') === media_status::PURGED) {
            throw new \DomainException('Purged Media cannot be copied');
        }
        $sourceVersion = $this->effective_version($sourceReference, $sourceMedia);
        if (($sourceVersion->status ?? '') !== version_status::READY) {
            throw new \DomainException('Only READY source versions can be copied');
        }
        $keymaterial = implode('|', [
            $restoreoperation,
            (string)$targetcontext->id,
            strtolower((string)$sourceMedia->uuid),
            (string)$sourceVersion->id,
        ]);
        $targetMediaUuid = $this->deterministic_uuid('digiera-media|'.$keymaterial);
        $targetRefUuid = $this->deterministic_uuid('digiera-ref|'.$keymaterial.'|'.strtolower((string)$sourceReference->uuid).'|'.$occurrence);
        $lock = $this->locks->acquire('restore-copy:'.hash('sha256', $keymaterial), 30);
        try {
            $existingMedia = $this->media->find_by_uuid($targetMediaUuid);
            $existingRef = $this->references->get_by_uuid($targetRefUuid);
            if ($existingMedia && $existingRef) {
                $existingVersions = $this->versions->list_for_media((int)$existingMedia->id);
                if ($existingVersions) {
                    $targetVersion = $this->find_target_v1($existingVersions);
                    if ($targetVersion) {
                        return ['media'=>$existingMedia, 'version'=>$targetVersion, 'reference'=>$existingRef];
                    }
                }
            }

            $targetObjectKey = $this->target_object_key($targetcontext, $targetMediaUuid, $sourceVersion);
            $bucket = (string)$sourceVersion->bucket;
            $head = $this->r2->head_object($bucket, $targetObjectKey);
            if (empty($head['exists'])) {
                $this->r2->copy_object($bucket, (string)$sourceVersion->objectkey, $targetObjectKey);
                $head = $this->r2->head_object($bucket, $targetObjectKey);
            }
            if (empty($head['exists'])) {
                throw new \RuntimeException('Copied R2 object could not be verified');
            }
            if (isset($sourceVersion->filesize) && (int)$sourceVersion->filesize > 0 && isset($head['size']) && (int)$head['size'] !== (int)$sourceVersion->filesize) {
                throw new \RuntimeException('Copied R2 object size mismatch');
            }

            $metadata = $this->target_metadata($targetcontext);
            return $this->tx->run(function() use ($existingMedia, $targetMediaUuid, $targetRefUuid, $sourceMedia, $sourceVersion, $sourceReference, $targetObjectKey, $bucket, $head, $metadata, $userid): array {
                $targetMedia = $existingMedia ?: $this->media->find_by_uuid($targetMediaUuid);
                if (!$targetMedia) {
                    $targetMedia = $this->build_target_media($sourceMedia, $targetMediaUuid, $metadata, $userid);
                    $targetMedia->id = $this->media->insert($targetMedia);
                }

                $targetVersion = $this->find_target_v1($this->versions->list_for_media((int)$targetMedia->id));
                if (!$targetVersion) {
                    $targetVersion = (object)[
                        'mediaid'=>(int)$targetMedia->id,
                        'versionno'=>1,
                        'bucket'=>$bucket,
                        'objectkey'=>$targetObjectKey,
                        'originalfilename'=>(string)($sourceVersion->originalfilename ?? ''),
                        'displayfilename'=>(string)($sourceVersion->displayfilename ?? $sourceVersion->originalfilename ?? ''),
                        'filesize'=>(int)($head['size'] ?? $sourceVersion->filesize ?? 0),
                        'mimetype'=>(string)($head['mimetype'] ?? $sourceVersion->mimetype ?? ''),
                        'etag'=>(string)($head['etag'] ?? $sourceVersion->etag ?? ''),
                        'checksum_sha256'=>(string)($sourceVersion->checksum_sha256 ?? ''),
                        'status'=>version_status::READY,
                        'restoredfromversionid'=>0,
                        'createdby'=>$userid,
                        'timecreated'=>time(),
                    ];
                    $targetVersion->id = $this->versions->insert($targetVersion);
                    $targetMedia->currentversionid = (int)$targetVersion->id;
                    $targetMedia->timemodified = time();
                    $this->media->update($targetMedia);
                }

                $targetReference = $this->references->get_by_uuid($targetRefUuid);
                if (!$targetReference) {
                    $targetReference = clone $sourceReference;
                    unset($targetReference->id);
                    $targetReference->uuid = $targetRefUuid;
                    $targetReference->mediaid = (int)$targetMedia->id;
                    $targetReference->contextid = $metadata['contextid'];
                    $targetReference->courseid = $metadata['courseid'];
                    $targetReference->cmid = $metadata['cmid'];
                    $targetReference->component = 'restore';
                    $targetReference->entityid = 0;
                    $targetReference->fieldname = '';
                    $targetReference->status = reference_status::DRAFT;
                    $targetReference->createdby = $userid;
                    $targetReference->timecreated = time();
                    $targetReference->timemodified = time();
                    if (($sourceReference->versionmode ?? 'FOLLOW_CURRENT') === 'PINNED_VERSION') {
                        $targetReference->versionmode = 'PINNED_VERSION';
                        $targetReference->pinnedversionid = (int)$targetVersion->id;
                    } else {
                        $targetReference->versionmode = 'FOLLOW_CURRENT';
                        $targetReference->pinnedversionid = 0;
                    }
                    $targetReference->id = $this->references->insert($targetReference);
                }
                return ['media'=>$targetMedia, 'version'=>$targetVersion, 'reference'=>$targetReference];
            });
        } finally {
            $lock->release();
        }
    }

    private function build_target_media(object $source, string $uuid, array $metadata, int $userid): \stdClass {
        $target = (object)[
            'uuid'=>$uuid,
            'name'=>(string)($source->name ?? ''),
            'description'=>(string)($source->description ?? ''),
            'mediatype'=>(string)($source->mediatype ?? 'file'),
            'mimetype'=>(string)($source->mimetype ?? ''),
            'owneruserid'=>$userid,
            'origincontextid'=>$metadata['contextid'],
            'origincourseid'=>$metadata['courseid'],
            'originsectionid'=>0,
            'visibility'=>$metadata['courseid'] > 0 ? 'COURSE' : 'PRIVATE',
            'status'=>media_status::ACTIVE,
            'currentversionid'=>0,
            'createdby'=>$userid,
            'modifiedby'=>$userid,
            'timecreated'=>time(),
            'timemodified'=>time(),
        ];
        foreach (['tagsjson','metadatajson'] as $field) {
            if (property_exists($source, $field)) {
                $target->{$field} = $source->{$field};
            }
        }
        return $target;
    }

    private function effective_version(object $reference, object $media): \stdClass {
        $id = ($reference->versionmode ?? 'FOLLOW_CURRENT') === 'PINNED_VERSION'
            ? (int)($reference->pinnedversionid ?? 0)
            : (int)($media->currentversionid ?? 0);
        if ($id <= 0) {
            throw new \DomainException('Source reference has no effective version');
        }
        $version = $this->versions->get($id);
        if ((int)($version->mediaid ?? 0) !== (int)($media->id ?? 0)) {
            throw new \DomainException('Source effective version does not belong to Media');
        }
        return $version;
    }

    private function target_object_key(\context $context, string $mediauuid, object $sourceversion): string {
        $extension = pathinfo((string)($sourceversion->objectkey ?? ''), PATHINFO_EXTENSION);
        if ($extension === '') {
            $extension = pathinfo((string)($sourceversion->displayfilename ?? $sourceversion->originalfilename ?? ''), PATHINFO_EXTENSION);
        }
        $suffix = $extension !== '' ? '.'.strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $extension)) : '';
        return 'digiera/restores/'.(int)$context->id.'/'.$mediauuid.'/v1'.$suffix;
    }

    private function target_metadata(\context $context): array {
        $courseid = 0; $cmid = 0;
        if (($context->contextlevel ?? 0) === (defined('CONTEXT_COURSE') ? CONTEXT_COURSE : 50)) {
            $courseid = (int)($context->instanceid ?? 0);
        } elseif (method_exists($context,'get_course_context')) {
            $coursecontext = $context->get_course_context(false);
            if ($coursecontext) $courseid = (int)($coursecontext->instanceid ?? 0);
        }
        if (($context->contextlevel ?? 0) === (defined('CONTEXT_MODULE') ? CONTEXT_MODULE : 70)) $cmid = (int)($context->instanceid ?? 0);
        return ['contextid'=>(int)$context->id,'courseid'=>$courseid,'cmid'=>$cmid];
    }

    private function find_target_v1(array $versions): ?\stdClass {
        foreach ($versions as $version) {
            if ((int)($version->versionno ?? 0) === 1 && ($version->status ?? '') === version_status::READY) return $version;
        }
        return null;
    }

    private function deterministic_uuid(string $input): string {
        $hex = substr(hash('sha256',$input),0,32);
        $hex[12] = '5';
        $variant = hexdec($hex[16]);
        $hex[16] = dechex(($variant & 0x3) | 0x8);
        return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20,12);
    }
}
