<?php
// This file is part of Moodle - http://moodle.org/.

require('../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$sessionid = optional_param('sessionid', 0, PARAM_INT);
[$cm, $course, $activity, $context] = worksheetgrader_get_page_context($id);
require_capability('mod/worksheetgrader:manageactivity', $context);

$session = worksheetgrader_get_selected_session((int)$activity->id, $sessionid);
$sessionid = $session ? (int)$session->id : 0;
worksheetgrader_setup_page($PAGE, $cm, $course, $activity, get_string('worksheetcontent', 'mod_worksheetgrader'),
    'content.php', $sessionid ? ['sessionid' => $sessionid] : []);
$v12 = \mod_worksheetgrader\service\v12_gate::enabled_for_course((int)$course->id);

if (!$session) {
    echo $OUTPUT->header();
    echo $OUTPUT->render(worksheetgrader_nav_tabs($cm, 'content'));
    echo worksheetgrader_render_workflow($cm, null, 'content');
    echo $OUTPUT->heading(get_string('worksheetcontent', 'mod_worksheetgrader'));
    echo $OUTPUT->notification(get_string('nosessionyet', 'mod_worksheetgrader'), 'info');
    echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/sessions.php', ['id' => $id]),
        'Tạo buổi/lượt đầu tiên', 'get');
    echo $OUTPUT->footer();
    exit;
}

if ($v12) {
    require(__DIR__ . '/v12/content.php');
    exit;
}

$contenturl = new moodle_url('/mod/worksheetgrader/content.php', ['id' => $id, 'sessionid' => $sessionid]);
$contentform = new \mod_worksheetgrader\form\content_form($contenturl, ['id' => $id, 'sessionid' => $sessionid],
    'post', '', ['id' => 'wsg-content-form']);
$importform = new \mod_worksheetgrader\form\import_form($contenturl, ['id' => $id, 'sessionid' => $sessionid],
    'post', '', ['id' => 'wsg-import-form']);
$docspaceform = new \mod_worksheetgrader\form\docspace_template_form($contenturl,
    ['id' => $id, 'sessionid' => $sessionid], 'post', '', ['id' => 'wsg-docspace-form']);
$PAGE->requires->js_call_amd('mod_worksheetgrader/worksheet_designer', 'init');

$contentform->set_data((object)[
    'id' => $id,
    'sessionid' => $sessionid,
    'contentaction' => 'savehtml',
    'contenthtml' => (string)($session->contenthtml ?: $activity->contenthtml ?: ''),
]);

$error = '';
$action = optional_param('contentaction', '', PARAM_ALPHA);

$showdocspaceclone = false;

if ($action === 'setmode' && data_submitted() && confirm_sesskey()) {
    try {
        $mode = required_param('contentmode', PARAM_ALPHA);
        if (!in_array($mode, ['html', 'docspace'], true)) {
            throw new moodle_exception('invalidparameter');
        }
        $currentmode = (string)($session->contentmode ?? 'html');
        $hasattempts = $DB->record_exists('wsg_attempt', ['sessionid' => $sessionid]);
        if ($mode !== $currentmode && $hasattempts) {
            $showdocspaceclone = $mode === 'docspace';
            throw new moodle_exception('docspaceexistingattemptswitch', 'mod_worksheetgrader');
        }
        if ($mode === 'docspace') {
            if (!\mod_worksheetgrader\service\docspace_manager::configured()) {
                throw new moodle_exception('docspacenotconfigured', 'mod_worksheetgrader');
            }
            if (!\mod_worksheetgrader\service\docspace_manager::get_template($sessionid)) {
                \mod_worksheetgrader\service\docspace_manager::create_template_from_session_source_or_blank(
                    $activity, $session, $context, (int)$USER->id
                );
            } else {
                $session->contentmode = 'docspace';
                $session->timemodified = time();
                $DB->update_record('wsg_session', $session);
            }
        } else {
            $session->contentmode = 'html';
            $session->timemodified = time();
            $DB->update_record('wsg_session', $session);
        }
        worksheetgrader_log_action((int)$activity->id, 'content_mode_changed', ['mode' => $mode], $sessionid);
        worksheetgrader_redirect($contenturl, $mode === 'docspace'
            ? get_string('docspaceautocreated', 'mod_worksheetgrader')
            : 'Đã chuyển sang phiếu HTML tương tác.');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

if ($action === 'clonedocspace' && data_submitted() && confirm_sesskey()) {
    try {
        if (!\mod_worksheetgrader\service\docspace_manager::configured()) {
            throw new moodle_exception('docspacenotconfigured', 'mod_worksheetgrader');
        }
        $newsessionid = \mod_worksheetgrader\service\session_manager::create((int)$activity->id, [
            'name' => $session->name . ' - ONLYOFFICE',
            'sessiondate' => (int)$session->sessiondate,
            'teammode' => (string)$session->teammode,
            'contentmode' => 'docspace',
            'maxpoints' => (float)$session->maxpoints,
            'timeopen' => 0,
            'timeclose' => 0,
            'contenthtml' => '',
        ]);
        if (($session->teammode ?? 'temporary') !== 'individual') {
            \mod_worksheetgrader\service\session_manager::copy_teams(
                (int)$session->id, $newsessionid, (int)$USER->id
            );
        }
        $newsession = $DB->get_record('wsg_session', ['id' => $newsessionid], '*', MUST_EXIST);
        $sourcefiles = get_file_storage()->get_area_files(
            $context->id, 'mod_worksheetgrader', 'source', (int)$session->id,
            'timemodified DESC, id DESC', false
        );
        $source = reset($sourcefiles);
        if ($source && preg_match('/\.(docx|xlsx|pptx|pdf)$/i', $source->get_filename())) {
            \mod_worksheetgrader\service\docspace_manager::create_template_from_stored_file(
                $activity, $newsession, $source, (int)$USER->id
            );
        } else {
            \mod_worksheetgrader\service\docspace_manager::create_blank_template(
                $activity, $newsession, $newsession->name . '.docx', (int)$USER->id
            );
        }
        worksheetgrader_redirect(new moodle_url('/mod/worksheetgrader/content.php', [
            'id' => $id,
            'sessionid' => $newsessionid,
        ]), 'Đã tạo bản sao ONLYOFFICE; bài nộp của buổi cũ được giữ nguyên.');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

if ($action === 'savehtml' && ($data = $contentform->get_data())) {
    try {
        $session->contenthtml = worksheetgrader_normalize_interactive_template(
            clean_text((string)($data->contenthtml ?? ''), FORMAT_HTML));
        $session->contentformat = FORMAT_HTML;
        $session->contentmode = 'html';
        $session->timemodified = time();
        $DB->update_record('wsg_session', $session);
        worksheetgrader_log_action((int)$activity->id, 'worksheet_html_saved', [], $sessionid);
        worksheetgrader_redirect($contenturl, 'Đã lưu nội dung và cập nhật HTML Preview.');
    } catch (Throwable $exception) {
        $error = get_string('content_save_failed', 'mod_worksheetgrader', $exception->getMessage());
    }
}

if ($action === 'importfile' && $importform->is_cancelled()) {
    worksheetgrader_redirect(new moodle_url('/mod/worksheetgrader/sessions.php', ['id' => $id]));
}

if ($action === 'importfile' && ($data = $importform->get_data())) {
    $tmp = '';
    try {
        $draftid = file_get_submitted_draft_itemid('sourcefile');
        $fs = get_file_storage();
        $files = $fs->get_area_files(context_user::instance($USER->id)->id, 'user', 'draft', $draftid,
            'id DESC', false);
        $file = reset($files);
        if (!$file) {
            throw new moodle_exception('nofile');
        }
        $tmp = $file->copy_content_to_temp();
        $client = new \mod_worksheetgrader\engine\client();
        $result = $client->convert($tmp, $file->get_filename(), (string)$data->mode);
        if (empty($result['content_html']) || !is_string($result['content_html'])) {
            throw new moodle_exception('engineinvalidresponse', 'mod_worksheetgrader');
        }
        $session->contenthtml = worksheetgrader_normalize_interactive_template(
            clean_text($result['content_html'], FORMAT_HTML));
        $session->contentformat = FORMAT_HTML;
        $session->contentmode = 'html';
        $session->sourcefilename = $file->get_filename();
        $session->metadatajson = json_encode($result['metadata'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $session->timemodified = time();
        $DB->update_record('wsg_session', $session);
        $fs->delete_area_files($context->id, 'mod_worksheetgrader', 'source', $sessionid);
        $fs->create_file_from_storedfile([
            'contextid' => $context->id,
            'component' => 'mod_worksheetgrader',
            'filearea' => 'source',
            'itemid' => $sessionid,
            'filepath' => '/',
            'filename' => $file->get_filename(),
        ], $file);
        worksheetgrader_log_action((int)$activity->id, 'worksheet_imported', [
            'filename' => $file->get_filename(),
            'documents' => count($result['documents'] ?? []),
        ], $sessionid);
        @unlink($tmp);
        worksheetgrader_redirect($contenturl, 'Đã chuyển đổi phiếu và cập nhật HTML Preview.');
    } catch (Throwable $exception) {
        if ($tmp) {
            @unlink($tmp);
        }
        $error = get_string('content_import_failed', 'mod_worksheetgrader', $exception->getMessage());
    }
}

if ($action === 'docspace' && ($data = $docspaceform->get_data())) {
    try {
        if (!\mod_worksheetgrader\service\docspace_manager::configured()) {
            throw new moodle_exception('docspacenotconfigured', 'mod_worksheetgrader');
        }
        if (empty($data->docspaceconfirmreplace)) {
            throw new moodle_exception('required');
        }
        $operation = (string)$data->docspaceoperation;
        if ($operation === 'blank') {
            \mod_worksheetgrader\service\docspace_manager::create_blank_template(
                $activity, $session, (string)$data->docspacefilename, (int)$USER->id
            );
        } else {
            $draftid = file_get_submitted_draft_itemid('docspacefile');
            $fs = get_file_storage();
            $files = $fs->get_area_files(context_user::instance($USER->id)->id, 'user', 'draft', $draftid,
                'id DESC', false);
            $file = reset($files);
            if (!$file) {
                throw new moodle_exception('nofile');
            }
            \mod_worksheetgrader\service\docspace_manager::create_template_from_stored_file(
                $activity, $session, $file, (int)$USER->id
            );
            $fs->delete_area_files($context->id, 'mod_worksheetgrader', 'docspacetemplate', $sessionid);
            $fs->create_file_from_storedfile([
                'contextid' => $context->id,
                'component' => 'mod_worksheetgrader',
                'filearea' => 'docspacetemplate',
                'itemid' => $sessionid,
                'filepath' => '/',
                'filename' => $file->get_filename(),
            ], $file);
        }
        worksheetgrader_redirect($contenturl, 'Đã tạo tài liệu mẫu DocSpace. Bạn có thể chỉnh tài liệu ngay trong Moodle.');
    } catch (Throwable $exception) {
        $error = get_string('content_save_failed', 'mod_worksheetgrader', $exception->getMessage());
    }
}

$sessions = $DB->get_records('wsg_session', ['worksheetgraderid' => $activity->id], 'sessiondate DESC, id DESC');
$session = $DB->get_record('wsg_session', ['id' => $sessionid], '*', MUST_EXIST);
$content = (string)($session->contenthtml ?: $activity->contenthtml ?: '');
$contentmode = (string)($session->contentmode ?? 'html');
$templateworkspace = \mod_worksheetgrader\service\docspace_manager::get_template($sessionid);

if (!$contentform->is_submitted() || !$error) {
    $contentform->set_data((object)[
        'id' => $id,
        'sessionid' => $sessionid,
        'contentaction' => 'savehtml',
        'contenthtml' => $content,
    ]);
}

echo $OUTPUT->header();
echo $OUTPUT->render(worksheetgrader_nav_tabs($cm, 'content', $sessionid));
echo worksheetgrader_render_workflow($cm, $session, 'content');
echo $OUTPUT->heading('Nội dung phiếu: ' . format_string($session->name));
echo worksheetgrader_render_session_selector($id, $sessions, $sessionid, 'content.php');
if ($error !== '') {
    echo $OUTPUT->notification(s($error), 'error');
}
if (!empty($showdocspaceclone)) {
    echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'mb-3']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'contentaction', 'value' => 'clonedocspace']);
    echo html_writer::tag('button', get_string('docspaceclonebutton', 'mod_worksheetgrader'), [
        'type' => 'submit',
        'class' => 'btn btn-primary',
    ]);
    echo html_writer::end_tag('form');
}

// Explicit content-mode switch; both modes remain available and data is preserved.
echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'wsg-docspace-toolbar']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'contentaction', 'value' => 'setmode']);
echo html_writer::label(get_string('docspacecontentmode', 'mod_worksheetgrader'), 'wsg-contentmode', false,
    ['class' => 'fw-bold mb-0']);
echo html_writer::select([
    'html' => get_string('docspacecontentmode_html', 'mod_worksheetgrader'),
    'docspace' => get_string('docspacecontentmode_docspace', 'mod_worksheetgrader'),
], 'contentmode', $contentmode, false, ['id' => 'wsg-contentmode', 'class' => 'form-select w-auto']);
echo html_writer::tag('button', 'Áp dụng', ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

if ($contentmode === 'docspace') {
    if (worksheetgrader_show_guidance_notices()) {
        echo $OUTPUT->notification(get_string(\mod_worksheetgrader\service\docspace_manager::shared_mode() ?
            'docspacesharedwarning' : 'docspacepublicwarning', 'mod_worksheetgrader'), 'warning');
    }
    if (!\mod_worksheetgrader\service\docspace_manager::configured()) {
        echo $OUTPUT->notification(get_string('docspacenotconfigured', 'mod_worksheetgrader'), 'error');
        if (is_siteadmin()) {
            echo $OUTPUT->single_button(new moodle_url('/admin/settings.php', ['section' => 'modsettingworksheetgrader']),
                'Mở cấu hình plugin', 'get');
        }
    } else {
        if ($templateworkspace) {
            echo html_writer::div('Phòng mẫu: ' . s($templateworkspace->roomtitle) .
                ' · Tệp: ' . s($templateworkspace->filename), 'wsg-docspace-status mb-3');
            echo worksheetgrader_render_docspace_frame($templateworkspace, 'teacher-template-' . $sessionid, false);
            echo html_writer::tag('p', 'Các thay đổi được DocSpace tự lưu. Khi học sinh bắt đầu, plugin tạo một bản riêng cho nhóm hoặc cá nhân.',
                ['class' => 'text-muted mt-2']);
        } else if (worksheetgrader_show_guidance_notices()) {
            echo $OUTPUT->notification('Chưa có tài liệu mẫu DocSpace. Bấm "Tạo tài liệu mẫu" ở dưới; plugin sẽ mở editor Desktop đầy đủ ngay trong Moodle.', 'info');
        }
        echo html_writer::start_div('wsg-content-card mt-4');
        echo $OUTPUT->heading($templateworkspace ? 'Thay tài liệu mẫu DocSpace' : 'Tạo tài liệu mẫu DocSpace', 3);
        $docspaceform->display();
        echo html_writer::end_div();
    }
} else {
    $statusitems = [];
    $statusitems[] = $content !== '' ? 'Đã có nội dung' : 'Chưa có nội dung';
    $statusitems[] = $session->sourcefilename ? 'Nguồn: ' . s($session->sourcefilename) : 'Nguồn: nhập trực tiếp';
    $statusitems[] = 'Số ô trả lời: ' . preg_match_all('/\[\[[CSBLU]\d+\]\]|data-code="[CSBLU]\d+"/i', $content);
    echo html_writer::div(implode(' · ', $statusitems), 'alert alert-light border');
    if ($content !== '') {
        echo html_writer::start_tag('details', ['open' => 'open', 'class' => 'wsg-preview-panel mb-4']);
        echo html_writer::tag('summary', 'HTML Preview dành cho học sinh');
        echo html_writer::div(worksheetgrader_render_answer_fields($content, [], false), 'wsg-worksheet-content mt-3');
        echo html_writer::end_tag('details');
    } else if (worksheetgrader_show_guidance_notices()) {
        echo $OUTPUT->notification('Buổi này chưa có nội dung. Hãy nhập HTML hoặc tải DOCX/PDF ở dưới.', 'warning');
    }
    echo html_writer::start_div('wsg-content-grid');
    echo html_writer::start_div('wsg-content-card');
    echo $OUTPUT->heading('Thiết kế phiếu trực quan', 3);
    $contentform->display();
    echo html_writer::end_div();
    echo html_writer::start_div('wsg-content-card');
    echo $OUTPUT->heading('Import DOCX/PDF/ảnh', 3);
    $importform->display();
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::start_div('wsg-next-actions mt-4');
echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/teams.php', ['id' => $id, 'sessionid' => $sessionid]),
    $session->teammode === 'individual' ? 'Tiếp theo: Mở buổi cá nhân' : 'Tiếp theo: Chia nhóm & thành viên', 'get');
echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/sessions.php', ['id' => $id]),
    'Quay lại danh sách buổi', 'get');
echo html_writer::end_div();
echo $OUTPUT->footer();
