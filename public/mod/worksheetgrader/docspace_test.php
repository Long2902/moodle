<?php
// This file is part of Moodle - http://moodle.org/.

require('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/mod/worksheetgrader/docspace_test.php'));
$PAGE->set_title(get_string('docspacetest', 'mod_worksheetgrader'));
$PAGE->set_heading(get_string('docspacetest', 'mod_worksheetgrader'));

$portal = rtrim((string)get_config('mod_worksheetgrader', 'docspaceurl'), '/');
$sdkversion = trim((string)get_config('mod_worksheetgrader', 'docspacesdkversion')) ?: 'auto';
$originparts = parse_url($CFG->wwwroot);
$origin = ($originparts['scheme'] ?? 'https') . '://' . ($originparts['host'] ?? '');
if (!empty($originparts['port'])) {
    $origin .= ':' . $originparts['port'];
}

$error = '';
$summary = '';
try {
    $client = new \mod_worksheetgrader\docspace\client();
    $result = $client->test_connection();
    $summary = 'API trả về JSON hợp lệ (' . count($result) . ' trường cấp cao).';
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('docspacetest', 'mod_worksheetgrader'));

if ($error !== '') {
    echo html_writer::div('<strong>Kết nối API thất bại.</strong><br>' . s($error), 'alert alert-danger');
} else {
    echo html_writer::div('<strong>Kết nối API thành công.</strong><br>' . s($summary), 'alert alert-success');
}

$table = new html_table();
$table->head = ['Mục kiểm tra', 'Giá trị'];
$table->data = [
    ['Portal DocSpace', s($portal ?: '(chưa cấu hình)')],
    ['Moodle origin cần cho phép', html_writer::tag('code', s($origin))],
    ['SDK version', s($sdkversion) . ' — chế độ auto sẽ fallback về 2.0.0'],
    ['Plugin release', '11.5.4-beta12 (build 2026080702)'],
    ['Workspace mode', s((string)get_config('mod_worksheetgrader', 'docspaceworkspacemode') ?: 'sharedfolder')],
    ['Folder ID', s((string)get_config('mod_worksheetgrader', 'docspacefolderid') ?: '(chưa cấu hình)')],
    ['Shared requestToken', trim((string)get_config('mod_worksheetgrader', 'docspacerequesttoken')) !== '' ? 'Đã cấu hình' : '(chưa cấu hình)'],
];
echo html_writer::table($table);

echo html_writer::div('Đang kiểm tra SDK trình duyệt…', 'alert alert-info', ['id' => 'wsg-docspace-sdk-diagnostic']);
if ($portal !== '') {
    $PAGE->requires->js_call_amd('mod_worksheetgrader/docspace_embed', 'diagnose', [[
        'targetId' => 'wsg-docspace-sdk-diagnostic',
        'src' => $portal,
        'sdkVersion' => $sdkversion,
    ]]);
}

if (worksheetgrader_show_guidance_notices()) {
    echo $OUTPUT->notification('Trong DocSpace → Developer Tools → Embed SDK, hãy thêm chính xác: ' . s($origin) . '. Với Shared Folder, Folder ID phải nằm trong đúng Public Room của requestToken và room link phải có quyền Editing.', 'info');
}
echo $OUTPUT->single_button(new moodle_url('/admin/settings.php', ['section' => 'modsettingworksheetgrader']),
    get_string('settings'), 'get');
echo $OUTPUT->footer();
