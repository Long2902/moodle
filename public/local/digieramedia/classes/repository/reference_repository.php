<?php
namespace local_digieramedia\repository;
class reference_repository {
    public function get(int $id): \stdClass { global $DB; return $DB->get_record('local_digieramedia_reference',['id'=>$id],'*',MUST_EXIST); }
    public function count_active(int $mediaid): int { global $DB; return (int)$DB->count_records('local_digieramedia_reference',['mediaid'=>$mediaid,'status'=>'ACTIVE']); }
    public function get_by_uuid(string $uuid): ?\stdClass { global $DB; return $DB->get_record('local_digieramedia_reference',['uuid'=>$uuid]) ?: null; }
    public function insert(\stdClass $record): int { global $DB; return (int)$DB->insert_record('local_digieramedia_reference',$record); }
    public function update(\stdClass $record): void { global $DB; $DB->update_record('local_digieramedia_reference',$record); }
    public function list_active_for_usage(string $component, int $entityid, string $fieldname): array { global $DB; return array_values($DB->get_records('local_digieramedia_reference',['component'=>$component,'entityid'=>$entityid,'fieldname'=>$fieldname,'status'=>'ACTIVE'])); }
}
