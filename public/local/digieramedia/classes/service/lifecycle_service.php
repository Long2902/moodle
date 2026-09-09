<?php

namespace local_digieramedia\service;

use context;
use local_digieramedia\r2\client_interface;
use local_digieramedia\r2\config;
use local_digieramedia\repository\reference_repository;

/**
 * Server-side lifecycle state machine for DIGIERA Media.
 */
final class lifecycle_service {
    private client_interface $client;
    private reference_repository $references;

    public function __construct(
        ?client_interface $client = null,
        ?reference_repository $references = null
    ) {
        if ($client === null) {
            $runtimeconfig = config::load();
            $runtimeconfig->require_credentials();
            $client = new \local_digieramedia\r2\sigv4_client($runtimeconfig);
        }
        $this->client = $client;
        $this->references = $references ?? new reference_repository();
    }

    public function trash(int $userid, context $context, string $mediauuid, string $reason = ''): array {
        global $DB;

        $media = $this->media_by_uuid($mediauuid);
        $this->require_trash_permission($userid, $context, $media);
        $lock = $this->lock_media((int)$media->id);

        try {
            $media = $DB->get_record('local_digieramedia_media', ['id' => (int)$media->id], '*', MUST_EXIST);
            if ((string)$media->status === 'TRASHED') {
                return $this->result($media, $this->references->count_live((int)$media->id));
            }
            if ((string)$media->status !== 'ACTIVE') {
                throw new \invalid_parameter_exception('Only ACTIVE DIGIERA Media can be moved to Trash.');
            }

            $now = time();
            $transaction = $DB->start_delegated_transaction();
            $previousvisibility = (string)$media->visibility;
            $trash = $DB->get_record('local_digieramedia_trash', ['mediaid' => (int)$media->id]);
            $trashrecord = (object)[
                'mediaid' => (int)$media->id,
                'deletedby' => $userid,
                'deletedat' => $now,
                'purgeafter' => 0,
                'reason' => clean_param($reason, PARAM_TEXT),
                'previousvisibility' => $previousvisibility,
            ];
            if ($trash) {
                $trashrecord->id = (int)$trash->id;
                $DB->update_record('local_digieramedia_trash', $trashrecord);
            } else {
                $DB->insert_record('local_digieramedia_trash', $trashrecord);
            }

            $DB->update_record('local_digieramedia_media', (object)[
                'id' => (int)$media->id,
                'status' => 'TRASHED',
                'visibility' => 'PRIVATE',
                'timemodified' => $now,
                'modifiedby' => $userid,
            ]);
            $this->audit(
                $userid,
                $context,
                'MEDIA_TRASH',
                (int)$media->id,
                ['status' => 'ACTIVE', 'visibility' => $previousvisibility],
                ['status' => 'TRASHED', 'visibility' => 'PRIVATE']
            );
            $transaction->allow_commit();

            $media->status = 'TRASHED';
            $media->visibility = 'PRIVATE';
            return $this->result($media, $this->references->count_live((int)$media->id));
        } finally {
            $lock->release();
        }
    }

    public function restore(int $userid, context $context, string $mediauuid): array {
        global $DB;

        require_capability('local/digieramedia:restore', $context, $userid);
        $media = $this->media_by_uuid($mediauuid);
        $lock = $this->lock_media((int)$media->id);

        try {
            $media = $DB->get_record('local_digieramedia_media', ['id' => (int)$media->id], '*', MUST_EXIST);
            if ((string)$media->status === 'ACTIVE') {
                return $this->result($media, $this->references->count_live((int)$media->id));
            }
            if ((string)$media->status !== 'TRASHED') {
                throw new \invalid_parameter_exception('Only TRASHED DIGIERA Media can be restored.');
            }

            $trash = $DB->get_record('local_digieramedia_trash', ['mediaid' => (int)$media->id], '*', MUST_EXIST);
            $now = time();
            $transaction = $DB->start_delegated_transaction();
            $visibility = trim((string)$trash->previousvisibility) ?: 'PRIVATE';
            $DB->update_record('local_digieramedia_media', (object)[
                'id' => (int)$media->id,
                'status' => 'ACTIVE',
                'visibility' => $visibility,
                'timemodified' => $now,
                'modifiedby' => $userid,
            ]);
            $DB->delete_records('local_digieramedia_trash', ['mediaid' => (int)$media->id]);
            $this->audit(
                $userid,
                $context,
                'MEDIA_RESTORE',
                (int)$media->id,
                ['status' => 'TRASHED', 'visibility' => 'PRIVATE'],
                ['status' => 'ACTIVE', 'visibility' => $visibility]
            );
            $transaction->allow_commit();

            $media->status = 'ACTIVE';
            $media->visibility = $visibility;
            return $this->result($media, $this->references->count_live((int)$media->id));
        } finally {
            $lock->release();
        }
    }

    public function purge(int $userid, context $context, string $mediauuid, bool $force = false): array {
        global $DB;

        require_capability('local/digieramedia:purge', $context, $userid);
        $media = $this->media_by_uuid($mediauuid);
        $lock = $this->lock_media((int)$media->id);

        try {
            $media = $DB->get_record('local_digieramedia_media', ['id' => (int)$media->id], '*', MUST_EXIST);
            if ((string)$media->status === 'PURGED') {
                return $this->result($media, 0, 0, 0);
            }
            if (!in_array((string)$media->status, ['TRASHED', 'PURGING'], true)) {
                throw new \invalid_parameter_exception('DIGIERA Media must be in Trash before permanent purge.');
            }

            $livecount = $this->references->count_live((int)$media->id);
            if ($livecount > 0 && !$force) {
                throw new \invalid_parameter_exception(
                    'DIGIERA Media has live references and cannot be purged without force.'
                );
            }

            $now = time();
            $unresolved = 0;
            if ((string)$media->status !== 'PURGING') {
                $transaction = $DB->start_delegated_transaction();
                $DB->update_record('local_digieramedia_media', (object)[
                    'id' => (int)$media->id,
                    'status' => 'PURGING',
                    'timemodified' => $now,
                    'modifiedby' => $userid,
                ]);
                if ($force && $livecount > 0) {
                    $beforeunresolved = $this->references->count_status((int)$media->id, 'UNRESOLVED');
                    $afterunresolved = $this->references->mark_live_unresolved((int)$media->id, $now);
                    $unresolved = max(0, $afterunresolved - $beforeunresolved);
                }
                $this->audit(
                    $userid,
                    $context,
                    $force ? 'MEDIA_FORCE_PURGE' : 'MEDIA_PURGE_START',
                    (int)$media->id,
                    ['status' => (string)$media->status, 'livereferences' => $livecount],
                    ['status' => 'PURGING', 'unresolvedreferences' => $unresolved]
                );
                $transaction->allow_commit();
                $media->status = 'PURGING';
            } else if ($force && $livecount > 0) {
                $beforeunresolved = $this->references->count_status((int)$media->id, 'UNRESOLVED');
                $afterunresolved = $this->references->mark_live_unresolved((int)$media->id, $now);
                $unresolved = max(0, $afterunresolved - $beforeunresolved);
            }

            $deletedversions = 0;
            $versions = $DB->get_records(
                'local_digieramedia_version',
                ['mediaid' => (int)$media->id],
                'versionno ASC, id ASC'
            );
            try {
                foreach ($versions as $version) {
                    if ((string)$version->status === 'PURGED') {
                        continue;
                    }
                    $this->client->delete_object((string)$version->bucket, (string)$version->objectkey);
                    $DB->update_record('local_digieramedia_version', (object)[
                        'id' => (int)$version->id,
                        'status' => 'PURGED',
                        'timepurged' => time(),
                    ]);
                    $deletedversions++;
                }
            } catch (\Throwable $e) {
                $this->audit(
                    $userid,
                    $context,
                    'MEDIA_PURGE_FAILED',
                    (int)$media->id,
                    ['status' => 'PURGING'],
                    ['status' => 'PURGING', 'deletedversions' => $deletedversions]
                );
                throw $e;
            }

            $finished = time();
            $DB->update_record('local_digieramedia_media', (object)[
                'id' => (int)$media->id,
                'status' => 'PURGED',
                'visibility' => 'PRIVATE',
                'timemodified' => $finished,
                'modifiedby' => $userid,
            ]);
            $this->audit(
                $userid,
                $context,
                'MEDIA_PURGE_COMPLETE',
                (int)$media->id,
                ['status' => 'PURGING'],
                ['status' => 'PURGED', 'deletedversions' => $deletedversions]
            );
            $media->status = 'PURGED';
            $media->visibility = 'PRIVATE';
            return $this->result($media, 0, $unresolved, $deletedversions);
        } finally {
            $lock->release();
        }
    }

    private function media_by_uuid(string $uuid): \stdClass {
        global $DB;
        return $DB->get_record('local_digieramedia_media', ['uuid' => $uuid], '*', MUST_EXIST);
    }

    private function require_trash_permission(int $userid, context $context, \stdClass $media): void {
        $own = (int)$media->owneruserid === $userid
            && has_capability('local/digieramedia:trashown', $context, $userid);
        if ($own || has_capability('local/digieramedia:trash', $context, $userid)) {
            return;
        }
        throw new \required_capability_exception($context, 'local/digieramedia:trashown', 'nopermissions', '');
    }

    private function lock_media(int $mediaid): \core\lock\lock {
        $factory = \core\lock\lock_config::get_lock_factory('local_digieramedia');
        $lock = $factory->get_lock('media-lifecycle-' . $mediaid, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout', 'local_digieramedia');
        }
        return $lock;
    }

    private function result(
        \stdClass $media,
        int $livecount,
        int $unresolvedreferences = 0,
        int $deletedversions = 0
    ): array {
        return [
            'mediauuid' => (string)$media->uuid,
            'status' => (string)$media->status,
            'visibility' => (string)$media->visibility,
            'livecount' => $livecount,
            'unresolvedreferences' => $unresolvedreferences,
            'deletedversions' => $deletedversions,
        ];
    }

    private function audit(
        int $userid,
        context $context,
        string $action,
        int $mediaid,
        array $before,
        array $after
    ): void {
        global $DB;

        if (!$DB->get_manager()->table_exists('local_digieramedia_audit')) {
            return;
        }
        $DB->insert_record('local_digieramedia_audit', (object)[
            'userid' => $userid,
            'action' => $action,
            'mediaid' => $mediaid,
            'versionid' => 0,
            'referenceid' => 0,
            'contextid' => (int)$context->id,
            'beforejson' => json_encode($before, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'afterjson' => json_encode($after, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'ip' => (string)(getremoteaddr() ?: ''),
            'useragent' => clean_param((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), PARAM_TEXT),
            'timecreated' => time(),
        ]);
    }
}
