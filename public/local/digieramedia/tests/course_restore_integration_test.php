<?php
namespace local_digieramedia;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/tests/backup_restore_base_testcase.php');

/**
 * Proves DIGIERA Media references survive a real Moodle course backup/restore.
 *
 * @coversNothing
 */
final class course_restore_integration_test extends \core_backup_backup_restore_base_testcase {
    public function test_page_restore_creates_new_reference_while_sharing_media(): void {
        global $DB, $USER;

        $sourcecourse = $this->getDataGenerator()->create_course();
        $targetcourse = $this->getDataGenerator()->create_course();

        $mediauuid = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $sourceuuid = '11111111-1111-4111-8111-111111111111';
        $now = time();

        $mediaid = (int)$DB->insert_record('local_digieramedia_media', (object)[
            'uuid' => $mediauuid,
            'name' => 'Restore integration PDF',
            'description' => '',
            'mediatype' => 'pdf',
            'mimetype' => 'application/pdf',
            'owneruserid' => $USER->id,
            'origincontextid' => \context_course::instance($sourcecourse->id)->id,
            'origincourseid' => $sourcecourse->id,
            'originsectionid' => 0,
            'visibility' => 'COURSE',
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
            'bucket' => 'integration-test-bucket',
            'objectkey' => 'integration/test.pdf',
            'originalfilename' => 'test.pdf',
            'displayfilename' => 'test.pdf',
            'filesize' => 123,
            'mimetype' => 'application/pdf',
            'etag' => 'integration-etag',
            'checksum_sha256' => str_repeat('a', 64),
            'status' => 'READY',
            'timecreated' => $now,
            'createdby' => $USER->id,
            'restoredfromversionid' => 0,
            'timepurged' => 0,
        ]);
        $DB->set_field('local_digieramedia_media', 'currentversionid', $versionid, ['id' => $mediaid]);

        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $sourcecourse->id,
            'name' => 'DIGIERA restore integration page',
            'content' => "Before [[digiera-ref:{$sourceuuid}]] After",
            'contentformat' => FORMAT_HTML,
        ]);
        $sourcecm = get_coursemodule_from_instance('page', $page->id, $sourcecourse->id, false, MUST_EXIST);
        $sourcecontext = \context_module::instance($sourcecm->id);

        $sourcereferenceid = (int)$DB->insert_record('local_digieramedia_reference', (object)[
            'uuid' => $sourceuuid,
            'mediaid' => $mediaid,
            'contextid' => $sourcecontext->id,
            'courseid' => $sourcecourse->id,
            'cmid' => $sourcecm->id,
            'component' => 'mod_page',
            'entitytype' => 'page',
            'entityid' => $page->id,
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

        $backupid = $this->perform_backup($sourcecourse);
        $this->perform_restore($backupid, $targetcourse);

        $restoredpages = array_values($DB->get_records('page', ['course' => $targetcourse->id]));
        $this->assertCount(1, $restoredpages);
        $restoredpage = $restoredpages[0];
        $restoredcm = get_coursemodule_from_instance('page', $restoredpage->id, $targetcourse->id, false, MUST_EXIST);
        $restoredcontext = \context_module::instance($restoredcm->id);

        $this->assertStringNotContainsString($sourceuuid, $restoredpage->content,
            'A restored Page must not reuse the source Reference UUID.');

        preg_match('/\\[\\[digiera-ref:([0-9a-f-]{36})\\]\\]/i', $restoredpage->content, $matches);
        $this->assertNotEmpty($matches[1] ?? '', 'The restored Page must contain a DIGIERA Reference marker.');
        $targetuuid = strtolower($matches[1]);
        $this->assertNotSame($sourceuuid, $targetuuid);

        $targetreference = $DB->get_record('local_digieramedia_reference', ['uuid' => $targetuuid], '*', MUST_EXIST);
        $this->assertNotSame($sourcereferenceid, (int)$targetreference->id);
        $this->assertSame($mediaid, (int)$targetreference->mediaid,
            'Default restore must share the same logical Media instead of duplicating the R2 object.');
        $this->assertSame((int)$targetcourse->id, (int)$targetreference->courseid);
        $this->assertSame((int)$restoredcm->id, (int)$targetreference->cmid);
        $this->assertSame((int)$restoredcontext->id, (int)$targetreference->contextid);
        $this->assertSame('FOLLOW_CURRENT', $targetreference->versionmode);
        $this->assertSame(0, (int)$targetreference->pinnedversionid);

        $this->assertSame(1, $DB->count_records('local_digieramedia_media', ['id' => $mediaid]));
        $this->assertSame(1, $DB->count_records('local_digieramedia_version', ['mediaid' => $mediaid]));
        $this->assertSame(2, $DB->count_records('local_digieramedia_reference', ['mediaid' => $mediaid]));
    }
}
