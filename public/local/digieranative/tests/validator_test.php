<?php
namespace local_digieranative;

final class validator_test extends \advanced_testcase {
    public function test_accepts_minimal_document(): void {
        $json = json_encode([
            'type' => 'worksheet',
            'version' => 1,
            'content' => [
                ['type' => 'paragraph', 'content' => [
                    ['type' => 'text', 'text' => 'Xin chào'],
                ]],
            ],
        ], JSON_UNESCAPED_UNICODE);
        $doc = \local_digieranative\document\validator::validate_json($json);
        $this->assertSame(1, $doc['version']);
    }

    public function test_rejects_unknown_node_type(): void {
        $this->expectException(\invalid_parameter_exception::class);
        \local_digieranative\document\validator::validate_json(
            '{"type":"worksheet","version":1,"content":[{"type":"script"}]}'
        );
    }

    public function test_rejects_unsafe_image_asset_key(): void {
        $this->expectException(\invalid_parameter_exception::class);
        \local_digieranative\document\validator::validate_json(
            '{"type":"worksheet","version":1,"content":[{"type":"image","attrs":{"assetKey":"../bad"}}]}'
        );
    }
}
