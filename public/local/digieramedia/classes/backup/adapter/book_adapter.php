<?php
namespace local_digieramedia\backup\adapter;

use local_digieramedia\backup\content_adapter_interface;
use local_digieramedia\backup\content_record;

final class book_adapter implements content_adapter_interface {
    public function supports(string $modname): bool {
        return $modname === 'book';
    }

    public function source_records(int $instanceid): array {
        global $DB;
        $chapters = $DB->get_records('book_chapters', ['bookid' => $instanceid], 'pagenum ASC, id ASC', 'id,content');
        $records = [];
        foreach ($chapters as $chapter) {
            $records[] = new content_record('book', (int)$chapter->id, 'content', (string)$chapter->content);
        }
        return $records;
    }
}
