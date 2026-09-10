<?php
namespace local_worksheetlibrary\service;

use local_digieranative\document\renderer;
use local_digieranative\document\validator;
use local_worksheetlibrary\exception\native_revision_conflict_exception;

final class native_version_service {
    public static function save_draft(
        int $versionid,
        int $expectedrevision,
        string $nativejson,
        int $userid
    ): \stdClass {
        global $DB;

        $factory = \core\lock\lock_config::get_lock_factory('local_worksheetlibrary');
        $lock = $factory->get_lock('native_version_' . $versionid, 10);
        if (!$lock) {
            throw new \RuntimeException('Native worksheet is busy');
        }

        try {
            $transaction = $DB->start_delegated_transaction();
            $version = $DB->get_record('wslib_version', ['id' => $versionid], '*', MUST_EXIST);
            if ($version->state !== 'draft') {
                throw new \invalid_parameter_exception('Only Native draft versions can be saved');
            }

            $item = $DB->get_record('wslib_item', ['id' => $version->itemid], '*', MUST_EXIST);
            if ($item->kind !== 'native') {
                throw new \invalid_parameter_exception('Worksheet is not Native');
            }

            $currentrevision = (int)($version->revision ?? 0);
            if ($currentrevision !== $expectedrevision) {
                throw new native_revision_conflict_exception($currentrevision);
            }

            $document = validator::validate_json($nativejson);
            $renderedhtml = renderer::render_json(
                $nativejson,
                'preview',
                native_asset_service::asset_urls($versionid)
            );
            $nextrevision = $currentrevision + 1;

            $DB->update_record('wslib_version', (object)[
                'id' => $versionid,
                'nativejson' => $nativejson,
                'schemaversion' => (int)$document['version'],
                'revision' => $nextrevision,
                'renderedhtml' => $renderedhtml,
                'contenthash' => hash('sha256', $nativejson),
            ]);

            $transaction->allow_commit();
            return $DB->get_record('wslib_version', ['id' => $versionid], '*', MUST_EXIST);
        } finally {
            $lock->release();
        }
    }
}
