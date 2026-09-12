<?php
namespace local_digieramedia\backup\adapter;

use local_digieramedia\backup\content_adapter_interface;
use local_digieramedia\backup\content_record;

final class generic_intro_adapter implements content_adapter_interface {
    public function __construct(private string $modname) {}

    public function supports(string $modname): bool {
        global $DB;
        if ($modname !== $this->modname || $modname === 'subsection' || !preg_match('/^[a-z][a-z0-9_]*$/', $modname)) {
            return false;
        }
        $manager = $DB->get_manager();
        $table = new \xmldb_table($modname);
        return $manager->table_exists($table) && $manager->field_exists($table, new \xmldb_field('intro'));
    }

    public function source_records(int $instanceid): array {
        global $DB;
        if (!$this->supports($this->modname)) {
            return [];
        }
        $record = $DB->get_record($this->modname, ['id' => $instanceid], 'id,intro', MUST_EXIST);
        return [new content_record('generic_intro', (int)$record->id, 'intro', (string)$record->intro)];
    }
}
