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
