<?php

namespace filter_digieramedia;

/**
 * Runtime tests for the DIGIERA Media text filter.
 *
 * @covers \filter_digieramedia\text_filter
 */
final class text_filter_test extends \advanced_testcase {
    public function test_pdf_reference_renders_existing_cdn_object_with_page_width_viewer(): void {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        set_config('cdnbaseurl', 'https://cdn.digiera.vn', 'local_digieramedia');
        set_config('pdfviewerurl', 'https://cdn.digiera.vn/pdfjs/web/viewer.html', 'local_digieramedia');

        $now = time();
        $mediaid = (int)$DB->insert_record('local_digieramedia_media', (object)[
            'uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Kế hoạch bài dạy',
            'description' => '',
            'mediatype' => 'pdf',
            'mimetype' => 'application/pdf',
            'owneruserid' => $USER->id,
            'origincontextid' => \context_system::instance()->id,
            'origincourseid' => 0,
            'originsectionid' => 0,
            'visibility' => 'GLOBAL',
            'status' => 'ACTIVE',
            'currentversionid' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
            'createdby' => $USER->id,
            'modifiedby' => $USER->id,
        ]);

        $versionid = (int)$DB->insert_record('local_digieramedia_version', (object)[
            'mediaid' => $mediaid,
            'versionno' => 1,
            'bucket' => 'digiera-media',
            'objectkey' => 'newAI-THCS/lop6/bai1/6_a_bai1_khbd.pdf',
            'originalfilename' => '6_a_bai1_khbd.pdf',
            'displayfilename' => '6_a_bai1_khbd.pdf',
            'filesize' => 123456,
            'mimetype' => 'application/pdf',
            'etag' => 'renderer-test-etag',
            'checksum_sha256' => str_repeat('a', 64),
            'status' => 'READY',
            'timecreated' => $now,
            'createdby' => $USER->id,
            'restoredfromversionid' => 0,
            'timepurged' => 0,
        ]);
        $DB->set_field('local_digieramedia_media', 'currentversionid', $versionid, ['id' => $mediaid]);

        $referenceuuid = '11111111-1111-4111-8111-111111111111';
        $DB->insert_record('local_digieramedia_reference', (object)[
            'uuid' => $referenceuuid,
            'mediaid' => $mediaid,
            'contextid' => \context_system::instance()->id,
            'courseid' => 0,
            'cmid' => 0,
            'component' => 'filter_test',
            'entitytype' => 'test',
            'entityid' => 0,
            'fieldname' => 'content',
            'displayprofile' => 'pdf_full',
            'versionmode' => 'FOLLOW_CURRENT',
            'pinnedversionid' => 0,
            'status' => 'ACTIVE',
            'createdby' => $USER->id,
            'alttext' => '',
            'caption' => '',
            'optionsjson' => '{}',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $filter = new text_filter(\context_system::instance(), []);
        $output = $filter->filter("Before [[digiera-ref:{$referenceuuid}]] After", [
            'originalformat' => FORMAT_HTML,
        ]);

        $this->assertStringNotContainsString($referenceuuid, $output);
        $this->assertStringContainsString('<iframe', $output);
        $this->assertStringContainsString('https://cdn.digiera.vn/pdfjs/web/viewer.html?file=', $output);
        $this->assertStringContainsString(
            'https%3A%2F%2Fcdn.digiera.vn%2FnewAI-THCS%2Flop6%2Fbai1%2F6_a_bai1_khbd.pdf',
            $output
        );
        $this->assertStringContainsString('#zoom=page-width', $output);
    }

    public function test_follow_current_and_pinned_version_render_different_objects_after_replace(): void {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config('cdnbaseurl', 'https://cdn.digiera.vn', 'local_digieramedia');
        set_config('pdfviewerurl', 'https://cdn.digiera.vn/pdfjs/web/viewer.html', 'local_digieramedia');

        $now = time();
        $mediaid = (int)$DB->insert_record('local_digieramedia_media', (object)[
            'uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'name' => 'Tài liệu có phiên bản',
            'description' => '',
            'mediatype' => 'pdf',
            'mimetype' => 'application/pdf',
            'owneruserid' => $USER->id,
            'origincontextid' => \context_system::instance()->id,
            'origincourseid' => 0,
            'originsectionid' => 0,
            'visibility' => 'GLOBAL',
            'status' => 'ACTIVE',
            'currentversionid' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
            'createdby' => $USER->id,
            'modifiedby' => $USER->id,
        ]);

        $version1id = $this->create_pdf_version($mediaid, 1, 'digiera/versioning/v1.pdf', $now);
        $version2id = $this->create_pdf_version($mediaid, 2, 'digiera/versioning/v2.pdf', $now + 1);
        $DB->set_field('local_digieramedia_media', 'currentversionid', $version2id, ['id' => $mediaid]);

        $followuuid = '22222222-2222-4222-8222-222222222222';
        $pinneduuid = '33333333-3333-4333-8333-333333333333';
        $this->create_reference($followuuid, $mediaid, 'FOLLOW_CURRENT', 0, $now);
        $this->create_reference($pinneduuid, $mediaid, 'PINNED_VERSION', $version1id, $now);

        $filter = new text_filter(\context_system::instance(), []);
        $follow = $filter->filter("[[digiera-ref:{$followuuid}]]", ['originalformat' => FORMAT_HTML]);
        $pinned = $filter->filter("[[digiera-ref:{$pinneduuid}]]", ['originalformat' => FORMAT_HTML]);

        $this->assertStringContainsString('digiera%2Fversioning%2Fv2.pdf', $follow);
        $this->assertStringNotContainsString('digiera%2Fversioning%2Fv1.pdf', $follow);
        $this->assertStringContainsString('digiera%2Fversioning%2Fv1.pdf', $pinned);
        $this->assertStringNotContainsString('digiera%2Fversioning%2Fv2.pdf', $pinned);
    }

    private function create_pdf_version(int $mediaid, int $versionno, string $objectkey, int $created): int {
        global $DB, $USER;

        return (int)$DB->insert_record('local_digieramedia_version', (object)[
            'mediaid' => $mediaid,
            'versionno' => $versionno,
            'bucket' => 'digiera',
            'objectkey' => $objectkey,
            'originalfilename' => basename($objectkey),
            'displayfilename' => basename($objectkey),
            'filesize' => 1000 + $versionno,
            'mimetype' => 'application/pdf',
            'etag' => 'etag-v' . $versionno,
            'checksum_sha256' => null,
            'status' => 'READY',
            'timecreated' => $created,
            'createdby' => $USER->id,
            'restoredfromversionid' => 0,
            'timepurged' => 0,
        ]);
    }

    private function create_reference(
        string $uuid,
        int $mediaid,
        string $versionmode,
        int $pinnedversionid,
        int $created
    ): void {
        global $DB, $USER;

        $DB->insert_record('local_digieramedia_reference', (object)[
            'uuid' => $uuid,
            'mediaid' => $mediaid,
            'contextid' => \context_system::instance()->id,
            'courseid' => 0,
            'cmid' => 0,
            'component' => 'filter_test',
            'entitytype' => 'test',
            'entityid' => 0,
            'fieldname' => 'content',
            'displayprofile' => 'pdf_full',
            'versionmode' => $versionmode,
            'pinnedversionid' => $pinnedversionid,
            'status' => 'ACTIVE',
            'createdby' => $USER->id,
            'alttext' => '',
            'caption' => '',
            'optionsjson' => '{}',
            'timecreated' => $created,
            'timemodified' => $created,
        ]);
    }
}
