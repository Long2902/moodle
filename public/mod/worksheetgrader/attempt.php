<?php
// This file is part of Moodle - http://moodle.org/.

require('../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$sessionid = required_param('sessionid', PARAM_INT);
[$cm, $course, $activity, $context] = worksheetgrader_get_page_context($id);
require_capability('mod/worksheetgrader:submit', $context);

$session = $DB->get_record('wsg_session', [
    'id' => $sessionid,
    'worksheetgraderid' => $activity->id,
], '*', MUST_EXIST);
$isindividual = ($session->teammode ?? '') === 'individual';
$isnative = ($session->worksheetkind ?? '') === 'native';
$isdocspace = !$isnative && ($session->contentmode ?? 'html') === 'docspace';
$isopen = \mod_worksheetgrader\service\session_manager::is_open($session);
$team = \mod_worksheetgrader\service\team_manager::get_user_team($sessionid, (int)$USER->id);
if (!$team && $isindividual && $isopen) {
    $team = \mod_worksheetgrader\service\team_manager::get_or_create_individual_team(
        $session, $USER, (int)$USER->id
    );
}
if (!$team) {
    if (!$isindividual) {
        throw new moodle_exception('notassignedteam', 'mod_worksheetgrader');
    }
    worksheetgrader_setup_page($PAGE, $cm, $course, $activity, 'Bài làm cá nhân', 'attempt.php', [
        'sessionid' => $sessionid,
    ]);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($session->name));
    echo $OUTPUT->notification('Buổi cá nhân này chưa được mở. Hãy quay lại sau khi giáo viên mở buổi.', 'info');
    echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/view.php', ['id' => $id]), 'Quay lại hoạt động', 'get');
    echo $OUTPUT->footer();
    exit;
}

$existingattempt = $DB->get_record('wsg_attempt', ['teamid' => $team->id, 'attemptnumber' => 1]);
if (!$existingattempt && !$isopen) {
    worksheetgrader_setup_page($PAGE, $cm, $course, $activity, $isindividual ? 'Bài làm cá nhân' : 'Bài làm nhóm', 'attempt.php', [
        'sessionid' => $sessionid,
    ]);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($session->name));
    echo $OUTPUT->notification('Buổi này chưa được giáo viên mở. Học sinh chưa thể bắt đầu làm bài.', 'info');
    echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/view.php', ['id' => $id]), 'Quay lại hoạt động', 'get');
    echo $OUTPUT->footer();
    exit;
}

$attempt = $existingattempt ?: \mod_worksheetgrader\service\attempt_manager::get_or_create($session, $team, (int)$USER->id);
$canedit = $isopen && ($isindividual || (bool)$session->membershiplocked) &&
    \mod_worksheetgrader\service\attempt_manager::can_edit($activity, $team, (int)$USER->id) &&
    $attempt->status === 'inprogress';

if (\mod_worksheetgrader\service\v12_gate::enabled_for_course((int)$course->id) &&
        ($session->worksheetkind ?? '') === 'office') {
    require(__DIR__ . '/v12/attempt_office.php');
    exit;
}

$memberids = \mod_worksheetgrader\service\team_manager::get_member_ids((int)$team->id);
$users = $DB->get_records_list('user', 'id', $memberids, 'lastname,firstname', 'id,firstname,lastname,email,idnumber');
$membernames = implode(', ', array_map('fullname', $users));
$template = ($isdocspace || $isnative) ? '' : worksheetgrader_normalize_interactive_template(
    (string)($session->contenthtml ?: $activity->contenthtml)
);
$answers = ($isdocspace || $isnative) ? [] : (json_decode($attempt->answersjson ?: '{}', true) ?: []);
if (!$isdocspace && !$isnative) {
    $answers = worksheetgrader_prefill_student_info($template, $answers, $course, $team, array_values($users));
}

$submiterror = '';
$docspaceworkspace = null;
if ($isdocspace) {
    try {
        $docspaceworkspace = \mod_worksheetgrader\service\docspace_manager::ensure_attempt_workspace(
            $activity, $session, $team, $attempt, (int)$USER->id
        );
    } catch (Throwable $exception) {
        $submiterror = $exception->getMessage();
    }
}

if (data_submitted()) {
    try {
        require_sesskey();
        if (!$canedit) {
            throw new moodle_exception('attemptnoteditable', 'mod_worksheetgrader');
        }
        if ($isdocspace) {
            if (!optional_param('submitteam', 0, PARAM_BOOL)) {
                worksheetgrader_redirect(new moodle_url('/mod/worksheetgrader/attempt.php', [
                    'id' => $id, 'sessionid' => $sessionid,
                ]), 'ONLYOFFICE tự lưu tài liệu. Bài chưa được nộp.');
            }
            $docspaceworkspace = \mod_worksheetgrader\service\docspace_manager::finalise_attempt(
                $context, $activity, $session, $team, $attempt, (int)$USER->id
            );
            \mod_worksheetgrader\service\attempt_manager::submit(
                $attempt, $session, $activity, $cm, (int)$USER->id
            );
            $docspaceworkspace = \mod_worksheetgrader\service\docspace_manager::make_attempt_readonly(
                $docspaceworkspace, $activity, $session, $team, $attempt, (int)$USER->id
            );
            worksheetgrader_redirect(new moodle_url('/mod/worksheetgrader/attempt.php', [
                'id' => $id, 'sessionid' => $sessionid,
            ]), $isindividual ? 'Đã nộp tài liệu cá nhân.' : 'Đã nộp tài liệu thay mặt toàn bộ thành viên trong nhóm.');
        }
        if ($isnative) {
            if (!optional_param('submitteam', 0, PARAM_BOOL)) {
                worksheetgrader_redirect(new moodle_url('/mod/worksheetgrader/attempt.php', [
                    'id' => $id, 'sessionid' => $sessionid,
                ]), 'Phiếu Native tự động lưu. Bài chưa được nộp.');
            }
            \mod_worksheetgrader\service\attempt_manager::submit(
                $attempt, $session, $activity, $cm, (int)$USER->id
            );
            worksheetgrader_redirect(new moodle_url('/mod/worksheetgrader/attempt.php', [
                'id' => $id, 'sessionid' => $sessionid,
            ]), $isindividual ? 'Đã nộp bài cá nhân.' : 'Đã nộp bài thay mặt toàn bộ thành viên trong nhóm.');
        }

        $submittedanswers = optional_param_array('answers', [], PARAM_RAW);
        $uploadedanswers = worksheetgrader_save_attempt_images(
            $context, (int)$attempt->id, $_FILES['answerfiles'] ?? []
        );
        $submittedanswers = array_merge($answers, $submittedanswers, $uploadedanswers);
        $attempt = \mod_worksheetgrader\service\attempt_manager::save(
            $attempt, $submittedanswers, (int)$USER->id, 0
        );
        if (optional_param('submitteam', 0, PARAM_BOOL)) {
            \mod_worksheetgrader\service\attempt_manager::submit(
                $attempt, $session, $activity, $cm, (int)$USER->id
            );
            worksheetgrader_redirect(new moodle_url('/mod/worksheetgrader/attempt.php', [
                'id' => $id, 'sessionid' => $sessionid,
            ]), $isindividual ? 'Đã nộp bài cá nhân.' : 'Đã nộp bài thay mặt toàn bộ thành viên trong nhóm.');
        }
        worksheetgrader_redirect(new moodle_url('/mod/worksheetgrader/attempt.php', [
            'id' => $id, 'sessionid' => $sessionid,
        ]), $isindividual ? 'Đã lưu bài làm cá nhân.' : 'Đã lưu bài làm nhóm.');
    } catch (Throwable $exception) {
        $submiterror = get_string('attemptsavefailed', 'mod_worksheetgrader', $exception->getMessage());
        $attempt = $DB->get_record('wsg_attempt', ['id' => $attempt->id], '*', MUST_EXIST);
        if (!$isdocspace && !$isnative) {
            $answers = json_decode($attempt->answersjson ?: '{}', true) ?: [];
            $answers = worksheetgrader_prefill_student_info($template, $answers, $course, $team, array_values($users));
        }
    }
}

worksheetgrader_setup_page($PAGE, $cm, $course, $activity, $isindividual ? 'Bài làm cá nhân' : 'Bài làm nhóm', 'attempt.php', [
    'sessionid' => $sessionid,
]);
if ($isnative) {
    $PAGE->requires->css('/local/digieranative/styles.css');
}
if ($isnative && $attempt->status === 'inprogress') {
    $PAGE->requires->js_call_amd('mod_worksheetgrader/native_attempt', 'init', [[
        'elementid' => 'wsg-native-attempt-editor',
        'statusid' => 'wsg-native-attempt-status',
        'formid' => 'wsg-native-attempt-form',
        'attemptid' => (int)$attempt->id,
        'version' => (int)$attempt->version,
        'nativejson' => (string)$attempt->answersjson,
        'canedit' => (bool)$canedit,
    ]]);
} else if ($canedit && !$isdocspace) {
    $PAGE->requires->js_call_amd('mod_worksheetgrader/attempt_autosave', 'init', [[
        'attemptid' => (int)$attempt->id,
        'version' => (int)$attempt->version,
        'interval' => 10000,
    ]]);
}

$statuslabels = ['inprogress' => 'Đang làm', 'submitted' => 'Đã nộp', 'graded' => 'Đã chấm'];
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($session->name));
echo html_writer::div(
    html_writer::tag('strong', $isindividual ? 'Bài làm cá nhân' : format_string($team->name)) .
    html_writer::span(' · ' . ($statuslabels[$attempt->status] ?? s($attempt->status)) .
        ($isindividual ? '' : ' · Thành viên: ' . s($membernames))),
    'alert alert-info wsg-attempt-context'
);
if ($submiterror !== '') {
    echo $OUTPUT->notification(s($submiterror), 'error');
}
if (!$isindividual && !$session->membershiplocked && $session->status === 'open') {
    echo $OUTPUT->notification(
        'Giáo viên đang điều chỉnh lại nhóm. Bài đã lưu vẫn an toàn; bạn có thể tiếp tục sau khi nhóm được khóa lại.',
        'warning'
    );
}
if (!$canedit && $attempt->status === 'inprogress') {
    if (!$isopen) {
        echo $OUTPUT->notification('Buổi hiện không mở. Bài đã lưu vẫn được giữ nguyên.', 'warning');
    } else if ($activity->representative === 'fixed' && (int)$team->representativeuserid !== (int)$USER->id) {
        $representative = $users[(int)$team->representativeuserid] ?? null;
        echo $OUTPUT->notification('Chỉ học sinh đại diện' . ($representative ? ' (' . fullname($representative) . ')' : '') .
            ' được sửa bài. Các thành viên khác có thể theo dõi bài làm chung.', 'info');
    }
}

if ($isnative) {
    if (in_array($attempt->status, ['submitted', 'graded'], true)) {
        echo html_writer::div((string)$attempt->submissionhtml, 'wsg-worksheet-content wsg-native-submission');
    } else {
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'id' => 'wsg-native-attempt-form',
            'class' => 'wsg-native-attempt-form',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'submitteam', 'value' => '1']);
        echo html_writer::div('', 'wsg-native-attempt-editor', [
            'id' => 'wsg-native-attempt-editor',
            'data-region' => 'native-attempt-editor',
        ]);
        echo html_writer::div(
            html_writer::span($canedit ? 'Đã lưu' : 'Chỉ xem', 'wsg-native-attempt-status', [
                'id' => 'wsg-native-attempt-status',
                'data-region' => 'native-save-status',
                'aria-live' => 'polite',
            ]),
            'wsg-native-attempt-footer mt-3'
        );
        if ($canedit) {
            echo html_writer::tag('button', $isindividual ? 'Nộp bài' : 'Nộp bài cho cả nhóm', [
                'type' => 'submit',
                'class' => 'btn btn-primary',
                'onclick' => $isindividual
                    ? "return confirm('Nộp bài cá nhân? Sau khi nộp, bạn không thể sửa thêm.');"
                    : "return confirm('Nộp bài thay mặt: " . s($membernames) . "? Sau khi nộp, nhóm không thể sửa thêm.');",
            ]);
            echo html_writer::span('Phiếu được tự động lưu khi bạn chỉnh sửa.', 'text-muted small ms-3');
        }
        echo html_writer::end_tag('form');
    }
} else if ($isdocspace) {
    if (worksheetgrader_show_guidance_notices()) {
        echo $OUTPUT->notification(get_string(\mod_worksheetgrader\service\docspace_manager::shared_mode() ?
            'docspacesharedwarning' : 'docspacepublicwarning', 'mod_worksheetgrader'), 'warning');
    }
    if ($docspaceworkspace) {
        echo worksheetgrader_render_docspace_frame(
            $docspaceworkspace,
            'student-attempt-' . $attempt->id,
            !$canedit || $attempt->status !== 'inprogress'
        );
    }
    if ($canedit && $docspaceworkspace) {
        echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'mt-3']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        if (worksheetgrader_show_guidance_notices()) {
            echo $OUTPUT->notification(get_string('docspacesubmitwarning', 'mod_worksheetgrader'), 'info');
        }
        echo html_writer::tag('button', $isindividual ? 'Nộp tài liệu' : 'Nộp tài liệu cho cả nhóm', [
            'type' => 'submit',
            'class' => 'btn btn-primary btn-lg',
            'name' => 'submitteam',
            'value' => '1',
            'onclick' => $isindividual
                ? "return confirm('Nộp tài liệu cá nhân? Moodle sẽ lưu một bản chụp và khóa bài.');"
                : "return confirm('Nộp tài liệu thay mặt: " . s($membernames) . "? Moodle sẽ lưu một bản chụp và khóa bài.');",
        ]);
        echo html_writer::end_tag('form');
    }
} else {
    echo html_writer::start_tag('form', [
        'method' => 'post', 'id' => 'wsg-attempt-form', 'enctype' => 'multipart/form-data',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::div(worksheetgrader_render_answer_fields($template, $answers, !$canedit, [
        'contextid' => (int)$context->id, 'attemptid' => (int)$attempt->id,
    ]), 'wsg-worksheet-content wsg-student-worksheet');
    if ($canedit) {
        echo html_writer::start_div('wsg-attempt-actions mt-3');
        echo html_writer::tag('button', 'Lưu bài', ['type' => 'submit', 'class' => 'btn btn-secondary']);
        echo html_writer::tag('button', $isindividual ? 'Nộp bài' : 'Nộp bài cho cả nhóm', [
            'type' => 'submit', 'class' => 'btn btn-primary', 'name' => 'submitteam', 'value' => '1',
            'onclick' => $isindividual
                ? "return confirm('Nộp bài cá nhân? Sau khi nộp, bạn không thể sửa thêm.');"
                : "return confirm('Nộp bài thay mặt: " . s($membernames) . "? Sau khi nộp, nhóm không thể sửa thêm.');",
        ]);
        echo html_writer::span('Bài được tự động lưu khoảng 10 giây một lần.', 'text-muted small');
        echo html_writer::end_div();
    }
    echo html_writer::end_tag('form');
}

if (in_array($attempt->status, ['submitted', 'graded'], true)) {
    $submitter = $attempt->submitteruserid ? $DB->get_record('user', ['id' => $attempt->submitteruserid]) : null;
    if ($submitter) {
        echo $OUTPUT->notification('Người nộp đại diện: ' . fullname($submitter) . ' · ' .
            userdate((int)$attempt->timesubmitted, get_string('strftimedatetimeshort')), 'success');
    }
    if ($isdocspace) {
        $snapshot = \mod_worksheetgrader\service\docspace_manager::get_snapshot($context, (int)$attempt->id);
        if ($snapshot) {
            $url = moodle_url::make_pluginfile_url(
                $context->id, 'mod_worksheetgrader', 'docspacesnapshot', $attempt->id, '/', $snapshot->get_filename(), true
            );
            echo html_writer::link($url, 'Tải bản tài liệu đã nộp lưu trong Moodle', ['class' => 'btn btn-outline-secondary mb-3']);
        }
    }
}
if ($attempt->status === 'graded') {
    $grade = $DB->get_record('wsg_grade', ['attemptid' => $attempt->id, 'userid' => $USER->id]);
    if ($grade && $grade->published) {
        echo $OUTPUT->notification('Điểm của bạn: ' . format_float($grade->finalgrade, 2) .
            ' / ' . format_float($session->maxpoints, 2) . ' · Nhận xét: ' . s($grade->feedback), 'success');
    }
}

echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/view.php', ['id' => $id]), 'Quay lại danh sách buổi', 'get');
echo $OUTPUT->footer();
