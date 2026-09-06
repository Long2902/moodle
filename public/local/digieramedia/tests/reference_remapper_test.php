<?php
namespace local_digieramedia;

use local_digieramedia\repository\media_repository;
use local_digieramedia\repository\reference_repository;
use local_digieramedia\repository\version_repository;
use local_digieramedia\restore\clone_mode;
use local_digieramedia\restore\reference_remapper;

/** @covers \local_digieramedia\restore\reference_remapper */
final class reference_remapper_test extends \advanced_testcase {
    private function fixture(): array {
        $references = new class extends reference_repository {
            public array $records = [];
            public int $nextid = 100;
            public function get_by_uuid(string $uuid): ?\stdClass {
                return isset($this->records[$uuid]) ? clone $this->records[$uuid] : null;
            }
            public function insert(\stdClass $record): int {
                $record->id = $this->nextid++;
                $this->records[$record->uuid] = clone $record;
                return $record->id;
            }
        };
        $media = new class extends media_repository {
            public array $records = [];
            public function get(int $id): \stdClass {
                if (!isset($this->records[$id])) {
                    throw new \RuntimeException('missing media');
                }
                return clone $this->records[$id];
            }
        };
        $versions = new class extends version_repository {
            public array $records = [];
            public function get(int $id): \stdClass {
                if (!isset($this->records[$id])) {
                    throw new \RuntimeException('missing version');
                }
                return clone $this->records[$id];
            }
        };

        $media->records = [
            5 => (object)[
                'id' => 5, 'uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                'status' => 'ACTIVE', 'currentversionid' => 53,
            ],
            6 => (object)[
                'id' => 6, 'uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
                'status' => 'PURGED', 'currentversionid' => 61,
            ],
        ];
        $versions->records = [
            51 => (object)['id' => 51, 'mediaid' => 5, 'versionno' => 1, 'status' => 'READY'],
            53 => (object)['id' => 53, 'mediaid' => 5, 'versionno' => 3, 'status' => 'READY'],
            61 => (object)['id' => 61, 'mediaid' => 6, 'versionno' => 1, 'status' => 'PURGED'],
        ];
        $ids = [
            'follow' => '11111111-1111-4111-8111-111111111111',
            'pinned' => '22222222-2222-4222-8222-222222222222',
            'purged' => '33333333-3333-4333-8333-333333333333',
        ];
        $references->records = [
            $ids['follow'] => (object)[
                'id' => 11, 'uuid' => $ids['follow'], 'mediaid' => 5,
                'displayprofile' => 'pdf_full', 'versionmode' => 'FOLLOW_CURRENT',
                'pinnedversionid' => 0, 'status' => 'ACTIVE',
            ],
            $ids['pinned'] => (object)[
                'id' => 12, 'uuid' => $ids['pinned'], 'mediaid' => 5,
                'displayprofile' => 'pdf_compact', 'versionmode' => 'PINNED_VERSION',
                'pinnedversionid' => 51, 'status' => 'ACTIVE',
            ],
            $ids['purged'] => (object)[
                'id' => 13, 'uuid' => $ids['purged'], 'mediaid' => 6,
                'displayprofile' => 'pdf_full', 'versionmode' => 'FOLLOW_CURRENT',
                'pinnedversionid' => 0, 'status' => 'ACTIVE',
            ],
        ];
        return [$references, $media, $versions, $ids];
    }

    public function test_mode_a_creates_new_reference_for_same_media(): void {
        $this->resetAfterTest();
        [$references, $media, $versions, $ids] = $this->fixture();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $newuuid = 'aaaaaaaa-1111-4111-8111-111111111111';
        $service = new reference_remapper($references, $media, $versions, fn() => $newuuid);

        $result = $service->remap_content(
            "Before [[digiera-ref:{$ids['follow']}]] After",
            $context,
            clone_mode::SHARED_FOLLOW,
            99
        );

        $this->assertSame("Before [[digiera-ref:$newuuid]] After", $result['content']);
        $this->assertCount(1, $result['mappings']);
        $this->assertSame([], $result['unresolved']);
        $target = $references->records[$newuuid];
        $this->assertNotSame($ids['follow'], $target->uuid);
        $this->assertSame(5, (int)$target->mediaid);
        $this->assertSame('FOLLOW_CURRENT', $target->versionmode);
        $this->assertSame(0, (int)$target->pinnedversionid);
        $this->assertSame((int)$context->id, (int)$target->contextid);
        $this->assertSame((int)$course->id, (int)$target->courseid);
        $this->assertSame('DRAFT', $target->status);
    }

    public function test_mode_b_pins_each_source_effective_version(): void {
        $this->resetAfterTest();
        [$references, $media, $versions, $ids] = $this->fixture();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $queue = [
            'bbbbbbbb-2222-4222-8222-222222222222',
            'cccccccc-3333-4333-8333-333333333333',
        ];
        $service = new reference_remapper(
            $references,
            $media,
            $versions,
            function() use (&$queue): string { return array_shift($queue); }
        );

        $result = $service->remap_content(
            "[[digiera-ref:{$ids['follow']}]] and [[digiera-ref:{$ids['pinned']}]]",
            $context,
            clone_mode::SHARED_PINNED,
            99
        );

        $this->assertCount(2, $result['mappings']);
        $first = $references->records['bbbbbbbb-2222-4222-8222-222222222222'];
        $second = $references->records['cccccccc-3333-4333-8333-333333333333'];
        $this->assertSame('PINNED_VERSION', $first->versionmode);
        $this->assertSame(53, (int)$first->pinnedversionid);
        $this->assertSame('PINNED_VERSION', $second->versionmode);
        $this->assertSame(51, (int)$second->pinnedversionid);
    }

    public function test_purged_media_is_unresolved_and_source_uuid_is_not_reused(): void {
        $this->resetAfterTest();
        [$references, $media, $versions, $ids] = $this->fixture();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $newuuid = 'dddddddd-4444-4444-8444-444444444444';
        $service = new reference_remapper($references, $media, $versions, fn() => $newuuid);

        $result = $service->remap_content(
            "P [[digiera-ref:{$ids['purged']}]] Q",
            $context,
            clone_mode::SHARED_FOLLOW,
            99
        );

        $this->assertCount(1, $result['unresolved']);
        $this->assertSame('PURGED_MEDIA', $result['unresolved'][0]['reason']);
        $this->assertStringContainsString("[[digiera-ref:$newuuid]]", $result['content']);
        $this->assertArrayNotHasKey($newuuid, $references->records);
    }
}
