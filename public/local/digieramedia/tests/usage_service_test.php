<?php

namespace local_digieramedia;

use local_digieramedia\service\usage_service;

final class usage_service_test extends \advanced_testcase {
    public function test_usage_reports_live_count_and_friendly_location_for_admin(): void {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Usage Course']);
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Usage Page',
            'content' => '<p>Usage</p>',
        ]);
        $context = \context_course::instance($course->id);
        $mediaid = $this->seed_media($context->id, $USER->id, $course->id);
        $mediauuid = (string)$DB->get_field('local_digieramedia_media', 'uuid', ['id' => $mediaid]);
        $this->insert_reference($mediaid, $context->id, $course->id, $page->cmid, $USER->id, 'ACTIVE', 'FOLLOW_CURRENT');
        $this->insert_reference($mediaid, $context->id, $course->id, 0, $USER->id, 'DRAFT', 'PINNED_VERSION');
        $this->insert_reference($mediaid, $context->id, $course->id, 0, $USER->id, 'STALE', 'FOLLOW_CURRENT');

        $result = (new usage_service())->get($USER->id, $context, $mediauuid);

        $this->assertSame(2, $result['livecount']);
        $this->assertCount(3, $result['usages']);
        $activity = array_values(array_filter($result['usages'], static fn(array $usage): bool => $usage['cmid'] > 0))[0];
        $this->assertSame('Usage Course', $activity['coursename']);
        $this->assertSame('Usage Page', $activity['activityname']);
        $this->assertStringContainsString('/mod/page/view.php?id=' . $page->cmid, $activity['url']);
        $this->assertTrue($result['canviewusage']);
    }

    public function test_course_teacher_does_not_receive_inaccessible_usage_details(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course1 = $this->getDataGenerator()->create_course(['fullname' => 'Visible Course']);
        $course2 = $this->getDataGenerator()->create_course(['fullname' => 'Hidden Course']);
        $teacher = $this->getDataGenerator()->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
        $this->getDataGenerator()->enrol_user($teacher->id, $course1->id, $teacherrole->id);
        $this->setUser($teacher);
        $context1 = \context_course::instance($course1->id);
        $context2 = \context_course::instance($course2->id);
        $mediaid = $this->seed_media($context1->id, $teacher->id, $course1->id);
        $mediauuid = (string)$DB->get_field('local_digieramedia_media', 'uuid', ['id' => $mediaid]);
        $this->insert_reference($mediaid, $context1->id, $course1->id, 0, $teacher->id, 'ACTIVE', 'FOLLOW_CURRENT');
        $this->insert_reference($mediaid, $context2->id, $course2->id, 0, $teacher->id, 'ACTIVE', 'FOLLOW_CURRENT');

        $result = (new usage_service())->get($teacher->id, $context1, $mediauuid);

        $this->assertSame(2, $result['livecount'], 'Safety count must include all live references.');
        $this->assertCount(1, $result['usages'], 'Details from inaccessible courses must not be exposed.');
        $this->assertSame('Visible Course', $result['usages'][0]['coursename']);
    }

    private function seed_media(int $contextid, int $userid, int $courseid): int {
        global $DB;
        $now = time();
        $mediaid = (int)$DB->insert_record('local_digieramedia_media', (object)[
            'uuid' => sprintf('cccccccc-cccc-4ccc-8ccc-%012d', random_int(1, 999999)),
            'name' => 'Usage media',
            'description' => '',
            'mediatype' => 'pdf',
            'mimetype' => 'application/pdf',
            'owneruserid' => $userid,
            'origincontextid' => $contextid,
            'origincourseid' => $courseid,
            'originsectionid' => 0,
            'visibility' => 'COURSE',
            'status' => 'ACTIVE',
            'currentversionid' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
            'createdby' => $userid,
            'modifiedby' => $userid,
        ]);
        $versionid = (int)$DB->insert_record('local_digieramedia_version', (object)[
            'mediaid' => $mediaid,
            'versionno' => 1,
            'bucket' => 'digiera',
            'objectkey' => 'digiera/tests/usage.pdf',
            'originalfilename' => 'usage.pdf',
            'displayfilename' => 'usage.pdf',
            'filesize' => 100,
            'mimetype' => 'application/pdf',
            'etag' => 'usage-etag',
            'checksum_sha256' => str_repeat('c', 64),
            'status' => 'READY',
            'timecreated' => $now,
            'createdby' => $userid,
            'restoredfromversionid' => 0,
            'timepurged' => 0,
        ]);
        $DB->set_field('local_digieramedia_media', 'currentversionid', $versionid, ['id' => $mediaid]);
        return $mediaid;
    }

    private function insert_reference(
        int $mediaid,
        int $contextid,
        int $courseid,
        int $cmid,
        int $userid,
        string $status,
        string $versionmode
    ): void {
        global $DB;
        $now = time();
        $currentversionid = (int)$DB->get_field('local_digieramedia_media', 'currentversionid', ['id' => $mediaid]);
        $DB->insert_record('local_digieramedia_reference', (object)[
            'uuid' => sprintf('dddddddd-dddd-4ddd-8ddd-%012d', random_int(1, 999999)),
            'mediaid' => $mediaid,
            'contextid' => $contextid,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'component' => 'tiny_digieramedia',
            'entitytype' => 'editor',
            'entityid' => 0,
            'fieldname' => 'content',
            'displayprofile' => 'embedded',
            'versionmode' => $versionmode,
            'pinnedversionid' => $versionmode === 'PINNED_VERSION' ? $currentversionid : 0,
            'status' => $status,
            'createdby' => $userid,
            'alttext' => '',
            'caption' => '',
            'optionsjson' => '{}',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
}
