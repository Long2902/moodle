<?php
require('../../config.php');
require_once(__DIR__ . '/locallib.php');

header('Content-Type: application/json; charset=utf-8');

try {
    $attemptid = required_param('attemptid', PARAM_INT);
    require_sesskey();

    $attempt = $DB->get_record('wsg_attempt', ['id' => $attemptid], '*', MUST_EXIST);
    $session = $DB->get_record('wsg_session', ['id' => $attempt->sessionid], '*', MUST_EXIST);
    $activity = $DB->get_record('worksheetgrader', ['id' => $session->worksheetgraderid], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('worksheetgrader', $activity->id, $activity->course, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $activity->course], '*', MUST_EXIST);
    $context = context_module::instance($cm->id);

    require_login($course, true, $cm);
    require_capability('mod/worksheetgrader:submit', $context);

    if (($session->worksheetkind ?? '') !== 'native') {
        throw new moodle_exception('Worksheet is not Native');
    }

    $team = $DB->get_record('wsg_team', ['id' => $attempt->teamid, 'sessionid' => $session->id], '*', MUST_EXIST);
    $members = \mod_worksheetgrader\service\team_manager::get_member_ids((int)$team->id);
    if (!in_array((int)$USER->id, array_map('intval', $members), true)) {
        throw new required_capability_exception($context, 'mod/worksheetgrader:submit', 'nopermissions', '');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        echo json_encode([
            'ok' => true,
            'asseturls' => \mod_worksheetgrader\service\native_asset_service::asset_urls($context, $attemptid),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($attempt->status !== 'inprogress' ||
            !\mod_worksheetgrader\service\session_manager::is_open($session) || !(bool)$session->membershiplocked ||
            !\mod_worksheetgrader\service\attempt_manager::can_edit($activity, $team, (int)$USER->id)) {
        throw new moodle_exception('attemptnoteditable', 'mod_worksheetgrader');
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
            'worksheetgrader',
            (string)$attemptid,
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
            $result = \mod_worksheetgrader\service\native_asset_service::replace_bytes(
                $context,
                $attemptid,
                $assetkey,
                $assembled['bytes'],
                $assembled['original'],
                (int)$USER->id
            );
        } else {
            $result = \mod_worksheetgrader\service\native_asset_service::store_bytes(
                $context,
                $attemptid,
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

    // Validate again at the web boundary before the File API service is called.
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
        $result = \mod_worksheetgrader\service\native_asset_service::replace_upload(
            $context,
            $attemptid,
            $assetkey,
            $upload,
            (int)$USER->id
        );
    } else {
        $result = \mod_worksheetgrader\service\native_asset_service::store_upload(
            $context,
            $attemptid,
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
