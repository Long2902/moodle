<?php
namespace local_worksheetlibrary\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_worksheetlibrary\exception\native_revision_conflict_exception;
use local_worksheetlibrary\service\native_version_service;

final class save_native_draft extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'versionid' => new external_value(PARAM_INT, 'Native draft version id'),
            'expectedrevision' => new external_value(PARAM_INT, 'Expected optimistic revision'),
            'nativejson' => new external_value(PARAM_RAW, 'Canonical Native worksheet JSON'),
        ]);
    }

    public static function execute(int $versionid, int $expectedrevision, string $nativejson): array {
        global $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            compact('versionid', 'expectedrevision', 'nativejson')
        );
        self::validate_context(\context_system::instance());
        \local_worksheetlibrary\service\access_service::require_author();

        try {
            $saved = native_version_service::save_draft(
                (int)$params['versionid'],
                (int)$params['expectedrevision'],
                (string)$params['nativejson'],
                (int)$USER->id
            );
            return [
                'ok' => true,
                'conflict' => false,
                'revision' => (int)$saved->revision,
                'schemaversion' => (int)$saved->schemaversion,
                'renderedhtml' => (string)$saved->renderedhtml,
            ];
        } catch (native_revision_conflict_exception $e) {
            return [
                'ok' => false,
                'conflict' => true,
                'revision' => $e->current_revision(),
                'schemaversion' => 0,
                'renderedhtml' => '',
            ];
        }
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ok' => new external_value(PARAM_BOOL, 'Saved'),
            'conflict' => new external_value(PARAM_BOOL, 'Optimistic revision conflict'),
            'revision' => new external_value(PARAM_INT, 'Current revision'),
            'schemaversion' => new external_value(PARAM_INT, 'Native schema version'),
            'renderedhtml' => new external_value(PARAM_RAW, 'Server-rendered preview HTML'),
        ]);
    }
}
