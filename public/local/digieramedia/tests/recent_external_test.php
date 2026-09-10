<?php

namespace local_digieramedia;

use local_digieramedia\external\create_reference;
use local_digieramedia\external\search_media;
use local_digieramedia\external\update_reference_version;
use local_digieramedia\service\recent_service;

/** @coversNothing */
final class recent_external_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_successful_create_reference_touches_recent_but_failed_create_does_not(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id);
        $context = \context_course::instance($course->id);
        [$mediaid, , $mediauuid] = $this->seed_media((int)$teacher->id, (int)$context->id, (int)$course->id, 'GLOBAL', 100);
        [, , $privateuuid] = $this->seed_media((int)$other->id, (int)$context->id, (int)$course->id, 'PRIVATE', 200);

        $this->setUser($teacher);
        create_reference::execute((int)$context->id, $mediauuid);

        $row = $DB->get_record('local_digieramedia_recent', [
            'userid' => (int)$teacher->id,
            'mediaid' => $mediaid,
        ], '*', MUST_EXIST);
        $this->assertSame(1, (int)$row->usecount);
        $this->assertSame('CREATE_REFERENCE', (string)$row->lastaction);
        $this->assertSame((int)$context->id, (int)$row->contextid);

        try {
            create_reference::execute((int)$context->id, $privateuuid);
            $this->fail('Private media owned by another user must not create a reference.');
        } catch (\required_capability_exception $e) {
            $this->assertTrue(true);
        }
        $privatemediaid = (int)$DB->get_field('local_digieramedia_media', 'id', ['uuid' => $privateuuid], MUST_EXIST);
        $this->assertFalse($DB->record_exists('local_digieramedia_recent', [
            'userid' => (int)$teacher->id,
            'mediaid' => $privatemediaid,
        ]));
    }

    public function test_update_reference_touches_same_recent_row(): void {
        global $DB, $USER;

        $this->setAdminUser();
        $context = \context_system::instance();
        [$mediaid, $versionid, $mediauuid] = $this->seed_media((int)$USER->id, (int)$context->id, 0, 'GLOBAL', 100);

        $created = create_reference::execute((int)$context->id, $mediauuid);
        update_reference_version::execute(
            (int)$context->id,
            (string)$created['referenceuuid'],
            'PINNED_VERSION',
            $versionid
        );

        $row = $DB->get_record('local_digieramedia_recent', [
            'userid' => (int)$USER->id,
            'mediaid' => $mediaid,
        ], '*', MUST_EXIST);
        $this->assertSame(2, (int)$row->usecount);
        $this->assertSame('UPDATE_REFERENCE', (string)$row->lastaction);
    }

    public function test_recent_is_user_specific_for_viewall_and_orders_by_last_use_not_media_modified(): void {
        global $USER;

        $this->setAdminUser();
        $context = \context_system::instance();
        $adminid = (int)$USER->id;
        $other = $this->getDataGenerator()->create_user();

        [$xid, , $xuuid] = $this->seed_media($adminid, (int)$context->id, 0, 'GLOBAL', 9000, 'X old use');
        [$yid, , $yuuid] = $this->seed_media($adminid, (int)$context->id, 0, 'GLOBAL', 100, 'Y new use');
        [$zid] = $this->seed_media((int)$other->id, (int)$context->id, 0, 'GLOBAL', 20000, 'Z other user');

        $recent = new recent_service();
        $recent->touch($adminid, $xid, (int)$context->id, 'CREATE_REFERENCE', 1000);
        $recent->touch($adminid, $yid, (int)$context->id, 'CREATE_REFERENCE', 2000);
        $recent->touch((int)$other->id, $zid, (int)$context->id, 'CREATE_REFERENCE', 3000);

        $result = search_media::execute((int)$context->id, 'recent', '', 0, 24);
        $uuids = array_column($result['items'], 'uuid');

        $this->assertSame([$yuuid, $xuuid], $uuids);
        $this->assertSame(2, (int)$result['total']);
    }

    public function test_recent_hides_trashed_media_and_restore_state_makes_preserved_row_visible_again(): void {
        global $DB, $USER;

        $this->setAdminUser();
        $context = \context_system::instance();
        [$mediaid, , $mediauuid] = $this->seed_media((int)$USER->id, (int)$context->id, 0, 'GLOBAL', 100);
        (new recent_service())->touch((int)$USER->id, $mediaid, (int)$context->id, 'CREATE_REFERENCE', 1000);

        $DB->set_field('local_digieramedia_media', 'status', 'TRASHED', ['id' => $mediaid]);
        $trashed = search_media::execute((int)$context->id, 'recent', '', 0, 24);
        $this->assertSame([], array_column($trashed['items'], 'uuid'));
        $this->assertTrue($DB->record_exists('local_digieramedia_recent', [
            'userid' => (int)$USER->id,
            'mediaid' => $mediaid,
        ]));

        $DB->set_field('local_digieramedia_media', 'status', 'ACTIVE', ['id' => $mediaid]);
        $restored = search_media::execute((int)$context->id, 'recent', '', 0, 24);
        $this->assertSame([$mediauuid], array_column($restored['items'], 'uuid'));
    }

    private function seed_media(
        int $owneruserid,
        int $contextid,
        int $courseid,
        string $visibility,
        int $modified,
        string $name = 'Recent external media'
    ): array {
        global $DB;

        $uuid = $this->uuidv4();
        $mediaid = (int)$DB->insert_record('local_digieramedia_media', (object)[
            'uuid' => $uuid,
            'name' => $name,
            'description' => null,
            'mediatype' => 'pdf',
            'mimetype' => 'application/pdf',
            'owneruserid' => $owneruserid,
            'origincontextid' => $contextid,
            'origincourseid' => $courseid,
            'originsectionid' => 0,
            'visibility' => $visibility,
            'status' => 'ACTIVE',
            'currentversionid' => 0,
            'timecreated' => 1,
            'timemodified' => $modified,
            'createdby' => $owneruserid,
            'modifiedby' => $owneruserid,
        ]);
        $versionid = (int)$DB->insert_record('local_digieramedia_version', (object)[
            'mediaid' => $mediaid,
            'versionno' => 1,
            'bucket' => 'digiera',
            'objectkey' => 'digiera/tests/recent/' . $uuid . '.pdf',
            'originalfilename' => $name . '.pdf',
            'displayfilename' => $name . '.pdf',
            'filesize' => 100,
            'mimetype' => 'application/pdf',
            'etag' => 'recent-etag-' . $mediaid,
            'checksum_sha256' => str_repeat('a', 64),
            'status' => 'READY',
            'timecreated' => 1,
            'createdby' => $owneruserid,
            'restoredfromversionid' => 0,
            'timepurged' => 0,
        ]);
        $DB->set_field('local_digieramedia_media', 'currentversionid', $versionid, ['id' => $mediaid]);
        return [$mediaid, $versionid, $uuid];
    }

    private function uuidv4(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
