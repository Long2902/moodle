<?php
namespace local_digieramedia\backup\adapter;

use local_digieramedia\backup\content_adapter_interface;
use local_digieramedia\backup\content_record;

final class label_adapter implements content_adapter_interface {
    public function supports(string $modname): bool {
        return $modname === 'label';
    }

    public function source_records(int $instanceid): array {
        global $DB;
        $label = $DB->get_record('label', ['id' => $instanceid], 'id,intro', MUST_EXIST);
        return [new content_record('label', (int)$label->id, 'intro', (string)$label->intro)];
    }
}
