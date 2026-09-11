<?php

namespace local_digieramedia;

use local_digieramedia\external\create_reference;
use local_digieramedia\external\update_reference_version;
use local_digieramedia\r2\client_interface;
use local_digieramedia\service\lifecycle_service;
use local_digieramedia\service\usage_service;

final class permission_matrix_fake_r2_client implements client_interface {
    public function head_object(string $bucket, string $key): array { return []; }
    public function presign_put(string $bucket, string $key, string $contenttype, int $ttl): string { return ''; }
    public function create_multipart_upload(string $bucket, string $key, string $contenttype): string { return ''; }
    public function presign_upload_part(string $bucket, string $key, string $uploadid, int $partnumber, int $ttl): string { return ''; }
    public function complete_multipart_upload(string $bucket, string $key, string $uploadid, array $parts): array { return []; }
    public function abort_multipart_upload(string $bucket, string $key, string $uploadid): void {}
    public function delete_object(string $bucket, string $key): void {}
    public function copy_object(string $sourcebucket, string $sourcekey, string $targetbucket, string $targetkey): array { return []; }
}

final class permission_matrix_test extends \advanced_testcase {
    public function test_role_capability_matrix_matches_rc1_contract(): void {
        global $DB;

        $this->resetAfterTest(true);
        $setup = $this->setup_roles();
        $context = $setup['coursecontext'];

        $teacherexpected = [
            'local/digieramedia:view' => true,
            'local/digieramedia:insert' => true,
            'local/digieramedia:upload' => true,
            'local/digieramedia:viewusage' => true,
            'local/digieramedia:trashown' => true,
            'local/digieramedia:replace' => false,
            'local/digieramedia:manageversions' => false,
            'local/digieramedia:restore' => false,
            'local/digieramedia:purge' => false,
        ];
        $this->assert_capabilities($setup['teacher']->id, $context, $teacherexpected);

        $managerexpected = [
            'local/digieramedia:view' => true,
            'local/digieramedia:insert' => true,
            'local/digieramedia:upload' => true,
            'local/digieramedia:viewusage' => true,
            'local/digieramedia:replace' => true,
            'local/digieramedia:manageversions' => true,
            'local/digieramedia:trash' => true,
            'local/digieramedia:restore' => true,
            'local/digieramedia:managevisibility' => true,
            'local/digieramedia:viewall' => true,
            'local/digieramedia:manage' => true,
            'local/digieramedia:purge' => false,
        ];
        $this->assert_capabilities($setup['manager']->id, $context, $managerexpected);

        $ktvexpected = [
            'local/digieramedia:view' => true,
            'local/digieramedia:insert' => true,
            'local/digieramedia:upload' => true,
            'local/digieramedia:viewusage' => true,
            'local/digieramedia:replace' => true,
            'local/digieramedia:manageversions' => true,
            'local/digieramedia:trash' => true,
            'local/digieramedia:restore' => true,
            'local/digieramedia:managevisibility' => true,
            'local/digieramedia:purge' => false,
        ];
        $this->assert_capabilities($setup['ktv']->id, $context, $ktvexpected);

        $ordinaryexpected = [
            'local/digieramedia:view' => false,
            'local/digieramedia:insert' => false,
            'local/digieramedia:upload' => false,
            'local/digieramedia:viewusage' => false,
            'local/digieramedia:replace' => false,
            'local/digieramedia:manageversions' => false,
            'local/digieramedia:trash' => false,
            'local/digieramedia:restore' => false,
            'local/digieramedia:purge' => false,
            'local/digieramedia:manage' => false,
        ];
        $this->assert_capabilities($setup['ordinary']->id, $context, $ordinaryexpected);

        assign_capability(
            'local/digieramedia:purge',
            CAP_ALLOW,
            $setup['ktvroleid'],
            $setup['systemcontext']->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(has_capability('local/digieramedia:purge', $context, $setup['ktv']->id));

        $this->assertNotEmpty($DB->get_record('role', ['id' => $setup['ktvroleid']]));
    }

    public function test_backend_rejects_denied_operations_and_accepts_explicit_grants(): void {
        $this->resetAfterTest(true);
        $setup = $this->setup_roles();
        $context = $setup['coursecontext'];
        [$mediaid, $mediauuid] = $this->seed_media($context->id, $setup['teacher']->id);

        $this->setUser($setup['ordinary']);
        try {
            create_reference::execute($context->id, $mediauuid);
            $this->fail('A non-privileged user must not create DIGIERA references.');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString('permission', strtolower($e->getMessage()));
        }

        try {
            (new usage_service())->get($setup['ordinary']->id, $context, $mediauuid);
            $this->fail('A non-privileged user must not inspect media usage.');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString('permission', strtolower($e->getMessage()));
        }

        $this->setUser($setup['teacher']);
        $created = create_reference::execute($context->id, $mediauuid);
        $this->assertNotEmpty($created['referenceuuid']);

        try {
            update_reference_version::execute(
                $context->id,
                $created['referenceuuid'],
                'FOLLOW_CURRENT',
                0
            );
            $this->fail('Editing teachers must not manage reference versions by default.');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString('permission', strtolower($e->getMessage()));
        }

        $lifecycle = new lifecycle_service(new permission_matrix_fake_r2_client());
        try {
            $lifecycle->restore($setup['teacher']->id, $context, $mediauuid);
            $this->fail('Editing teachers must not restore media by default.');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString('permission', strtolower($e->getMessage()));
        }

        try {
            $lifecycle->purge($setup['manager']->id, $context, $mediauuid, false);
            $this->fail('Manager purge must remain opt-in.');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString('permission', strtolower($e->getMessage()));
        }

        $this->setUser($setup['ktv']);
        $updated = update_reference_version::execute(
            $context->id,
            $created['referenceuuid'],
            'FOLLOW_CURRENT',
            0
        );
        $this->assertSame($created['referenceuuid'], $updated['referenceuuid']);

        try {
            $lifecycle->purge($setup['ktv']->id, $context, $mediauuid, false);
            $this->fail('KTV purge must remain denied until explicitly granted.');
        } catch (\required_capability_exception $e) {
            $this->assertStringContainsString('permission', strtolower($e->getMessage()));
        }

        assign_capability(
            'local/digieramedia:purge',
            CAP_ALLOW,
            $setup['ktvroleid'],
            $setup['systemcontext']->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertTrue(has_capability('local/digieramedia:purge', $context, $setup['ktv']->id));

        try {
            $lifecycle->purge($setup['ktv']->id, $context, $mediauuid, false);
            $this->fail('ACTIVE media should reach lifecycle validation after purge capability is granted.');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('trash', strtolower($e->getMessage()));
        }

        $this->assertGreaterThan(0, $mediaid);
    }

    private function setup_roles(): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $systemcontext = \context_system::instance();
        $coursecontext = \context_course::instance($course->id);

        $teacher = $generator->create_user();
        $manager = $generator->create_user();
        $ktv = $generator->create_user();
        $ordinary = $generator->create_user();

        $teacherroleid = (int)$DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $managerroleid = (int)$DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($teacherroleid, $teacher->id, $coursecontext->id);
        role_assign($managerroleid, $manager->id, $systemcontext->id);

        $ktvroleid = create_role('DIGIERA KTV test', 'digiera_ktv_test', 'RC1 permission matrix test role');
        set_role_contextlevels($ktvroleid, [CONTEXT_SYSTEM, CONTEXT_COURSE]);
        $ktvcapabilities = [
            'local/digieramedia:view',
            'local/digieramedia:insert',
            'local/digieramedia:upload',
            'local/digieramedia:viewusage',
            'local/digieramedia:replace',
            'local/digieramedia:manageversions',
            'local/digieramedia:trash',
            'local/digieramedia:restore',
            'local/digieramedia:managevisibility',
        ];
        foreach ($ktvcapabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $ktvroleid, $systemcontext->id, true);
        }
        role_assign($ktvroleid, $ktv->id, $systemcontext->id);
        accesslib_clear_all_caches_for_unit_testing();

        return [
            'course' => $course,
            'systemcontext' => $systemcontext,
            'coursecontext' => $coursecontext,
            'teacher' => $teacher,
            'manager' => $manager,
            'ktv' => $ktv,
            'ordinary' => $ordinary,
            'ktvroleid' => $ktvroleid,
        ];
    }

    private function assert_capabilities(int $userid, \context $context, array $expected): void {
        foreach ($expected as $capability => $allowed) {
            $this->assertSame(
                $allowed,
                has_capability($capability, $context, $userid),
                $capability . ' did not match the approved RC1 matrix.'
            );
        }
    }

    private function seed_media(int $contextid, int $owneruserid): array {
        global $DB;

        $now = time();
        $uuid = sprintf('cccccccc-cccc-4ccc-8ccc-%012d', random_int(1, 999999));
        $mediaid = (int)$DB->insert_record('local_digieramedia_media', (object)[
            'uuid' => $uuid,
            'name' => 'Permission matrix media',
            'description' => '',
            'mediatype' => 'pdf',
            'mimetype' => 'application/pdf',
            'owneruserid' => $owneruserid,
            'origincontextid' => $contextid,
            'origincourseid' => 0,
            'originsectionid' => 0,
            'visibility' => 'SHARED',
            'status' => 'ACTIVE',
            'currentversionid' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
            'createdby' => $owneruserid,
            'modifiedby' => $owneruserid,
        ]);

        return [$mediaid, $uuid];
    }
}
