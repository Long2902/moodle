<?php

namespace local_digieramedia;

/**
 * Regression coverage for reopening and editing a saved reference version mode.
 */
final class reference_version_state_test extends \advanced_testcase {
    public function test_saved_pinned_state_can_be_resolved_and_updated(): void {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $context = \context_system::instance();
        $now = time();

        $mediaid = (int)$DB->insert_record('local_digieramedia_media', (object)[
            'uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Version state smoke PDF',
            'description' => '',
            'mediatype' => 'pdf',
            'mimetype' => 'application/pdf',
            'owneruserid' => $USER->id,
            'origincontextid' => $context->id,
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
            'bucket' => 'digiera',
            'objectkey' => 'digiera/tests/version-state-v1.pdf',
            'originalfilename' => 'version-state-v1.pdf',
            'displayfilename' => 'version-state-v1.pdf',
            'filesize' => 1234,
            'mimetype' => 'application/pdf',
            'etag' => 'version-state-etag',
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
            'contextid' => $context->id,
            'courseid' => 0,
            'cmid' => 0,
            'component' => 'tiny_digieramedia',
            'entitytype' => 'editor',
            'entityid' => 0,
            'fieldname' => 'content',
            'displayprofile' => 'embedded',
            'versionmode' => 'PINNED_VERSION',
            'pinnedversionid' => $versionid,
            'status' => 'ACTIVE',
            'createdby' => $USER->id,
            'alttext' => '',
            'caption' => '',
            'optionsjson' => '{}',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $resolved = \local_digieramedia\external\resolve_references::execute(
            $context->id,
            [$referenceuuid]
        );

        $this->assertCount(1, $resolved);
        $this->assertArrayHasKey('versionmode', $resolved[0]);
        $this->assertArrayHasKey('pinnedversionid', $resolved[0]);
        $this->assertSame('PINNED_VERSION', $resolved[0]['versionmode']);
        $this->assertSame($versionid, $resolved[0]['pinnedversionid']);

        $this->assertTrue(
            class_exists(\local_digieramedia\external\update_reference_version::class),
            'The reference version update external API must exist.'
        );

        $updated = \local_digieramedia\external\update_reference_version::execute(
            $context->id,
            $referenceuuid,
            'FOLLOW_CURRENT',
            0
        );
        $this->assertSame('FOLLOW_CURRENT', $updated['versionmode']);
        $this->assertSame(0, $updated['pinnedversionid']);

        $record = $DB->get_record('local_digieramedia_reference', ['uuid' => $referenceuuid], '*', MUST_EXIST);
        $this->assertSame('FOLLOW_CURRENT', $record->versionmode);
        $this->assertSame(0, (int)$record->pinnedversionid);

        $updated = \local_digieramedia\external\update_reference_version::execute(
            $context->id,
            $referenceuuid,
            'PINNED_VERSION',
            $versionid
        );
        $this->assertSame('PINNED_VERSION', $updated['versionmode']);
        $this->assertSame($versionid, $updated['pinnedversionid']);
    }
}
