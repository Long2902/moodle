<?php

namespace local_digieramedia\external;

use context;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_digieramedia\service\lifecycle_service;

final class purge_media extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Editor context id'),
            'mediauuid' => new external_value(PARAM_ALPHANUMEXT, 'Media UUID'),
            'force' => new external_value(PARAM_BOOL, 'Force purge live references', VALUE_DEFAULT, false),
        ]);
    }

    public static function execute(int $contextid, string $mediauuid, bool $force = false): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('contextid', 'mediauuid', 'force'));
        $context = context::instance_by_id($params['contextid'], MUST_EXIST);
        self::validate_context($context);
        return (new lifecycle_service())->purge((int)$USER->id, $context, $params['mediauuid'], (bool)$params['force']);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'mediauuid' => new external_value(PARAM_ALPHANUMEXT, 'Media UUID'),
            'status' => new external_value(PARAM_ALPHA, 'Media lifecycle status'),
            'visibility' => new external_value(PARAM_ALPHA, 'Media visibility'),
            'livecount' => new external_value(PARAM_INT, 'Live reference count after purge'),
            'unresolvedreferences' => new external_value(PARAM_INT, 'References changed to UNRESOLVED'),
            'deletedversions' => new external_value(PARAM_INT, 'Physically deleted version count'),
        ]);
    }
}
