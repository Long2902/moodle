<?php

namespace local_digieramedia;

use local_digieramedia\external\create_reference;
use local_digieramedia\external\get_media_versions;
use local_digieramedia\external\resolve_references;
use local_digieramedia\external\search_media;
use local_digieramedia\external\update_media;
use local_digieramedia\external\update_reference_version;

/** @coversNothing */
final class metadata_reference_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_create_reference_persists_alttext_and_caption(): void {
        global $DB;

        $setup = $this->setup_course_and_teacher();
        [$mediaid, , $mediauuid] = $this->seed_media(
            (int)$setup['teacher']->id,
            (int)$setup['context']->id,
            (int)$setup['course']->id,
            'COURSE',
            'Sơ đồ chu trình nước'
        );

        $this->setUser($setup['teacher']);
        $result = create_reference::execute(
            (int)$setup['context']->id,
            $mediauuid,
            'embedded',
            'FOLLOW_CURRENT',
            0,
            'Hình minh họa chu trình nước trong tự nhiên',
            'Hình 1.1: Sơ đồ chu trình tuần hoàn của nước'
        );

        $this->assertNotEmpty($result['referenceuuid']);
        $this->assertEquals('Hình minh họa chu trình nước trong tự nhiên', $result['alttext']);
        $this->assertEquals('Hình 1.1: Sơ đồ chu trình tuần hoàn của nước', $result['caption']);

        $row = $DB->get_record('local_digieramedia_reference', ['uuid' => $result['referenceuuid']], '*', MUST_EXIST);
        $this->assertEquals('Hình minh họa chu trình nước trong tự nhiên', $row->alttext);
        $this->assertEquals('Hình 1.1: Sơ đồ chu trình tuần hoàn của nước', $row->caption);
    }

    public function test_resolve_references_returns_alttext_caption_displayprofile(): void {
        $setup = $this->setup_course_and_teacher();
        [$mediaid, , $mediauuid] = $this->seed_media(
            (int)$setup['teacher']->id,
            (int)$setup['context']->id,
            (int)$setup['course']->id,
            'COURSE',
            'Infographic bài 2'
        );

        $this->setUser($setup['teacher']);
        $created = create_reference::execute(
            (int)$setup['context']->id,
            $mediauuid,
            'compact',
            'FOLLOW_CURRENT',
            0,
            'Alt text Infographic bài 2',
            'Chú thích Infographic'
        );

        $resolved = resolve_references::execute((int)$setup['context']->id, [$created['referenceuuid']]);
        $this->assertCount(1, $resolved);
        $item = $resolved[0];

        $this->assertEquals($created['referenceuuid'], $item['referenceuuid']);
        $this->assertEquals('Alt text Infographic bài 2', $item['alttext']);
        $this->assertEquals('Chú thích Infographic', $item['caption']);
        $this->assertEquals('compact', $item['displayprofile']);
    }

    public function test_update_reference_version_updates_alttext_caption_and_avoids_duplicates(): void {
        global $DB;

        $setup = $this->setup_course_and_teacher();
        [$mediaid, , $mediauuid] = $this->seed_media(
            (int)$setup['teacher']->id,
            (int)$setup['context']->id,
            (int)$setup['course']->id,
            'COURSE',
            'Bài giảng hình ảnh'
        );

        $this->setUser($setup['teacher']);
        $created = create_reference::execute(
            (int)$setup['context']->id,
            $mediauuid,
            'embedded',
            'FOLLOW_CURRENT',
            0,
            'Alt ban đầu',
            'Caption ban đầu'
        );

        $initialCount = $DB->count_records('local_digieramedia_reference', ['contextid' => $setup['context']->id]);

        $this->setUser($setup['manager']);
        $updated = update_reference_version::execute(
            (int)$setup['context']->id,
            $created['referenceuuid'],
            'FOLLOW_CURRENT',
            0,
            'Alt đã cập nhật',
            'Caption đã cập nhật'
        );

        $this->assertEquals($created['referenceuuid'], $updated['referenceuuid']);
        $this->assertEquals('Alt đã cập nhật', $updated['alttext']);
        $this->assertEquals('Caption đã cập nhật', $updated['caption']);

        $finalCount = $DB->count_records('local_digieramedia_reference', ['contextid' => $setup['context']->id]);
        $this->assertEquals($initialCount, $finalCount);

        $row = $DB->get_record('local_digieramedia_reference', ['uuid' => $created['referenceuuid']], '*', MUST_EXIST);
        $this->assertEquals('Alt đã cập nhật', $row->alttext);
        $this->assertEquals('Caption đã cập nhật', $row->caption);
    }

    public function test_update_reference_version_can_repoint_media_without_duplicate_reference(): void {
        global $DB;

        $setup = $this->setup_course_and_teacher();
        [$media1id, , $media1uuid] = $this->seed_media(
            (int)$setup['teacher']->id,
            (int)$setup['context']->id,
            (int)$setup['course']->id,
            'COURSE',
            'Media cũ'
        );
        [$media2id, , $media2uuid] = $this->seed_media(
            (int)$setup['teacher']->id,
            (int)$setup['context']->id,
            (int)$setup['course']->id,
            'COURSE',
            'Media mới'
        );

        $this->setUser($setup['teacher']);
        $created = create_reference::execute(
            (int)$setup['context']->id,
            $media1uuid,
            'embedded',
            'FOLLOW_CURRENT',
            0
        );

        $refCountBefore = $DB->count_records('local_digieramedia_reference', ['contextid' => $setup['context']->id]);

        $this->setUser($setup['manager']);
        $updated = update_reference_version::execute(
            (int)$setup['context']->id,
            $created['referenceuuid'],
            'FOLLOW_CURRENT',
            0,
            'Alt mới',
            'Caption mới',
            $media2uuid
        );

        $refCountAfter = $DB->count_records('local_digieramedia_reference', ['contextid' => $setup['context']->id]);
        $this->assertEquals($refCountBefore, $refCountAfter);
        $this->assertEquals($created['referenceuuid'], $updated['referenceuuid']);
        $this->assertEquals($media2uuid, $updated['mediauuid']);

        $row = $DB->get_record('local_digieramedia_reference', ['uuid' => $created['referenceuuid']], '*', MUST_EXIST);
        $this->assertEquals($media2id, (int)$row->mediaid);
        $this->assertEquals('Alt mới', $row->alttext);
        $this->assertEquals('Caption mới', $row->caption);
    }

    public function test_update_media_renames_and_changes_visibility_with_permission_enforcement(): void {
        global $DB;

        $setup = $this->setup_course_and_teacher();
        [$mediaid, , $mediauuid] = $this->seed_media(
            (int)$setup['teacher']->id,
            (int)$setup['context']->id,
            (int)$setup['course']->id,
            'PRIVATE',
            'Tên gốc'
        );

        $this->setUser($setup['teacher']);
        $renamed = update_media::execute(
            (int)$setup['context']->id,
            $mediauuid,
            'Tên mới sau khi sửa'
        );
        $this->assertEquals('Tên mới sau khi sửa', $renamed['name']);

        try {
            update_media::execute(
                (int)$setup['context']->id,
                $mediauuid,
                'Tên mới',
                'GLOBAL'
            );
            $this->fail('Expected permission exception when teacher tries to change visibility');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString('visibility', strtolower($e->getMessage()));
        }

        $this->setUser($setup['manager']);
        $visUpdated = update_media::execute(
            (int)$setup['context']->id,
            $mediauuid,
            'Tên mới quản lý',
            'GLOBAL'
        );
        $this->assertEquals('GLOBAL', $visUpdated['visibility']);

        $row = $DB->get_record('local_digieramedia_media', ['uuid' => $mediauuid], '*', MUST_EXIST);
        $this->assertEquals('Tên mới quản lý', $row->name);
        $this->assertEquals('GLOBAL', $row->visibility);
    }

    public function test_search_media_and_versions_expose_storagepath_and_capabilities_for_admin_ktv(): void {
        $setup = $this->setup_course_and_teacher();
        [$mediaid, , $mediauuid] = $this->seed_media(
            (int)$setup['teacher']->id,
            (int)$setup['context']->id,
            (int)$setup['course']->id,
            'GLOBAL',
            'Học liệu công khai'
        );

        $this->setUser($setup['teacher']);
        $teacherSearch = search_media::execute((int)$setup['context']->id, 'library');
        $this->assertFalse($teacherSearch['canmanagevisibility']);
        $this->assertEmpty($teacherSearch['items'][0]['storagepath']);

        $this->setUser($setup['manager']);
        $managerSearch = search_media::execute((int)$setup['context']->id, 'library');
        $this->assertTrue($managerSearch['canmanagevisibility']);
        $this->assertTrue($managerSearch['canedit']);
        $this->assertNotEmpty($managerSearch['items'][0]['storagepath']);
        $this->assertStringContainsString('digiera/tests/', $managerSearch['items'][0]['storagepath']);

        $versionResult = get_media_versions::execute((int)$setup['context']->id, $mediauuid);
        $this->assertNotEmpty($versionResult['versions'][0]['storagepath']);
    }

    private function setup_course_and_teacher(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $manager = $this->getDataGenerator()->create_user();

        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        $managerrole = $DB->get_record('role', ['shortname' => 'manager'], '*', MUST_EXIST);

        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $teacherrole->id);
        $this->getDataGenerator()->enrol_user($manager->id, $course->id, $managerrole->id);

        $context = \context_course::instance($course->id);
        return [
            'course' => $course,
            'teacher' => $teacher,
            'manager' => $manager,
            'context' => $context,
        ];
    }

    private function seed_media(
        int $owneruserid,
        int $contextid,
        int $courseid,
        string $visibility,
        string $name = 'Test media'
    ): array {
        global $DB;

        $uuid = $this->uuidv4();
        $mediaid = (int)$DB->insert_record('local_digieramedia_media', (object)[
            'uuid' => $uuid,
            'name' => $name,
            'description' => null,
            'mediatype' => 'image',
            'mimetype' => 'image/png',
            'owneruserid' => $owneruserid,
            'origincontextid' => $contextid,
            'origincourseid' => $courseid,
            'originsectionid' => 0,
            'visibility' => $visibility,
            'status' => 'ACTIVE',
            'currentversionid' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $owneruserid,
            'modifiedby' => $owneruserid,
        ]);
        $versionid = (int)$DB->insert_record('local_digieramedia_version', (object)[
            'mediaid' => $mediaid,
            'versionno' => 1,
            'bucket' => 'digiera',
            'objectkey' => 'digiera/tests/media/' . $uuid . '.png',
            'originalfilename' => $name . '.png',
            'displayfilename' => $name . '.png',
            'filesize' => 2048,
            'mimetype' => 'image/png',
            'etag' => 'meta-etag-' . $mediaid,
            'checksum_sha256' => str_repeat('b', 64),
            'status' => 'READY',
            'timecreated' => time(),
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
