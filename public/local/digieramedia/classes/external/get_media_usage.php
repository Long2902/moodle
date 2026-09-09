<?php

namespace local_digieramedia\external;

use context;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_digieramedia\service\usage_service;

final class get_media_usage extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Editor context id'),
            'mediauuid' => new external_value(PARAM_ALPHANUMEXT, 'Media UUID'),
        ]);
    }

    public static function execute(int $contextid, string $mediauuid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), compact('contextid', 'mediauuid'));
        $context = context::instance_by_id($params['contextid'], MUST_EXIST);
        self::validate_context($context);
        require_capability('local/digieramedia:viewusage', $context);
        return (new usage_service())->get((int)$USER->id, $context, $params['mediauuid']);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'mediauuid' => new external_value(PARAM_ALPHANUMEXT, 'Media UUID'),
            'mediastatus' => new external_value(PARAM_ALPHA, 'Media lifecycle status'),
            'livecount' => new external_value(PARAM_INT, 'ACTIVE + DRAFT reference count'),
            'visiblecount' => new external_value(PARAM_INT, 'Usage rows visible to the caller'),
            'canviewusage' => new external_value(PARAM_BOOL, 'Caller may view usage'),
            'cantrash' => new external_value(PARAM_BOOL, 'Caller may soft-trash this Media'),
            'canrestore' => new external_value(PARAM_BOOL, 'Caller may restore this Media'),
            'canpurge' => new external_value(PARAM_BOOL, 'Caller may permanently purge this Media'),
            'canforcepurge' => new external_value(PARAM_BOOL, 'Caller may force purge live references'),
            'deletedat' => new external_value(PARAM_INT, 'Trash timestamp or zero'),
            'deletedby' => new external_value(PARAM_INT, 'Trash actor id or zero'),
            'deletedbyname' => new external_value(PARAM_TEXT, 'Trash actor display name'),
            'reason' => new external_value(PARAM_TEXT, 'Trash reason'),
            'usages' => new external_multiple_structure(new external_single_structure([
                'referenceuuid' => new external_value(PARAM_ALPHANUMEXT, 'Reference UUID'),
                'status' => new external_value(PARAM_ALPHA, 'Reference status'),
                'courseid' => new external_value(PARAM_INT, 'Course id or zero'),
                'coursename' => new external_value(PARAM_TEXT, 'Course name when visible'),
                'cmid' => new external_value(PARAM_INT, 'Course-module id or zero'),
                'activityname' => new external_value(PARAM_TEXT, 'Activity name when visible'),
                'component' => new external_value(PARAM_COMPONENT, 'Reference component'),
                'entitytype' => new external_value(PARAM_ALPHANUMEXT, 'Entity type'),
                'entityid' => new external_value(PARAM_INT, 'Entity id'),
                'fieldname' => new external_value(PARAM_ALPHANUMEXT, 'Field name'),
                'versionmode' => new external_value(PARAM_ALPHANUMEXT, 'Reference version mode'),
                'pinnedversionno' => new external_value(PARAM_INT, 'Pinned logical version number or zero'),
                'url' => new external_value(PARAM_RAW_TRIMMED, 'Safe Moodle location URL or empty'),
                'fallback' => new external_value(PARAM_TEXT, 'Fallback registry location label'),
                'timecreated' => new external_value(PARAM_INT, 'Reference creation time'),
                'timemodified' => new external_value(PARAM_INT, 'Reference modification time'),
            ])),
        ]);
    }
}
