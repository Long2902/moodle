<?php

namespace filter_digieramedia;

final class text_filter_lifecycle_test extends \advanced_testcase {
    public function test_trashed_media_keeps_existing_reference_rendering(): void {
        [$mediaid, $referenceuuid] = $this->seed('TRASHED', 'ACTIVE');
        $filter = new text_filter(\context_system::instance(), []);
        $output = $filter->filter("[[digiera-ref:{$referenceuuid}]]", ['originalformat' => FORMAT_HTML]);

        $this->assertStringContainsString('<iframe', $output);
        $this->assertStringNotContainsString('Học liệu hiện không khả dụng.', $output);
    }

    public function test_purged_media_and_unresolved_reference_render_unavailable(): void {
        global $DB;

        [$mediaid, $referenceuuid] = $this->seed('PURGED', 'ACTIVE');
        $filter = new text_filter(\context_system::instance(), []);
        $purged = $filter->filter("[[digiera-ref:{$referenceuuid}]]", ['originalformat' => FORMAT_HTML]);
        $this->assertStringContainsString('Học liệu hiện không khả dụng.', $purged);

        $DB->set_field('local_digieramedia_media', 'status', 'ACTIVE', ['id' => $mediaid]);
        $DB->set_field('local_digieramedia_reference', 'status', 'UNRESOLVED', ['uuid' => $referenceuuid]);
        $unresolved = $filter->filter("[[digiera-ref:{$referenceuuid}]]", ['originalformat' => FORMAT_HTML]);
        $this->assertStringContainsString('Học liệu hiện không khả dụng.', $unresolved);
    }

    private function seed(string $mediastatus, string $referencestatus): array {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        set_config('cdnbaseurl', 'https://cdn.digiera.vn', 'local_digieramedia');
        set_config('pdfviewerurl', 'https://cdn.digiera.vn/pdfjs/web/viewer.html', 'local_digieramedia');
        $context = \context_system::instance();
        $now = time();
        $mediaid = (int)$DB->insert_record('local_digieramedia_media', (object)[
            'uuid' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            'name' => 'Lifecycle renderer PDF',
            'description' => '',
            'mediatype' => 'pdf',
            'mimetype' => 'application/pdf',
            'owneruserid' => $USER->id,
            'origincontextid' => $context->id,
            'origincourseid' => 0,
            'originsectionid' => 0,
            'visibility' => 'PRIVATE',
            'status' => $mediastatus,
            'currentversionid' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
            'createdby' => $USER->id,
            'modifiedby' => $USER->id,
        ]);
        $versionid = (int)$DB->insert_record('local_digieramedia_version', (object)[
            'mediaid' => $mediaid,
            'versionno' => 1,
            'bucket' => 'digiera',
            'objectkey' => 'digiera/tests/lifecycle-renderer.pdf',
            'originalfilename' => 'lifecycle-renderer.pdf',
            'displayfilename' => 'lifecycle-renderer.pdf',
            'filesize' => 100,
            'mimetype' => 'application/pdf',
            'etag' => 'lifecycle-renderer-etag',
            'checksum_sha256' => str_repeat('e', 64),
            'status' => 'READY',
            'timecreated' => $now,
            'createdby' => $USER->id,
            'restoredfromversionid' => 0,
            'timepurged' => 0,
        ]);
        $DB->set_field('local_digieramedia_media', 'currentversionid', $versionid, ['id' => $mediaid]);
        $referenceuuid = 'ffffffff-ffff-4fff-8fff-ffffffffffff';
        $DB->insert_record('local_digieramedia_reference', (object)[
            'uuid' => $referenceuuid,
            'mediaid' => $mediaid,
            'contextid' => $context->id,
            'courseid' => 0,
            'cmid' => 0,
            'component' => 'filter_test',
            'entitytype' => 'test',
            'entityid' => 0,
            'fieldname' => 'content',
            'displayprofile' => 'pdf_full',
            'versionmode' => 'FOLLOW_CURRENT',
            'pinnedversionid' => 0,
            'status' => $referencestatus,
            'createdby' => $USER->id,
            'alttext' => '',
            'caption' => '',
            'optionsjson' => '{}',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        return [$mediaid, $referenceuuid];
    }
}
