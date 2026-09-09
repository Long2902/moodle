<?php
require('../../config.php');
require_login();
\local_worksheetlibrary\service\access_service::require_view();

$itemid = required_param('itemid', PARAM_INT);
$item = $DB->get_record('wslib_item', ['id' => $itemid], '*', MUST_EXIST);
$versions = $DB->get_records('wslib_version', ['itemid' => $itemid], 'versionno DESC');
$bindings = $DB->get_records('wslib_binding', ['itemid' => $itemid]);
$can = \local_worksheetlibrary\service\access_service::can_author();
$folders = \local_worksheetlibrary\service\folder_service::tree();

$folderopts = [0 => 'Kho phiếu (gốc)'];
foreach ($folders as $folder) {
    $folderopts[$folder->id] = str_repeat('— ', min(5, $folder->depth)) . $folder->name;
}

$selectedcourse = optional_param('courseid', 0, PARAM_INT);
$courses = [];
foreach (enrol_get_users_courses((int)$USER->id, true, 'id,fullname') as $course) {
    if (has_capability('moodle/course:update', context_course::instance($course->id))) {
        $courses[$course->id] = $course;
    }
}
if (!$selectedcourse && $courses) {
    $selectedcourse = (int)array_key_first($courses);
}
$sections = $selectedcourse
    ? $DB->get_records('course_sections', ['course' => $selectedcourse], 'section', 'id,section,name')
    : [];

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/worksheetlibrary/detail.php', ['itemid' => $itemid]);
$PAGE->set_title($item->name);
$PAGE->set_heading($item->name);
$PAGE->requires->css('/local/worksheetlibrary/styles.css');
$PAGE->requires->css('/local/digieranative/styles.css');
$PAGE->requires->js_call_amd('local_worksheetlibrary/browser', 'init');

$nativedraft = null;
$publishedids = [];
foreach ($versions as $candidate) {
    if ($candidate->state === 'published') {
        $publishedids[] = (int)$candidate->id;
    }
    if ($can && $item->kind === 'native' && $nativedraft === null && $candidate->state === 'draft') {
        $nativedraft = $candidate;
    }
}

if ($nativedraft) {
    $nativejson = (string)($nativedraft->nativejson ?: '{"type":"worksheet","version":1,"content":[]}');
    $PAGE->requires->js_call_amd('local_worksheetlibrary/native_editor', 'init', [[
        'elementid' => 'wslib-native-editor-' . (int)$nativedraft->id,
        'statusid' => 'wslib-native-status-' . (int)$nativedraft->id,
        'versionid' => (int)$nativedraft->id,
        'revision' => (int)($nativedraft->revision ?? 0),
        'nativejson' => $nativejson,
    ]]);
}

$backurl = new moodle_url('/local/worksheetlibrary/index.php', ['folderid' => $item->folderid]);
$detailurl = new moodle_url('/local/worksheetlibrary/detail.php', ['itemid' => $itemid]);
$kindlabel = strtoupper($item->kind);
$latest = $versions ? reset($versions) : null;
$currentstate = $latest ? ($latest->state === 'published' ? 'Đã xuất bản' : 'Bản nháp') : 'Chưa có phiên bản';

echo $OUTPUT->header();
?>
<div class="wslib-detail-page" data-wslib-itemid="<?= (int)$itemid ?>" data-published-versions="<?= s(implode(',', $publishedids)) ?>">
    <div class="wslib-editor-topbar">
        <div class="wslib-editor-breadcrumb">
            <a href="<?= $backurl->out(false) ?>">Kho phiếu học tập</a>
            <span aria-hidden="true">›</span>
            <span><?= s($item->name) ?></span>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="<?= $backurl->out(false) ?>">← Trở về kho phiếu</a>
    </div>

    <div class="wslib-editor-hero">
        <div class="wslib-editor-titlegroup">
            <span class="wslib-editor-titleicon"><?= s(substr($kindlabel, 0, 1)) ?></span>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h2><?= s($item->name) ?></h2>
                    <span class="wslib-v1-kind wslib-kind-<?= s($item->kind) ?>"><?= s($kindlabel) ?></span>
                </div>
                <div class="wslib-editor-subtitle">Một phiếu dùng chung · phiên bản bất biến · có thể gắn vào nhiều khóa học</div>
            </div>
        </div>
        <div class="wslib-editor-toolbar">
            <?php if ($nativedraft): ?>
                <span id="wslib-native-status-<?= (int)$nativedraft->id ?>" data-region="native-save-status" data-state="saved" class="wslib-save-status">Đã lưu</span>
                <button type="button" class="btn btn-primary" data-action="open-publish">Xem trước &amp; Xuất bản</button>
            <?php else: ?>
                <span class="wslib-v1-state <?= $latest && $latest->state === 'published' ? 'is-published' : '' ?>"><?= s($currentstate) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="wslib-editor-workspace">
        <main class="wslib-editor-main">
            <?php if ($item->kind === 'native' && $nativedraft): ?>
                <div class="wslib-paper-stage">
                    <div class="wslib-native-editor-shell">
                        <div class="wslib-native-editor-head">
                            <strong>Native Editor</strong>
                        </div>
                        <div id="wslib-native-editor-<?= (int)$nativedraft->id ?>" data-region="native-editor" class="wslib-native-editor"></div>
                    </div>
                </div>
            <?php elseif ($item->kind === 'native'): ?>
                <div class="p-5 text-center bg-white">
                    <h3>Phiếu hiện không có bản nháp</h3>
                    <p class="text-muted">Tạo một draft mới từ phiên bản đã xuất bản để tiếp tục chỉnh sửa.</p>
                    <?php if ($can && $item->currentversionid): ?>
                        <form method="post" action="manage.php">
                            <input type="hidden" name="sesskey" value="<?= sesskey() ?>">
                            <input type="hidden" name="action" value="newdraft">
                            <input type="hidden" name="return" value="detail">
                            <input type="hidden" name="itemid" value="<?= (int)$itemid ?>">
                            <button class="btn btn-primary">Tạo draft phiên bản mới</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="p-4 bg-white">
                    <h3 class="h5 mb-3">Nội dung &amp; phiên bản</h3>
                    <?php foreach ($versions as $version): ?>
                        <section class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                <strong>v<?= (int)$version->versionno ?></strong>
                                <span class="badge <?= $version->state === 'published' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= s($version->state) ?></span>
                            </div>
                            <?php if ($can && $version->state === 'draft' && $item->kind === 'html'): ?>
                                <form method="post" action="manage.php">
                                    <input type="hidden" name="sesskey" value="<?= sesskey() ?>"><input type="hidden" name="action" value="savehtml"><input type="hidden" name="return" value="detail"><input type="hidden" name="itemid" value="<?= (int)$itemid ?>"><input type="hidden" name="versionid" value="<?= (int)$version->id ?>">
                                    <textarea name="contenthtml" class="form-control font-monospace" rows="12"><?= s((string)$version->contenthtml) ?></textarea>
                                    <button class="btn btn-outline-primary mt-2">Lưu draft HTML</button>
                                </form>
                            <?php elseif ($can && $version->state === 'draft' && $item->kind === 'office'): ?>
                                <a class="btn btn-primary" href="office.php?versionid=<?= (int)$version->id ?>">Mở ONLYOFFICE</a>
                            <?php elseif ($can && $version->state === 'draft' && in_array($item->kind, ['office', 'pdf'], true)): ?>
                                <form method="post" action="manage.php" enctype="multipart/form-data">
                                    <input type="hidden" name="sesskey" value="<?= sesskey() ?>"><input type="hidden" name="action" value="upload"><input type="hidden" name="return" value="detail"><input type="hidden" name="itemid" value="<?= (int)$itemid ?>"><input type="hidden" name="versionid" value="<?= (int)$version->id ?>">
                                    <div class="input-group"><input type="file" name="contentfile" class="form-control" accept="<?= $item->kind === 'pdf' ? '.pdf' : '.docx,.xlsx,.pptx' ?>"><button class="btn btn-outline-primary">Thay file draft</button></div>
                                </form>
                            <?php else: ?>
                                <p class="text-muted mb-0"><?= s($version->filename ?: 'Phiên bản nội dung') ?></p>
                            <?php endif; ?>
                            <?php if ($can && $version->state === 'draft'): ?>
                                <form method="post" action="manage.php" class="mt-3">
                                    <input type="hidden" name="sesskey" value="<?= sesskey() ?>"><input type="hidden" name="action" value="publish"><input type="hidden" name="return" value="detail"><input type="hidden" name="itemid" value="<?= (int)$itemid ?>"><input type="hidden" name="versionid" value="<?= (int)$version->id ?>">
                                    <button class="btn btn-success btn-sm">Xuất bản v<?= (int)$version->versionno ?></button>
                                </form>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>

        <aside class="wslib-editor-inspector">
            <section class="wslib-editor-section">
                <div class="wslib-editor-section-title">Phiên bản &amp; trạng thái</div>
                <?php foreach ($versions as $version): ?>
                    <div class="wslib-editor-version">
                        <span><strong>v<?= (int)$version->versionno ?></strong></span>
                        <span class="<?= $version->state === 'published' ? 'text-success' : 'text-warning' ?>"><?= $version->state === 'published' ? 'Đã xuất bản' : 'Bản nháp' ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (!$versions): ?><p class="text-muted small mb-0">Chưa có phiên bản.</p><?php endif; ?>
            </section>

            <section class="wslib-editor-section">
                <div class="wslib-editor-section-title">Nơi sử dụng</div>
                <?php foreach ($bindings as $binding): $course = get_course($binding->courseid); ?>
                    <div class="wslib-binding"><strong><?= s($course->fullname) ?></strong><small><?= $binding->sectionid ? 'Section #' . (int)$binding->sectionid : 'Toàn khóa học' ?></small></div>
                <?php endforeach; ?>
                <?php if (!$bindings): ?><p class="text-muted small">Chưa gắn vào khóa học.</p><?php endif; ?>

                <?php if ($can && $courses): ?>
                    <form method="get" class="mt-3 mb-2">
                        <input type="hidden" name="itemid" value="<?= (int)$itemid ?>">
                        <label class="form-label small" for="wslib-course">Khóa học</label>
                        <select id="wslib-course" class="form-select form-select-sm" name="courseid" onchange="this.form.submit()">
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= (int)$course->id ?>" <?= (int)$course->id === $selectedcourse ? 'selected' : '' ?>><?= s($course->fullname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <form method="post" action="manage.php">
                        <input type="hidden" name="sesskey" value="<?= sesskey() ?>"><input type="hidden" name="action" value="bind"><input type="hidden" name="return" value="detail"><input type="hidden" name="itemid" value="<?= (int)$itemid ?>"><input type="hidden" name="courseid" value="<?= (int)$selectedcourse ?>">
                        <label class="form-label small" for="wslib-section">Bài học / Section</label>
                        <select id="wslib-section" class="form-select form-select-sm mb-2" name="sectionid"><option value="0">Toàn khóa học</option><?php foreach ($sections as $section): ?><option value="<?= (int)$section->id ?>"><?= $section->section == 0 ? 'Chung' : 'Bài ' . (int)$section->section ?><?= $section->name ? ' — ' . s($section->name) : '' ?></option><?php endforeach; ?></select>
                        <button class="btn btn-primary btn-sm w-100">Gắn phiếu</button>
                    </form>
                <?php endif; ?>
            </section>

            <?php if ($can): ?>
                <section class="wslib-editor-section">
                    <div class="wslib-editor-section-title">Quản lý phiếu</div>
                    <form method="post" action="manage.php" class="mb-2">
                        <input type="hidden" name="sesskey" value="<?= sesskey() ?>"><input type="hidden" name="action" value="copyitem"><input type="hidden" name="return" value="detail"><input type="hidden" name="itemid" value="<?= (int)$itemid ?>">
                        <?= html_writer::select($folderopts, 'targetfolderid', $item->folderid, false, ['class' => 'form-select form-select-sm mb-2']) ?>
                        <button class="btn btn-outline-primary btn-sm w-100">Sao chép phiếu</button>
                    </form>
                    <form method="post" action="manage.php">
                        <input type="hidden" name="sesskey" value="<?= sesskey() ?>"><input type="hidden" name="action" value="moveitem"><input type="hidden" name="return" value="detail"><input type="hidden" name="itemid" value="<?= (int)$itemid ?>">
                        <?= html_writer::select($folderopts, 'targetfolderid', $item->folderid, false, ['class' => 'form-select form-select-sm mb-2']) ?>
                        <button class="btn btn-outline-secondary btn-sm w-100">Di chuyển phiếu</button>
                    </form>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</div>

<?php if ($can && $nativedraft): ?>
<div class="wslib-modal" data-modal="publish" aria-hidden="true">
    <div class="wslib-modal-dialog wslib-modal-lg" role="dialog" aria-modal="true" aria-labelledby="wslib-publish-title">
        <div class="wslib-modal-header">
            <div><h3 id="wslib-publish-title">Xem trước &amp; Xuất bản</h3><p>Kiểm tra trải nghiệm học sinh trước khi đóng băng phiên bản.</p></div>
            <button type="button" class="wslib-modal-close" data-close-modal aria-label="Đóng">×</button>
        </div>
        <div class="wslib-publish-layout">
            <div class="wslib-publish-preview"><div class="wslib-publish-preview-sheet" data-region="publish-preview"><p class="text-muted">Bản xem trước sẽ xuất hiện tại đây.</p></div></div>
            <div class="wslib-publish-settings">
                <span class="wslib-v1-kind wslib-kind-native">NATIVE</span>
                <h4 class="mt-3"><?= s($item->name) ?></h4>
                <p>Phiên bản <strong>v<?= (int)$nativedraft->versionno ?></strong> sẽ trở thành bản bất biến dùng cho các phiên học sau khi xuất bản.</p>
                <div class="alert alert-light border small">Autosave chỉ lưu bản nháp. Xuất bản là thao tác riêng và không thể thay đổi nội dung của phiên bản đã đóng băng.</div>
                <form method="post" action="manage.php" data-publish-form data-itemid="<?= (int)$itemid ?>" data-versionid="<?= (int)$nativedraft->id ?>">
                    <input type="hidden" name="sesskey" value="<?= sesskey() ?>"><input type="hidden" name="action" value="publish"><input type="hidden" name="return" value="detail"><input type="hidden" name="itemid" value="<?= (int)$itemid ?>"><input type="hidden" name="versionid" value="<?= (int)$nativedraft->id ?>">
                    <button class="btn btn-primary w-100">Xuất bản v<?= (int)$nativedraft->versionno ?></button>
                </form>
            </div>
        </div>
        <div class="wslib-modal-footer"><button type="button" class="btn btn-outline-secondary" data-close-modal>Quay lại chỉnh sửa</button></div>
    </div>
</div>
<?php endif; ?>

<div class="wslib-modal wslib-success-modal" data-modal="publish-success" aria-hidden="true">
    <div class="wslib-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="wslib-success-title">
        <div class="wslib-modal-body">
            <div class="wslib-success-card">
                <span class="wslib-success-icon" aria-hidden="true">✓</span>
                <div class="wslib-success-copy"><h3 id="wslib-success-title">Xuất bản thành công!</h3><p>Phiếu học tập đã được đóng băng thành phiên bản mới và sẵn sàng sử dụng.</p></div>
            </div>
        </div>
        <div class="wslib-modal-footer">
            <button type="button" class="btn btn-outline-primary" data-action="copy-link">Sao chép liên kết</button>
            <button type="button" class="btn btn-primary" data-close-modal>Đi tới phiếu</button>
        </div>
    </div>
</div>
<?php
echo $OUTPUT->footer();
