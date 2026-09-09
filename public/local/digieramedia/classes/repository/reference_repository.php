<?php

namespace local_digieramedia\repository;

class reference_repository {
    public function get(int $id): \stdClass {
        global $DB;
        return $DB->get_record('local_digieramedia_reference', ['id' => $id], '*', MUST_EXIST);
    }

    public function count_active(int $mediaid): int {
        global $DB;
        return (int)$DB->count_records('local_digieramedia_reference', [
            'mediaid' => $mediaid,
            'status' => 'ACTIVE',
        ]);
    }

    public function count_live(int $mediaid): int {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal(['ACTIVE', 'DRAFT'], SQL_PARAMS_NAMED, 'livestatus');
        $params['mediaid'] = $mediaid;
        return (int)$DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {local_digieramedia_reference}
              WHERE mediaid = :mediaid
                AND status {$insql}",
            $params
        );
    }

    public function list_live_for_media(int $mediaid): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal(['ACTIVE', 'DRAFT'], SQL_PARAMS_NAMED, 'livestatus');
        $params['mediaid'] = $mediaid;
        return array_values($DB->get_records_sql(
            "SELECT *
               FROM {local_digieramedia_reference}
              WHERE mediaid = :mediaid
                AND status {$insql}
           ORDER BY timemodified DESC, id DESC",
            $params
        ));
    }

    public function mark_live_unresolved(int $mediaid, int $now): int {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal(['ACTIVE', 'DRAFT'], SQL_PARAMS_NAMED, 'livestatus');
        $params['mediaid'] = $mediaid;
        $params['status'] = 'UNRESOLVED';
        $params['timemodified'] = $now;
        $sql = "UPDATE {local_digieramedia_reference}
                   SET status = :status,
                       timemodified = :timemodified
                 WHERE mediaid = :mediaid
                   AND status {$insql}";
        $DB->execute($sql, $params);
        return $this->count_status($mediaid, 'UNRESOLVED');
    }

    public function count_status(int $mediaid, string $status): int {
        global $DB;
        return (int)$DB->count_records('local_digieramedia_reference', [
            'mediaid' => $mediaid,
            'status' => $status,
        ]);
    }

    public function get_by_uuid(string $uuid): ?\stdClass {
        global $DB;
        return $DB->get_record('local_digieramedia_reference', ['uuid' => $uuid]) ?: null;
    }

    public function insert(\stdClass $record): int {
        global $DB;
        return (int)$DB->insert_record('local_digieramedia_reference', $record);
    }

    public function update(\stdClass $record): void {
        global $DB;
        $DB->update_record('local_digieramedia_reference', $record);
    }

    public function list_active_for_usage(string $component, int $entityid, string $fieldname): array {
        global $DB;
        return array_values($DB->get_records('local_digieramedia_reference', [
            'component' => $component,
            'entityid' => $entityid,
            'fieldname' => $fieldname,
            'status' => 'ACTIVE',
        ]));
    }
}
