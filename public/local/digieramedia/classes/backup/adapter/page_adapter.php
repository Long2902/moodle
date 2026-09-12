<?php
namespace local_digieramedia\backup\adapter;

use local_digieramedia\backup\content_adapter_interface;
use local_digieramedia\backup\content_record;

final class page_adapter implements content_adapter_interface {
    public function supports(string $modname): bool {
        return $modname === 'page';
    }

    public function source_records(int $instanceid): array {
        global $DB;
        $page = $DB->get_record('page', ['id' => $instanceid], 'id,content', MUST_EXIST);
        return [new content_record('page', (int)$page->id, 'content', (string)$page->content)];
    }
}
