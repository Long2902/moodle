<?php

namespace local_digieramedia\external;

use context;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_digieramedia\service\upload_session_service;

final class create_upload_session extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Editor context id'),
            'filename' => new external_value(PARAM_RAW_TRIMMED, 'Original file name'),
            'mimetype' => new external_value(PARAM_RAW_TRIMMED, 'Browser MIME type', VALUE_DEFAULT, 'application/octet-stream'),
            'filesize' => new external_value(PARAM_INT, 'File size in bytes'),
            'replacemediauuid' => new external_value(PARAM_ALPHANUMEXT, 'Logical media UUID to replace', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(
        int $contextid,
        string $filename,
        string $mimetype,
        int $filesize,
        string $replacemediauuid = ''
    ): array {
        global $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            compact('contextid', 'filename', 'mimetype', 'filesize', 'replacemediauuid')
        );
        $context = context::instance_by_id($params['contextid'], MUST_EXIST);
        self::validate_context($context);
        require_capability('local/digieramedia:upload', $context);

        return (new upload_session_service())->create(
            (int)$USER->id,
            $context,
            $params['filename'],
            $params['mimetype'],
            (int)$params['filesize'],
            $params['replacemediauuid']
        );
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sessionuuid' => new external_value(PARAM_ALPHANUMEXT, 'Upload session UUID'),
            'uploadtype' => new external_value(PARAM_ALPHA, 'single'),
            'uploadurl' => new external_value(PARAM_RAW, 'Short-lived presigned R2 URL'),
            'requiredheaders' => new external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_RAW_TRIMMED, 'HTTP header name'),
                'value' => new external_value(PARAM_RAW, 'HTTP header value'),
            ])),
            'expiresat' => new external_value(PARAM_INT, 'Presigned URL expiry timestamp'),
            'maxsinglebytes' => new external_value(PARAM_INT, 'Current single-PUT maximum'),
        ]);
    }
}
