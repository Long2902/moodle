<?php
// This file is part of Moodle - http://moodle.org/.

defined('MOODLE_INTERNAL') || die();

function worksheetgrader_get_page_context(int $cmid): array {
    global $DB;
    $cm = get_coursemodule_from_id('worksheetgrader', $cmid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $activity = $DB->get_record('worksheetgrader', ['id' => $cm->instance], '*', MUST_EXIST);
    $context = context_module::instance($cm->id);
    require_login($course, true, $cm);
    require_capability('mod/worksheetgrader:view', $context);
    return [$cm, $course, $activity, $context];
}

function worksheetgrader_setup_page($PAGE, $cm, $course, $activity, string $title, string $script, array $params = []): void {
    $PAGE->set_url(new moodle_url('/mod/worksheetgrader/' . $script, ['id' => $cm->id] + $params));
    $PAGE->set_title(format_string($activity->name) . ': ' . $title);
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->set_context(context_module::instance($cm->id));
    $PAGE->activityheader->set_attrs(['hidecompletion' => false]);
}

/** Return the requested session or the newest session for this activity. */
function worksheetgrader_get_selected_session(int $activityid, int $sessionid = 0, bool $mustexist = false): ?stdClass {
    global $DB;
    if ($sessionid > 0) {
        $session = $DB->get_record('wsg_session', ['id' => $sessionid, 'worksheetgraderid' => $activityid]);
    } else {
        $session = $DB->get_record_sql(
            'SELECT * FROM {wsg_session} WHERE worksheetgraderid = :activityid ORDER BY sessiondate DESC, id DESC',
            ['activityid' => $activityid], IGNORE_MULTIPLE);
    }
    if (!$session && $mustexist) {
        throw new moodle_exception('nosessionyet', 'mod_worksheetgrader');
    }
    return $session ?: null;
}

function worksheetgrader_nav_tabs($cm, string $active, int $sessionid = 0): tabtree {
    $sessionparams = ['id' => $cm->id];
    if ($sessionid > 0) {
        $sessionparams['sessionid'] = $sessionid;
    }
    $tabs = [
        new tabobject('view', new moodle_url('/mod/worksheetgrader/view.php', ['id' => $cm->id]), get_string('overview', 'mod_worksheetgrader')),
        new tabobject('sessions', new moodle_url('/mod/worksheetgrader/sessions.php', ['id' => $cm->id]), get_string('sessions', 'mod_worksheetgrader')),
        new tabobject('content', new moodle_url('/mod/worksheetgrader/content.php', $sessionparams), get_string('worksheetcontent', 'mod_worksheetgrader')),
        new tabobject('teams', new moodle_url('/mod/worksheetgrader/teams.php', $sessionparams), get_string('teams', 'mod_worksheetgrader')),
        new tabobject('grading', new moodle_url('/mod/worksheetgrader/grade.php', $sessionparams), get_string('grading', 'mod_worksheetgrader')),
        new tabobject('reports', new moodle_url('/mod/worksheetgrader/report.php', ['id' => $cm->id]), get_string('reports', 'mod_worksheetgrader')),
    ];
    return new tabtree($tabs, $active);
}

/** Render the five-step teacher workflow for the currently selected session. */
function worksheetgrader_render_workflow($cm, ?stdClass $session, string $active): string {
    $sessionid = $session ? (int)$session->id : 0;
    $steps = [
        'sessions' => ['1', 'Tạo buổi', new moodle_url('/mod/worksheetgrader/sessions.php', ['id' => $cm->id])],
        'content' => ['2', 'Nội dung phiếu', new moodle_url('/mod/worksheetgrader/content.php', ['id' => $cm->id, 'sessionid' => $sessionid])],
        'teams' => ['3', $session && ($session->teammode ?? '') === 'individual'
            ? 'Cá nhân (không chia nhóm)'
            : 'Nhóm & thành viên', new moodle_url('/mod/worksheetgrader/teams.php', ['id' => $cm->id, 'sessionid' => $sessionid])],
        'grading' => ['4', 'Chấm điểm', new moodle_url('/mod/worksheetgrader/grade.php', ['id' => $cm->id, 'sessionid' => $sessionid])],
        'reports' => ['5', 'Báo cáo', new moodle_url('/mod/worksheetgrader/report.php', ['id' => $cm->id])],
    ];
    $items = [];
    foreach ($steps as $key => [$number, $label, $url]) {
        $classes = 'wsg-workflow-step' . ($key === $active ? ' is-active' : '');
        if (!$session && in_array($key, ['content', 'teams', 'grading'], true)) {
            $items[] = html_writer::span(html_writer::span($number, 'wsg-step-number') . html_writer::span($label), $classes . ' is-disabled');
        } else {
            $items[] = html_writer::link($url, html_writer::span($number, 'wsg-step-number') . html_writer::span($label), ['class' => $classes]);
        }
    }
    return html_writer::div(implode('', $items), 'wsg-workflow');
}

/** Render a compact session selector that retains the current page. */
function worksheetgrader_render_session_selector(int $cmid, array $sessions, int $selectedid, string $script): string {
    if (!$sessions) {
        return '';
    }
    $options = [];
    foreach ($sessions as $session) {
        $options[(int)$session->id] = format_string($session->name) . ' — ' . userdate($session->sessiondate, get_string('strftimedatetimeshort'));
    }
    $form = html_writer::start_tag('form', ['method' => 'get', 'class' => 'wsg-session-selector mb-3']);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cmid]);
    $form .= html_writer::label('Buổi đang thao tác', 'wsg-sessionid', false, ['class' => 'mb-0 fw-bold']);
    $form .= html_writer::select($options, 'sessionid', $selectedid, false, [
        'id' => 'wsg-sessionid',
        'class' => 'form-select w-auto',
        'onchange' => 'this.form.submit()',
    ]);
    $form .= html_writer::end_tag('form');
    return $form;
}

/** Immediate redirect that does not expose Moodle's theme-dependent Continue page. */
function worksheetgrader_redirect(moodle_url $url, string $message = '', string $type = \core\output\notification::NOTIFY_SUCCESS): void {
    redirect($url, $message, 0, $type);
}

function worksheetgrader_log_action(int $activityid, string $action, array $details = [], int $sessionid = 0,
        int $teamid = 0, int $attemptid = 0, int $actorid = 0): void {
    \mod_worksheetgrader\service\audit_logger::log(
        $activityid, $action, $details, $sessionid, $teamid, $attemptid, $actorid
    );
}

function worksheetgrader_get_students(context_module $context): array {
    $users = get_enrolled_users($context, 'mod/worksheetgrader:submit', 0,
        'u.id,u.firstname,u.lastname,u.email,u.idnumber,u.username', 'u.lastname,u.firstname');
    return array_values($users);
}

function worksheetgrader_session_is_open(stdClass $session): bool {
    return \mod_worksheetgrader\service\session_manager::is_open($session);
}

/** Convert raw empty-box glyphs into stable interactive markers. */
function worksheetgrader_normalize_interactive_template(string $html): string {
    if ($html === '') {
        return '';
    }
    $next = 1;
    if (preg_match_all('/(?:data-code=["\']|\[\[)B(\d+)(?:["\']|\]\])/i', $html, $matches)) {
        $values = array_map('intval', $matches[1]);
        $next = $values ? max($values) + 1 : 1;
    }
    // Split around tags so box glyphs inside HTML text become stable B-codes,
    // while attributes and existing checkbox marker spans remain untouched.
    $pieces = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    $insidecheckbox = false;
    foreach ($pieces as &$piece) {
        if (preg_match('/^<span\b[^>]*worksheet-checkbox-slot/i', $piece)) {
            $insidecheckbox = true;
            continue;
        }
        if ($insidecheckbox && preg_match('/^<\/span/i', $piece)) {
            $insidecheckbox = false;
            continue;
        }
        if ($insidecheckbox || str_starts_with($piece, '<')) {
            continue;
        }
        $piece = preg_replace_callback('/[☐□]/u', static function() use (&$next): string {
            $code = 'B' . $next++;
            return '[[' . $code . ']]';
        }, $piece);
    }
    unset($piece);
    return implode('', $pieces);
}

/** Prefill logistics fields from Moodle context without pretending users typed them. */
function worksheetgrader_prefill_student_info(string $template, array $answers, stdClass $course,
        stdClass $team, array $users): array {
    if (!preg_match_all('/\[\[(S\d+)\]\]/', $template, $matches, PREG_OFFSET_CAPTURE)) {
        return $answers;
    }
    $membernames = implode(', ', array_map('fullname', $users));
    foreach ($matches[1] as $index => $match) {
        [$code] = $match;
        if (isset($answers[$code]) && trim((string)$answers[$code]) !== '') {
            continue;
        }
        $offset = $matches[0][$index][1];
        $before = core_text::strtolower(strip_tags(substr($template, max(0, $offset - 90), 90)));
        if (str_contains($before, 'nhóm')) {
            $answers[$code] = format_string($team->name);
        } else if (str_contains($before, 'lớp')) {
            $answers[$code] = format_string($course->shortname ?: $course->fullname);
        } else if (str_contains($before, 'họ tên') || str_contains($before, 'họ và tên') ||
                str_contains($before, 'tên học sinh') || str_contains($before, 'thành viên')) {
            $answers[$code] = $membernames;
        } else if (str_contains($before, 'ngày')) {
            $answers[$code] = userdate(time(), get_string('strftimedatefullshort'));
        }
    }
    return $answers;
}

/** Get the latest image uploaded for an inline U-code. */
function worksheetgrader_get_attempt_image(int $contextid, int $attemptid, string $code): ?stored_file {
    if (!preg_match('/^U\d+$/', $code)) {
        return null;
    }
    $files = get_file_storage()->get_area_files(
        $contextid,
        'mod_worksheetgrader',
        'attemptimage',
        $attemptid,
        'timemodified DESC, id DESC',
        false
    );
    foreach ($files as $file) {
        if ($file->get_filepath() === '/' . $code . '/') {
            return $file;
        }
    }
    return null;
}

/** Save one image per U-code from a normal multipart attempt submission. */
function worksheetgrader_save_attempt_images(context_module $context, int $attemptid, array $uploads): array {
    global $USER;
    $saved = [];
    if (empty($uploads['name']) || !is_array($uploads['name'])) {
        return $saved;
    }
    $sitemaxbytes = (int)get_max_upload_file_size();
    $maxbytes = $sitemaxbytes > 0 ? min(10 * 1024 * 1024, $sitemaxbytes) : 10 * 1024 * 1024;
    $allowedextensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $allowedmimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $fs = get_file_storage();

    foreach ($uploads['name'] as $code => $originalname) {
        if (!preg_match('/^U\d+$/', (string)$code)) {
            continue;
        }
        $error = (int)($uploads['error'][$code] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new moodle_exception('imageuploadfailed', 'mod_worksheetgrader', '', $code);
        }
        $tmpname = (string)($uploads['tmp_name'][$code] ?? '');
        $size = (int)($uploads['size'][$code] ?? 0);
        if ($tmpname === '' || !is_uploaded_file($tmpname) || $size <= 0 || $size > $maxbytes) {
            throw new moodle_exception('imageuploadinvalid', 'mod_worksheetgrader', '', $code);
        }
        $filename = clean_filename((string)$originalname);
        $extension = core_text::strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimetype = '';
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimetype = (string)$finfo->file($tmpname);
        }
        if (!in_array($extension, $allowedextensions, true) ||
                ($mimetype !== '' && !in_array($mimetype, $allowedmimes, true))) {
            throw new moodle_exception('imageuploadtype', 'mod_worksheetgrader', '', $filename);
        }

        foreach ($fs->get_area_files(
            $context->id, 'mod_worksheetgrader', 'attemptimage', $attemptid, 'id', false
        ) as $oldfile) {
            if ($oldfile->get_filepath() === '/' . $code . '/') {
                $oldfile->delete();
            }
        }
        $fs->create_file_from_pathname([
            'contextid' => $context->id,
            'component' => 'mod_worksheetgrader',
            'filearea' => 'attemptimage',
            'itemid' => $attemptid,
            'filepath' => '/' . $code . '/',
            'filename' => $filename,
            'userid' => $USER->id,
            'author' => fullname($USER),
            'license' => 'allrightsreserved',
        ], $tmpname);
        $saved[$code] = $filename;
    }
    return $saved;
}

/** Render all worksheet markers as student controls or read-only submitted answers. */
function worksheetgrader_render_answer_fields(string $html, array $answers = [], bool $readonly = false,
        array $options = []): string {
    $html = worksheetgrader_normalize_interactive_template($html);
    $escape = static fn($value) => s((string)$value);
    $ischecked = static fn($value): bool => in_array(core_text::strtolower(trim((string)$value)),
        ['1', 'true', 'yes', 'on', 'checked'], true);
    $contextid = (int)($options['contextid'] ?? 0);
    $attemptid = (int)($options['attemptid'] ?? 0);

    $html = preg_replace_callback('/<span[^>]*class="[^"]*essay-answer-slot[^"]*"[^>]*data-code="(C\d+)"[^>]*>.*?<\/span>/is',
        static function($matches) use ($answers, $readonly, $escape) {
            $code = $matches[1];
            $value = $escape($answers[$code] ?? '');
            return $readonly
                ? '<div class="wsg-answer-readonly wsg-answer-long-readonly">' .
                    ($value !== '' ? nl2br($value) : '—') . '</div>'
                : '<textarea class="form-control wsg-answer-long" name="answers[' . $code . ']" data-wsg-code="' .
                    $code . '">' . $value . '</textarea>';
        }, $html);
    $html = preg_replace_callback('/<span[^>]*class="[^"]*matching-answer-slot[^"]*"[^>]*data-code="(C\d+)"[^>]*>.*?<\/span>/is',
        static function($matches) use ($answers, $readonly, $escape) {
            $code = $matches[1];
            $value = $escape($answers[$code] ?? '');
            return $readonly
                ? '<span class="wsg-answer-readonly wsg-answer-short">' . ($value !== '' ? $value : '—') . '</span>'
                : '<input class="form-control wsg-answer-short" name="answers[' . $code . ']" value="' . $value .
                    '" data-wsg-code="' . $code . '">';
        }, $html);
    $html = preg_replace_callback('/<span[^>]*class="[^"]*worksheet-checkbox-slot[^"]*"[^>]*data-code="(B\d+)"[^>]*>.*?<\/span>/is',
        static function($matches) use ($answers, $readonly, $ischecked) {
            $code = $matches[1];
            $checked = $ischecked($answers[$code] ?? '0');
            if ($readonly) {
                return '<span class="wsg-checkbox-readonly" aria-label="' .
                    ($checked ? 'Đã chọn' : 'Chưa chọn') . '">' . ($checked ? '☑' : '☐') . '</span>';
            }
            return '<span class="wsg-checkbox-control">' .
                '<input type="hidden" name="answers[' . $code . ']" value="0">' .
                '<input type="checkbox" class="form-check-input" name="answers[' . $code . ']" value="1" ' .
                'data-wsg-code="' . $code . '"' . ($checked ? ' checked' : '') .
                ' aria-label="Lựa chọn ' . $code . '"></span>';
        }, $html);

    $html = preg_replace_callback('/\[\[([CSBLU]\d+)\]\]/',
        static function($matches) use ($answers, $readonly, $escape, $ischecked, $contextid, $attemptid) {
            $code = $matches[1];
            if (str_starts_with($code, 'B')) {
                $checked = $ischecked($answers[$code] ?? '0');
                if ($readonly) {
                    return '<span class="wsg-checkbox-readonly">' . ($checked ? '☑' : '☐') . '</span>';
                }
                return '<span class="wsg-checkbox-control">' .
                    '<input type="hidden" name="answers[' . $code . ']" value="0">' .
                    '<input type="checkbox" class="form-check-input" name="answers[' . $code . ']" value="1" ' .
                    'data-wsg-code="' . $code . '"' . ($checked ? ' checked' : '') . '></span>';
            }
            if (str_starts_with($code, 'U')) {
                $file = ($contextid && $attemptid)
                    ? worksheetgrader_get_attempt_image($contextid, $attemptid, $code)
                    : null;
                $imagehtml = '';
                if ($file) {
                    $url = moodle_url::make_pluginfile_url(
                        $contextid,
                        'mod_worksheetgrader',
                        'attemptimage',
                        $attemptid,
                        $file->get_filepath(),
                        $file->get_filename(),
                        false
                    );
                    $imagehtml = '<a href="' . $url->out(false) . '" target="_blank" rel="noopener">' .
                        '<img class="wsg-uploaded-image" src="' . $url->out(false) . '" alt="Ảnh nộp ' . $code . '"></a>';
                }
                if ($readonly) {
                    return '<div class="wsg-image-answer">' .
                        ($imagehtml !== '' ? $imagehtml : '<span class="text-muted">Chưa nộp ảnh</span>') . '</div>';
                }
                return '<div class="wsg-image-upload" data-upload-code="' . $code . '">' .
                    '<div class="wsg-image-preview">' .
                    ($imagehtml !== '' ? $imagehtml : '<span class="text-muted">Chưa chọn ảnh</span>') . '</div>' .
                    '<label class="btn btn-outline-primary btn-sm mb-0">Chọn ảnh' .
                    '<input type="file" class="visually-hidden" name="answerfiles[' . $code . ']" ' .
                    'accept="image/jpeg,image/png,image/webp,image/gif" data-wsg-image-input="' . $code . '"></label>' .
                    '<span class="small text-muted"> JPG, PNG, WEBP hoặc GIF; tối đa 10 MB.</span></div>';
            }
            $value = $escape($answers[$code] ?? '');
            if ($readonly) {
                if (str_starts_with($code, 'L')) {
                    return '<div class="wsg-answer-readonly wsg-answer-long-readonly">' .
                        ($value !== '' ? nl2br($value) : '—') . '</div>';
                }
                return '<span class="wsg-answer-readonly">' . ($value !== '' ? $value : '—') . '</span>';
            }
            if (str_starts_with($code, 'L')) {
                return '<textarea class="form-control wsg-answer-long" rows="5" name="answers[' . $code . ']" ' .
                    'data-wsg-code="' . $code . '">' . $value . '</textarea>';
            }
            $class = str_starts_with($code, 'S') ? 'wsg-info-field' : 'wsg-answer-field';
            return '<input class="form-control ' . $class . '" name="answers[' . $code . ']" value="' . $value .
                '" data-wsg-code="' . $code . '">';
        }, $html);
    return $html;
}

function worksheetgrader_build_submission_html(string $template, array $answers, array $options = []): string {
    return worksheetgrader_render_answer_fields($template, $answers, true, $options);
}

function worksheetgrader_update_completion($cm, array $userids): void {
    global $DB;
    if (!$cm->completion) {
        return;
    }
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $completion = new completion_info($course);
    foreach (array_unique(array_map('intval', $userids)) as $userid) {
        if ($userid > 0) {
            $completion->update_state($cm, COMPLETION_COMPLETE, $userid);
        }
    }
}

/** Whether optional instructional/help notifications should be shown. */
function worksheetgrader_show_guidance_notices(): bool {
    return (bool)get_config('mod_worksheetgrader', 'showguidancenotices');
}

/** Whether the DocSpace SDK runtime status strip should be shown. */
function worksheetgrader_show_docspace_status(): bool {
    return (bool)get_config('mod_worksheetgrader', 'showdocspacestatus');
}

/** Whether supplemental runtime error notifications should be shown. */
function worksheetgrader_show_error_notices(): bool {
    $value = get_config('mod_worksheetgrader', 'showerrornotices');
    // Preserve safe behaviour for upgraded installs where the setting does not yet exist.
    return $value === false ? true : (bool)$value;
}

/** Return whether a session has usable HTML or DocSpace content. */
function worksheetgrader_session_has_content(stdClass $session): bool {
    return \mod_worksheetgrader\service\session_manager::has_content($session);
}

/** Render an anonymous, room-token DocSpace editor inside Moodle. */
function worksheetgrader_render_docspace_frame(stdClass $mapping, string $key, bool $readonly = false): string {
    global $PAGE;
    $mapping = \mod_worksheetgrader\service\docspace_manager::ensure_link($mapping);
    $frameid = 'wsg-docspace-' . preg_replace('/[^a-z0-9_-]/i', '-', $key) . '-' . (int)$mapping->id;
    $config = \mod_worksheetgrader\service\docspace_manager::frame_config($mapping, $frameid, $readonly);
    $config['errorMessage'] = get_string('docspaceembedfailed', 'mod_worksheetgrader');
    $config['showRuntimeStatus'] = worksheetgrader_show_docspace_status();
    $config['showErrorNotices'] = worksheetgrader_show_error_notices();
    $PAGE->requires->js_call_amd('mod_worksheetgrader/docspace_embed', 'init', [[$config]]);
    $toolbar = html_writer::start_div('wsg-docspace-local-toolbar');
    $isshared = (($config['authMode'] ?? '') === 'shared-editor');
    $toolbar .= html_writer::span($readonly ? 'ONLYOFFICE Viewer' :
        ($isshared ? 'ONLYOFFICE Editor · Shared Public Room token' : 'ONLYOFFICE Public Room · chỉnh sửa không cần tài khoản DocSpace'), 'fw-bold');
    $toolbar .= html_writer::start_div('wsg-docspace-local-actions');
    if (!$readonly) {
        $toolbar .= html_writer::tag('button', $isshared ? 'Mở lại Editor' : 'Tải lại phòng chỉnh sửa', [
            'type' => 'button',
            'class' => 'btn btn-sm btn-outline-primary',
            'data-docspace-retry-editor' => '1',
        ]);
        if (!$isshared) {
            $toolbar .= html_writer::tag('button', 'Không gian bài làm (Public Room)', [
                'type' => 'button',
                'class' => 'btn btn-sm btn-outline-secondary',
                'data-docspace-workspace' => '1',
                'title' => 'Mở Public Room riêng có quyền Editing. Trong phòng chỉ có một tài liệu của phiếu/bài làm.',
            ]);
        }
    }
    $toolbar .= html_writer::tag('button', 'Toàn màn hình', [
        'type' => 'button',
        'class' => 'btn btn-sm btn-outline-secondary',
        'data-docspace-fullscreen' => '1',
    ]);
    $toolbar .= html_writer::end_div();
    $toolbar .= html_writer::end_div();
    $status = '';
    if (worksheetgrader_show_docspace_status()) {
        $status = html_writer::div('Đang chuẩn bị ONLYOFFICE…',
            'wsg-docspace-runtime-status alert alert-info py-2 mb-2', [
                'data-docspace-status' => '1',
            ]);
    }
    $html = html_writer::div('', 'wsg-docspace-frame', ['id' => $frameid]);
    return html_writer::div($toolbar . $status . $html,
        'wsg-docspace-shell' . ($readonly ? ' is-readonly' : ''));
}
