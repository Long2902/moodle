<?php
namespace local_digieramedia;

use local_digieramedia\backup\reference_collector;
use local_digieramedia\backup\reference_manifest;
use local_digieramedia\repository\media_repository;
use local_digieramedia\repository\reference_repository;
use local_digieramedia\repository\version_repository;

/** @covers \local_digieramedia\backup\reference_collector
 *  @covers \local_digieramedia\backup\reference_manifest
 */
final class backup_manifest_test extends \advanced_testcase {
    private function fixture(): array {
        $references = new class extends reference_repository {
            public array $records = [];
            public function get_by_uuid(string $uuid): ?\stdClass {
                return isset($this->records[$uuid]) ? clone $this->records[$uuid] : null;
            }
        };
        $media = new class extends media_repository {
            public array $records = [];
            public function get(int $id): \stdClass {
                return clone $this->records[$id];
            }
        };
        $versions = new class extends version_repository {
            public array $records = [];
            public function get(int $id): \stdClass {
                return clone $this->records[$id];
            }
        };

        $media->records = [
            10 => (object)['id' => 10, 'uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'currentversionid' => 101],
            20 => (object)['id' => 20, 'uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'currentversionid' => 201],
        ];
        $versions->records = [
            101 => (object)['id' => 101, 'mediaid' => 10, 'versionno' => 3],
            102 => (object)['id' => 102, 'mediaid' => 10, 'versionno' => 1],
            201 => (object)['id' => 201, 'mediaid' => 20, 'versionno' => 7],
        ];
        $ids = [
            'follow' => '11111111-1111-4111-8111-111111111111',
            'pinned' => '22222222-2222-4222-8222-222222222222',
            'other' => '33333333-3333-4333-8333-333333333333',
        ];
        $references->records = [
            $ids['follow'] => (object)[
                'uuid' => $ids['follow'], 'mediaid' => 10, 'displayprofile' => 'pdf_full',
                'versionmode' => 'FOLLOW_CURRENT', 'pinnedversionid' => 0,
            ],
            $ids['pinned'] => (object)[
                'uuid' => $ids['pinned'], 'mediaid' => 10, 'displayprofile' => 'pdf_compact',
                'versionmode' => 'PINNED_VERSION', 'pinnedversionid' => 102,
            ],
            $ids['other'] => (object)[
                'uuid' => $ids['other'], 'mediaid' => 20, 'displayprofile' => 'file_link',
                'versionmode' => 'FOLLOW_CURRENT', 'pinnedversionid' => 0,
            ],
        ];

        return [new reference_collector($references, $media, $versions), $references, $media, $versions, $ids];
    }

    public function test_no_marker_content_returns_empty_manifest(): void {
        [$collector] = $this->fixture();
        $this->assertSame([], $collector->collect_from_text('<p>No marker</p>'));
    }

    public function test_one_marker_yields_portable_follow_current_manifest(): void {
        [$collector, , , , $ids] = $this->fixture();
        $result = $collector->collect_from_text("A [[digiera-ref:{$ids['follow']}]] B");

        $this->assertCount(1, $result);
        $this->assertSame($ids['follow'], $result[0]['source_reference_uuid']);
        $this->assertSame('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', $result[0]['media_uuid']);
        $this->assertSame('pdf_full', $result[0]['displayprofile']);
        $this->assertSame('FOLLOW_CURRENT', $result[0]['versionmode']);
        $this->assertNull($result[0]['pinned_version_no']);
        $this->assertSame(
            ['source_reference_uuid', 'media_uuid', 'displayprofile', 'versionmode', 'pinned_version_no'],
            array_keys($result[0])
        );
        $this->assertArrayNotHasKey('objectkey', $result[0]);
        $this->assertArrayNotHasKey('bucket', $result[0]);
    }

    public function test_same_media_used_twice_keeps_distinct_reference_entries(): void {
        [$collector, , , , $ids] = $this->fixture();
        $result = $collector->collect_from_text(
            "[[digiera-ref:{$ids['follow']}]][[digiera-ref:{$ids['pinned']}]]"
        );

        $this->assertCount(2, $result);
        $this->assertSame($result[0]['media_uuid'], $result[1]['media_uuid']);
        $this->assertNotSame($result[0]['source_reference_uuid'], $result[1]['source_reference_uuid']);
        $this->assertSame('PINNED_VERSION', $result[1]['versionmode']);
        $this->assertSame(1, $result[1]['pinned_version_no']);
    }

    public function test_multiple_media_preserve_marker_order(): void {
        [$collector, , , , $ids] = $this->fixture();
        $result = $collector->collect_from_text(
            "X [[digiera-ref:{$ids['other']}]] Y [[digiera-ref:{$ids['follow']}]]"
        );
        $this->assertSame([$ids['other'], $ids['follow']], array_column($result, 'source_reference_uuid'));
    }

    public function test_manifest_rejects_cross_media_version(): void {
        [, $references, $media, $versions, $ids] = $this->fixture();
        $this->expectException(\DomainException::class);
        reference_manifest::from_reference(
            $references->records[$ids['pinned']],
            $media->records[10],
            $versions->records[201]
        );
    }
}
