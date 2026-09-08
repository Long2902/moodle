<?php

namespace local_digieramedia\service;

use context;
use local_digieramedia\r2\client_interface;
use local_digieramedia\r2\config;

final class upload_finalize_service {
    private client_interface $client;
    private file_policy $policy;

    public function __construct(?client_interface $client = null, ?file_policy $policy = null) {
        $runtimeconfig = config::load();
        $runtimeconfig->require_credentials();
        $this->client = $client ?? new \local_digieramedia\r2\sigv4_client($runtimeconfig);
        $this->policy = $policy ?? new file_policy($runtimeconfig);
    }

    public function finalize(int $userid, context $context, string $sessionuuid): array {
        global $DB;

        require_capability('local/digieramedia:upload', $context, $userid);
        $factory = \core\lock\lock_config::get_lock_factory('local_digieramedia');
        $lock = $factory->get_lock('upload-finalize-' . $sessionuuid, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout', 'local_digieramedia');
        }

        try {
            $upload = $DB->get_record('local_digieramedia_upload', ['sessionuuid' => $sessionuuid], '*', MUST_EXIST);
            if ((int)$upload->userid !== $userid || (int)$upload->contextid !== (int)$context->id) {
                throw new \required_capability_exception($context, 'local/digieramedia:upload', 'nopermissions', '');
            }

            if ((int)$upload->committedmediaid > 0) {
                return $this->media_result((int)$upload->committedmediaid);
            }
            if (!in_array((string)$upload->status, ['AUTHORIZED', 'UPLOADING', 'UPLOADED', 'VERIFYING'], true)) {
                throw new \invalid_parameter_exception('Upload session is not finalizable.');
            }
            if (time() > ((int)$upload->expiresat + 3600)) {
                throw new \invalid_parameter_exception('Upload session expired before finalize.');
            }

            $file = $this->policy->validate(
                (string)$upload->originalfilename,
                (string)$upload->expectedmimetype,
                (int)$upload->expectedsize
            );
            $head = $this->client->head_object((string)$upload->targetbucket, (string)$upload->targetkey);
            if ((int)$head['contentlength'] !== (int)$upload->expectedsize) {
                throw new \invalid_parameter_exception('R2 object size does not match the authorized upload.');
            }

            $headtype = strtolower(trim(explode(';', (string)($head['contenttype'] ?? ''), 2)[0]));
            if ($headtype !== '' && $headtype !== strtolower((string)$upload->expectedmimetype)) {
                throw new \invalid_parameter_exception('R2 object Content-Type does not match the authorized upload.');
            }
            if (trim((string)($head['etag'] ?? '')) === '') {
                throw new \invalid_parameter_exception('R2 object ETag is missing.');
            }

            $transaction = $DB->start_delegated_transaction();
            $fresh = $DB->get_record('local_digieramedia_upload', ['id' => (int)$upload->id], '*', MUST_EXIST);
            if ((int)$fresh->committedmediaid > 0) {
                $transaction->allow_commit();
                return $this->media_result((int)$fresh->committedmediaid);
            }

            $now = time();
            $mediauuid = self::uuidv4();
            $visibility = (int)$upload->courseid > 0 ? 'COURSE' : 'PRIVATE';
            $mediaid = (int)$DB->insert_record('local_digieramedia_media', (object)[
                'uuid' => $mediauuid,
                'name' => $file['filename'],
                'description' => null,
                'mediatype' => $file['mediatype'],
                'mimetype' => $file['mimetype'],
                'owneruserid' => $userid,
                'origincontextid' => (int)$context->id,
                'origincourseid' => (int)$upload->courseid,
                'originsectionid' => (int)$upload->sectionid,
                'visibility' => $visibility,
                'status' => 'ACTIVE',
                'currentversionid' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
                'createdby' => $userid,
                'modifiedby' => $userid,
            ]);

            $versionid = (int)$DB->insert_record('local_digieramedia_version', (object)[
                'mediaid' => $mediaid,
                'versionno' => 1,
                'bucket' => (string)$upload->targetbucket,
                'objectkey' => (string)$upload->targetkey,
                'originalfilename' => $file['filename'],
                'displayfilename' => $file['filename'],
                'filesize' => (int)$upload->expectedsize,
                'mimetype' => $file['mimetype'],
                'etag' => (string)$head['etag'],
                'checksum_sha256' => null,
                'width' => null,
                'height' => null,
                'duration' => null,
                'status' => 'READY',
                'timecreated' => $now,
                'createdby' => $userid,
                'restoredfromversionid' => 0,
                'timepurged' => 0,
            ]);

            $DB->set_field('local_digieramedia_media', 'currentversionid', $versionid, ['id' => $mediaid]);
            $DB->update_record('local_digieramedia_upload', (object)[
                'id' => (int)$upload->id,
                'status' => 'READY',
                'bytesuploaded' => (int)$upload->expectedsize,
                'timemodified' => $now,
                'committedmediaid' => $mediaid,
            ]);
            $transaction->allow_commit();

            return $this->media_result($mediaid);
        } finally {
            $lock->release();
        }
    }

    private function media_result(int $mediaid): array {
        global $DB;
        $media = $DB->get_record('local_digieramedia_media', ['id' => $mediaid], '*', MUST_EXIST);
        $version = $DB->get_record('local_digieramedia_version', ['id' => (int)$media->currentversionid], '*', MUST_EXIST);
        return [
            'uuid' => (string)$media->uuid,
            'name' => (string)$media->name,
            'mediatype' => (string)$media->mediatype,
            'mimetype' => (string)$media->mimetype,
            'size' => (int)$version->filesize,
            'modified' => (int)$media->timemodified,
            'visibility' => (string)$media->visibility,
            'status' => (string)$media->status,
            'ready' => ((string)$version->status === 'READY'),
        ];
    }

    private static function uuidv4(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
