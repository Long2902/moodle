<?php
// This file is part of Moodle - http://moodle.org/.

require('../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
[$cm, $course, $activity, $context] = worksheetgrader_get_page_context($id);
worksheetgrader_setup_page($PAGE, $cm, $course, $activity, format_string($activity->name), 'view.php');

$completion = new completion_info($course);
$completion->set_module_viewed($cm);
$ismanager = has_capability('mod/worksheetgrader:managesessions', $context);
$sessions = $DB->get_records('wsg_session', ['worksheetgraderid' => $activity->id], 'sessiondate DESC, id DESC');
$latestsession = $sessions ? reset($sessions) : null;

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($activity->name));

if ($ismanager) {
    echo $OUTPUT->render(worksheetgrader_nav_tabs($cm, 'view', $latestsession ? (int)$latestsession->id : 0));
    echo worksheetgrader_render_workflow($cm, $latestsession ?: null, 'view');

    $stats = ['sessions' => count($sessions), 'open' => 0, 'submitted' => 0, 'graded' => 0];
    foreach ($sessions as $session) {
        if (\mod_worksheetgrader\service\session_manager::is_open($session)) {
            $stats['open']++;
        }
        $stats['submitted'] += $DB->count_records_select('wsg_attempt', 'sessionid = ? AND status IN (?, ?)',
            [$session->id, 'submitted', 'graded']);
        $stats['graded'] += $DB->count_records('wsg_attempt', ['sessionid' => $session->id, 'status' => 'graded']);
    }

    echo html_writer::start_div('wsg-stat-grid');
    foreach ([
        ['Buổi/Lượt', $stats['sessions']],
        ['Đang mở', $stats['open']],
        ['Đã nộp', $stats['submitted']],
        ['Đã chấm', $stats['graded']],
    ] as $item) {
        echo html_writer::div(html_writer::tag('strong', $item[1]) . html_writer::span($item[0]), 'wsg-stat-card');
    }
    echo html_writer::end_div();

    if (worksheetgrader_show_guidance_notices()) {
    echo $OUTPUT->notification('Luồng chuẩn: Tạo buổi → Nhập/kiểm tra nội dung phiếu → Chia nhóm → Khóa nhóm & mở buổi → Học sinh mở chính hoạt động này trong khóa học để làm bài → Chấm điểm → Báo cáo.', 'info');
}
    echo html_writer::div(
        html_writer::tag('strong', 'Học sinh làm bài ở đâu? ') .
        'Sau khi buổi được mở, mỗi thành viên vào hoạt động “' . format_string($activity->name) .
        '” trong trang khóa học. Hệ thống tự nhận nhóm của buổi và hiển thị nút Bắt đầu/Tiếp tục bài làm nhóm.',
        'alert alert-light border'
    );

    echo html_writer::start_div('wsg-dashboard-actions');
    echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/sessions.php', ['id' => $cm->id]),
        $sessions ? 'Quản lý các buổi học' : 'Tạo buổi đầu tiên', 'get');
    if ($latestsession) {
        echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/content.php', [
            'id' => $cm->id,
            'sessionid' => $latestsession->id,
        ]), 'Nội dung phiếu buổi gần nhất', 'get');
        echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/teams.php', [
            'id' => $cm->id,
            'sessionid' => $latestsession->id,
        ]), 'Nhóm & thành viên', 'get');
    }
    echo html_writer::end_div();

    if ($sessions) {
        echo $OUTPUT->heading('Các buổi gần đây', 3);
        $table = new html_table();
        $table->head = ['Buổi', 'Trạng thái', 'Nội dung', 'Nhóm', 'Bài nộp', 'Đi tới'];
        foreach (array_slice(array_values($sessions), 0, 8) as $session) {
            $links = [
                html_writer::link(new moodle_url('/mod/worksheetgrader/content.php', [
                    'id' => $id,
                    'sessionid' => $session->id,
                ]), 'Nội dung'),
                html_writer::link(new moodle_url('/mod/worksheetgrader/teams.php', [
                    'id' => $id,
                    'sessionid' => $session->id,
                ]), 'Nhóm'),
                html_writer::link(new moodle_url('/mod/worksheetgrader/grade.php', [
                    'id' => $id,
                    'sessionid' => $session->id,
                ]), 'Chấm'),
            ];
            $table->data[] = [
                format_string($session->name),
                s($session->status),
                worksheetgrader_session_has_content($session) ? (($session->contentmode ?? 'html') === 'docspace' ? 'DocSpace' : 'Đã có') : html_writer::span('Chưa có', 'text-danger'),
                $session->teammode === 'individual'
                    ? 'Cá nhân'
                    : $DB->count_records('wsg_team', ['sessionid' => $session->id]),
                $DB->count_records_select('wsg_attempt', 'sessionid = ? AND status IN (?, ?)',
                    [$session->id, 'submitted', 'graded']),
                implode(' · ', $links),
            ];
        }
        echo html_writer::table($table);
    }
} else {
    $visible = 0;
    foreach ($sessions as $session) {
        $isindividual = ($session->teammode ?? '') === 'individual';
        $team = \mod_worksheetgrader\service\team_manager::get_user_team((int)$session->id, (int)$USER->id);
        if (!$team && !$isindividual) {
            continue;
        }
        $visible++;
        $attempt = $team ? $DB->get_record('wsg_attempt', ['teamid' => $team->id, 'attemptnumber' => 1]) : false;
        $members = $team
            ? \mod_worksheetgrader\service\team_manager::get_member_ids((int)$team->id)
            : [(int)$USER->id];
        $memberusers = $DB->get_records_list('user', 'id', $members, 'lastname,firstname', 'id,firstname,lastname');
        $isopen = \mod_worksheetgrader\service\session_manager::is_open($session);
        $canwork = $isopen && ($isindividual || (bool)$session->membershiplocked);
        if ($isopen && !$session->membershiplocked) {
            $status = 'Giáo viên đang điều chỉnh nhóm';
        } else {
            $status = $attempt ? $attempt->status : ($canwork ? 'Sẵn sàng làm' :
                ($session->status === 'draft' ? 'Chờ giáo viên mở' : 'Đã đóng'));
        }
        $url = new moodle_url('/mod/worksheetgrader/attempt.php', [
            'id' => $cm->id,
            'sessionid' => $session->id,
        ]);
        echo html_writer::start_div('card mb-3');
        echo html_writer::start_div('card-body');
        echo html_writer::tag('h4', format_string($session->name));
        $contextlabel = $isindividual
            ? 'Hình thức: Cá nhân'
            : 'Nhóm: ' . format_string($team->name) . ' · Thành viên: ' .
                implode(', ', array_map('fullname', $memberusers));
        echo html_writer::tag('p', $contextlabel . ' · Trạng thái: ' . s($status));
        if ($attempt) {
            $label = $attempt->status === 'inprogress' && $canwork
                ? ($isindividual ? 'Tiếp tục bài làm cá nhân' : 'Tiếp tục bài làm nhóm')
                : ($isindividual ? 'Xem bài làm cá nhân' : 'Xem bài làm nhóm');
            echo $OUTPUT->single_button($url, $label, 'get');
        } else if ($canwork) {
            echo $OUTPUT->single_button($url, $isindividual ? 'Bắt đầu làm bài cá nhân' : 'Bắt đầu làm bài nhóm', 'get');
        } else if ($isopen && !$session->membershiplocked) {
            echo html_writer::span('Giáo viên đang điều chỉnh nhóm. Hãy tải lại trang sau ít phút.', 'text-warning');
        } else {
            echo html_writer::span('Giáo viên chưa mở buổi hoặc thời gian làm bài chưa đến.', 'text-muted');
        }
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
    if (!$visible) {
        echo $OUTPUT->notification('Hiện bạn chưa được xếp vào nhóm của buổi nào. Hãy liên hệ giáo viên.', 'info');
    }
}

echo $OUTPUT->footer();
