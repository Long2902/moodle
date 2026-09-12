<?php

namespace local_coursepublisher;

use local_coursepublisher\local\logical_placement;

/** @covers \local_coursepublisher\local\logical_placement */
final class logical_placement_test extends \advanced_testcase {
    public function test_normalise_text_collapses_whitespace_and_lowercases_vietnamese(): void {
        $this->assertSame('bài 5: ứng dụng ai', logical_placement::normalise_text("  BÀI 5:   ỨNG DỤNG AI  "));
    }

    public function test_unique_candidate_uses_module_section_and_ordinal_evidence(): void {
        $signature = [
            'kind' => 'activity', 'modname' => 'page', 'name' => 'video giới thiệu', 'ordinal' => 2,
            'section' => ['name' => 'bài 5', 'sectiontype' => 'normal'],
        ];
        $candidates = [
            ['id' => 10, 'kind' => 'activity', 'modname' => 'page', 'name' => 'video giới thiệu', 'ordinal' => 2,
                'section' => ['name' => 'bài 5', 'sectiontype' => 'normal']],
            ['id' => 11, 'kind' => 'activity', 'modname' => 'url', 'name' => 'video giới thiệu', 'ordinal' => 2,
                'section' => ['name' => 'bài 5', 'sectiontype' => 'normal']],
        ];
        $result = logical_placement::choose_unique_candidate($signature, $candidates);
        $this->assertSame('resolved', $result['status']);
        $this->assertSame(10, $result['candidate']['id']);
    }

    public function test_equal_duplicate_candidates_are_ambiguous(): void {
        $signature = ['kind' => 'section', 'sectiontype' => 'normal', 'name' => 'bài 5', 'ordinal' => 2];
        $candidates = [
            ['id' => 10, 'kind' => 'section', 'sectiontype' => 'normal', 'name' => 'bài 5', 'ordinal' => 2],
            ['id' => 11, 'kind' => 'section', 'sectiontype' => 'normal', 'name' => 'bài 5', 'ordinal' => 2],
        ];
        $result = logical_placement::choose_unique_candidate($signature, $candidates);
        $this->assertSame('ambiguous', $result['status']);
    }
}

// Resolver integration cases are exercised in a Moodle test environment because they read course structure.
