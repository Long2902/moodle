<?php
namespace local_digieranative;

final class renderer_test extends \advanced_testcase {
    public function test_renderer_escapes_text_and_renders_answer_block(): void {
        $json = json_encode([
            'type' => 'worksheet', 'version' => 1,
            'content' => [
                ['type' => 'question', 'attrs' => ['id' => 'q1'],
                    'content' => [['type' => 'text', 'text' => '<b>Không chạy HTML</b>']]],
                ['type' => 'longAnswer', 'attrs' => ['questionId' => 'q1', 'minHeight' => 160]],
            ],
        ]);
        $html = \local_digieranative\document\renderer::render_json($json, 'student', []);
        $this->assertStringContainsString('&lt;b&gt;Không chạy HTML&lt;/b&gt;', $html);
        $this->assertStringContainsString('data-digiera-answer="long"', $html);
    }

    public function test_renderer_hides_teacher_notes_from_student(): void {
        $json = json_encode([
            'type' => 'worksheet', 'version' => 1,
            'content' => [[
                'type' => 'teacherOnlyNote', 'attrs' => ['label' => 'GV'],
                'content' => [['type' => 'text', 'text' => 'Bí mật']],
            ]],
        ]);
        $html = \local_digieranative\document\renderer::render_json($json, 'student', []);
        $this->assertStringNotContainsString('Bí mật', $html);
    }
}
