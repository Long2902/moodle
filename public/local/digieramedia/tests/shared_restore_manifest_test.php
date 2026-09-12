<?php
namespace local_digieramedia;

use local_digieramedia\integration\coursepublisher_bridge;
use local_digieramedia\repository\media_repository;
use local_digieramedia\repository\reference_repository;
use local_digieramedia\repository\version_repository;
use local_digieramedia\restore\clone_policy_scope;
use local_digieramedia\restore\reference_remapper;

/**
 * @covers \local_digieramedia\restore\clone_policy_scope
 * @covers \local_digieramedia\integration\coursepublisher_bridge
 * @covers \local_digieramedia\restore\reference_remapper
 */
final class shared_restore_manifest_test extends \advanced_testcase {
    private static int $fixtureidx = 0;
    private function fixture(string $mediastatus = 'ACTIVE', string $versionstatus = 'READY'): array {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $target = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($target->id);
        $now = time();

        global $DB, $USER;
        $mediaid = (int)$DB->insert_record('local_digieramedia_media', (object)[
            'uuid' => str_pad(dechex(++self::$fixtureidx), 8, 'a', STR_PAD_LEFT) . '-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Manifest source',
            'description' => '',
            'mediatype' => 'pdf',
            'mimetype' => 'application/pdf',
            'owneruserid' => (int)$USER->id,
            'origincontextid' => \context_course::instance($course->id)->id,
            'origincourseid' => (int)$course->id,
            'originsectionid' => 0,
            'visibility' => 'COURSE',
            'status' => $mediastatus,
            'currentversionid' => 0,
            'createdby' => (int)$USER->id,
            'modifiedby' => (int)$USER->id,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $versionid = (int)$DB->insert_record('local_digieramedia_version', (object)[
            'mediaid' => $mediaid,
            'versionno' => 3,
            'bucket' => 'test',
            'objectkey' => 'manifest/v3.pdf',
            'originalfilename' => 'manifest.pdf',
            'displayfilename' => 'manifest.pdf',
            'filesize' => 123,
            'mimetype' => 'application/pdf',
            'etag' => 'e3',
            'checksum_sha256' => str_repeat('a', 64),
            'status' => $versionstatus,
            'restoredfromversionid' => 0,
            'createdby' => (int)$USER->id,
            'timecreated' => $now,
            'timepurged' => 0,
        ]);
        $DB->set_field('local_digieramedia_media', 'currentversionid', $versionid, ['id' => $mediaid]);

        $manifest = [
            'source_reference_uuid' => '11111111-1111-4111-8111-111111111111',
            'media_uuid' => str_pad(dechex(self::$fixtureidx), 8, 'a', STR_PAD_LEFT) . '-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'displayprofile' => 'pdf_full',
            'source_versionmode' => 'FOLLOW_CURRENT',
            'effective_version_no' => 3,
            'alttext' => 'Alt',
            'caption' => 'Caption',
            'optionsjson' => '{}',
            'adapter' => 'page',
            'source_entity_id' => 77,
            'fieldname' => 'content',
            'occurrence' => 0,
        ];
        $remapper = new reference_remapper(
            new reference_repository(),
            new media_repository(),
            new version_repository()
        );
        return [$remapper, $context, $manifest, $mediaid, $versionid, $target];
    }

    public function test_policy_scope_defaults_and_restores_nested_state(): void {
        $this->assertSame('SHARED_FOLLOW', clone_policy_scope::current_mode());
        $this->assertNotSame('', clone_policy_scope::current_operation_id());

        $result = coursepublisher_bridge::run('shared_pinned', 'job-42', 321, function(): array {
            $inside = [clone_policy_scope::current_mode(), clone_policy_scope::current_operation_id(), clone_policy_scope::current_target_courseid()];
            $nested = coursepublisher_bridge::run('shared_follow', 'nested', 654, fn(): array => [
                clone_policy_scope::current_mode(),
                clone_policy_scope::current_operation_id(),
                clone_policy_scope::current_target_courseid(),
            ]);
            return [$inside, $nested];
        });

        $this->assertSame(['SHARED_PINNED', 'job-42', 321], $result[0]);
        $this->assertSame(['SHARED_FOLLOW', 'nested', 654], $result[1]);
        $this->assertSame('SHARED_FOLLOW', clone_policy_scope::current_mode());
    }

    public function test_shared_follow_restores_from_manifest_without_source_reference_row(): void {
        [$remapper, $context, $manifest, $mediaid] = $this->fixture();
        $content = 'Before [[digiera-ref:11111111-1111-4111-8111-111111111111]] After';

        $result = $remapper->remap_manifest_content($content, [$manifest], $context, 'SHARED_FOLLOW', 'restore-1', 2);
        $this->assertSame([], $result['unresolved']);
        $this->assertCount(1, $result['mappings']);
        $target = (new reference_repository())->get((int)$result['mappings'][0]['target_reference_id']);
        $this->assertSame($mediaid, (int)$target->mediaid);
        $this->assertSame('FOLLOW_CURRENT', $target->versionmode);
        $this->assertSame(0, (int)$target->pinnedversionid);
        $this->assertSame('DRAFT', $target->status);
        $this->assertSame('Alt', $target->alttext);
        $this->assertStringNotContainsString($manifest['source_reference_uuid'], $result['content']);
    }

    public function test_shared_pinned_resolves_exact_effective_version(): void {
        [$remapper, $context, $manifest, $mediaid, $versionid] = $this->fixture();
        $content = '[[digiera-ref:11111111-1111-4111-8111-111111111111]]';

        $result = $remapper->remap_manifest_content($content, [$manifest], $context, 'SHARED_PINNED', 'restore-2', 2);
        $target = (new reference_repository())->get((int)$result['mappings'][0]['target_reference_id']);
        $this->assertSame($mediaid, (int)$target->mediaid);
        $this->assertSame('PINNED_VERSION', $target->versionmode);
        $this->assertSame($versionid, (int)$target->pinnedversionid);
    }

    public function test_missing_snapshotted_version_fails_without_current_fallback(): void {
        [$remapper, $context, $manifest] = $this->fixture();
        $manifest['effective_version_no'] = 2;
        $this->expectException(\DomainException::class);
        $remapper->remap_manifest_content(
            '[[digiera-ref:11111111-1111-4111-8111-111111111111]]',
            [$manifest],
            $context,
            'SHARED_PINNED',
            'restore-3',
            2
        );
    }

    public function test_non_ready_version_is_rejected(): void {
        [$remapper, $context, $manifest] = $this->fixture('ACTIVE', 'PURGED');
        $this->expectException(\DomainException::class);
        $remapper->remap_manifest_content(
            '[[digiera-ref:11111111-1111-4111-8111-111111111111]]',
            [$manifest], $context, 'SHARED_FOLLOW', 'restore-4', 2
        );
    }

    public function test_trashed_media_is_allowed_but_purging_media_is_rejected(): void {
        [$remapper, $context, $manifest] = $this->fixture('TRASHED');
        $result = $remapper->remap_manifest_content(
            '[[digiera-ref:11111111-1111-4111-8111-111111111111]]',
            [$manifest], $context, 'SHARED_FOLLOW', 'restore-5', 2
        );
        $this->assertCount(1, $result['mappings']);

        [$remapper2, $context2, $manifest2] = $this->fixture('PURGING');
        $this->expectException(\DomainException::class);
        $remapper2->remap_manifest_content(
            '[[digiera-ref:11111111-1111-4111-8111-111111111111]]',
            [$manifest2], $context2, 'SHARED_FOLLOW', 'restore-6', 2
        );
    }
}
