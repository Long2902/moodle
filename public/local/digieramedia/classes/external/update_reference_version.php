<?php

namespace local_digieramedia\external;

use context;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

final class update_reference_version extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Editor context id'),
            'referenceuuid' => new external_value(PARAM_ALPHANUMEXT, 'DIGIERA reference UUID'),
            'versionmode' => new external_value(PARAM_ALPHANUMEXT, 'FOLLOW_CURRENT or PINNED_VERSION'),
            'pinnedversionid' => new external_value(PARAM_INT, 'Version id when pinned', VALUE_DEFAULT, 0),
            'alttext' => new external_value(PARAM_TEXT, 'Updated alt text', VALUE_DEFAULT, null),
            'caption' => new external_value(PARAM_TEXT, 'Updated caption', VALUE_DEFAULT, null),
            'newmediauuid' => new external_value(PARAM_ALPHANUMEXT, 'Re-point reference to a different media item', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(
        int $contextid,
        string $referenceuuid,
        string $versionmode,
        int $pinnedversionid = 0,
        ?string $alttext = null,
        ?string $caption = null,
        string $newmediauuid = ''
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            compact('contextid', 'referenceuuid', 'versionmode', 'pinnedversionid', 'alttext', 'caption', 'newmediauuid')
        );

        $context = context::instance_by_id($params['contextid'], MUST_EXIST);
        self::validate_context($context);
        require_capability('local/digieramedia:view', $context);

        $canmanage = has_capability('local/digieramedia:manageversions', $context)
            || has_capability('local/digieramedia:replace', $context);
        if (!$canmanage) {
            throw new \required_capability_exception(
                $context,
                'local/digieramedia:manageversions',
                'nopermissions',
                ''
            );
        }

        $reference = $DB->get_record('local_digieramedia_reference', [
            'uuid' => $params['referenceuuid'],
            'contextid' => (int)$context->id,
        ], '*', MUST_EXIST);
        if ((string)$reference->status === 'TRASHED') {
            throw new \invalid_parameter_exception('Cannot update a trashed DIGIERA reference.');
        }

        $media = null;
        if (!empty($params['newmediauuid'])) {
            $media = $DB->get_record('local_digieramedia_media', [
                'uuid' => $params['newmediauuid'],
                'status' => 'ACTIVE',
            ], '*', MUST_EXIST);
            $reference->mediaid = (int)$media->id;
        } else {
            $media = $DB->get_record('local_digieramedia_media', [
                'id' => (int)$reference->mediaid,
                'status' => 'ACTIVE',
            ], '*', MUST_EXIST);
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

        $reference->versionmode = $versionmode;
        $reference->pinnedversionid = $pinnedversionid;
        if ($params['alttext'] !== null) {
            $reference->alttext = trim((string)$params['alttext']);
        }
        if ($params['caption'] !== null) {
            $reference->caption = trim((string)$params['caption']);
        }
        $reference->timemodified = time();
        $DB->update_record('local_digieramedia_reference', $reference);

        (new \local_digieramedia\service\recent_service())->touch(
            (int)$USER->id,
            (int)$media->id,
            (int)$context->id,
            'UPDATE_REFERENCE'
        );

        return [
            'referenceuuid' => (string)$reference->uuid,
            'mediauuid' => (string)$media->uuid,
            'name' => (string)$media->name,
            'mediatype' => (string)$media->mediatype,
            'versionmode' => $versionmode,
            'pinnedversionid' => $pinnedversionid,
            'alttext' => $reference->alttext,
            'caption' => $reference->caption,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'referenceuuid' => new external_value(PARAM_ALPHANUMEXT, 'Reference UUID'),
            'mediauuid' => new external_value(PARAM_ALPHANUMEXT, 'Media UUID'),
            'name' => new external_value(PARAM_TEXT, 'Media name'),
            'mediatype' => new external_value(PARAM_ALPHANUMEXT, 'Media type'),
            'versionmode' => new external_value(PARAM_ALPHANUMEXT, 'Saved reference version mode'),
            'pinnedversionid' => new external_value(PARAM_INT, 'Saved pinned version id or zero'),
            'alttext' => new external_value(PARAM_TEXT, 'Saved alt text', VALUE_OPTIONAL),
            'caption' => new external_value(PARAM_TEXT, 'Saved caption', VALUE_OPTIONAL),
        ]);
    }
}
