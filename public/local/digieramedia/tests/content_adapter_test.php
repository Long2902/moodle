<?php
namespace local_digieramedia;

use local_digieramedia\backup\adapter\book_adapter;
use local_digieramedia\backup\adapter\generic_intro_adapter;
use local_digieramedia\backup\adapter\label_adapter;
use local_digieramedia\backup\adapter\page_adapter;
use local_digieramedia\backup\content_adapter_registry;

/**
 * @covers \local_digieramedia\backup\content_adapter_registry
 * @covers \local_digieramedia\backup\adapter\page_adapter
 * @covers \local_digieramedia\backup\adapter\label_adapter
 * @covers \local_digieramedia\backup\adapter\book_adapter
 * @covers \local_digieramedia\backup\adapter\generic_intro_adapter
 */
final class content_adapter_test extends \advanced_testcase {
    public function test_page_adapter_reads_content(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'DIGIERA Page',
            'content' => '<p>Page [[digiera-ref:11111111-1111-4111-8111-111111111111]]</p>',
            'contentformat' => FORMAT_HTML,
        ]);

        $records = (new page_adapter())->source_records((int)$page->id);
        $this->assertCount(1, $records);
        $this->assertSame('page', $records[0]->adapter);
        $this->assertSame((int)$page->id, $records[0]->sourceentityid);
        $this->assertSame('content', $records[0]->fieldname);
        $this->assertStringContainsString('digiera-ref', $records[0]->content);
    }

    public function test_label_adapter_reads_intro(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $label = $this->getDataGenerator()->create_module('label', [
            'course' => $course->id,
            'intro' => '<p>Label [[digiera-ref:22222222-2222-4222-8222-222222222222]]</p>',
            'introformat' => FORMAT_HTML,
        ]);

        $records = (new label_adapter())->source_records((int)$label->id);
        $this->assertCount(1, $records);
        $this->assertSame('label', $records[0]->adapter);
        $this->assertSame((int)$label->id, $records[0]->sourceentityid);
        $this->assertSame('intro', $records[0]->fieldname);
        $this->assertStringContainsString('digiera-ref', $records[0]->content);
    }

    public function test_book_adapter_returns_each_chapter_in_order(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', [
            'course' => $course->id,
            'name' => 'DIGIERA Book',
        ]);
        $now = time();
        $firstid = (int)$DB->insert_record('book_chapters', (object)[
            'bookid' => $book->id,
            'pagenum' => 1,
            'subchapter' => 0,
            'title' => 'One',
            'content' => 'One [[digiera-ref:33333333-3333-4333-8333-333333333333]]',
            'contentformat' => FORMAT_HTML,
            'hidden' => 0,
            'timemodified' => $now,
            'importsrc' => '',
        ]);
        $secondid = (int)$DB->insert_record('book_chapters', (object)[
            'bookid' => $book->id,
            'pagenum' => 2,
            'subchapter' => 0,
            'title' => 'Two',
            'content' => 'Two [[digiera-ref:44444444-4444-4444-8444-444444444444]]',
            'contentformat' => FORMAT_HTML,
            'hidden' => 0,
            'timemodified' => $now,
            'importsrc' => '',
        ]);

        $records = (new book_adapter())->source_records((int)$book->id);
        $this->assertCount(2, $records);
        $this->assertSame([$firstid, $secondid], array_map(
            static fn($record): int => $record->sourceentityid,
            $records
        ));
        $this->assertSame(['content', 'content'], array_column(array_map(
            static fn($record): array => ['fieldname' => $record->fieldname],
            $records
        ), 'fieldname'));
    }

    public function test_generic_intro_adapter_reads_real_intro_column(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'name' => 'DIGIERA Assignment',
            'intro' => '<p>Assignment [[digiera-ref:55555555-5555-4555-8555-555555555555]]</p>',
            'introformat' => FORMAT_HTML,
        ]);

        $adapter = new generic_intro_adapter('assign');
        $this->assertTrue($adapter->supports('assign'));
        $records = $adapter->source_records((int)$assign->id);
        $this->assertCount(1, $records);
        $this->assertSame('generic_intro', $records[0]->adapter);
        $this->assertSame('intro', $records[0]->fieldname);
        $this->assertStringContainsString('digiera-ref', $records[0]->content);
    }

    public function test_registry_rejects_module_without_supported_content(): void {
        $this->resetAfterTest();
        $registry = new content_adapter_registry();
        $this->assertNull($registry->for_module('subsection'));
    }
}
