<?php

namespace local_digieramedia\external;

use context;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

final class get_media_versions extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Editor context id'),
            'mediauuid' => new external_value(PARAM_ALPHANUMEXT, 'Media UUID'),
        ]);
    }

    public static function execute(int $contextid, string $mediauuid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), compact('contextid', 'mediauuid'));
        $context = context::instance_by_id($params['contextid'], MUST_EXIST);
        self::validate_context($context);
        require_capability('local/digieramedia:view', $context);

        $canreplace = has_capability('local/digieramedia:replace', $context);
        $canmanageversions = has_capability('local/digieramedia:manageversions', $context);
        $canoverridepath = has_capability('local/digieramedia:overridepath', $context)
            || has_capability('local/digieramedia:manage', $context);
        $canlifecycle = has_capability('local/digieramedia:viewusage', $context)
            || has_capability('local/digieramedia:restore', $context)
            || has_capability('local/digieramedia:purge', $context);
        if (!$canreplace && !$canmanageversions && !$canlifecycle) {
            throw new \required_capability_exception(
                $context,
                'local/digieramedia:manageversions',
                'nopermissions',
                ''
            );
        }

        [$statussql, $statusparams] = $DB->get_in_or_equal(
            ['ACTIVE', 'TRASHED', 'PURGING'],
            SQL_PARAMS_NAMED,
            'mediastatus'
        );
        $statusparams['mediauuid'] = $params['mediauuid'];
        $media = $DB->get_record_sql(
            "SELECT *
               FROM {local_digieramedia_media}
              WHERE uuid = :mediauuid
                AND status {$statussql}",
            $statusparams,
            MUST_EXIST
        );
        $records = $DB->get_records(
            'local_digieramedia_version',
            ['mediaid' => (int)$media->id],
            'versionno DESC, id DESC'
        );

        $versions = [];
        foreach ($records as $version) {
            $storagepath = '';
            if ($canoverridepath && !empty($version->objectkey)) {
                $storagepath = (string)$version->objectkey;
            }
            $versions[] = [
                'id' => (int)$version->id,
                'versionno' => (int)$version->versionno,
                'displayfilename' => (string)$version->displayfilename,
                'filesize' => (int)$version->filesize,
                'mimetype' => (string)$version->mimetype,
                'status' => (string)$version->status,
                'timecreated' => (int)$version->timecreated,
                'iscurrent' => (int)$version->id === (int)$media->currentversionid,
                'storagepath' => $storagepath,
            ];
        }

        $active = (string)$media->status === 'ACTIVE';
        return [
            'mediauuid' => (string)$media->uuid,
            'mediastatus' => (string)$media->status,
            'currentversionid' => (int)$media->currentversionid,
            'canreplace' => $active && $canreplace,
            'canmanageversions' => $active && $canmanageversions,
            'versions' => $versions,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'mediauuid' => new external_value(PARAM_ALPHANUMEXT, 'Media UUID'),
            'mediastatus' => new external_value(PARAM_ALPHA, 'Media lifecycle status'),
            'currentversionid' => new external_value(PARAM_INT, 'Current version id'),
            'canreplace' => new external_value(PARAM_BOOL, 'Can replace the logical media'),
            'canmanageversions' => new external_value(PARAM_BOOL, 'Can manage versions'),
            'versions' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Version id'),
                'versionno' => new external_value(PARAM_INT, 'Logical version number'),
                'displayfilename' => new external_value(PARAM_TEXT, 'Display file name'),
                'filesize' => new external_value(PARAM_INT, 'Bytes'),
                'mimetype' => new external_value(PARAM_RAW_TRIMMED, 'MIME type'),
                'status' => new external_value(PARAM_ALPHA, 'Version status'),
                'timecreated' => new external_value(PARAM_INT, 'Creation time'),
                'iscurrent' => new external_value(PARAM_BOOL, 'Current version flag'),
                'storagepath' => new external_value(PARAM_RAW_TRIMMED, 'Storage path for admin/ktv', VALUE_OPTIONAL),
            ])),
        ]);
    }
}
