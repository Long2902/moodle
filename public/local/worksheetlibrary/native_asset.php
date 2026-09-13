<?php
require('../../config.php');

header('Content-Type: application/json; charset=utf-8');

try {
    require_login();
    require_sesskey();
    \local_worksheetlibrary\service\access_service::require_author();

    $versionid = required_param('versionid', PARAM_INT);
    $version = $DB->get_record('wslib_version', ['id' => $versionid], '*', MUST_EXIST);
    $item = $DB->get_record('wslib_item', ['id' => $version->itemid], '*', MUST_EXIST);
    if ($item->kind !== 'native') {
        throw new moodle_exception('Worksheet is not Native');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        echo json_encode([
            'ok' => true,
            'asseturls' => \local_worksheetlibrary\service\native_asset_service::asset_urls($versionid),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($version->state !== 'draft') {
        throw new moodle_exception('Only draft Native versions accept images');
    }

    $action = optional_param('action', 'create', PARAM_ALPHA);
    if (!in_array($action, ['create', 'replace'], true)) {
        throw new moodle_exception('Unsupported image action');
    }

    $mode = optional_param('mode', 'multipart', PARAM_ALPHA);
    if ($mode === 'chunk') {
        $uploadid = required_param('uploadid', PARAM_ALPHANUMEXT);
        $chunkindex = required_param('chunkindex', PARAM_INT);
        $chunktotal = required_param('chunktotal', PARAM_INT);
        $filesize = required_param('filesize', PARAM_INT);
        $filename = required_param('filename', PARAM_FILE);
        $mimetype = optional_param('mimetype', '', PARAM_RAW_TRIMMED);
        $assetkey = $action === 'replace' ? required_param('assetkey', PARAM_ALPHANUMEXT) : '';

        $contenttype = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
        if ($contenttype !== 'application/octet-stream') {
            throw new moodle_exception('Chunk body must be application/octet-stream');
        }
        $body = file_get_contents('php://input');
        if ($body === false) {
            throw new moodle_exception('Unable to read image chunk');
        }

        $scope = implode('|', [
            'worksheetlibrary',
            (string)$versionid,
            $action,
            $assetkey,
            $filename,
            $mimetype,
            (string)$filesize,
            (string)$chunktotal,
        ]);
        $assembled = \local_digieranative\service\chunk_upload_service::accept_chunk(
            $scope,
            strtolower($uploadid),
            $chunkindex,
            $chunktotal,
            $filesize,
            $filename,
            $mimetype,
            $body,
            (int)$USER->id
        );

        if (empty($assembled['complete'])) {
            echo json_encode(['ok' => true] + $assembled, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'replace') {
            $result = \local_worksheetlibrary\service\native_asset_service::replace_bytes(
                $versionid,
                $assetkey,
                $assembled['bytes'],
                $assembled['original'],
                (int)$USER->id
            );
        } else {
            $result = \local_worksheetlibrary\service\native_asset_service::store_bytes(
                $versionid,
                $assembled['bytes'],
                $assembled['original'],
                (int)$USER->id
            );
        }

        echo json_encode(['ok' => true, 'complete' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($mode !== 'multipart') {
        throw new moodle_exception('Unsupported upload mode');
    }
    if (empty($_FILES['image'])) {
        throw new moodle_exception('Image upload is required');
    }

    // Keep validation visible in this web boundary as a defence-in-depth contract.
    $upload = $_FILES['image'];
    $size = (int)($upload['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        throw new moodle_exception('Image must be no larger than 5 MiB');
    }
    $tmpname = (string)($upload['tmp_name'] ?? '');
    if ($tmpname === '' || !is_uploaded_file($tmpname)) {
        throw new moodle_exception('Invalid uploaded image');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmpname);
    $allowed = ['image/png', 'image/jpeg', 'image/webp'];
    if (!in_array($mime, $allowed, true)) {
        throw new moodle_exception('Unsupported image type');
    }

    if ($action === 'replace') {
        $assetkey = required_param('assetkey', PARAM_ALPHANUMEXT);
        $result = \local_worksheetlibrary\service\native_asset_service::replace_upload(
            $versionid,
            $assetkey,
            $upload,
            (int)$USER->id
        );
    } else {
        $result = \local_worksheetlibrary\service\native_asset_service::store_upload(
            $versionid,
            $upload,
            (int)$USER->id
        );
    }

    echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => clean_text($e->getMessage()),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
