<?php
class invalid_parameter_exception extends Exception {}
function s($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
require_once __DIR__ . '/../classes/document/schema.php';
require_once __DIR__ . '/../classes/document/validator.php';
require_once __DIR__ . '/../classes/document/renderer.php';

use local_digieranative\document\renderer;

function assert_contains(string $needle, string $haystack, string $message): void {
    if (!str_contains($haystack, $needle)) { throw new RuntimeException($message . "\nHTML: " . $haystack); }
}
function assert_not_contains(string $needle, string $haystack, string $message): void {
    if (str_contains($haystack, $needle)) { throw new RuntimeException($message . "\nHTML: " . $haystack); }
}

$json = json_encode([
    'type' => 'worksheet', 'version' => 1,
    'content' => [
        ['type' => 'question', 'attrs' => ['id' => 'q1'],
            'content' => [['type' => 'text', 'text' => '<b>Không chạy HTML</b>', 'marks' => [['type' => 'bold']]]]],
        ['type' => 'longAnswer', 'attrs' => ['questionId' => 'q1', 'minHeight' => 160]],
        ['type' => 'teacherOnlyNote', 'attrs' => ['label' => 'GV'], 'content' => [['type' => 'text', 'text' => 'Đáp án bí mật']]],
        ['type' => 'image', 'attrs' => ['assetKey' => 'img_1', 'alt' => 'Ảnh minh họa']],
    ],
], JSON_UNESCAPED_UNICODE);

$student = renderer::render_json($json, 'student', ['img_1' => 'https://cdn.example.test/a.png']);
assert_contains('&lt;b&gt;Không chạy HTML&lt;/b&gt;', $student, 'renderer must escape text');
assert_contains('data-digiera-answer="long"', $student, 'renderer must render long answer block');
assert_contains('<strong>', $student, 'renderer must render bold mark');
assert_contains('https://cdn.example.test/a.png', $student, 'renderer must use managed asset url');
assert_not_contains('Đáp án bí mật', $student, 'teacher-only note must be hidden from students');

$teacher = renderer::render_json($json, 'teacher', ['img_1' => 'https://cdn.example.test/a.png']);
assert_contains('Đáp án bí mật', $teacher, 'teacher must see teacher-only note');

echo "RENDERER_STANDALONE_PASS\n";
