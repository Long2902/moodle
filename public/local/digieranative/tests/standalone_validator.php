<?php
// Standalone contract tests for local_digieranative validator.
class invalid_parameter_exception extends Exception {}
require_once __DIR__ . '/../classes/document/schema.php';
require_once __DIR__ . '/../classes/document/validator.php';

use local_digieranative\document\validator;

function assert_true(bool $value, string $message): void {
    if (!$value) { throw new RuntimeException($message); }
}

$valid = json_encode([
    'type' => 'worksheet',
    'version' => 1,
    'content' => [[
        'type' => 'paragraph',
        'content' => [[
            'type' => 'text',
            'text' => 'Xin chào',
            'marks' => [[
                'type' => 'link',
                'attrs' => [
                    'href' => 'https://example.org/path',
                    'target' => '_blank',
                    'rel' => 'noopener noreferrer nofollow',
                    'class' => null,
                ],
            ]],
        ]],
    ]],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$doc = validator::validate_json($valid);
assert_true($doc['version'] === 1, 'minimal document with safe link must be accepted');

// Phase 5 CambridgePlus migration contract: server must accept the exact
// approved Native text-style marks and structural image metadata. This is
// intentionally RED until JS/PHP schema parity is implemented.
$cambridgevalid = json_encode([
    'type' => 'worksheet',
    'version' => 1,
    'content' => [
        [
            'type' => 'paragraph',
            'content' => [[
                'type' => 'text',
                'text' => 'Styled',
                'marks' => [
                    ['type' => 'fontFamily', 'attrs' => ['family' => 'Arial']],
                    ['type' => 'fontSize', 'attrs' => ['px' => 16]],
                    ['type' => 'textColor', 'attrs' => ['color' => '#123456']],
                    ['type' => 'highlight', 'attrs' => ['color' => '#FFF2A8']],
                ],
            ]],
        ],
        [
            'type' => 'image',
            'attrs' => [
                'assetKey' => 'asset_cambridge_1',
                'alt' => 'Ảnh',
                'title' => null,
                'caption' => 'Chú thích',
                'width' => null,
                'widthPercent' => 70,
                'align' => 'center',
                'cropX' => 0.1,
                'cropY' => 0.1,
                'cropW' => 0.8,
                'cropH' => 0.8,
                'rotation' => 90,
            ],
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$cambridgedoc = validator::validate_json($cambridgevalid);
assert_true($cambridgedoc['content'][1]['attrs']['widthPercent'] === 70, 'Cambridge image metadata must be accepted');

$cases = [
    ['{"type":"worksheet","version":1,"content":[{"type":"script"}]}', 'unknown node'],
    ['{"type":"worksheet","version":1,"content":[{"type":"heading","attrs":{"level":7}}]}', 'heading level'],
    ['{"type":"worksheet","version":1,"content":[{"type":"image","attrs":{"assetKey":"../evil"}}]}', 'unsafe assetKey'],
    ['{"type":"worksheet","version":1,"content":[{"type":"shortAnswer","attrs":{"questionId":"<bad>"}}]}', 'unsafe question id'],
    ['{"type":"worksheet","version":1,"content":[{"type":"paragraph","content":[{"type":"text","text":"bad","marks":[{"type":"link","attrs":{"href":"javascript:alert(1)"}}]}]}]}', 'unsafe link'],
    ['{"type":"worksheet","version":1,"content":[{"type":"paragraph","content":[{"type":"text","text":"bad","marks":[{"type":"fontFamily","attrs":{"family":"Comic Sans MS"}}]}]}]}', 'unsafe font family'],
    ['{"type":"worksheet","version":1,"content":[{"type":"paragraph","content":[{"type":"text","text":"bad","marks":[{"type":"fontSize","attrs":{"px":13}}]}]}]}', 'unsafe font size'],
    ['{"type":"worksheet","version":1,"content":[{"type":"paragraph","content":[{"type":"text","text":"bad","marks":[{"type":"highlight","attrs":{"color":"red"}}]}]}]}', 'unsafe highlight'],
    ['{"type":"worksheet","version":1,"content":[{"type":"image","attrs":{"assetKey":"safe","cropX":0.8,"cropY":0,"cropW":0.5,"cropH":1,"rotation":0,"widthPercent":50,"align":"center"}}]}', 'invalid crop bounds'],
];
foreach ($cases as [$json, $label]) {
    try {
        validator::validate_json($json);
        throw new RuntimeException("expected rejection: {$label}");
    } catch (invalid_parameter_exception $e) {
        // Expected.
    }
}

echo "VALIDATOR_STANDALONE_PASS\n";
