<?php

namespace local_digieramedia\external;

use context;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

final class update_media extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Editor context id'),
            'mediauuid' => new external_value(PARAM_ALPHANUMEXT, 'Media UUID'),
            'name' => new external_value(PARAM_TEXT, 'New media name', VALUE_DEFAULT, null),
            'visibility' => new external_value(PARAM_ALPHA, 'PRIVATE, COURSE, GLOBAL or SHARED', VALUE_DEFAULT, null),
        ]);
    }

    public static function execute(
        int $contextid,
        string $mediauuid,
        ?string $name = null,
        ?string $visibility = null
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            compact('contextid', 'mediauuid', 'name', 'visibility')
        );

        $context = context::instance_by_id($params['contextid'], MUST_EXIST);
        self::validate_context($context);
        require_capability('local/digieramedia:view', $context);

        $media = $DB->get_record('local_digieramedia_media', [
            'uuid' => $params['mediauuid'],
            'status' => 'ACTIVE',
        ], '*', MUST_EXIST);

        $isOwner = (int)$media->owneruserid === (int)$USER->id;

        if ($params['name'] !== null) {
            $canEdit = $isOwner
                ? (has_capability('local/digieramedia:editown', $context) || has_capability('local/digieramedia:manage', $context))
                : (has_capability('local/digieramedia:editall', $context) || has_capability('local/digieramedia:manage', $context));

            if (!$canEdit) {
                throw new \required_capability_exception(
                    $context,
                    $isOwner ? 'local/digieramedia:editown' : 'local/digieramedia:editall',
                    'nopermissions',
                    ''
                );
            }
            $cleanName = trim((string)$params['name']);
            if ($cleanName !== '') {
                $media->name = $cleanName;
            }
        }

        if ($params['visibility'] !== null) {
            $canManageVis = has_capability('local/digieramedia:managevisibility', $context)
                || has_capability('local/digieramedia:manage', $context);
            if (!$canManageVis) {
                throw new \required_capability_exception(
                    $context,
                    'local/digieramedia:managevisibility',
                    'nopermissions',
                    ''
                );
            }
            $cleanVis = strtoupper(trim((string)$params['visibility']));
            if (!in_array($cleanVis, ['PRIVATE', 'COURSE', 'GLOBAL', 'SHARED'], true)) {
                throw new \invalid_parameter_exception('Invalid visibility value.');
            }
            $media->visibility = $cleanVis;
        }

        $media->timemodified = time();
        $media->modifiedby = (int)$USER->id;
        $DB->update_record('local_digieramedia_media', $media);

        return [
            'uuid' => (string)$media->uuid,
            'name' => (string)$media->name,
            'visibility' => (string)$media->visibility,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'uuid' => new external_value(PARAM_ALPHANUMEXT, 'Media UUID'),
            'name' => new external_value(PARAM_TEXT, 'Updated name'),
            'visibility' => new external_value(PARAM_ALPHA, 'Updated visibility'),
        ]);
    }
}
