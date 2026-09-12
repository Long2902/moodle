<?php
namespace local_digieramedia;

use local_digieramedia\backup\content_record;
use local_digieramedia\backup\reference_collector;
use local_digieramedia\backup\reference_manifest;
use local_digieramedia\repository\media_repository;
use local_digieramedia\repository\reference_repository;
use local_digieramedia\repository\version_repository;

/**
 * @covers \local_digieramedia\backup\reference_collector
 * @covers \local_digieramedia\backup\reference_manifest
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
            10 => (object)[
                'id' => 10,
                'uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                'status' => 'ACTIVE',
                'currentversionid' => 101,
            ],
            20 => (object)[
                'id' => 20,
                'uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
                'status' => 'ACTIVE',
                'currentversionid' => 201,
            ],
        ];
        $versions->records = [
            101 => (object)[
                'id' => 101, 'mediaid' => 10, 'versionno' => 3,
                'status' => 'READY', 'bucket' => 'must-not-export', 'objectkey' => 'secret/v3.pdf',
            ],
            102 => (object)[
                'id' => 102, 'mediaid' => 10, 'versionno' => 1,
                'status' => 'READY', 'bucket' => 'must-not-export', 'objectkey' => 'secret/v1.pdf',
            ],
            201 => (object)[
                'id' => 201, 'mediaid' => 20, 'versionno' => 7,
                'status' => 'READY', 'bucket' => 'must-not-export', 'objectkey' => 'secret/v7.bin',
            ],
        ];
        $ids = [
            'follow' => '11111111-1111-4111-8111-111111111111',
            'pinned' => '22222222-2222-4222-8222-222222222222',
            'other' => '33333333-3333-4333-8333-333333333333',
            'unused' => '44444444-4444-4444-8444-444444444444',
        ];
        $references->records = [
            $ids['follow'] => (object)[
                'uuid' => $ids['follow'], 'mediaid' => 10, 'displayprofile' => 'pdf_full',
                'versionmode' => 'FOLLOW_CURRENT', 'pinnedversionid' => 0, 'status' => 'DRAFT',
                'alttext' => 'Alt follow', 'caption' => 'Caption follow', 'optionsjson' => '{"fit":"page"}',
            ],
            $ids['pinned'] => (object)[
                'uuid' => $ids['pinned'], 'mediaid' => 10, 'displayprofile' => 'pdf_compact',
                'versionmode' => 'PINNED_VERSION', 'pinnedversionid' => 102, 'status' => 'ACTIVE',
                'alttext' => '', 'caption' => 'Pinned caption', 'optionsjson' => '{}',
            ],
            $ids['other'] => (object)[
                'uuid' => $ids['other'], 'mediaid' => 20, 'displayprofile' => 'file_link',
                'versionmode' => 'FOLLOW_CURRENT', 'pinnedversionid' => 0, 'status' => 'ACTIVE',
                'alttext' => null, 'caption' => null, 'optionsjson' => null,
            ],
            $ids['unused'] => (object)[
                'uuid' => $ids['unused'], 'mediaid' => 20, 'displayprofile' => 'file_link',
                'versionmode' => 'FOLLOW_CURRENT', 'pinnedversionid' => 0, 'status' => 'ACTIVE',
                'alttext' => null, 'caption' => null, 'optionsjson' => null,
            ],
        ];

        return [new reference_collector($references, $media, $versions), $references, $media, $versions, $ids];
    }

    public function test_no_marker_content_returns_empty_manifest(): void {
        [$collector] = $this->fixture();
        $record = new content_record('page', 77, 'content', '<p>No marker</p>');
        $this->assertSame([], $collector->collect_content($record));
    }

    public function test_draft_marker_is_exported_but_unreferenced_rows_are_not(): void {
        [$collector, , , , $ids] = $this->fixture();
        $record = new content_record(
            'page',
            77,
            'content',
            "Before [[digiera-ref:{$ids['follow']}]] After"
        );
        $result = $collector->collect_content($record);

        $this->assertCount(1, $result);
        $this->assertSame($ids['follow'], $result[0]['source_reference_uuid']);
        $this->assertNotContains($ids['unused'], array_column($result, 'source_reference_uuid'));
    }

    public function test_follow_reference_exports_effective_version_no(): void {
        [$collector, , , , $ids] = $this->fixture();
        $record = new content_record('page', 77, 'content', "[[digiera-ref:{$ids['follow']}]]");
        $result = $collector->collect_content($record);

        $this->assertSame('FOLLOW_CURRENT', $result[0]['source_versionmode']);
        $this->assertSame(3, $result[0]['effective_version_no']);
    }

    public function test_pinned_reference_exports_effective_version_no(): void {
        [$collector, , , , $ids] = $this->fixture();
        $record = new content_record('page', 77, 'content', "[[digiera-ref:{$ids['pinned']}]]");
        $result = $collector->collect_content($record);

        $this->assertSame('PINNED_VERSION', $result[0]['source_versionmode']);
        $this->assertSame(1, $result[0]['effective_version_no']);
    }

    public function test_unresolved_persisted_marker_fails_closed(): void {
        [$collector] = $this->fixture();
        $record = new content_record(
            'page',
            77,
            'content',
            '[[digiera-ref:99999999-9999-4999-8999-999999999999]]'
        );
        $this->expectException(\UnexpectedValueException::class);
        $collector->collect_content($record);
    }

    public function test_manifest_never_exports_local_ids_or_r2_location(): void {
        [$collector, , , , $ids] = $this->fixture();
        $record = new content_record('page', 77, 'content', "[[digiera-ref:{$ids['follow']}]]");
        $result = $collector->collect_content($record);

        $this->assertSame([
            'source_reference_uuid',
            'media_uuid',
            'displayprofile',
            'source_versionmode',
            'effective_version_no',
            'alttext',
            'caption',
            'optionsjson',
            'adapter',
            'source_entity_id',
            'fieldname',
            'occurrence',
        ], array_keys($result[0]));
        foreach (['id', 'mediaid', 'versionid', 'pinnedversionid', 'bucket', 'objectkey'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $result[0]);
        }
    }

    public function test_metadata_and_occurrence_survive_collection(): void {
        [$collector, , , , $ids] = $this->fixture();
        $record = new content_record(
            'page',
            77,
            'content',
            "A [[digiera-ref:{$ids['follow']}]] B [[digiera-ref:{$ids['follow']}]]"
        );
        $result = $collector->collect_content($record);

        $this->assertCount(2, $result);
        $this->assertSame('Alt follow', $result[0]['alttext']);
        $this->assertSame('Caption follow', $result[0]['caption']);
        $this->assertSame('{"fit":"page"}', $result[0]['optionsjson']);
        $this->assertSame('page', $result[0]['adapter']);
        $this->assertSame(77, $result[0]['source_entity_id']);
        $this->assertSame('content', $result[0]['fieldname']);
        $this->assertSame(0, $result[0]['occurrence']);
        $this->assertSame(1, $result[1]['occurrence']);
    }

    public function test_manifest_rejects_cross_media_version(): void {
        [, $references, $media, $versions, $ids] = $this->fixture();
        $this->expectException(\DomainException::class);
        reference_manifest::from_reference(
            $references->records[$ids['pinned']],
            $media->records[10],
            $versions->records[201],
            'page',
            77,
            'content',
            0
        );
    }
}
