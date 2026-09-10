<?php

namespace local_digieramedia;

/**
 * Tests for per-user recent history writes.
 *
 * @covers \local_digieramedia\service\recent_service
 */
final class recent_service_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_touch_creates_and_updates_one_row_per_user_media(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $context = \context_system::instance();
        $mediaid = $this->create_media((int)$user->id);

        $service = new \local_digieramedia\service\recent_service();
        $service->touch((int)$user->id, $mediaid, (int)$context->id, 'CREATE_REFERENCE', 1000);
        $service->touch((int)$user->id, $mediaid, (int)$context->id, 'UPDATE_REFERENCE', 2000);

        $this->assertEquals(1, $DB->count_records('local_digieramedia_recent', [
            'userid' => (int)$user->id,
            'mediaid' => $mediaid,
        ]));
        $row = $DB->get_record('local_digieramedia_recent', [
            'userid' => (int)$user->id,
            'mediaid' => $mediaid,
        ], '*', MUST_EXIST);
        $this->assertSame(2, (int)$row->usecount);
        $this->assertSame(2000, (int)$row->lastusedat);
        $this->assertSame('UPDATE_REFERENCE', (string)$row->lastaction);
        $this->assertSame((int)$context->id, (int)$row->contextid);
    }

    public function test_touch_is_isolated_per_user(): void {
        global $DB;

        $usera = $this->getDataGenerator()->create_user();
        $userb = $this->getDataGenerator()->create_user();
        $mediaid = $this->create_media((int)$usera->id);
        $contextid = (int)\context_system::instance()->id;
        $service = new \local_digieramedia\service\recent_service();

        $service->touch((int)$usera->id, $mediaid, $contextid, 'CREATE_REFERENCE', 1000);
        $service->touch((int)$userb->id, $mediaid, $contextid, 'CREATE_REFERENCE', 2000);

        $this->assertEquals(2, $DB->count_records('local_digieramedia_recent', ['mediaid' => $mediaid]));
        $this->assertEquals(1, $DB->count_records('local_digieramedia_recent', [
            'userid' => (int)$usera->id,
            'mediaid' => $mediaid,
        ]));
        $this->assertEquals(1, $DB->count_records('local_digieramedia_recent', [
            'userid' => (int)$userb->id,
            'mediaid' => $mediaid,
        ]));
    }

    public function test_touch_rejects_unknown_action(): void {
        $user = $this->getDataGenerator()->create_user();
        $mediaid = $this->create_media((int)$user->id);

        $this->expectException(\invalid_parameter_exception::class);
        (new \local_digieramedia\service\recent_service())->touch(
            (int)$user->id,
            $mediaid,
            (int)\context_system::instance()->id,
            'PREVIEW_ONLY',
            1000
        );
    }

    private function create_media(int $owneruserid): int {
        global $DB;

        return (int)$DB->insert_record('local_digieramedia_media', (object)[
            'uuid' => $this->uuidv4(),
            'name' => 'Recent service test media',
            'description' => null,
            'mediatype' => 'pdf',
            'mimetype' => 'application/pdf',
            'owneruserid' => $owneruserid,
            'origincontextid' => 0,
            'origincourseid' => 0,
            'originsectionid' => 0,
            'visibility' => 'GLOBAL',
            'status' => 'ACTIVE',
            'currentversionid' => 0,
            'timecreated' => 1,
            'timemodified' => 1,
            'createdby' => $owneruserid,
            'modifiedby' => $owneruserid,
        ]);
    }

    private function uuidv4(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
