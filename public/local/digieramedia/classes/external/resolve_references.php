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
                           r.versionmode,
                           r.pinnedversionid,
                           r.alttext,
                           r.caption,
                           r.displayprofile,
                           m.uuid AS mediauuid,
                           m.name,
                           m.mediatype,
                           m.status AS mediastatus,
                           m.visibility,
                           m.timemodified AS modified,
                           COALESCE(v.filesize, 0) AS size
                      FROM {local_digieramedia_reference} r
                      JOIN {local_digieramedia_media} m ON m.id = r.mediaid
                 LEFT JOIN {local_digieramedia_version} v ON v.id = m.currentversionid
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
                'versionmode' => (string)$record->versionmode,
                'pinnedversionid' => (int)$record->pinnedversionid,
                'visibility' => (string)$record->visibility,
                'modified' => (int)$record->modified,
                'size' => (int)$record->size,
                'alttext' => $record->alttext !== null ? (string)$record->alttext : '',
                'caption' => $record->caption !== null ? (string)$record->caption : '',
                'displayprofile' => (string)($record->displayprofile ?? 'embedded'),
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
                'versionmode' => new external_value(PARAM_ALPHANUMEXT, 'Saved reference version mode'),
                'pinnedversionid' => new external_value(PARAM_INT, 'Saved pinned version id or zero'),
                'visibility' => new external_value(PARAM_ALPHANUMEXT, 'Media visibility'),
                'modified' => new external_value(PARAM_INT, 'Media modified time'),
                'size' => new external_value(PARAM_INT, 'Current version bytes'),
                'alttext' => new external_value(PARAM_TEXT, 'Alternative text', VALUE_OPTIONAL),
                'caption' => new external_value(PARAM_TEXT, 'Caption', VALUE_OPTIONAL),
                'displayprofile' => new external_value(PARAM_ALPHANUMEXT, 'Display profile', VALUE_OPTIONAL),
            ])
        );
    }
}
