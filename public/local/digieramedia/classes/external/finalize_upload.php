<?php

namespace local_digieramedia\external;

use context;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_digieramedia\service\upload_finalize_service;

final class finalize_upload extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Editor context id'),
            'sessionuuid' => new external_value(PARAM_ALPHANUMEXT, 'Upload session UUID'),
        ]);
    }

    public static function execute(int $contextid, string $sessionuuid): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('contextid', 'sessionuuid'));
        $context = context::instance_by_id($params['contextid'], MUST_EXIST);
        self::validate_context($context);
        require_capability('local/digieramedia:upload', $context);

        return (new upload_finalize_service())->finalize((int)$USER->id, $context, $params['sessionuuid']);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'uuid' => new external_value(PARAM_ALPHANUMEXT, 'Media UUID'),
            'name' => new external_value(PARAM_TEXT, 'Media name'),
            'mediatype' => new external_value(PARAM_ALPHA, 'Media type'),
            'mimetype' => new external_value(PARAM_RAW_TRIMMED, 'MIME type'),
            'size' => new external_value(PARAM_INT, 'Bytes'),
            'modified' => new external_value(PARAM_INT, 'Modified time'),
            'visibility' => new external_value(PARAM_ALPHA, 'Visibility'),
            'status' => new external_value(PARAM_ALPHA, 'Media status'),
            'ready' => new external_value(PARAM_BOOL, 'Current version is ready'),
        ]);
    }
}
