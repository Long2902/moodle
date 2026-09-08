<?php

namespace local_digieramedia\service;

use context;
use context_system;
use local_digieramedia\r2\config;
use local_digieramedia\r2\client_interface;

final class upload_session_service {
    private config $config;
    private client_interface $client;
    private file_policy $policy;

    public function __construct(?config $config = null, ?client_interface $client = null, ?file_policy $policy = null) {
        $this->config = $config ?? config::load();
        $this->config->require_credentials();
        $this->client = $client ?? new \local_digieramedia\r2\sigv4_client($this->config);
        $this->policy = $policy ?? new file_policy($this->config);
    }

    public function create(int $userid, context $context, string $filename, string $mimetype, int $filesize): array {
        global $DB;

        require_capability('local/digieramedia:upload', $context, $userid);
        $teacherupload = get_config('local_digieramedia', 'teacherupload');
        if ($teacherupload === '0' && !has_capability('local/digieramedia:manage', context_system::instance(), $userid)) {
            throw new \required_capability_exception($context, 'local/digieramedia:upload', 'nopermissions', '');
        }

        $file = $this->policy->validate($filename, $mimetype, $filesize);
        $courseid = 0;
        try {
            $coursecontext = $context->get_course_context(false);
            if ($coursecontext) {
                $courseid = (int)$coursecontext->instanceid;
            }
        } catch (\Throwable $e) {
            $courseid = 0;
        }

        $sessionuuid = self::uuidv4();
        $objectuuid = self::uuidv4();
        $namespace = $courseid > 0 ? 'courses/' . $courseid . '/general' : 'shared';
        $targetkey = 'digiera/' . $namespace . '/' . $file['mediatype'] . '/' . $objectuuid . '.' . $file['extension'];
        $now = time();
        $expiresat = $now + $this->config->presign_ttl();

        $record = (object)[
            'sessionuuid' => $sessionuuid,
            'userid' => $userid,
            'contextid' => (int)$context->id,
            'courseid' => $courseid,
            'sectionid' => 0,
            'originalfilename' => $file['filename'],
            'expectedsize' => $file['filesize'],
            'expectedmimetype' => $file['mimetype'],
            'targetbucket' => $this->config->bucket(),
            'targetkey' => $targetkey,
            'uploadtype' => 'SINGLE',
            'multipartuploadid' => null,
            'status' => 'AUTHORIZED',
            'bytesuploaded' => 0,
            'expiresat' => $expiresat,
            'timecreated' => $now,
            'timemodified' => $now,
            'committedmediaid' => 0,
        ];
        $DB->insert_record('local_digieramedia_upload', $record);

        $uploadurl = $this->client->presign_put(
            $record->targetbucket,
            $record->targetkey,
            $record->expectedmimetype,
            $this->config->presign_ttl()
        );

        return [
            'sessionuuid' => $sessionuuid,
            'uploadtype' => 'single',
            'uploadurl' => $uploadurl,
            'requiredheaders' => [
                ['name' => 'Content-Type', 'value' => $record->expectedmimetype],
            ],
            'expiresat' => $expiresat,
            'maxsinglebytes' => $this->config->single_put_max_bytes(),
        ];
    }

    private static function uuidv4(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
