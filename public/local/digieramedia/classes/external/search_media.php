<?php

namespace local_digieramedia\external;

use context;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

final class search_media extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Editor context id'),
            'tab' => new external_value(PARAM_ALPHA, 'library, recent or trash', VALUE_DEFAULT, 'library'),
            'query' => new external_value(PARAM_RAW_TRIMMED, 'Text query', VALUE_DEFAULT, ''),
            'page' => new external_value(PARAM_INT, 'Zero-based page', VALUE_DEFAULT, 0),
            'pagesize' => new external_value(PARAM_INT, 'Page size', VALUE_DEFAULT, 24),
        ]);
    }

    public static function execute(int $contextid, string $tab = 'library', string $query = '', int $page = 0, int $pagesize = 24): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), compact('contextid', 'tab', 'query', 'page', 'pagesize'));
        $context = context::instance_by_id($params['contextid'], MUST_EXIST);
        self::validate_context($context);
        require_capability('local/digieramedia:view', $context);

        $page = max(0, (int)$params['page']);
        $pagesize = min(50, max(1, (int)$params['pagesize']));
        $tab = in_array($params['tab'], ['library', 'recent', 'trash'], true) ? $params['tab'] : 'library';
        $query = trim($params['query']);

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
        $where = [];
        $sqlparams = [];

        $where[] = 'm.status = :mediastatus';
        $sqlparams['mediastatus'] = $tab === 'trash' ? 'TRASHED' : 'ACTIVE';

        if (!$canviewall) {
            $sqlparams['owneruserid'] = (int)$USER->id;
            if ($tab === 'trash') {
                $where[] = 'm.owneruserid = :owneruserid';
            } else {
                $scope = [
                    'm.owneruserid = :owneruserid',
                    "m.visibility = 'GLOBAL'",
                    "m.visibility = 'SHARED'",
                ];
                if ($courseid > 0) {
                    $scope[] = "(m.visibility = 'COURSE' AND m.origincourseid = :courseid)";
                    $sqlparams['courseid'] = $courseid;
                }
                $where[] = '(' . implode(' OR ', $scope) . ')';
            }
        }

        if ($query !== '') {
            $where[] = $DB->sql_like('m.name', ':query', false, false);
            $sqlparams['query'] = '%' . $DB->sql_like_escape($query) . '%';
        }

        $wheresql = implode(' AND ', $where);
        $fromsql = " FROM {local_digieramedia_media} m
                     LEFT JOIN {local_digieramedia_version} v ON v.id = m.currentversionid ";
        $total = (int)$DB->count_records_sql('SELECT COUNT(1)' . $fromsql . ' WHERE ' . $wheresql, $sqlparams);

        $select = "SELECT m.id, m.uuid, m.name, m.mediatype, m.mimetype, m.visibility, m.status,
                          m.owneruserid, m.timemodified, v.filesize, v.displayfilename, v.status AS versionstatus";
        $order = ' ORDER BY m.timemodified DESC, m.id DESC';
        $records = $DB->get_records_sql(
            $select . $fromsql . ' WHERE ' . $wheresql . $order,
            $sqlparams,
            $page * $pagesize,
            $pagesize
        );

        $items = [];
        foreach ($records as $record) {
            $items[] = [
                'uuid' => (string)$record->uuid,
                'name' => (string)$record->name,
                'mediatype' => (string)$record->mediatype,
                'mimetype' => (string)$record->mimetype,
                'size' => (int)($record->filesize ?? 0),
                'modified' => (int)$record->timemodified,
                'visibility' => (string)$record->visibility,
                'status' => (string)$record->status,
                'ready' => (($record->versionstatus ?? '') === 'READY'),
            ];
        }

        return [
            'items' => $items,
            'page' => $page,
            'pagesize' => $pagesize,
            'total' => $total,
            'canupload' => has_capability('local/digieramedia:upload', $context),
            'canreplace' => has_capability('local/digieramedia:replace', $context),
            'canmanageversions' => has_capability('local/digieramedia:manageversions', $context),
            'canviewusage' => has_capability('local/digieramedia:viewusage', $context),
            'cantrash' => has_capability('local/digieramedia:trashown', $context)
                || has_capability('local/digieramedia:trash', $context),
            'canrestore' => has_capability('local/digieramedia:restore', $context),
            'canpurge' => has_capability('local/digieramedia:purge', $context),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(new external_single_structure([
                'uuid' => new external_value(PARAM_ALPHANUMEXT, 'Media UUID'),
                'name' => new external_value(PARAM_TEXT, 'Media name'),
                'mediatype' => new external_value(PARAM_ALPHA, 'Media type'),
                'mimetype' => new external_value(PARAM_RAW_TRIMMED, 'MIME type'),
                'size' => new external_value(PARAM_INT, 'Bytes'),
                'modified' => new external_value(PARAM_INT, 'Modified time'),
                'visibility' => new external_value(PARAM_ALPHA, 'Visibility'),
                'status' => new external_value(PARAM_ALPHA, 'Media status'),
                'ready' => new external_value(PARAM_BOOL, 'Current version is ready'),
            ])),
            'page' => new external_value(PARAM_INT, 'Zero-based page'),
            'pagesize' => new external_value(PARAM_INT, 'Page size'),
            'total' => new external_value(PARAM_INT, 'Total results'),
            'canupload' => new external_value(PARAM_BOOL, 'Upload capability'),
            'canreplace' => new external_value(PARAM_BOOL, 'Replace capability'),
            'canmanageversions' => new external_value(PARAM_BOOL, 'Version-management capability'),
            'canviewusage' => new external_value(PARAM_BOOL, 'Usage-view capability'),
            'cantrash' => new external_value(PARAM_BOOL, 'Trash capability summary'),
            'canrestore' => new external_value(PARAM_BOOL, 'Restore capability summary'),
            'canpurge' => new external_value(PARAM_BOOL, 'Permanent-purge capability summary'),
        ]);
    }
}
