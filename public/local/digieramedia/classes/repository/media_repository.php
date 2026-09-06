<?php
namespace local_digieramedia\repository;
class media_repository {
    public function insert(\stdClass $record): int { global $DB; return (int)$DB->insert_record('local_digieramedia_media',$record); }
    public function get(int $id): \stdClass { global $DB; return $DB->get_record('local_digieramedia_media',['id'=>$id],'*',MUST_EXIST); }
    public function update(\stdClass $record): void { global $DB; $DB->update_record('local_digieramedia_media',$record); }
    public function find_by_uuid(string $uuid): ?\stdClass { global $DB; return $DB->get_record('local_digieramedia_media',['uuid'=>$uuid]) ?: null; }
    public function find_by_storage(string $bucket, string $objectkey): ?\stdClass { global $DB; return $DB->get_record_sql('SELECT m.* FROM {local_digieramedia_media} m JOIN {local_digieramedia_version} v ON v.mediaid=m.id WHERE v.bucket=? AND v.objectkey=? ORDER BY v.versionno DESC',[$bucket,$objectkey],IGNORE_MULTIPLE) ?: null; }
}
