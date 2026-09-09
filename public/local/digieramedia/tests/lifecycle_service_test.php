<?php

namespace local_digieramedia;

use local_digieramedia\r2\client_interface;
use local_digieramedia\service\lifecycle_service;

final class lifecycle_fake_r2_client implements client_interface {
    public array $deleted = [];
    public int $failondelete = 0;
    private int $deletecount = 0;

    public function head_object(string $bucket, string $key): array { return []; }
    public function presign_put(string $bucket, string $key, string $contenttype, int $ttl): string { return ''; }
    public function create_multipart_upload(string $bucket, string $key, string $contenttype): string { return ''; }
    public function presign_upload_part(string $bucket, string $key, string $uploadid, int $partnumber, int $ttl): string { return ''; }
    public function complete_multipart_upload(string $bucket, string $key, string $uploadid, array $parts): array { return []; }
    public function abort_multipart_upload(string $bucket, string $key, string $uploadid): void {}
    public function copy_object(string $sourcebucket, string $sourcekey, string $targetbucket, string $targetkey): array { return []; }

    public function delete_object(string $bucket, string $key): void {
        $this->deletecount++;
        if ($this->failondelete > 0 && $this->deletecount === $this->failondelete) {
            throw new \runtime_exception('synthetic delete failure');
        }
        $this->deleted[] = $bucket . '/' . $key;
    }
}

final class lifecycle_service_test extends \advanced_testcase {
    public function test_trash_and_restore_preserve_identity_and_physical_object(): void {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $context = \context_system::instance();
        [$mediaid, $versionids] = $this->seed_media($context->id, $USER->id, 'SHARED', 1);
        $uuid = (string)$DB->get_field('local_digieramedia_media', 'uuid', ['id' => $mediaid]);
        $client = new lifecycle_fake_r2_client();
        $service = new lifecycle_service($client);

        $result = $service->trash($USER->id, $context, $uuid, 'RC lifecycle test');

        $media = $DB->get_record('local_digieramedia_media', ['id' => $mediaid], '*', MUST_EXIST);
        $trash = $DB->get_record('local_digieramedia_trash', ['mediaid' => $mediaid], '*', MUST_EXIST);
        $this->assertSame('TRASHED', $media->status);
        $this->assertSame('PRIVATE', $media->visibility);
        $this->assertSame('SHARED', $trash->previousvisibility);
        $this->assertSame($versionids[0], (int)$media->currentversionid);
        $this->assertSame([], $client->deleted, 'Soft trash must never delete R2 objects.');
        $this->assertSame('TRASHED', $result['status']);

        $restored = $service->restore($USER->id, $context, $uuid);
        $media = $DB->get_record('local_digieramedia_media', ['id' => $mediaid], '*', MUST_EXIST);
        $this->assertSame('ACTIVE', $media->status);
        $this->assertSame('SHARED', $media->visibility);
        $this->assertSame($versionids[0], (int)$media->currentversionid);
        $this->assertFalse($DB->record_exists('local_digieramedia_trash', ['mediaid' => $mediaid]));
        $this->assertSame('ACTIVE', $restored['status']);
    }

    public function test_normal_purge_rejects_live_references_without_deleting_r2(): void {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $context = \context_system::instance();
        [$mediaid] = $this->seed_media($context->id, $USER->id, 'PRIVATE', 1);
        $uuid = (string)$DB->get_field('local_digieramedia_media', 'uuid', ['id' => $mediaid]);
        $this->insert_reference($mediaid, $context->id, $USER->id, 'ACTIVE');
        $client = new lifecycle_fake_r2_client();
        $service = new lifecycle_service($client);
        $service->trash($USER->id, $context, $uuid);

        try {
            $service->purge($USER->id, $context, $uuid, false);
            $this->fail('Normal purge must reject live references.');
        } catch (\moodle_exception|\invalid_parameter_exception $e) {
            $this->assertStringContainsString('reference', strtolower($e->getMessage()));
        }

        $this->assertSame([], $client->deleted);
        $this->assertSame('TRASHED', $DB->get_field('local_digieramedia_media', 'status', ['id' => $mediaid]));
    }

    public function test_zero_reference_purge_deletes_all_versions_and_is_idempotent(): void {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $context = \context_system::instance();
        [$mediaid, $versionids] = $this->seed_media($context->id, $USER->id, 'COURSE', 3);
        $uuid = (string)$DB->get_field('local_digieramedia_media', 'uuid', ['id' => $mediaid]);
        $client = new lifecycle_fake_r2_client();
        $service = new lifecycle_service($client);
        $service->trash($USER->id, $context, $uuid);

        $result = $service->purge($USER->id, $context, $uuid, false);
        $this->assertSame('PURGED', $result['status']);
        $this->assertCount(3, $client->deleted);
        $this->assertSame('PURGED', $DB->get_field('local_digieramedia_media', 'status', ['id' => $mediaid]));
        foreach ($versionids as $versionid) {
            $version = $DB->get_record('local_digieramedia_version', ['id' => $versionid], '*', MUST_EXIST);
            $this->assertSame('PURGED', $version->status);
            $this->assertGreaterThan(0, (int)$version->timepurged);
        }

        $again = $service->purge($USER->id, $context, $uuid, false);
        $this->assertSame('PURGED', $again['status']);
        $this->assertCount(3, $client->deleted, 'Retry after completion must not delete versions twice.');
    }

    public function test_partial_delete_failure_is_retryable_and_skips_purged_versions(): void {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $context = \context_system::instance();
        [$mediaid, $versionids] = $this->seed_media($context->id, $USER->id, 'PRIVATE', 3);
        $uuid = (string)$DB->get_field('local_digieramedia_media', 'uuid', ['id' => $mediaid]);
        $client = new lifecycle_fake_r2_client();
        $client->failondelete = 2;
        $service = new lifecycle_service($client);
        $service->trash($USER->id, $context, $uuid);

        try {
            $service->purge($USER->id, $context, $uuid, false);
            $this->fail('Synthetic R2 failure must propagate.');
        } catch (\runtime_exception $e) {
            $this->assertStringContainsString('synthetic delete failure', $e->getMessage());
        }
        $this->assertSame('PURGING', $DB->get_field('local_digieramedia_media', 'status', ['id' => $mediaid]));
        $this->assertSame('PURGED', $DB->get_field('local_digieramedia_version', 'status', ['id' => $versionids[0]]));
        $this->assertSame('READY', $DB->get_field('local_digieramedia_version', 'status', ['id' => $versionids[1]]));

        $client->failondelete = 0;
        $result = $service->purge($USER->id, $context, $uuid, false);
        $this->assertSame('PURGED', $result['status']);
        $this->assertCount(3, $client->deleted, 'One successful first attempt + two remaining retry deletes expected.');
    }

    public function test_force_purge_marks_live_references_unresolved_before_completion(): void {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $context = \context_system::instance();
        [$mediaid] = $this->seed_media($context->id, $USER->id, 'PRIVATE', 1);
        $uuid = (string)$DB->get_field('local_digieramedia_media', 'uuid', ['id' => $mediaid]);
        $referenceid = $this->insert_reference($mediaid, $context->id, $USER->id, 'DRAFT');
        $client = new lifecycle_fake_r2_client();
        $service = new lifecycle_service($client);
        $service->trash($USER->id, $context, $uuid);

        $result = $service->purge($USER->id, $context, $uuid, true);

        $this->assertSame('PURGED', $result['status']);
        $this->assertSame(1, $result['unresolvedreferences']);
        $this->assertSame('UNRESOLVED', $DB->get_field('local_digieramedia_reference', 'status', ['id' => $referenceid]));
    }

    private function seed_media(int $contextid, int $userid, string $visibility, int $versions): array {
        global $DB;
        $now = time();
        $uuid = sprintf('aaaaaaaa-aaaa-4aaa-8aaa-%012d', random_int(1, 999999));
        $mediaid = (int)$DB->insert_record('local_digieramedia_media', (object)[
            'uuid' => $uuid,
            'name' => 'Lifecycle test media',
            'description' => '',
            'mediatype' => 'pdf',
            'mimetype' => 'application/pdf',
            'owneruserid' => $userid,
            'origincontextid' => $contextid,
            'origincourseid' => 0,
            'originsectionid' => 0,
            'visibility' => $visibility,
            'status' => 'ACTIVE',
            'currentversionid' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
            'createdby' => $userid,
            'modifiedby' => $userid,
        ]);
        $versionids = [];
        for ($i = 1; $i <= $versions; $i++) {
            $versionids[] = (int)$DB->insert_record('local_digieramedia_version', (object)[
                'mediaid' => $mediaid,
                'versionno' => $i,
                'bucket' => 'digiera',
                'objectkey' => 'digiera/tests/lifecycle-v' . $i . '.pdf',
                'originalfilename' => 'lifecycle-v' . $i . '.pdf',
                'displayfilename' => 'lifecycle-v' . $i . '.pdf',
                'filesize' => 1000 + $i,
                'mimetype' => 'application/pdf',
                'etag' => 'etag-' . $i,
                'checksum_sha256' => str_repeat((string)$i, 64),
                'status' => 'READY',
                'timecreated' => $now + $i,
                'createdby' => $userid,
                'restoredfromversionid' => 0,
                'timepurged' => 0,
            ]);
        }
        $DB->set_field('local_digieramedia_media', 'currentversionid', end($versionids), ['id' => $mediaid]);
        return [$mediaid, $versionids];
    }

    private function insert_reference(int $mediaid, int $contextid, int $userid, string $status): int {
        global $DB;
        $now = time();
        return (int)$DB->insert_record('local_digieramedia_reference', (object)[
            'uuid' => sprintf('bbbbbbbb-bbbb-4bbb-8bbb-%012d', random_int(1, 999999)),
            'mediaid' => $mediaid,
            'contextid' => $contextid,
            'courseid' => 0,
            'cmid' => 0,
            'component' => 'tiny_digieramedia',
            'entitytype' => 'editor',
            'entityid' => 0,
            'fieldname' => 'content',
            'displayprofile' => 'embedded',
            'versionmode' => 'FOLLOW_CURRENT',
            'pinnedversionid' => 0,
            'status' => $status,
            'createdby' => $userid,
            'alttext' => '',
            'caption' => '',
            'optionsjson' => '{}',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
}
