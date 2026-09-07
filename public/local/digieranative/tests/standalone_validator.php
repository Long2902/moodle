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
        'content' => [['type' => 'text', 'text' => 'Xin chào']],
    ]],
], JSON_UNESCAPED_UNICODE);
$doc = validator::validate_json($valid);
assert_true($doc['version'] === 1, 'minimal document must be accepted');

$cases = [
    ['{"type":"worksheet","version":1,"content":[{"type":"script"}]}', 'unknown node'],
    ['{"type":"worksheet","version":1,"content":[{"type":"heading","attrs":{"level":7}}]}', 'heading level'],
    ['{"type":"worksheet","version":1,"content":[{"type":"image","attrs":{"assetKey":"../evil"}}]}', 'unsafe assetKey'],
    ['{"type":"worksheet","version":1,"content":[{"type":"shortAnswer","attrs":{"questionId":"<bad>"}}]}', 'unsafe question id'],
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
