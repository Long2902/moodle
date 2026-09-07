<?php
// This file is part of Moodle - http://moodle.org/.

require('../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$sessionid = optional_param('sessionid', 0, PARAM_INT);
$selectedattemptid = optional_param('attemptid', 0, PARAM_INT);
[$cm, $course, $activity, $context] = worksheetgrader_get_page_context($id);
require_capability('mod/worksheetgrader:grade', $context);

$session = worksheetgrader_get_selected_session((int)$activity->id, $sessionid);
$sessionid = $session ? (int)$session->id : 0;
$error = '';

if ($session && data_submitted() && confirm_sesskey()) {
    try {
        $attemptid = required_param('attemptid', PARAM_INT);
        $attempt = $DB->get_record('wsg_attempt', ['id' => $attemptid, 'sessionid' => $sessionid], '*', MUST_EXIST);
        $groupgrade = required_param('groupgrade', PARAM_FLOAT);
        $feedback = optional_param('feedback', '', PARAM_TEXT);
        $adjustments = optional_param_array('adjustment', [], PARAM_FLOAT);
        $publish = optional_param('publish', 0, PARAM_BOOL);
        if ($publish) {
            require_capability('mod/worksheetgrader:publishgrades', $context);
        }
        \mod_worksheetgrader\service\grading_manager::save_attempt_grades(
            $attempt, $session, $activity, $cm, $groupgrade, $feedback,
            $adjustments, (bool)$publish, (int)$USER->id
        );
        worksheetgrader_redirect(new moodle_url('/mod/worksheetgrader/grade.php', [
            'id' => $id,
            'sessionid' => $sessionid,
            'attemptid' => $attemptid,
        ]), $publish ? 'Đã công bố điểm cho toàn bộ thành viên.' : 'Đã lưu bản nháp điểm.');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

worksheetgrader_setup_page($PAGE, $cm, $course, $activity, get_string('grading', 'mod_worksheetgrader'),
    'grade.php', $sessionid ? ['sessionid' => $sessionid, 'attemptid' => $selectedattemptid] : []);
$sessions = $DB->get_records('wsg_session', ['worksheetgraderid' => $activity->id], 'sessiondate DESC, id DESC');

echo $OUTPUT->header();
echo $OUTPUT->render(worksheetgrader_nav_tabs($cm, 'grading', $sessionid));
echo worksheetgrader_render_workflow($cm, $session, 'grading');
echo $OUTPUT->heading('Chấm bài: xem phiếu và nhập điểm cùng lúc');

if (!$session) {
    echo $OUTPUT->notification(get_string('nosessionyet', 'mod_worksheetgrader'), 'info');
    echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/sessions.php', ['id' => $id]),
        'Tạo buổi/lượt đầu tiên', 'get');
    echo $OUTPUT->footer();
    exit;
}

echo worksheetgrader_render_session_selector($id, $sessions, $sessionid, 'grade.php');
if ($error !== '') {
    echo $OUTPUT->notification(s($error), 'error');
}

$attempts = array_values($DB->get_records_select('wsg_attempt', 'sessionid = ? AND status IN (?, ?)',
    [$sessionid, 'submitted', 'graded'], 'timesubmitted ASC, id ASC'));
if (!$attempts) {
    echo $OUTPUT->notification('Chưa có bài nhóm nào đã nộp trong buổi này.', 'info');
    echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/teams.php', [
        'id' => $id,
        'sessionid' => $sessionid,
    ]), 'Kiểm tra nhóm & mở buổi', 'get');
    echo $OUTPUT->footer();
    exit;
}

$selectedindex = 0;
if ($selectedattemptid) {
    foreach ($attempts as $index => $candidate) {
        if ((int)$candidate->id === $selectedattemptid) {
            $selectedindex = $index;
            break;
        }
    }
}
$attempt = $attempts[$selectedindex];
$team = $DB->get_record('wsg_team', ['id' => $attempt->teamid], '*', MUST_EXIST);
$members = json_decode($attempt->membersnapshot ?: '[]', true)
    ?: \mod_worksheetgrader\service\team_manager::get_member_ids((int)$team->id);
$users = $DB->get_records_list('user', 'id', $members, 'lastname,firstname', 'id,firstname,lastname,email,idnumber');
$existing = $DB->get_records('wsg_grade', ['attemptid' => $attempt->id]);
$submitter = $attempt->submitteruserid
    ? $DB->get_record('user', ['id' => $attempt->submitteruserid], 'id,firstname,lastname', IGNORE_MISSING)
    : null;

// Build HTML, immutable V12 Office submission, or legacy DocSpace preview.
$isnative = ($session->worksheetkind ?? '') === 'native';
$isoffice = \mod_worksheetgrader\service\v12_gate::enabled_for_course((int)$course->id) &&
    ($session->worksheetkind ?? '') === 'office';
$isdocspace = !$isnative && !$isoffice && ($session->contentmode ?? 'html') === 'docspace';
if ($isnative) {
    $PAGE->requires->css('/local/digieranative/styles.css');
}
$docspaceworkspace = $isdocspace
    ? \mod_worksheetgrader\service\docspace_manager::get_attempt_workspace((int)$attempt->id)
    : null;
$docspacesnapshot = $isdocspace
    ? \mod_worksheetgrader\service\docspace_manager::get_snapshot($context, (int)$attempt->id)
    : null;
$answers = json_decode($attempt->answersjson ?: '{}', true) ?: [];
$submissionhtml = $isnative ? (string)$attempt->submissionhtml : '';
if (!$isnative && !$isdocspace && !$isoffice) {
    // Rebuild so inline image submissions are always rendered through Moodle File API.
    $submissionhtml = worksheetgrader_build_submission_html(
        worksheetgrader_normalize_interactive_template((string)($session->contenthtml ?: $activity->contenthtml)),
        $answers,
        ['contextid' => (int)$context->id, 'attemptid' => (int)$attempt->id]
    );
}
$officeviewer = null;
if ($isoffice) {
    try {
        $officeviewer = \mod_worksheetgrader\service\office_attempt_service::editor_config(
            $context, $activity, $session, $team, $attempt, $USER, true
        );
        $PAGE->requires->js_call_amd('mod_worksheetgrader/office_viewer', 'init', [[
            'target' => 'wsg-grading-office-' . (int)$attempt->id,
            'server' => $officeviewer['server'],
            'config' => $officeviewer['config'],
        ]]);
    } catch (Throwable $exception) {
        $officeviewer = null;
    }
}

// Team navigator.
echo html_writer::start_div('wsg-grading-teamnav mb-3');
foreach ($attempts as $index => $candidate) {
    $candidateTeam = $DB->get_record('wsg_team', ['id' => $candidate->teamid], 'id,name', MUST_EXIST);
    $label = format_string($candidateTeam->name);
    if ($candidate->status === 'graded') {
        $label .= ' · ' . format_float((float)$candidate->groupgrade, 2);
    } else {
        $label .= ' · Chờ chấm';
    }
    $classes = 'btn ' . ((int)$candidate->id === (int)$attempt->id ? 'btn-primary' : 'btn-outline-secondary');
    echo html_writer::link(new moodle_url('/mod/worksheetgrader/grade.php', [
        'id' => $id,
        'sessionid' => $sessionid,
        'attemptid' => $candidate->id,
    ]), $label, ['class' => $classes]);
}
echo html_writer::end_div();

echo html_writer::start_div('wsg-grading-workspace');

// Left: submitted worksheet.
echo html_writer::start_tag('section', ['class' => 'wsg-grading-preview card']);
echo html_writer::start_div('card-header');
echo html_writer::tag('h3', 'Bài làm của ' . format_string($team->name), ['class' => 'mb-1']);
echo html_writer::div(
    'Người nộp: ' . ($submitter ? fullname($submitter) : 'Không xác định') .
    ' · Nộp lúc: ' . ($attempt->timesubmitted ? userdate((int)$attempt->timesubmitted, get_string('strftimedatetimeshort')) : '—') .
    ' · Thành viên: ' . implode(', ', array_map('fullname', $users)),
    'small text-muted'
);
echo html_writer::end_div();
echo html_writer::start_div('card-body wsg-submission-preview wsg-worksheet-content');
if ($isnative) {
    echo (string)$attempt->submissionhtml;
} else if ($isoffice) {
    if ($officeviewer) {
        echo html_writer::div('', 'wsg-office-grading-viewer', ['id' => 'wsg-grading-office-' . (int)$attempt->id]);
        $submittedfile = null;
        foreach (get_file_storage()->get_area_files($context->id, 'mod_worksheetgrader', 'officesubmission', (int)$attempt->id, 'id', false) as $file) { $submittedfile = $file; break; }
        if ($submittedfile) {
            $url = moodle_url::make_pluginfile_url($context->id, 'mod_worksheetgrader', 'officesubmission', (int)$attempt->id, '/', $submittedfile->get_filename(), true);
            echo html_writer::link($url, 'Tải bản Office đã nộp trong Moodle', ['class'=>'btn btn-outline-secondary mt-3']);
        }
    } else {
        echo $OUTPUT->notification('Không mở được bản Office đã nộp. Hãy kiểm tra ONLYOFFICE hoặc file submission trong Moodle.', 'warning');
    }
} else if ($isdocspace) {
    if ($docspaceworkspace) {
        echo html_writer::div(
            worksheetgrader_render_docspace_frame($docspaceworkspace, 'grading-' . $attempt->id, true),
            'wsg-docspace-grading'
        );
    } else {
        echo $OUTPUT->notification(get_string('docspacetemplatemissing', 'mod_worksheetgrader'), 'warning');
    }
    if ($docspacesnapshot) {
        $snapshoturl = moodle_url::make_pluginfile_url(
            $context->id,
            'mod_worksheetgrader',
            'docspacesnapshot',
            $attempt->id,
            '/',
            $docspacesnapshot->get_filename(),
            true
        );
        echo html_writer::link($snapshoturl, 'Tải bản tài liệu đã nộp lưu trong Moodle', [
            'class' => 'btn btn-outline-secondary mt-3',
        ]);
    } else {
        echo $OUTPUT->notification(get_string('docspacesnapshotmissing', 'mod_worksheetgrader'), 'warning');
    }
} else {
    echo $submissionhtml;
}
echo html_writer::end_div();
echo html_writer::end_tag('section');

// Right: grading form.
echo html_writer::start_tag('aside', ['class' => 'wsg-grading-panel card']);
echo html_writer::start_div('card-header');
echo html_writer::tag('h3', 'Điểm & phản hồi', ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::start_div('card-body');
echo html_writer::start_tag('form', ['method' => 'post']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'attemptid', 'value' => $attempt->id]);

echo html_writer::start_div('mb-3');
echo html_writer::label('Điểm chung của nhóm', 'groupgrade_' . $attempt->id, false, ['class' => 'form-label fw-bold']);
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'step' => '0.01',
    'min' => 0,
    'max' => $session->maxpoints,
    'class' => 'form-control form-control-lg',
    'id' => 'groupgrade_' . $attempt->id,
    'name' => 'groupgrade',
    'value' => $attempt->groupgrade ?? '',
    'required' => 'required',
]);
echo html_writer::div('Thang điểm: 0–' . format_float((float)$session->maxpoints, 2), 'form-text');
echo html_writer::end_div();

echo html_writer::start_div('mb-3');
echo html_writer::label('Nhận xét chung', 'feedback_' . $attempt->id, false, ['class' => 'form-label fw-bold']);
echo html_writer::tag('textarea', s($attempt->groupfeedback ?? ''), [
    'class' => 'form-control',
    'id' => 'feedback_' . $attempt->id,
    'name' => 'feedback',
    'rows' => 6,
    'placeholder' => 'Nhận xét về bài làm, điểm mạnh và nội dung cần cải thiện…',
]);
echo html_writer::end_div();

if ($activity->allowadjust) {
    echo html_writer::tag('h4', 'Điều chỉnh cá nhân', ['class' => 'mt-4']);
    foreach ($users as $user) {
        $grade = null;
        foreach ($existing as $existinggrade) {
            if ((int)$existinggrade->userid === (int)$user->id) {
                $grade = $existinggrade;
                break;
            }
        }
        echo html_writer::start_div('wsg-member-adjustment');
        echo html_writer::div(html_writer::tag('strong', fullname($user)) .
            html_writer::span($user->email ? ' · ' . s($user->email) : '', 'small text-muted'), 'wsg-member-name');
        echo html_writer::empty_tag('input', [
            'type' => 'number',
            'step' => '0.01',
            'class' => 'form-control',
            'name' => 'adjustment[' . $user->id . ']',
            'value' => $grade->adjustment ?? 0,
            'aria-label' => 'Điều chỉnh điểm cho ' . fullname($user),
        ]);
        echo html_writer::div($grade ? 'Điểm hiện tại: ' . format_float($grade->finalgrade, 2) : 'Điểm cuối được tính khi lưu',
            'small text-muted');
        echo html_writer::end_div();
    }
}

echo html_writer::start_div('wsg-grade-actions mt-4');
echo html_writer::tag('button', 'Lưu nháp', ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::tag('button', 'Công bố cho cả nhóm', [
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'name' => 'publish',
    'value' => '1',
    'onclick' => "return confirm('Ghi điểm vào Gradebook cho toàn bộ thành viên của nhóm này?');",
]);
echo html_writer::end_div();
echo html_writer::end_tag('form');

echo html_writer::start_div('wsg-grade-pagination mt-4');
if ($selectedindex > 0) {
    echo html_writer::link(new moodle_url('/mod/worksheetgrader/grade.php', [
        'id' => $id, 'sessionid' => $sessionid, 'attemptid' => $attempts[$selectedindex - 1]->id,
    ]), '← Nhóm trước', ['class' => 'btn btn-outline-secondary']);
}
if ($selectedindex + 1 < count($attempts)) {
    echo html_writer::link(new moodle_url('/mod/worksheetgrader/grade.php', [
        'id' => $id, 'sessionid' => $sessionid, 'attemptid' => $attempts[$selectedindex + 1]->id,
    ]), 'Nhóm tiếp theo →', ['class' => 'btn btn-outline-secondary']);
}
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_tag('aside');
echo html_writer::end_div();

echo $OUTPUT->footer();
