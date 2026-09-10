<?php

namespace local_digieramedia\external;

use context;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

final class create_reference extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Editor context id'),
            'mediauuid' => new external_value(PARAM_ALPHANUMEXT, 'Media UUID'),
            'displayprofile' => new external_value(PARAM_ALPHANUMEXT, 'Display profile', VALUE_DEFAULT, 'embedded'),
            'versionmode' => new external_value(PARAM_ALPHANUMEXT, 'FOLLOW_CURRENT or PINNED_VERSION', VALUE_DEFAULT, 'FOLLOW_CURRENT'),
            'pinnedversionid' => new external_value(PARAM_INT, 'Version id when pinned', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(
        int $contextid,
        string $mediauuid,
        string $displayprofile = 'embedded',
        string $versionmode = 'FOLLOW_CURRENT',
        int $pinnedversionid = 0
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            compact('contextid', 'mediauuid', 'displayprofile', 'versionmode', 'pinnedversionid')
        );
        $context = context::instance_by_id($params['contextid'], MUST_EXIST);
        self::validate_context($context);
        require_capability('local/digieramedia:insert', $context);

        $media = $DB->get_record(
            'local_digieramedia_media',
            ['uuid' => $params['mediauuid'], 'status' => 'ACTIVE'],
            '*',
            MUST_EXIST
        );

        $courseid = 0;
        try {
            $coursecontext = $context->get_course_context(false);
            if ($coursecontext) {
                $courseid = (int)$coursecontext->instanceid;
            }
        } catch (\Throwable $e) {
            $courseid = 0;
        }

        $canviewall = has_capability('local/digieramedia:viewall', $context);
        $visibility = (string)$media->visibility;
        $visible = (int)$media->owneruserid === (int)$USER->id
            || in_array($visibility, ['GLOBAL', 'SHARED'], true)
            || ($visibility === 'COURSE' && $courseid > 0 && (int)$media->origincourseid === $courseid);
        if (!$canviewall && !$visible) {
            throw new \required_capability_exception($context, 'local/digieramedia:view', 'nopermissions', '');
        }

        $versionmode = strtoupper((string)$params['versionmode']);
        if (!in_array($versionmode, ['FOLLOW_CURRENT', 'PINNED_VERSION'], true)) {
            throw new \invalid_parameter_exception('Unsupported DIGIERA version mode.');
        }
        $pinnedversionid = 0;
        if ($versionmode === 'PINNED_VERSION') {
            $pinnedversionid = (int)$params['pinnedversionid'];
            if ($pinnedversionid <= 0) {
                throw new \invalid_parameter_exception('Pinned version is required.');
            }
            $DB->get_record('local_digieramedia_version', [
                'id' => $pinnedversionid,
                'mediaid' => (int)$media->id,
                'status' => 'READY',
            ], '*', MUST_EXIST);
        }

        $uuid = self::uuidv4();
        $now = time();
        $record = (object)[
            'uuid' => $uuid,
            'mediaid' => (int)$media->id,
            'contextid' => (int)$context->id,
            'courseid' => $courseid,
            'cmid' => 0,
            'component' => 'tiny_digieramedia',
            'entitytype' => 'editor',
            'entityid' => 0,
            'fieldname' => 'content',
            'displayprofile' => $params['displayprofile'],
            'versionmode' => $versionmode,
            'pinnedversionid' => $pinnedversionid,
            'status' => 'DRAFT',
            'createdby' => (int)$USER->id,
            'alttext' => null,
            'caption' => null,
            'optionsjson' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $DB->insert_record('local_digieramedia_reference', $record);
        (new \local_digieramedia\service\recent_service())->touch(
            (int)$USER->id,
            (int)$media->id,
            (int)$context->id,
            'CREATE_REFERENCE'
        );

        return [
            'referenceuuid' => $uuid,
            'mediauuid' => (string)$media->uuid,
            'name' => (string)$media->name,
            'mediatype' => (string)$media->mediatype,
            'marker' => '[[digiera-ref:' . $uuid . ']]',
            'versionmode' => $versionmode,
            'pinnedversionid' => $pinnedversionid,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'referenceuuid' => new external_value(PARAM_ALPHANUMEXT, 'Reference UUID'),
            'mediauuid' => new external_value(PARAM_ALPHANUMEXT, 'Media UUID'),
            'name' => new external_value(PARAM_TEXT, 'Media name'),
            'mediatype' => new external_value(PARAM_ALPHA, 'Media type'),
            'marker' => new external_value(PARAM_RAW, 'Stored marker'),
            'versionmode' => new external_value(PARAM_ALPHANUMEXT, 'Version mode'),
            'pinnedversionid' => new external_value(PARAM_INT, 'Pinned version id or zero'),
        ]);
    }

    private static function uuidv4(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
