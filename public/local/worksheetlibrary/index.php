<?php
require('../../config.php');
require_login();
\local_worksheetlibrary\service\access_service::require_view();

$sys = context_system::instance();
$PAGE->set_context($sys);
$PAGE->set_url('/local/worksheetlibrary/index.php');
$PAGE->set_title(get_string('library', 'local_worksheetlibrary'));
$PAGE->set_heading(get_string('library', 'local_worksheetlibrary'));
$PAGE->requires->css('/local/worksheetlibrary/styles.css');
$PAGE->requires->js_call_amd('local_worksheetlibrary/browser', 'init');

$folderid = optional_param('folderid', 0, PARAM_INT);
$q = optional_param('q', '', PARAM_TEXT);
$can = \local_worksheetlibrary\service\access_service::can_author();

if (optional_param('action', '', PARAM_ALPHA) !== '' && confirm_sesskey()) {
    \local_worksheetlibrary\service\access_service::require_author();
    $action = required_param('action', PARAM_ALPHA);
    if ($action === 'folder') {
        \local_worksheetlibrary\service\folder_service::create(
            $folderid,
            required_param('name', PARAM_TEXT),
            $USER->id
        );
    }
    if ($action === 'item') {
        \local_worksheetlibrary\service\version_service::create_item(
            $folderid,
            required_param('name', PARAM_TEXT),
            required_param('kind', PARAM_ALPHA),
            $USER->id
        );
    }
    if ($action === 'publish') {
        \local_worksheetlibrary\service\version_service::publish(
            required_param('versionid', PARAM_INT),
            $USER->id
        );
    }
    redirect(new moodle_url('/local/worksheetlibrary/index.php', ['folderid' => $folderid]));
}

$folders = \local_worksheetlibrary\service\folder_service::tree();
$params = ['folderid' => $folderid];
$where = 'i.folderid=:folderid AND i.archived=0';
if ($q !== '') {
    $where .= ' AND ' . $DB->sql_like('i.name', ':q', false, false);
    $params['q'] = '%' . $DB->sql_like_escape($q) . '%';
}
$items = $DB->get_records_sql(
    "SELECT i.*, v.versionno, v.state, v.filename
       FROM {wslib_item} i
  LEFT JOIN {wslib_version} v ON v.id=i.currentversionid
      WHERE $where
   ORDER BY i.name",
    $params
);

$kindlabels = [
    'native' => 'NATIVE',
    'html' => 'HTML',
    'office' => 'OFFICE',
    'pdf' => 'PDF',
];

$foldername = 'Kho phiếu';
foreach ($folders as $folder) {
    if ((int)$folder->id === $folderid) {
        $foldername = $folder->name;
        break;
    }
}

echo $OUTPUT->header();
?>
<div class="wslib-v1-shell">
    <header class="wslib-v1-header">
        <div class="wslib-v1-title">
            <span class="wslib-v1-titleicon" aria-hidden="true">▣</span>
            <div>
                <h2>Kho phiếu học tập</h2>
                <p>Lưu trữ, tổ chức và chia sẻ các phiếu học tập để sử dụng trong các khóa học.</p>
            </div>
        </div>
        <?php if ($can): ?>
            <div class="wslib-v1-actions">
                <button type="button" class="btn btn-primary" data-action="new-item">＋ Tạo phiếu mới</button>
                <button type="button" class="btn btn-outline-secondary" data-action="new-folder">▢ Tạo thư mục</button>
                <button type="button" class="btn btn-outline-secondary" disabled title="Chọn một phiếu PDF/Office để tải tệp lên">⇧ Nhập tệp</button>
            </div>
        <?php endif; ?>
    </header>

    <div class="wslib-v1-grid">
        <aside class="wslib-v1-folders" aria-label="Thư mục phiếu học tập">
            <div class="wslib-v1-panel-title">Thư mục</div>
            <a class="wslib-v1-folder <?= $folderid === 0 ? 'is-active' : '' ?>" href="<?= (new moodle_url('/local/worksheetlibrary/index.php'))->out(false) ?>">
                <span aria-hidden="true">▤</span><span>Kho phiếu</span>
            </a>
            <?php foreach ($folders as $folder): ?>
                <a class="wslib-v1-folder <?= (int)$folder->id === $folderid ? 'is-active' : '' ?>"
                   style="--wslib-depth: <?= (int)min(6, $folder->depth) ?>"
                   href="<?= (new moodle_url('/local/worksheetlibrary/index.php', ['folderid' => $folder->id]))->out(false) ?>">
                    <span aria-hidden="true">▱</span><span><?= s($folder->name) ?></span>
                </a>
            <?php endforeach; ?>
        </aside>

        <main class="wslib-v1-results">
            <div class="wslib-v1-resultbar">
                <div>
                    <strong><?= s($foldername) ?></strong>
                    <span><?= count($items) ?> phiếu</span>
                </div>
                <form class="wslib-v1-search" method="get">
                    <input type="hidden" name="folderid" value="<?= $folderid ?>">
                    <label class="sr-only" for="wslib-search">Tìm kiếm</label>
                    <input id="wslib-search" class="form-control" type="search" name="q" value="<?= s($q) ?>" placeholder="Tìm kiếm phiếu học tập…">
                </form>
            </div>

            <div class="wslib-v1-tablewrap">
                <table class="wslib-v1-table">
                    <thead>
                    <tr>
                        <th>Phiếu học tập</th>
                        <th>Loại</th>
                        <th>Phiên bản</th>
                        <th>Trạng thái</th>
                        <th><span class="sr-only">Thao tác</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item):
                        $kind = $kindlabels[$item->kind] ?? strtoupper($item->kind);
                        $version = $item->currentversionid ? 'v' . (int)$item->versionno : '—';
                        $state = $item->currentversionid ? ($item->state === 'published' ? 'Đã xuất bản' : 'Bản nháp') : 'Chưa xuất bản';
                        ?>
                        <tr class="wslib-v1-tablerow">
                            <td>
                                <button class="wslib-row" type="button"
                                        data-item="<?= (int)$item->id ?>"
                                        data-name="<?= s($item->name) ?>"
                                        data-kind="<?= s($item->kind) ?>"
                                        data-version="<?= s($version) ?>"
                                        data-state="<?= s($state) ?>">
                                    <span class="wslib-v1-fileicon wslib-kind-<?= s($item->kind) ?>"><?= s(substr($kind, 0, 1)) ?></span>
                                    <span class="wslib-v1-filename"><?= s($item->name) ?></span>
                                </button>
                            </td>
                            <td><span class="wslib-v1-kind wslib-kind-<?= s($item->kind) ?>"><?= s($kind) ?></span></td>
                            <td><?= s($version) ?></td>
                            <td><span class="wslib-v1-state <?= $item->state === 'published' ? 'is-published' : '' ?>"><?= s($state) ?></span></td>
                            <td><a class="wslib-v1-open" href="<?= (new moodle_url('/local/worksheetlibrary/detail.php', ['itemid' => $item->id]))->out(false) ?>" aria-label="Mở <?= s($item->name) ?>">›</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$items): ?>
                        <tr><td colspan="5"><div class="wslib-v1-empty">Chưa có phiếu trong thư mục này.</div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>

        <aside class="wslib-v1-inspector" data-region="detail">
            <div class="wslib-v1-panel-title">Chi tiết</div>
            <div class="wslib-v1-inspector-empty" data-region="detail-empty">
                <span class="wslib-v1-inspector-icon" aria-hidden="true">▧</span>
                <strong>Chọn một phiếu</strong>
                <p>Xem phiên bản, trạng thái và thao tác nhanh tại đây.</p>
            </div>
            <div data-region="detail-body"></div>
        </aside>
    </div>
</div>

<?php if ($can): ?>
<div class="wslib-modal" data-modal="create-item" aria-hidden="true">
    <div class="wslib-modal-dialog wslib-modal-lg" role="dialog" aria-modal="true" aria-labelledby="wslib-create-title">
        <div class="wslib-modal-header">
            <div>
                <h3 id="wslib-create-title">Tạo phiếu học tập mới</h3>
                <p>Chọn loại nội dung phù hợp để bắt đầu.</p>
            </div>
            <button type="button" class="wslib-modal-close" data-close-modal aria-label="Đóng">×</button>
        </div>
        <form method="post">
            <input type="hidden" name="sesskey" value="<?= sesskey() ?>">
            <input type="hidden" name="folderid" value="<?= $folderid ?>">
            <input type="hidden" name="action" value="item">
            <div class="wslib-modal-body">
                <div class="wslib-v1-stepper" aria-label="Tiến trình tạo phiếu">
                    <span class="is-current"><b>1</b>Chọn loại</span><span><b>2</b>Thiết lập</span><span><b>3</b>Hoàn tất</span>
                </div>
                <label class="form-label" for="wslib-new-name">Tên phiếu học tập</label>
                <input id="wslib-new-name" class="form-control mb-4" name="name" required maxlength="255" placeholder="Ví dụ: Phiếu luyện tập chương 1">
                <div class="wslib-v1-kindgrid">
                    <label class="wslib-v1-kindcard is-recommended"><input type="radio" name="kind" value="native" checked><span class="wslib-v1-kindicon">N</span><strong>Native DIGIERA</strong><small>Soạn trực tiếp với Ribbon và tự động lưu.</small><em>Khuyến nghị</em></label>
                    <label class="wslib-v1-kindcard"><input type="radio" name="kind" value="html"><span class="wslib-v1-kindicon">H</span><strong>HTML</strong><small>Nội dung HTML đơn giản và tương thích rộng.</small></label>
                    <label class="wslib-v1-kindcard"><input type="radio" name="kind" value="office"><span class="wslib-v1-kindicon">W</span><strong>ONLYOFFICE</strong><small>Dùng tài liệu DOCX/XLSX/PPTX.</small></label>
                    <label class="wslib-v1-kindcard"><input type="radio" name="kind" value="pdf"><span class="wslib-v1-kindicon">P</span><strong>PDF</strong><small>Tải lên tài liệu PDF cố định.</small></label>
                </div>
            </div>
            <div class="wslib-modal-footer"><button type="button" class="btn btn-outline-secondary" data-close-modal>Hủy</button><button class="btn btn-primary">Tiếp tục</button></div>
        </form>
    </div>
</div>

<div class="wslib-modal" data-modal="create-folder" aria-hidden="true">
    <div class="wslib-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="wslib-folder-title">
        <div class="wslib-modal-header"><div><h3 id="wslib-folder-title">Tạo thư mục</h3><p>Tổ chức phiếu theo môn học, khối hoặc mục đích sử dụng.</p></div><button type="button" class="wslib-modal-close" data-close-modal aria-label="Đóng">×</button></div>
        <form method="post">
            <input type="hidden" name="sesskey" value="<?= sesskey() ?>"><input type="hidden" name="folderid" value="<?= $folderid ?>"><input type="hidden" name="action" value="folder">
            <div class="wslib-modal-body"><label class="form-label" for="wslib-folder-name">Tên thư mục</label><input id="wslib-folder-name" class="form-control" name="name" required maxlength="255" placeholder="Tên thư mục"></div>
            <div class="wslib-modal-footer"><button type="button" class="btn btn-outline-secondary" data-close-modal>Hủy</button><button class="btn btn-primary">Tạo thư mục</button></div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php
echo $OUTPUT->footer();
