<?php
// This file is part of Moodle - http://moodle.org/.

require('../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$sessionid = optional_param('sessionid', 0, PARAM_INT);
[$cm, $course, $activity, $context] = worksheetgrader_get_page_context($id);
require_capability('mod/worksheetgrader:manageteams', $context);

$session = worksheetgrader_get_selected_session((int)$activity->id, $sessionid);
$sessionid = $session ? (int)$session->id : 0;
worksheetgrader_setup_page($PAGE, $cm, $course, $activity, get_string('teams', 'mod_worksheetgrader'),
    'teams.php', $sessionid ? ['sessionid' => $sessionid] : []);

if (!$session) {
    echo $OUTPUT->header();
    echo $OUTPUT->render(worksheetgrader_nav_tabs($cm, 'teams'));
    echo worksheetgrader_render_workflow($cm, null, 'teams');
    echo $OUTPUT->heading(get_string('teams', 'mod_worksheetgrader'));
    echo $OUTPUT->notification(get_string('nosessionyet', 'mod_worksheetgrader'), 'info');
    echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/sessions.php', ['id' => $id]),
        'Tạo buổi/lượt đầu tiên', 'get');
    echo $OUTPUT->footer();
    exit;
}


if (\mod_worksheetgrader\service\v12_gate::enabled_for_course((int)$course->id)) {
    require(__DIR__ . '/v12/teams.php');
    exit;
}

$error = '';
$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '' && confirm_sesskey()) {
    try {
        if ($action === 'unlockteams') {
            \mod_worksheetgrader\service\session_manager::set_membership_locked($session, false, (int)$USER->id);
            worksheetgrader_redirect(new moodle_url('/mod/worksheetgrader/teams.php', [
                'id' => $id, 'sessionid' => $sessionid,
            ]), 'Đã mở khóa chia nhóm. Học sinh tạm thời không thể sửa bài cho đến khi giáo viên khóa lại nhóm.');
        } else if ($action === 'lockteams') {
            \mod_worksheetgrader\service\session_manager::set_membership_locked($session, true, (int)$USER->id);
            worksheetgrader_redirect(new moodle_url('/mod/worksheetgrader/teams.php', [
                'id' => $id, 'sessionid' => $sessionid,
            ]), 'Đã khóa lại nhóm. Học sinh có thể tiếp tục làm bài.');
        } else if ($action === 'random') {
            if ($session->status === 'closed' || $session->membershiplocked) {
                throw new moodle_exception('teamslocked', 'mod_worksheetgrader');
            }
            if ($DB->record_exists('wsg_attempt', ['sessionid' => $sessionid])) {
                throw new moodle_exception('randomwithattempts', 'mod_worksheetgrader');
            }
            $randommode = optional_param('randommode', 'count', PARAM_ALPHA);
            $value = max(1, required_param('randomvalue', PARAM_INT));
            $studentsforrandom = worksheetgrader_get_students($context);
            $layout = $randommode === 'size'
                ? \mod_worksheetgrader\service\team_manager::random_layout($studentsforrandom, $value)
                : \mod_worksheetgrader\service\team_manager::random_layout_by_count($studentsforrandom, $value);
            \mod_worksheetgrader\service\team_manager::replace_layout($sessionid, $layout, (int)$USER->id);
        } else if ($action === 'moodlegroups') {
            if ($session->status !== 'draft') {
                throw new moodle_exception('randomonlydraft', 'mod_worksheetgrader');
            }
            \mod_worksheetgrader\service\team_manager::import_moodle_groups(
                (int)$course->id, $sessionid, (int)$USER->id
            );
        } else if ($action === 'copy') {
            if ($session->status !== 'draft') {
                throw new moodle_exception('randomonlydraft', 'mod_worksheetgrader');
            }
            $sourceid = required_param('sourceid', PARAM_INT);
            \mod_worksheetgrader\service\session_manager::copy_teams($sourceid, $sessionid, (int)$USER->id);
        }
        worksheetgrader_redirect(new moodle_url('/mod/worksheetgrader/teams.php', [
            'id' => $id, 'sessionid' => $sessionid,
        ]), 'Đã cập nhật danh sách nhóm.');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

// Refresh session after any action.
$session = $DB->get_record('wsg_session', ['id' => $sessionid], '*', MUST_EXIST);
$editable = $session->status !== 'closed' && !$session->membershiplocked;

if (optional_param('csvimport', 0, PARAM_BOOL) && data_submitted()) {
    try {
        require_sesskey();
        if (!$editable) {
            throw new moodle_exception('teamslocked', 'mod_worksheetgrader');
        }
        if ($session->teammode === 'individual') {
            throw new moodle_exception('csvnotindividual', 'mod_worksheetgrader');
        }
        if (empty($_FILES['csvfile']) || (int)$_FILES['csvfile']['error'] !== UPLOAD_ERR_OK) {
            throw new moodle_exception('csvinvalidfile', 'mod_worksheetgrader');
        }
        $result = \mod_worksheetgrader\service\team_csv_manager::import_csv(
            $sessionid,
            (string)$_FILES['csvfile']['tmp_name'],
            worksheetgrader_get_students($context),
            (int)$USER->id
        );
        worksheetgrader_redirect(new moodle_url('/mod/worksheetgrader/teams.php', [
            'id' => $id, 'sessionid' => $sessionid,
        ]), 'Đã nhập CSV: ' . $result['teams'] . ' nhóm, ' . $result['members'] . ' học sinh.');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

if (optional_param('fallbacksave', 0, PARAM_BOOL) && data_submitted() && confirm_sesskey()) {
    try {
        if (!$editable) {
            throw new moodle_exception('teamslocked', 'mod_worksheetgrader');
        }
        $assignments = optional_param_array('teamassignment', [], PARAM_INT);
        $names = optional_param_array('teamname', [], PARAM_TEXT);
        $teamids = optional_param_array('teamid', [], PARAM_INT);
        $representatives = optional_param_array('representative', [], PARAM_INT);
        $layoutbyteam = [];
        foreach ($assignments as $userid => $teamnumber) {
            $userid = (int)$userid;
            $teamnumber = (int)$teamnumber;
            if ($userid <= 0 || $teamnumber <= 0) {
                continue;
            }
            if (!isset($layoutbyteam[$teamnumber])) {
                $layoutbyteam[$teamnumber] = [
                    'id' => (int)($teamids[$teamnumber] ?? 0),
                    'name' => trim((string)($names[$teamnumber] ?? '')) ?: 'Nhóm ' . $teamnumber,
                    'members' => [],
                    'representativeuserid' => 0,
                ];
            }
            $layoutbyteam[$teamnumber]['members'][] = $userid;
        }
        ksort($layoutbyteam);
        foreach ($layoutbyteam as $teamnumber => &$item) {
            $requestedrepresentative = (int)($representatives[$teamnumber] ?? 0);
            $item['representativeuserid'] = in_array($requestedrepresentative, $item['members'], true)
                ? $requestedrepresentative
                : (int)($item['members'][0] ?? 0);
        }
        unset($item);
        \mod_worksheetgrader\service\team_manager::replace_layout(
            $sessionid, array_values($layoutbyteam), (int)$USER->id
        );
        worksheetgrader_redirect(new moodle_url('/mod/worksheetgrader/teams.php', [
            'id' => $id, 'sessionid' => $sessionid,
        ]), 'Đã lưu bảng phân nhóm dự phòng.');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$students = worksheetgrader_get_students($context);
$layout = \mod_worksheetgrader\service\team_manager::get_layout($sessionid);
$sessions = $DB->get_records('wsg_session', ['worksheetgraderid' => $activity->id], 'sessiondate DESC, id DESC');
$locked = !$editable;

if ($students) {
    $PAGE->requires->js_call_amd('mod_worksheetgrader/team_builder', 'init', [[
        'sessionid' => $sessionid,
        'layout' => $layout,
        'students' => array_map(static fn($user) => [
            'id' => (int)$user->id,
            'name' => fullname($user),
            'email' => $user->email,
        ], $students),
        'locked' => $locked,
    ]]);
}

echo $OUTPUT->header();
echo $OUTPUT->render(worksheetgrader_nav_tabs($cm, 'teams', $sessionid));
echo worksheetgrader_render_workflow($cm, $session, 'teams');
echo $OUTPUT->heading(($session->teammode === 'individual' ? 'Chế độ cá nhân: ' : 'Chia nhóm: ') . format_string($session->name));
echo worksheetgrader_render_session_selector($id, $sessions, $sessionid, 'teams.php');

if ($error !== '') {
    echo $OUTPUT->notification(s($error), 'error');
}

if ($session->teammode === 'individual') {
    $studentcount = count(worksheetgrader_get_students($context));
    $attemptcount = $DB->count_records('wsg_attempt', ['sessionid' => $sessionid]);
    echo $OUTPUT->heading('Buổi làm bài cá nhân', 3);
    echo $OUTPUT->notification(
        'Buổi này không chia nhóm. Hệ thống không tạo sẵn nhóm cho từng học sinh; ' .
        'không gian bài làm cá nhân được tạo tự động khi mỗi học sinh mở phiếu lần đầu.',
        'info'
    );
    $table = new html_table();
    $table->head = ['Học sinh có quyền làm bài', 'Đã bắt đầu/nộp', 'Trạng thái buổi'];
    $table->data[] = [$studentcount, $attemptcount, s($session->status)];
    echo html_writer::table($table);
    echo html_writer::start_div('wsg-next-actions mt-4');
    if ($session->status === 'draft' && worksheetgrader_session_has_content($session)) {
        echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/sessions.php', [
            'id' => $id, 'sessionid' => $sessionid, 'action' => 'open', 'sesskey' => sesskey(),
        ]), 'Mở buổi cá nhân cho học sinh', 'get');
    }
    echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/content.php', [
        'id' => $id, 'sessionid' => $sessionid,
    ]), 'Quay lại nội dung phiếu', 'get');
    echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/grade.php', [
        'id' => $id, 'sessionid' => $sessionid,
    ]), 'Chấm bài cá nhân', 'get');
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}
if (!worksheetgrader_session_has_content($session)) {
    echo $OUTPUT->notification(
        'Buổi này chưa có nội dung phiếu. Bạn có thể chia nhóm trước, nhưng cần nhập nội dung trước khi mở buổi.',
        'warning'
    );
    echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/content.php', [
        'id' => $id, 'sessionid' => $sessionid,
    ]), 'Nhập nội dung phiếu', 'get');
}
if (!$students) {
    echo $OUTPUT->notification(
        'Không tìm thấy học sinh có quyền làm bài trong hoạt động này. Hãy kiểm tra ghi danh và capability mod/worksheetgrader:submit.',
        'warning'
    );
}

if ($session->status === 'open' && !$session->membershiplocked) {
    echo $OUTPUT->notification(
        'Chế độ điều chỉnh nhóm đang bật. Học sinh tạm thời chỉ xem được bài, không thể sửa hoặc nộp cho đến khi bạn khóa lại nhóm.',
        'warning'
    );
} else if ($session->membershiplocked) {
    echo $OUTPUT->notification(
        'Danh sách thành viên đang khóa. Bài đã nộp luôn giữ snapshot cũ ngay cả khi nhóm được thay đổi ở thời điểm khác.',
        'info'
    );
}

$submittedcount = $DB->count_records_select('wsg_attempt', 'sessionid = ? AND status IN (?, ?)', [
    $sessionid, 'submitted', 'graded',
]);
if ($editable && $submittedcount > 0) {
    echo $OUTPUT->notification(
        'Buổi đã có ' . $submittedcount . ' bài đã nộp/chấm. Bạn vẫn có thể đổi các nhóm chưa nộp; thành viên của bài đã nộp được khóa để bảo toàn lịch sử và điểm.',
        'info'
    );
}

echo $OUTPUT->heading('Trình chia nhóm kéo thả', 3);
echo html_writer::div('', 'wsg-team-builder', ['id' => 'wsg-team-builder']);
echo html_writer::div(
    'Khi buổi đang mở, hãy bấm “Mở khóa chia nhóm”, điều chỉnh, lưu, rồi bấm “Khóa lại nhóm & tiếp tục buổi”.',
    'small text-muted mb-3'
);

if ($editable) {
    $hasattempts = $DB->record_exists('wsg_attempt', ['sessionid' => $sessionid]);
    echo html_writer::start_div('wsg-team-tools mt-3 p-3 border rounded bg-light');
    echo html_writer::tag('h4', 'Công cụ chia nhóm nhanh', ['class' => 'mb-3']);
    if (!$hasattempts) {
        echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'd-flex gap-2 align-items-end flex-wrap mb-3']);
        foreach (['id' => $id, 'sessionid' => $sessionid, 'action' => 'random', 'sesskey' => sesskey()] as $name => $value) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
        echo html_writer::start_div();
        echo html_writer::label('Cách chia', 'wsg-randommode', false, ['class' => 'form-label']);
        echo html_writer::select([
            'count' => 'Chia thành ... nhóm ngẫu nhiên',
            'size' => 'Mỗi nhóm ... học sinh',
        ], 'randommode', 'count', false, ['id' => 'wsg-randommode', 'class' => 'form-select']);
        echo html_writer::end_div();
        echo html_writer::start_div();
        echo html_writer::label('Số lượng', 'wsg-randomvalue', false, ['class' => 'form-label']);
        echo html_writer::empty_tag('input', [
            'type' => 'number', 'min' => 1, 'max' => max(1, count($students)), 'value' => 3,
            'name' => 'randomvalue', 'id' => 'wsg-randomvalue', 'class' => 'form-control',
        ]);
        echo html_writer::end_div();
        echo html_writer::tag('button', 'Chia ngẫu nhiên', ['type' => 'submit', 'class' => 'btn btn-primary']);
        echo html_writer::end_tag('form');
    } else {
        echo $OUTPUT->notification('Không thể chia lại toàn bộ ngẫu nhiên vì buổi đã có bài làm. Bạn vẫn có thể kéo thả các nhóm chưa nộp sau khi mở khóa.', 'info');
    }

    echo html_writer::start_div('d-flex gap-2 flex-wrap mb-3');
    if ($session->status === 'draft') {
        echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/teams.php', [
            'id' => $id, 'sessionid' => $sessionid, 'action' => 'moodlegroups', 'sesskey' => sesskey(),
        ]), 'Nạp Groups Moodle', 'get');
    }
    echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/teams_export.php', [
        'id' => $id, 'sessionid' => $sessionid,
    ]), 'Xuất CSV chia nhóm', 'get');
    echo html_writer::end_div();

    echo html_writer::start_tag('form', [
        'method' => 'post', 'enctype' => 'multipart/form-data',
        'class' => 'd-flex gap-2 align-items-end flex-wrap mb-3',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'csvimport', 'value' => 1]);
    echo html_writer::start_div();
    echo html_writer::label('Import CSV chia nhóm', 'wsg-csvfile', false, ['class' => 'form-label']);
    echo html_writer::empty_tag('input', [
        'type' => 'file', 'name' => 'csvfile', 'id' => 'wsg-csvfile',
        'accept' => '.csv,text/csv', 'required' => 'required', 'class' => 'form-control',
    ]);
    echo html_writer::end_div();
    echo html_writer::tag('button', 'Nhập CSV', ['type' => 'submit', 'class' => 'btn btn-outline-primary']);
    echo html_writer::end_tag('form');
    echo html_writer::div(
        'CSV xuất ra có các cột team_id, team_name, representative, user_id, username, email, idnumber, full_name. ' .
        'Mỗi dòng là một học sinh; có thể sửa bằng Excel rồi nhập lại.',
        'small text-muted'
    );
    $others = $session->status === 'draft'
        ? $DB->get_records_select_menu('wsg_session', 'worksheetgraderid = ? AND id <> ?',
            [$activity->id, $sessionid], 'sessiondate DESC', 'id,name')
        : [];
    if ($others) {
        echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'd-flex gap-2 align-items-center']);
        foreach ([
            'id' => $id,
            'sessionid' => $sessionid,
            'action' => 'copy',
            'sesskey' => sesskey(),
        ] as $name => $value) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
        echo html_writer::select($others, 'sourceid', '', false);
        echo html_writer::tag('button', 'Sao chép nhóm buổi trước', [
            'type' => 'submit', 'class' => 'btn btn-outline-secondary',
        ]);
        echo html_writer::end_tag('form');
    }
    echo html_writer::end_div();
}

// Server-rendered fallback which does not depend on JavaScript.
$currentassignment = [];
$currentnames = [];
$currentteamids = [];
$currentrepresentatives = [];
foreach ($layout as $index => $team) {
    $teamnumber = $index + 1;
    $currentnames[$teamnumber] = $team['name'];
    $currentteamids[$teamnumber] = (int)($team['id'] ?? 0);
    $currentrepresentatives[$teamnumber] = (int)($team['representativeuserid'] ?? 0);
    foreach ($team['members'] as $userid) {
        $currentassignment[(int)$userid] = $teamnumber;
    }
}
$maxteams = max(12, count($layout) + 4);

echo html_writer::start_tag('details', ['class' => 'wsg-fallback-panel mt-4']);
echo html_writer::tag('summary', 'Bảng phân nhóm dự phòng');
if ($locked) {
    echo $OUTPUT->notification('Bảng chỉ đọc vì danh sách nhóm đang khóa hoặc buổi đã đóng.', 'info');
}
echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'mt-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'fallbacksave', 'value' => 1]);

echo html_writer::start_div('wsg-team-name-grid mb-3');
for ($teamnumber = 1; $teamnumber <= $maxteams; $teamnumber++) {
    echo html_writer::start_div('wsg-team-name-item');
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'teamid[' . $teamnumber . ']',
        'value' => $currentteamids[$teamnumber] ?? 0,
    ]);
    echo html_writer::label('Tên nhóm ' . $teamnumber, 'teamname_' . $teamnumber, false, ['class' => 'small']);
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'id' => 'teamname_' . $teamnumber,
        'name' => 'teamname[' . $teamnumber . ']',
        'class' => 'form-control form-control-sm',
        'value' => $currentnames[$teamnumber] ?? 'Nhóm ' . $teamnumber,
        'disabled' => $locked ? 'disabled' : null,
    ]);
    echo html_writer::end_div();
}
echo html_writer::end_div();

$table = new html_table();
$table->head = ['Học sinh', 'Email/Mã', 'Xếp vào nhóm'];
$teamoptions = [0 => 'Chưa xếp nhóm'];
for ($teamnumber = 1; $teamnumber <= $maxteams; $teamnumber++) {
    $teamoptions[$teamnumber] = $currentnames[$teamnumber] ?? 'Nhóm ' . $teamnumber;
}
foreach ($students as $student) {
    $identity = $student->email ?: ($student->idnumber ?: $student->username);
    $select = html_writer::select(
        $teamoptions,
        'teamassignment[' . $student->id . ']',
        $currentassignment[(int)$student->id] ?? 0,
        false,
        ['class' => 'form-select form-select-sm', 'disabled' => $locked ? 'disabled' : null]
    );
    $table->data[] = [fullname($student), s($identity), $select];
}
echo html_writer::table($table);

if (!$locked && $layout) {
    echo html_writer::tag('h4', 'Học sinh đại diện', ['class' => 'mt-3']);
    foreach ($layout as $index => $team) {
        $teamnumber = $index + 1;
        $memberoptions = [];
        foreach ($students as $student) {
            if (($currentassignment[(int)$student->id] ?? 0) === $teamnumber) {
                $memberoptions[(int)$student->id] = fullname($student);
            }
        }
        if ($memberoptions) {
            echo html_writer::start_div('mb-2');
            echo html_writer::label(format_string($team['name']), 'representative_' . $teamnumber, false, [
                'class' => 'me-2 fw-bold',
            ]);
            echo html_writer::select(
                $memberoptions,
                'representative[' . $teamnumber . ']',
                $currentrepresentatives[$teamnumber] ?? 0,
                false,
                ['id' => 'representative_' . $teamnumber, 'class' => 'form-select d-inline-block w-auto']
            );
            echo html_writer::end_div();
        }
    }
}
if (!$locked) {
    echo html_writer::tag('button', 'Lưu bảng phân nhóm dự phòng', [
        'type' => 'submit', 'class' => 'btn btn-primary mt-3',
    ]);
}
echo html_writer::end_tag('form');
echo html_writer::end_tag('details');

echo html_writer::start_div('wsg-next-actions mt-4');
if ($session->status === 'draft' && count($layout) > 0 && worksheetgrader_session_has_content($session)) {
    echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/sessions.php', [
        'id' => $id, 'sessionid' => $sessionid, 'action' => 'open', 'sesskey' => sesskey(),
    ]), 'Khóa nhóm & mở buổi cho học sinh', 'get');
} else if ($session->status === 'open' && $session->membershiplocked) {
    echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/teams.php', [
        'id' => $id, 'sessionid' => $sessionid, 'action' => 'unlockteams', 'sesskey' => sesskey(),
    ]), 'Mở khóa chia nhóm', 'get', [
        'class' => 'btn btn-warning',
        'onclick' => "return confirm('Tạm dừng việc sửa bài của học sinh để điều chỉnh nhóm?');",
    ]);
} else if ($session->status === 'open' && !$session->membershiplocked) {
    echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/teams.php', [
        'id' => $id, 'sessionid' => $sessionid, 'action' => 'lockteams', 'sesskey' => sesskey(),
    ]), 'Khóa lại nhóm & tiếp tục buổi', 'get');
}
echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/content.php', [
    'id' => $id, 'sessionid' => $sessionid,
]), 'Quay lại nội dung phiếu', 'get');
echo html_writer::end_div();

echo $OUTPUT->footer();
