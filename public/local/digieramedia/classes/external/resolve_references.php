<?php

namespace local_digieramedia\external;

use context;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

final class resolve_references extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Editor context id'),
            'referenceuuids' => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'DIGIERA reference UUID')
            ),
        ]);
    }

    public static function execute(int $contextid, array $referenceuuids): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'referenceuuids' => $referenceuuids,
        ]);

        $context = context::instance_by_id($params['contextid'], MUST_EXIST);
        self::validate_context($context);
        require_capability('local/digieramedia:view', $context);

        $items = [];
        $seen = [];

        foreach ($params['referenceuuids'] as $uuid) {
            if (isset($seen[$uuid])) {
                continue;
            }
            $seen[$uuid] = true;

            $sql = "SELECT r.uuid AS referenceuuid,
                           m.uuid AS mediauuid,
                           m.name,
                           m.mediatype,
                           m.status AS mediastatus
                      FROM {local_digieramedia_reference} r
                      JOIN {local_digieramedia_media} m ON m.id = r.mediaid
                     WHERE r.uuid = :uuid
                       AND r.contextid = :contextid";

            $record = $DB->get_record_sql($sql, [
                'uuid' => $uuid,
                'contextid' => (int)$context->id,
            ]);

            if (!$record) {
                continue;
            }

            $items[] = [
                'referenceuuid' => (string)$record->referenceuuid,
                'mediauuid' => (string)$record->mediauuid,
                'name' => (string)$record->name,
                'mediatype' => (string)$record->mediatype,
                'mediastatus' => (string)$record->mediastatus,
            ];
        }

        return $items;
    }

    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'referenceuuid' => new external_value(PARAM_ALPHANUMEXT, 'Reference UUID'),
                'mediauuid' => new external_value(PARAM_ALPHANUMEXT, 'Media UUID'),
                'name' => new external_value(PARAM_TEXT, 'Media name'),
                'mediatype' => new external_value(PARAM_ALPHANUMEXT, 'Media type'),
                'mediastatus' => new external_value(PARAM_ALPHANUMEXT, 'Media status'),
            ])
        );
    }
}
