<?php
require('../../config.php');
require_once(__DIR__ . '/locallib.php');

$attemptid = required_param('attemptid', PARAM_INT);
require_sesskey();

header('Content-Type: application/json; charset=utf-8');

try {
    $attempt = $DB->get_record('wsg_attempt', ['id' => $attemptid], '*', MUST_EXIST);
    $session = $DB->get_record('wsg_session', ['id' => $attempt->sessionid], '*', MUST_EXIST);
    $activity = $DB->get_record('worksheetgrader', ['id' => $session->worksheetgraderid], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('worksheetgrader', $activity->id, $activity->course, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $activity->course], '*', MUST_EXIST);
    $context = context_module::instance($cm->id);

    require_login($course, true, $cm);
    require_capability('mod/worksheetgrader:submit', $context);

    if (($session->worksheetkind ?? '') !== 'native' || $attempt->status !== 'inprogress' ||
            !\mod_worksheetgrader\service\session_manager::is_open($session) || !(bool)$session->membershiplocked) {
        throw new moodle_exception('attemptnoteditable', 'mod_worksheetgrader');
    }
    $team = $DB->get_record('wsg_team', ['id' => $attempt->teamid, 'sessionid' => $session->id], '*', MUST_EXIST);
    if (!\mod_worksheetgrader\service\attempt_manager::can_edit($activity, $team, (int)$USER->id)) {
        throw new required_capability_exception($context, 'mod/worksheetgrader:submit', 'nopermissions', '');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        echo json_encode([
            'ok' => true,
            'asseturls' => \mod_worksheetgrader\service\native_asset_service::asset_urls($context, $attemptid),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
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

    $result = \mod_worksheetgrader\service\native_asset_service::store_upload(
        $context,
        $attemptid,
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
