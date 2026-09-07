<?php
namespace local_worksheetlibrary;

use advanced_testcase;
use local_worksheetlibrary\exception\native_revision_conflict_exception;
use local_worksheetlibrary\service\native_version_service;

final class native_version_service_test extends advanced_testcase {
    private function create_native_draft(): int {
        global $DB;
        $now = time();
        $itemid = $DB->insert_record('wslib_item', (object)[
            'folderid' => 0,
            'name' => 'Native test worksheet',
            'kind' => 'native',
            'currentversionid' => 0,
            'archived' => 0,
            'createdby' => 2,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        return (int)$DB->insert_record('wslib_version', (object)[
            'itemid' => $itemid,
            'versionno' => 1,
            'state' => 'draft',
            'contenthtml' => '',
            'nativejson' => null,
            'schemaversion' => null,
            'revision' => 0,
            'renderedhtml' => null,
            'filename' => '',
            'mimetype' => '',
            'contenthash' => '',
            'createdby' => 2,
            'timecreated' => $now,
            'timepublished' => 0,
        ]);
    }

    private function valid_json(string $text = 'Native test'): string {
        return json_encode([
            'type' => 'worksheet',
            'version' => 1,
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => $text]],
            ]],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function test_save_increments_revision_and_persists_server_render(): void {
        $this->resetAfterTest(true);
        $versionid = $this->create_native_draft();
        $json = $this->valid_json('Server render');

        $saved = native_version_service::save_draft($versionid, 0, $json, 2);

        $this->assertSame(1, (int)$saved->revision);
        $this->assertSame(1, (int)$saved->schemaversion);
        $this->assertSame($json, $saved->nativejson);
        $this->assertStringContainsString('Server render', $saved->renderedhtml);
        $this->assertSame(hash('sha256', $json), $saved->contenthash);
    }

    public function test_stale_revision_conflicts_without_mutation(): void {
        global $DB;
        $this->resetAfterTest(true);
        $versionid = $this->create_native_draft();
        $first = $this->valid_json('First');
        native_version_service::save_draft($versionid, 0, $first, 2);

        try {
            native_version_service::save_draft($versionid, 0, $this->valid_json('Stale'), 2);
            $this->fail('Expected Native revision conflict');
        } catch (native_revision_conflict_exception $e) {
            $this->assertSame(1, $e->current_revision());
        }

        $stored = $DB->get_record('wslib_version', ['id' => $versionid], '*', MUST_EXIST);
        $this->assertSame(1, (int)$stored->revision);
        $this->assertSame($first, $stored->nativejson);
    }

    public function test_invalid_native_json_is_rejected(): void {
        $this->resetAfterTest(true);
        $versionid = $this->create_native_draft();
        $this->expectException(\invalid_parameter_exception::class);
        native_version_service::save_draft($versionid, 0, '{"type":"wrong"}', 2);
    }
}
