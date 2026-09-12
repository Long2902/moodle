<?php
namespace local_digieramedia\repository;
class version_repository {
    public function get(int $id): \stdClass { global $DB; return $DB->get_record('local_digieramedia_version',['id'=>$id],'*',MUST_EXIST); }
    public function list_for_media(int $mediaid): array { global $DB; return array_values($DB->get_records('local_digieramedia_version',['mediaid'=>$mediaid],'versionno ASC')); }
    public function insert(\stdClass $record): int { global $DB; return (int)$DB->insert_record('local_digieramedia_version',$record); }
    public function update(\stdClass $record): void { global $DB; $DB->update_record('local_digieramedia_version',$record); }
    public function max_version_no(int $mediaid): int { global $DB; return (int)$DB->get_field_sql('SELECT COALESCE(MAX(versionno),0) FROM {local_digieramedia_version} WHERE mediaid = ?',[$mediaid]); }
    public function get_by_media_and_versionno(int $mediaid, int $versionno): ?\stdClass {
        global $DB;
        return $DB->get_record('local_digieramedia_version', ['mediaid' => $mediaid, 'versionno' => $versionno]) ?: null;
    }
}
