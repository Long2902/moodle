<?php
namespace local_digieramedia;

use local_digieramedia\db\transaction_runner_interface;
use local_digieramedia\lock\lock_handle_interface;
use local_digieramedia\lock\lock_manager_interface;
use local_digieramedia\r2\client_interface;
use local_digieramedia\repository\media_repository;
use local_digieramedia\repository\reference_repository;
use local_digieramedia\repository\version_repository;
use local_digieramedia\restore\independent_copy_service;

/** @covers \local_digieramedia\restore\independent_copy_service */
final class independent_copy_test extends \advanced_testcase {
    private function service_fixture(): array {
        $media = new class extends media_repository {
            public array $records = [];
            public int $nextid = 100;
            public function get(int $id): \stdClass { return clone $this->records[$id]; }
            public function find_by_uuid(string $uuid): ?\stdClass {
                foreach ($this->records as $record) {
                    if (($record->uuid ?? '') === $uuid) { return clone $record; }
                }
                return null;
            }
            public function insert(\stdClass $record): int {
                $record->id = $this->nextid++;
                $this->records[$record->id] = clone $record;
                return $record->id;
            }
            public function update(\stdClass $record): void { $this->records[$record->id] = clone $record; }
        };
        $versions = new class extends version_repository {
            public array $records = [];
            public int $nextid = 1000;
            public function get(int $id): \stdClass { return clone $this->records[$id]; }
            public function list_for_media(int $mediaid): array {
                return array_values(array_filter(
                    $this->records,
                    fn($record) => (int)$record->mediaid === $mediaid
                ));
            }
            public function insert(\stdClass $record): int {
                $record->id = $this->nextid++;
                $this->records[$record->id] = clone $record;
                return $record->id;
            }
        };
        $references = new class extends reference_repository {
            public array $records = [];
            public int $nextid = 2000;
            public function get_by_uuid(string $uuid): ?\stdClass {
                return isset($this->records[$uuid]) ? clone $this->records[$uuid] : null;
            }
            public function insert(\stdClass $record): int {
                $record->id = $this->nextid++;
                $this->records[$record->uuid] = clone $record;
                return $record->id;
            }
        };
        $r2 = new class implements client_interface {
            public array $copies = [];
            public array $objects = [];
            public function presign_put(string $bucket, string $key, string $contenttype, int $ttl): string { return 'url'; }
            public function create_multipart_upload(string $bucket, string $key, string $contenttype): string { return 'upload'; }
            public function presign_upload_part(string $bucket, string $key, string $uploadid, int $partnumber, int $ttl): string { return 'parturl'; }
            public function complete_multipart_upload(string $bucket, string $key, string $uploadid, array $parts): array { return []; }
            public function abort_multipart_upload(string $bucket, string $key, string $uploadid): void {}
            public function head_object(string $bucket, string $key): array {
                return isset($this->objects["$bucket/$key"])
                    ? ['exists' => true] + $this->objects["$bucket/$key"]
                    : ['exists' => false];
            }
            public function delete_object(string $bucket, string $key): void {}
            public function copy_object(string $bucket, string $sourcekey, string $targetkey, ?string $targetbucket = null): array {
                $targetbucket ??= $bucket;
                $this->copies[] = ["$bucket/$sourcekey", "$targetbucket/$targetkey"];
                $source = $this->objects["$bucket/$sourcekey"] ?? ['size' => 0, 'mimetype' => '', 'etag' => ''];
                $this->objects["$targetbucket/$targetkey"] = $source;
                return ['etag' => $source['etag'] ?? ''];
            }
        };
        $locks = new class implements lock_manager_interface {
            public array $keys = [];
            public function acquire(string $key, int $timeout = 10): lock_handle_interface {
                $this->keys[] = $key;
                return new class implements lock_handle_interface {
                    public function release(): void {}
                };
            }
        };
        $tx = new class implements transaction_runner_interface {
            public function run(callable $callback): mixed { return $callback(); }
        };

        $media->records[1] = (object)[
            'id' => 1, 'uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Source PDF', 'description' => 'D', 'mediatype' => 'pdf',
            'mimetype' => 'application/pdf', 'owneruserid' => 7, 'visibility' => 'COURSE',
            'status' => 'ACTIVE', 'currentversionid' => 13,
        ];
        $versions->records[11] = (object)[
            'id' => 11, 'mediaid' => 1, 'versionno' => 1, 'bucket' => 'bucket',
            'objectkey' => 'legacy/v1.pdf', 'originalfilename' => 'source.pdf',
            'displayfilename' => 'source.pdf', 'filesize' => 100, 'mimetype' => 'application/pdf',
            'etag' => 'e1', 'checksum_sha256' => 'c1', 'status' => 'READY',
        ];
        $versions->records[13] = (object)[
            'id' => 13, 'mediaid' => 1, 'versionno' => 3, 'bucket' => 'bucket',
            'objectkey' => 'legacy/v3.pdf', 'originalfilename' => 'source.pdf',
            'displayfilename' => 'source.pdf', 'filesize' => 300, 'mimetype' => 'application/pdf',
            'etag' => 'e3', 'checksum_sha256' => 'c3', 'status' => 'READY',
        ];
        $r2->objects['bucket/legacy/v1.pdf'] = ['size' => 100, 'mimetype' => 'application/pdf', 'etag' => 'e1'];
        $r2->objects['bucket/legacy/v3.pdf'] = ['size' => 300, 'mimetype' => 'application/pdf', 'etag' => 'e3'];

        $service = new independent_copy_service($media, $versions, $references, $r2, $locks, $tx);
        return [$service, $media, $versions, $references, $r2, $locks];
    }

    public function test_follow_current_creates_independent_media_and_is_idempotent(): void {
        $this->resetAfterTest();
        [$service, $media, $versions, $references, $r2, $locks] = $this->service_fixture();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $source = (object)[
            'id' => 21, 'uuid' => '11111111-1111-4111-8111-111111111111',
            'mediaid' => 1, 'displayprofile' => 'pdf_full', 'versionmode' => 'FOLLOW_CURRENT',
            'pinnedversionid' => 0, 'status' => 'ACTIVE', 'alttext' => '', 'caption' => '',
        ];

        $first = $service->copy_reference($source, $context, 'restore-op-1', 99, 0);
        $this->assertNotSame($media->records[1]->uuid, $first['media']->uuid);
        $this->assertNotSame(1, (int)$first['media']->id);
        $this->assertSame(1, (int)$first['version']->versionno);
        $this->assertNotSame('legacy/v3.pdf', $first['version']->objectkey);
        $this->assertSame('bucket/legacy/v3.pdf', $r2->copies[0][0]);
        $this->assertSame((int)$first['media']->id, (int)$first['reference']->mediaid);
        $this->assertNotSame($source->uuid, $first['reference']->uuid);
        $this->assertSame('FOLLOW_CURRENT', $first['reference']->versionmode);
        $this->assertSame((int)$context->id, (int)$first['reference']->contextid);
        $this->assertSame((int)$course->id, (int)$first['reference']->courseid);
        $this->assertStringStartsWith('restore-copy:', $locks->keys[0]);

        $counts = [count($media->records), count($versions->records), count($references->records), count($r2->copies)];
        $again = $service->copy_reference($source, $context, 'restore-op-1', 99, 0);
        $this->assertSame($counts, [count($media->records), count($versions->records), count($references->records), count($r2->copies)]);
        $this->assertSame($first['media']->uuid, $again['media']->uuid);
        $this->assertSame($first['reference']->uuid, $again['reference']->uuid);
    }

    public function test_pinned_source_copies_effective_pinned_binary(): void {
        $this->resetAfterTest();
        [$service, , , , $r2] = $this->service_fixture();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $source = (object)[
            'id' => 22, 'uuid' => '22222222-2222-4222-8222-222222222222',
            'mediaid' => 1, 'displayprofile' => 'pdf_compact', 'versionmode' => 'PINNED_VERSION',
            'pinnedversionid' => 11, 'status' => 'ACTIVE',
        ];

        $result = $service->copy_reference($source, $context, 'restore-op-2', 99, 0);
        $lastcopy = end($r2->copies);
        $this->assertSame('bucket/legacy/v1.pdf', $lastcopy[0]);
        $this->assertSame(100, (int)$result['version']->filesize);
        $this->assertSame('PINNED_VERSION', $result['reference']->versionmode);
        $this->assertSame((int)$result['version']->id, (int)$result['reference']->pinnedversionid);
    }
}
