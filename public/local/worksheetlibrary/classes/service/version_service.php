<?php
namespace local_worksheetlibrary\service;

final class version_service {
    private static function require_target_folder(int $folderid): void {
        global $DB;
        if ($folderid !== 0 && !$DB->record_exists('wslib_folder', ['id' => $folderid, 'archived' => 0])) {
            throw new \invalid_argument_exception('Target folder not found');
        }
    }

    public static function create_item(int $folderid, string $name, string $kind, int $userid, string $html = ''): int {
        global $DB;
        self::require_target_folder($folderid);
        $name = trim($name);
        if ($name === '') {
            throw new \invalid_argument_exception('Worksheet name required');
        }
        if (!in_array($kind, ['html', 'office', 'pdf', 'native'], true)) {
            throw new \invalid_argument_exception('Invalid kind');
        }
        $now = time();
        $itemid = $DB->insert_record('wslib_item', (object)[
            'folderid' => $folderid,
            'name' => $name,
            'kind' => $kind,
            'currentversionid' => 0,
            'archived' => 0,
            'createdby' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $nativejson = null;
        $schemaversion = null;
        $renderedhtml = null;
        if ($kind === 'native') {
            $nativejson = '{"type":"worksheet","version":1,"content":[]}';
            $document = \local_digieranative\document\validator::validate_json($nativejson);
            $schemaversion = (int)$document['version'];
            $renderedhtml = \local_digieranative\document\renderer::render_json($nativejson, 'preview');
        }
        $hashsource = $kind === 'native' ? $nativejson : $html;
        $versionid = $DB->insert_record('wslib_version', (object)[
            'itemid' => $itemid,
            'versionno' => 1,
            'state' => 'draft',
            'contenthtml' => $html,
            'nativejson' => $nativejson,
            'schemaversion' => $schemaversion,
            'revision' => 0,
            'renderedhtml' => $renderedhtml,
            'filename' => '',
            'mimetype' => '',
            'contenthash' => hash('sha256', (string)$hashsource),
            'createdby' => $userid,
            'timecreated' => $now,
            'timepublished' => 0,
        ]);
        if ($kind === 'office') {
            $tmp = blank_docx_service::create_to_temp($name);
            $file = file_service::store_path(
                $versionid,
                $tmp,
                'worksheet.docx',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            );
            $DB->update_record('wslib_version', (object)[
                'id' => $versionid,
                'filename' => $file->get_filename(),
                'mimetype' => $file->get_mimetype(),
                'contenthash' => $file->get_contenthash(),
            ]);
        }
        return $itemid;
    }

    public static function draft(int $itemid, int $userid): \stdClass {
        global $DB;
        if ($draft = $DB->get_record('wslib_version', ['itemid' => $itemid, 'state' => 'draft'])) {
            return $draft;
        }
        $item = $DB->get_record('wslib_item', ['id' => $itemid, 'archived' => 0], '*', MUST_EXIST);
        $base = $item->currentversionid
            ? $DB->get_record('wslib_version', ['id' => $item->currentversionid], '*', MUST_EXIST)
            : null;
        $max = (int)$DB->get_field_sql(
            'SELECT COALESCE(MAX(versionno),0) FROM {wslib_version} WHERE itemid=?',
            [$itemid]
        );
        $versionid = $DB->insert_record('wslib_version', (object)[
            'itemid' => $itemid,
            'versionno' => $max + 1,
            'state' => 'draft',
            'contenthtml' => $base->contenthtml ?? '',
            'nativejson' => $base->nativejson ?? null,
            'schemaversion' => $base->schemaversion ?? null,
            'revision' => 0,
            'renderedhtml' => $base->renderedhtml ?? null,
            'filename' => $base->filename ?? '',
            'mimetype' => $base->mimetype ?? '',
            'contenthash' => $base->contenthash ?? '',
            'createdby' => $userid,
            'timecreated' => time(),
            'timepublished' => 0,
        ]);
        if ($base) {
            file_service::clone_file((int)$base->id, $versionid);
        }
        return $DB->get_record('wslib_version', ['id' => $versionid], '*', MUST_EXIST);
    }

    public static function publish(int $versionid, int $userid): void {
        global $DB;
        $version = $DB->get_record('wslib_version', ['id' => $versionid], '*', MUST_EXIST);
        if ($version->state !== 'draft') {
            throw new \invalid_argument_exception('Only draft can be published');
        }
        $item = $DB->get_record('wslib_item', ['id' => $version->itemid, 'archived' => 0], '*', MUST_EXIST);
        if (in_array($item->kind, ['office', 'pdf'], true) && !file_service::primary($versionid)) {
            throw new \invalid_argument_exception('Published file missing');
        }
        if ($item->kind === 'native') {
            if (empty($version->nativejson)) {
                throw new \invalid_argument_exception('Published Native document missing');
            }
            $document = \local_digieranative\document\validator::validate_json((string)$version->nativejson);
            $version->schemaversion = (int)$document['version'];
            $version->renderedhtml = \local_digieranative\document\renderer::render_json((string)$version->nativejson, 'preview');
            $version->contenthash = hash('sha256', (string)$version->nativejson);
        }
        $version->state = 'published';
        $version->timepublished = time();
        $DB->update_record('wslib_version', $version);
        $DB->update_record('wslib_item', (object)[
            'id' => $item->id,
            'currentversionid' => $versionid,
            'timemodified' => time(),
        ]);
    }

    public static function move_item(int $itemid, int $folderid): void {
        global $DB;
        self::require_target_folder($folderid);
        $DB->get_record('wslib_item', ['id' => $itemid, 'archived' => 0], 'id', MUST_EXIST);
        $DB->update_record('wslib_item', (object)[
            'id' => $itemid,
            'folderid' => $folderid,
            'timemodified' => time(),
        ]);
    }

    public static function copy_item(int $itemid, int $folderid, int $userid): int {
        global $DB;
        self::require_target_folder($folderid);
        $source = $DB->get_record('wslib_item', ['id' => $itemid, 'archived' => 0], '*', MUST_EXIST);
        $newitemid = self::create_item($folderid, $source->name . ' - Copy', $source->kind, $userid, '');
        if ($source->currentversionid) {
            $sourceversion = $DB->get_record('wslib_version', ['id' => $source->currentversionid], '*', MUST_EXIST);
            $draft = $DB->get_record('wslib_version', ['itemid' => $newitemid, 'state' => 'draft'], '*', MUST_EXIST);
            $draft->contenthtml = $sourceversion->contenthtml;
            $draft->nativejson = $sourceversion->nativejson ?? null;
            $draft->schemaversion = $sourceversion->schemaversion ?? null;
            $draft->revision = 0;
            $draft->renderedhtml = $sourceversion->renderedhtml ?? null;
            $draft->filename = $sourceversion->filename;
            $draft->mimetype = $sourceversion->mimetype;
            $draft->contenthash = $sourceversion->contenthash;
            $DB->update_record('wslib_version', $draft);
            get_file_storage()->delete_area_files(
                \context_system::instance()->id,
                'local_worksheetlibrary',
                'content',
                $draft->id
            );
            file_service::clone_file((int)$sourceversion->id, (int)$draft->id);
            self::publish((int)$draft->id, $userid);
        }
        return $newitemid;
    }
}
