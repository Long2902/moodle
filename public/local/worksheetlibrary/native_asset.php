<?php
require('../../config.php');

require_login();
require_sesskey();
\local_worksheetlibrary\service\access_service::require_author();

header('Content-Type: application/json; charset=utf-8');

try {
    $versionid = required_param('versionid', PARAM_INT);
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

    // The service performs the same checks again and creates the File API record.
    $result = \local_worksheetlibrary\service\native_asset_service::store_upload(
        $versionid,
        $upload,
        (int)$USER->id
    );

    echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => clean_text($e->getMessage()),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
