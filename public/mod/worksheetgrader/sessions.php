<?php
// This file is part of Moodle - http://moodle.org/.

require('../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
[$cm, $course, $activity, $context] = worksheetgrader_get_page_context($id);
require_capability('mod/worksheetgrader:managesessions', $context);
worksheetgrader_setup_page($PAGE, $cm, $course, $activity, get_string('sessions', 'mod_worksheetgrader'), 'sessions.php');
$v12 = \mod_worksheetgrader\service\v12_gate::enabled_for_course((int)$course->id);

$form = $v12 ? new \mod_worksheetgrader\form\session_form_v12(null, ['id' => $id]) : new \mod_worksheetgrader\form\session_form(null, ['id' => $id]);
$form->set_data((object)( $v12 ? [
    'id' => $id,
    'sessiondate' => time(),
    'timeopen' => 0,
    'timeclose' => 0,
    'maxpoints' => (float)$activity->grade,
    'workmode' => 'grouped',
] : [
    'id' => $id,
    'sessiondate' => time(),
    'timeopen' => 0,
    'timeclose' => 0,
    'maxpoints' => (float)$activity->grade,
    'contentmode' => 'html',
    'workmode' => 'grouped',
    'teammode' => 'temporary',
]));
$saveerror = '';

if ($form->is_cancelled()) {
    worksheetgrader_redirect(new moodle_url('/mod/worksheetgrader/view.php', ['id' => $id]));
}

if ($data = $form->get_data()) {
    try {
        if ($v12) {
            $sessionid = \mod_worksheetgrader\service\session_manager::create_v12((int)$activity->id, (array)$data);
            worksheetgrader_redirect(new moodle_url('/mod/worksheetgrader/content.php', [
                'id' => $id, 'sessionid' => $sessionid,
            ]), 'Đã tạo buổi. Bây giờ hãy chọn phiếu học tập.');
        }
        // The teacher chooses the learning mode explicitly. Individual sessions create
        // one private record lazily only when a student opens the worksheet, avoiding
        // dozens of empty one-person teams at session creation time.
        $data->teammode = ($data->workmode ?? 'grouped') === 'individual'
            ? 'individual'
            : ($data->teammode ?? 'temporary');
        $sessionid = \mod_worksheetgrader\service\session_manager::create((int)$activity->id, (array)$data);
        if ($data->teammode === 'moodlegroups') {
            \mod_worksheetgrader\service\team_manager::import_moodle_groups((int)$course->id, $sessionid, (int)$USER->id);
        }
        $message = 'Đã tạo buổi. Bước tiếp theo: nhập hoặc kiểm tra nội dung phiếu.';
        if (($data->contentmode ?? 'html') === 'docspace') {
            $newsession = $DB->get_record('wsg_session', ['id' => $sessionid], '*', MUST_EXIST);
            \mod_worksheetgrader\service\docspace_manager::create_blank_template(
                $activity,
                $newsession,
                $newsession->name . '.docx',
                (int)$USER->id
            );
            $message = get_string('docspaceautocreated', 'mod_worksheetgrader');
        }
        worksheetgrader_redirect(new moodle_url('/mod/worksheetgrader/content.php', [
            'id' => $id,
            'sessionid' => $sessionid,
        ]), $message);
    } catch (Throwable $exception) {
        $saveerror = 'Không tạo được buổi: ' . $exception->getMessage();
    }
}

$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '' && confirm_sesskey()) {
    $sessionid = required_param('sessionid', PARAM_INT);
    $session = $DB->get_record('wsg_session', [
        'id' => $sessionid,
        'worksheetgraderid' => $activity->id,
    ], '*', MUST_EXIST);
    try {
        if (in_array($action, ['open', 'close', 'draft'], true)) {
            if ($v12 && $action === 'open') {
                \mod_worksheetgrader\service\readiness_service::open($session, $activity, $context, (int)$USER->id);
            } else {
                \mod_worksheetgrader\service\session_manager::set_status($session,
                    $action === 'close' ? 'closed' : $action);
            }
        } else if ($action === 'delete' && !$DB->record_exists('wsg_attempt', ['sessionid' => $sessionid])) {
            \mod_worksheetgrader\service\docspace_manager::delete_remote_rooms_for_session((int)$sessionid);
            foreach ($DB->get_records('wsg_team', ['sessionid' => $sessionid], '', 'id') as $team) {
                $DB->delete_records('wsg_member', ['teamid' => $team->id]);
            }
            $DB->delete_records('wsg_team', ['sessionid' => $sessionid]);
            $DB->delete_records('wsg_docspace', ['sessionid' => $sessionid]);
            $DB->delete_records('wsg_session', ['id' => $sessionid]);
            get_file_storage()->delete_area_files($context->id, 'mod_worksheetgrader', 'source', $sessionid);
            get_file_storage()->delete_area_files($context->id, 'mod_worksheetgrader', 'docspacetemplate', $sessionid);
        }
        worksheetgrader_redirect(new moodle_url('/mod/worksheetgrader/sessions.php', ['id' => $id]));
    } catch (Throwable $exception) {
        $saveerror = $exception->getMessage();
    }
}

$sessions = $DB->get_records('wsg_session', ['worksheetgraderid' => $activity->id], 'sessiondate DESC, id DESC');

if ($v12) {
    require(__DIR__ . '/v12/sessions_render.php');
    exit;
}

echo $OUTPUT->header();
if ($v12) { echo html_writer::div('<div><span class="wsg-v12-eyebrow">WORKSHEET GRADER V12</span><h2>Quản lý buổi học</h2><p>Tạo buổi, chọn phiếu, chia nhóm và mở buổi theo một quy trình rõ ràng.</p></div><div class="wsg-v12-hero-icon">✓</div>', 'wsg-v12-hero'); }
echo $OUTPUT->render(worksheetgrader_nav_tabs($cm, 'sessions'));
echo worksheetgrader_render_workflow($cm, worksheetgrader_get_selected_session((int)$activity->id), 'sessions');
echo $OUTPUT->heading('Buổi/Lượt hoạt động');

if ($saveerror !== '') {
    echo $OUTPUT->notification(s($saveerror), 'error');
}

if (!$sessions) {
    echo $OUTPUT->notification('Chưa có buổi nào. Tạo buổi đầu tiên bằng biểu mẫu phía dưới.', 'info');
} else {
    $table = new html_table();
    $table->head = ['Buổi', 'Ngày', 'Trạng thái', 'Nội dung', 'Nhóm', 'Bài nộp', 'Thao tác'];
    foreach ($sessions as $session) {
        $actions = [];
        $actions[] = html_writer::link(new moodle_url('/mod/worksheetgrader/content.php', [
            'id' => $id,
            'sessionid' => $session->id,
        ]), 'Nội dung phiếu');
        $actions[] = html_writer::link(new moodle_url('/mod/worksheetgrader/teams.php', [
            'id' => $id,
            'sessionid' => $session->id,
        ]), $session->teammode === 'individual' ? 'Chế độ cá nhân' : 'Chia nhóm');
        if ($session->status === 'draft') {
            $actions[] = html_writer::link(($v12 ? new moodle_url('/mod/worksheetgrader/ready.php', ['id'=>$id,'sessionid'=>$session->id]) : new moodle_url('/mod/worksheetgrader/sessions.php', [
                'id' => $id,
                'sessionid' => $session->id,
                'action' => 'open',
                'sesskey' => sesskey(),
            ])), $v12 ? 'Kiểm tra & mở' : 'Mở buổi');
        } else if ($session->status === 'open') {
            $actions[] = html_writer::link(new moodle_url('/mod/worksheetgrader/sessions.php', [
                'id' => $id,
                'sessionid' => $session->id,
                'action' => 'close',
                'sesskey' => sesskey(),
            ]), 'Đóng');
        } else if ($session->status === 'closed') {
            $actions[] = html_writer::link(new moodle_url('/mod/worksheetgrader/sessions.php', [
                'id' => $id,
                'sessionid' => $session->id,
                'action' => 'draft',
                'sesskey' => sesskey(),
            ]), 'Mở chỉnh sửa');
        }
        if (!$DB->record_exists('wsg_attempt', ['sessionid' => $session->id])) {
            $actions[] = html_writer::link(new moodle_url('/mod/worksheetgrader/sessions.php', [
                'id' => $id,
                'sessionid' => $session->id,
                'action' => 'delete',
                'sesskey' => sesskey(),
            ]), 'Xóa', ['class' => 'text-danger', 'onclick' => "return confirm('Xóa buổi này?');"]);
        }
        $hascontent = worksheetgrader_session_has_content($session);
        $table->data[] = [
            format_string($session->name),
            userdate($session->sessiondate),
            s($session->status),
            $hascontent ? (($session->contentmode ?? 'html') === 'docspace' ? 'DocSpace' : 'HTML') : html_writer::span('Chưa có', 'text-danger'),
            $session->teammode === 'individual'
                ? 'Cá nhân'
                : $DB->count_records('wsg_team', ['sessionid' => $session->id]),
            $DB->count_records_select('wsg_attempt', 'sessionid = ? AND status IN (?, ?)',
                [$session->id, 'submitted', 'graded']),
            implode(' · ', $actions),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->heading('Tạo buổi mới', 3);
if (worksheetgrader_show_guidance_notices()) {
    echo $OUTPUT->notification('Sau khi tạo, hệ thống sẽ chuyển thẳng sang bước Nội dung phiếu; không còn trang “Continue” trung gian.', 'info');
}
$form->display();
echo $OUTPUT->footer();
