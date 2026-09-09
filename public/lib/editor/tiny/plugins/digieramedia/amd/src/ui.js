import Ajax from 'core/ajax';
import ModalEvents from 'core/modal_events';
import DigieraMediaModal from './modal';
import {getConfig} from './options';
import {uploadFile} from './upload_client';
import * as ReferenceComponent from './reference_component';

const search = async(config, tab, query = '') => Ajax.call([{
    methodname: 'local_digieramedia_search_media',
    args: {contextid: config.contextid, tab, query, page: 0, pagesize: 24},
}])[0];

const getMediaVersions = async(config, mediauuid) => Ajax.call([{
    methodname: 'local_digieramedia_get_media_versions',
    args: {contextid: config.contextid, mediauuid},
}])[0];

const getMediaUsage = async(config, mediauuid) => Ajax.call([{
    methodname: 'local_digieramedia_get_media_usage',
    args: {contextid: config.contextid, mediauuid},
}])[0];

const trashMedia = async(config, mediauuid, reason = '') => Ajax.call([{
    methodname: 'local_digieramedia_trash_media',
    args: {contextid: config.contextid, mediauuid, reason},
}])[0];

const restoreMedia = async(config, mediauuid) => Ajax.call([{
    methodname: 'local_digieramedia_restore_media',
    args: {contextid: config.contextid, mediauuid},
}])[0];

const purgeMedia = async(config, mediauuid, force = false) => Ajax.call([{
    methodname: 'local_digieramedia_purge_media',
    args: {contextid: config.contextid, mediauuid, force},
}])[0];

const resolveReferences = async(config, referenceuuids) => Ajax.call([{
    methodname: 'local_digieramedia_resolve_references',
    args: {contextid: config.contextid, referenceuuids},
}])[0];

const createReference = async(
    config,
    mediauuid,
    versionmode = 'FOLLOW_CURRENT',
    pinnedversionid = 0
) => Ajax.call([{
    methodname: 'local_digieramedia_create_reference',
    args: {
        contextid: config.contextid,
        mediauuid,
        displayprofile: 'embedded',
        versionmode,
        pinnedversionid,
    },
}])[0];

const updateReferenceVersion = async(
    config,
    referenceuuid,
    versionmode,
    pinnedversionid = 0
) => Ajax.call([{
    methodname: 'local_digieramedia_update_reference_version',
    args: {
        contextid: config.contextid,
        referenceuuid,
        versionmode,
        pinnedversionid,
    },
}])[0];

const formatBytes = (bytes) => {
    const value = Number(bytes || 0);
    if (value < 1024) {
        return `${value} B`;
    }
    if (value < 1024 * 1024) {
        return `${(value / 1024).toFixed(1)} KB`;
    }
    if (value < 1024 * 1024 * 1024) {
        return `${(value / (1024 * 1024)).toFixed(1)} MB`;
    }
    return `${(value / (1024 * 1024 * 1024)).toFixed(2)} GB`;
};

const formatDate = (timestamp) => {
    const value = Number(timestamp || 0);
    if (!value) {
        return '—';
    }
    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(value * 1000));
};

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

const normaliseType = (type) => {
    const value = String(type || 'generic').toLowerCase();
    return ['pdf', 'video', 'image', 'audio', 'office'].includes(value) ? value : 'generic';
};

const typeLabel = (type) => ({
    pdf: 'PDF',
    video: 'VIDEO',
    image: 'IMAGE',
    audio: 'AUDIO',
    office: 'OFFICE',
    generic: 'FILE',
})[normaliseType(type)];

const statusLabel = (status) => {
    if (status === 'TRASHED') {
        return 'Trong thùng rác';
    }
    if (status === 'PURGING') {
        return 'Đang xóa vĩnh viễn';
    }
    if (status === 'PURGED') {
        return 'Đã xóa vĩnh viễn';
    }
    return 'Sẵn sàng';
};

const filteredItems = (data, root) => {
    const type = root.querySelector('[data-region="type-filter"]')?.value || '';
    const sort = root.querySelector('[data-region="sort"]')?.value || 'newest';
    let items = [...(data.items || [])];
    if (type) {
        items = items.filter((item) => normaliseType(item.mediatype) === type);
    }
    items.sort((a, b) => {
        if (sort === 'oldest') {
            return Number(a.modified || 0) - Number(b.modified || 0);
        }
        if (sort === 'name') {
            return String(a.name || '').localeCompare(String(b.name || ''), 'vi');
        }
        return Number(b.modified || 0) - Number(a.modified || 0);
    });
    return items;
};

const renderItems = (root, data, selectedUuid) => {
    const target = root.querySelector('[data-region="media-list"]');
    if (!target) {
        return;
    }
    const items = filteredItems(data, root);
    if (!items.length) {
        target.innerHTML = '<div class="tiny-digieramedia__empty">Không có học liệu phù hợp.</div>';
        return;
    }
    target.innerHTML = items.map((item) => {
        const type = normaliseType(item.mediatype);
        const selected = item.uuid === selectedUuid;
        const trashed = item.status === 'TRASHED';
        const trashBadge = trashed ? '<span class="badge text-bg-secondary mt-1">Thùng rác</span>' : '';
        return `<button type="button" class="tiny-digieramedia__card ${selected ? 'is-selected' : ''}"
            data-action="select-media" data-media-uuid="${escapeHtml(item.uuid)}"
            data-media-name="${escapeHtml(item.name)}" data-media-type="${escapeHtml(type)}"
            data-media-size="${Number(item.size || 0)}" data-media-modified="${Number(item.modified || 0)}"
            data-media-visibility="${escapeHtml(item.visibility || '')}"
            data-media-status="${escapeHtml(item.status || 'ACTIVE')}">
            <span class="tiny-digieramedia__selectedmark" aria-hidden="true">✓</span>
            <span class="tiny-digieramedia__thumb">
                <span class="tiny-digieramedia__fileicon" data-type="${escapeHtml(type)}">
                    ${typeLabel(type)}
                </span>
            </span>
            <span class="tiny-digieramedia__name">${escapeHtml(item.name)}</span>
            <span class="tiny-digieramedia__meta">${formatBytes(item.size)} · ${formatDate(item.modified)}</span>
            ${trashBadge}
        </button>`;
    }).join('');
};

const updatePreview = (root, selected) => {
    const preview = root.querySelector('[data-region="preview"]');
    const save = root.querySelector('[data-action="save"]');
    if (!preview) {
        return;
    }
    if (!selected) {
        preview.innerHTML = '<div class="tiny-digieramedia__empty">Chọn một học liệu để xem thông tin.</div>' +
            '<details class="tiny-digieramedia__advanced"><summary>Tùy chọn nâng cao (Admin/KTV)</summary>' +
            '<div class="tiny-digieramedia__advancedbody">Chọn học liệu để xem phiên bản và thao tác quản trị.</div>' +
            '</details>';
        if (save) {
            save.disabled = true;
        }
        return;
    }
    const type = normaliseType(selected.mediatype);
    const status = selected.status || 'ACTIVE';
    preview.innerHTML = `<div class="tiny-digieramedia__previewbox">
        <div class="tiny-digieramedia__previewfile" data-type="${escapeHtml(type)}">
            ${typeLabel(type)}
        </div>
        </div>
        <h4>${escapeHtml(selected.name)}</h4><dl>
        <dt>Loại</dt><dd>${escapeHtml(typeLabel(type))}</dd>
        <dt>Dung lượng</dt><dd>${formatBytes(selected.size)}</dd>
        <dt>Cập nhật</dt><dd>${formatDate(selected.modified)}</dd>
        <dt>Phạm vi</dt><dd>${escapeHtml(selected.visibility || '—')}</dd>
        <dt>Trạng thái</dt><dd>${escapeHtml(statusLabel(status))}</dd></dl>
        <details class="tiny-digieramedia__advanced" open>
            <summary>Tùy chọn nâng cao (Admin/KTV)</summary>
            <div class="tiny-digieramedia__advancedbody" data-region="advanced-body">
                <div class="text-muted small">Đang tải phiên bản và vị trí sử dụng…</div>
            </div>
        </details>`;
    if (save) {
        save.disabled = status !== 'ACTIVE';
    }
};

const showStatus = (root, html) => {
    const status = root.querySelector('[data-region="status"]');
    if (status) {
        status.innerHTML = html;
    }
};

const showUploadProgress = (root, file, loaded, total, state = 'uploading') => {
    const queue = root.querySelector('[data-region="upload-queue"]');
    if (!queue) {
        return;
    }
    const safeTotal = Math.max(1, Number(total || file.size || 1));
    const percent = Math.min(100, Math.round((Number(loaded || 0) / safeTotal) * 100));
    let label = `Đang tải lên ${percent}%`;
    if (state === 'verifying') {
        label = 'Đang xác minh trên R2…';
    } else if (state === 'done') {
        label = 'Đã tải lên';
    }
    queue.innerHTML = `<div class="border rounded p-2 mb-2">
        <div class="d-flex justify-content-between gap-2">
            <strong class="text-truncate">${escapeHtml(file.name)}</strong>
            <span>${escapeHtml(label)}</span>
        </div>
        <div class="progress mt-2" role="progressbar" aria-valuenow="${percent}"
            aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" style="width:${percent}%"></div>
        </div>
        <div class="small text-muted mt-1">
            ${formatBytes(loaded)} / ${formatBytes(safeTotal)}
        </div>
    </div>`;
};

const versionHistoryHtml = (data) => {
    const versions = data?.versions || [];
    if (!versions.length) {
        return '<div class="text-muted small">Chưa có phiên bản.</div>';
    }
    return versions.map((version) => `
        <div class="border rounded px-2 py-1 mb-1 small">
            <div class="d-flex justify-content-between gap-2">
                <strong>v${Number(version.versionno)}</strong>
                ${version.iscurrent ? '<span class="badge text-bg-primary">Hiện hành</span>' : ''}
            </div>
            <div class="text-truncate">${escapeHtml(version.displayfilename)}</div>
            <div class="text-muted">${formatBytes(version.filesize)} · ${formatDate(version.timecreated)}</div>
        </div>`).join('');
};

const versionControlsHtml = (data, versionmode, pinnedversionid, editingReference) => {
    const versions = data?.versions || [];
    const canManage = Boolean(data?.canmanageversions || data?.canreplace);
    if (!canManage || data?.mediastatus === 'TRASHED') {
        return '<div class="text-muted small">Lịch sử phiên bản ở chế độ chỉ đọc.</div>';
    }
    const pinValue = Number(pinnedversionid || data.currentversionid || 0);
    const options = versions.map((version) => `
        <option value="${Number(version.id)}" ${Number(version.id) === pinValue ? 'selected' : ''}>
            v${Number(version.versionno)} · ${escapeHtml(version.displayfilename)}
        </option>`).join('');
    const replaceButton = data.canreplace ?
        '<button type="button" class="btn btn-outline-primary btn-sm w-100" ' +
            'data-action="replace-media">Thay thế file</button>' : '';
    const label = editingReference ? 'Cấu hình reference đang chọn' : 'Cách reference mới chọn phiên bản';
    const editInfo = editingReference ?
        '<div class="small text-primary mb-2">Đang chỉnh reference đã chèn.</div>' : '';
    return `<div class="small fw-semibold mb-1">${escapeHtml(label)}</div>
        ${editInfo}
        <label class="d-block small mb-1">
            <input type="radio" name="digiera-version-mode" data-action="version-mode"
                value="FOLLOW_CURRENT" ${versionmode === 'FOLLOW_CURRENT' ? 'checked' : ''}>
            Luôn dùng phiên bản mới nhất
        </label>
        <label class="d-block small mb-1">
            <input type="radio" name="digiera-version-mode" data-action="version-mode"
                value="PINNED_VERSION" ${versionmode === 'PINNED_VERSION' ? 'checked' : ''}>
            Ghim một phiên bản
        </label>
        <select class="form-select form-select-sm mb-2" data-region="pin-version"
            ${versionmode === 'PINNED_VERSION' ? '' : 'disabled'}>${options}</select>
        ${replaceButton}`;
};

const usageHtml = (usage) => {
    if (!usage) {
        return '<div class="text-muted small">Không có quyền xem vị trí sử dụng.</div>';
    }
    const rows = usage.usages || [];
    const visibleInfo = Number(usage.visiblecount || 0) < Number(usage.livecount || 0) ?
        `<div class="small text-muted mb-1">Hiển thị ${Number(usage.visiblecount || 0)} vị trí bạn có quyền xem.</div>` : '';
    const list = rows.length ? rows.map((row) => {
        const primary = row.activityname || row.coursename || row.fallback;
        const secondary = row.activityname && row.coursename ? row.coursename : row.status;
        const pin = row.versionmode === 'PINNED_VERSION' && Number(row.pinnedversionno || 0) > 0 ?
            ` · ghim v${Number(row.pinnedversionno)}` : '';
        const label = `${escapeHtml(primary)}${escapeHtml(pin)}`;
        const linked = row.url ?
            `<a href="${escapeHtml(row.url)}" target="_blank" rel="noopener noreferrer">${label}</a>` : label;
        return `<div class="border rounded px-2 py-1 mb-1 small">
            <div>${linked}</div>
            <div class="text-muted">${escapeHtml(secondary || row.status)} · ${escapeHtml(row.status)}</div>
        </div>`;
    }).join('') : '<div class="text-muted small">Chưa có vị trí sử dụng hiển thị.</div>';
    return `<div class="mb-1"><strong>Vị trí đang sử dụng (${Number(usage.livecount || 0)})</strong></div>
        ${visibleInfo}${list}`;
};

const lifecycleHtml = (usage, purgeConfirming) => {
    if (!usage) {
        return '';
    }
    const status = usage.mediastatus || 'ACTIVE';
    if (status === 'ACTIVE') {
        if (!usage.cantrash) {
            return '<div class="text-muted small">Bạn không có quyền đưa học liệu này vào thùng rác.</div>';
        }
        const live = Number(usage.livecount || 0);
        const info = live > 0 ?
            `Đưa vào thùng rác không xóa file R2 và không làm hỏng ${live} vị trí đang sử dụng.` :
            'Đưa vào thùng rác chỉ ẩn học liệu khỏi thư viện; file R2 chưa bị xóa.';
        return `<div class="small text-muted mb-2">${escapeHtml(info)}</div>
            <button type="button" class="btn btn-outline-danger btn-sm w-100" data-action="trash-media">
                Đưa vào thùng rác
            </button>`;
    }
    if (status !== 'TRASHED' && status !== 'PURGING') {
        return '<div class="text-muted small">Học liệu không còn khả dụng để quản trị vòng đời.</div>';
    }

    const deleted = usage.deletedat ? `Đã đưa vào thùng rác ${formatDate(usage.deletedat)}` : 'Trong thùng rác';
    const actor = usage.deletedbyname ? ` bởi ${escapeHtml(usage.deletedbyname)}` : '';
    const reason = usage.reason ? `<div class="small text-muted">Lý do: ${escapeHtml(usage.reason)}</div>` : '';
    const restore = usage.canrestore && status === 'TRASHED' ?
        '<button type="button" class="btn btn-outline-primary btn-sm w-100 mb-2" ' +
            'data-action="restore-media">Khôi phục</button>' : '';
    let purge = '';
    if (usage.canpurge) {
        if (!purgeConfirming) {
            purge = '<button type="button" class="btn btn-danger btn-sm w-100" ' +
                'data-action="request-purge">Xóa vĩnh viễn</button>';
        } else {
            const live = Number(usage.livecount || 0);
            const warning = live > 0 ?
                `Học liệu đang được dùng tại ${live} vị trí. Xóa cưỡng bức sẽ xóa file vật lý khỏi R2 và làm ` +
                    `${live} reference chuyển sang trạng thái không khả dụng.` :
                'Xóa vĩnh viễn tất cả phiên bản vật lý khỏi Cloudflare R2? Thao tác này không thể hoàn tác.';
            purge = `<div class="alert alert-danger py-2 small">${escapeHtml(warning)}</div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" data-action="cancel-purge">
                        Không xóa
                    </button>
                    <button type="button" class="btn btn-danger btn-sm flex-fill" data-action="confirm-purge"
                        data-live-count="${live}">Xác nhận xóa vĩnh viễn</button>
                </div>`;
        }
    }
    return `<div class="mb-2"><span class="badge text-bg-secondary">Trong thùng rác</span></div>
        <div class="small mb-1">${escapeHtml(deleted)}${actor}</div>${reason}${restore}${purge}`;
};

const renderAdvanced = (
    root,
    versionData,
    usageData,
    versionmode,
    pinnedversionid,
    editingReference,
    purgeConfirming
) => {
    const body = root.querySelector('[data-region="advanced-body"]');
    if (!body) {
        return;
    }
    body.innerHTML = `<div class="mb-2"><strong>Lịch sử phiên bản</strong></div>
        ${versionHistoryHtml(versionData)}
        <hr>
        ${versionControlsHtml(versionData, versionmode, pinnedversionid, editingReference)}
        <hr>
        ${usageHtml(usageData)}
        <hr>
        <div class="mb-1"><strong>Vòng đời học liệu</strong></div>
        ${lifecycleHtml(usageData, purgeConfirming)}`;
};

export const open = async(editor) => {
    const config = getConfig(editor);
    if (!config.enabled || !config.contextid) {
        return;
    }

    const selectedReference = ReferenceComponent.getSelectedReference(editor);
    const bookmark = editor.selection.getBookmark();
    let editingReference = null;
    let tab = 'library';
    let selected = null;
    let lastData = {items: []};
    let uploading = false;
    let versionmode = 'FOLLOW_CURRENT';
    let pinnedversionid = 0;
    let advancedRequest = 0;
    let purgeConfirming = false;

    if (selectedReference?.referenceuuid) {
        try {
            const resolved = await resolveReferences(config, [selectedReference.referenceuuid]);
            const item = resolved?.[0] || null;
            if (item) {
                editingReference = item;
                selected = {
                    uuid: item.mediauuid,
                    name: item.name,
                    mediatype: item.mediatype,
                    size: Number(item.size || 0),
                    modified: Number(item.modified || 0),
                    visibility: item.visibility || '',
                    status: item.mediastatus || 'ACTIVE',
                };
                versionmode = item.versionmode === 'PINNED_VERSION' ? 'PINNED_VERSION' : 'FOLLOW_CURRENT';
                pinnedversionid = Number(item.pinnedversionid || 0);
                if (selected.status === 'TRASHED') {
                    tab = 'trash';
                }
            }
        } catch (error) {
            editingReference = null;
        }
    }

    const modal = await DigieraMediaModal.create({templateContext: {canupload: Boolean(config.canupload)}});
    const root = modal.getRoot()[0];
    const uploadInput = root.querySelector('[data-region="upload-input"]');
    const replaceInput = root.querySelector('[data-region="replace-input"]');
    const dropzone = root.querySelector('[data-region="upload-dropzone"]');
    const saveButton = root.querySelector('[data-action="save"]');
    const rerender = () => renderItems(root, lastData, selected?.uuid || '');
    const syncSaveMode = () => {
        if (saveButton) {
            saveButton.textContent = editingReference ? 'Cập nhật reference' : 'Chèn vào bài';
            saveButton.disabled = !selected || selected.status !== 'ACTIVE';
        }
    };
    const syncTab = () => {
        root.querySelectorAll('[data-tab]').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.tab === tab);
        });
    };
    const beginNewReference = () => {
        editingReference = null;
        versionmode = 'FOLLOW_CURRENT';
        pinnedversionid = 0;
        purgeConfirming = false;
        syncSaveMode();
    };

    const load = async(query = '') => {
        const list = root.querySelector('[data-region="media-list"]');
        if (list) {
            list.innerHTML = '<div class="tiny-digieramedia__empty">Đang tải…</div>';
        }
        try {
            lastData = await search(config, tab, query);
            rerender();
        } catch (error) {
            if (list) {
                list.innerHTML = '<div class="alert alert-danger">Không tải được thư viện học liệu.</div>';
            }
        }
    };

    const loadAdvanced = async(media) => {
        const requestId = ++advancedRequest;
        const body = root.querySelector('[data-region="advanced-body"]');
        if (!body || !media) {
            return;
        }
        body.innerHTML = '<div class="text-muted small">Đang tải phiên bản và vị trí sử dụng…</div>';
        const versionPromise = getMediaVersions(config, media.uuid);
        const usagePromise = lastData.canviewusage ? getMediaUsage(config, media.uuid) : Promise.resolve(null);
        const [versionResult, usageResult] = await Promise.allSettled([versionPromise, usagePromise]);
        if (requestId !== advancedRequest || selected?.uuid !== media.uuid) {
            return;
        }
        const versionData = versionResult.status === 'fulfilled' ? versionResult.value : {
            versions: [],
            currentversionid: 0,
            canreplace: false,
            canmanageversions: false,
            mediastatus: media.status || 'ACTIVE',
        };
        const usageData = usageResult.status === 'fulfilled' ? usageResult.value : null;
        if (versionmode === 'PINNED_VERSION' && !pinnedversionid) {
            pinnedversionid = Number(versionData.currentversionid || 0);
        }
        const isEditing = Boolean(editingReference && editingReference.mediauuid === media.uuid);
        renderAdvanced(
            root,
            versionData,
            usageData,
            versionmode,
            pinnedversionid,
            isEditing,
            purgeConfirming
        );
    };

    const refreshSelected = async(mediauuid, nextTab) => {
        tab = nextTab;
        syncTab();
        const query = root.querySelector('[data-region="search"]')?.value || '';
        await load(query);
        selected = (lastData.items || []).find((item) => item.uuid === mediauuid) || null;
        updatePreview(root, selected);
        syncSaveMode();
        if (selected) {
            rerender();
            await loadAdvanced(selected);
        }
    };

    const uploadFiles = async(files) => {
        if (!config.canupload || uploading || !files?.length) {
            return;
        }
        uploading = true;
        showStatus(root, '');
        try {
            for (const file of [...files]) {
                showUploadProgress(root, file, 0, file.size);
                const media = await uploadFile(
                    config,
                    file,
                    (loaded, total) => showUploadProgress(root, file, loaded, total)
                );
                showUploadProgress(root, file, file.size, file.size, 'verifying');
                selected = {...media, status: media.status || 'ACTIVE'};
                beginNewReference();
                tab = 'library';
                syncTab();
                await load('');
                updatePreview(root, selected);
                syncSaveMode();
                void loadAdvanced(selected);
                showUploadProgress(root, file, file.size, file.size, 'done');
            }
            showStatus(
                root,
                '<div class="alert alert-success py-2">Upload R2 hoàn tất. Học liệu đã sẵn sàng để chèn.</div>'
            );
        } catch (error) {
            const message = error?.message || 'Upload R2 thất bại.';
            showStatus(root, `<div class="alert alert-danger py-2">${escapeHtml(message)}</div>`);
        } finally {
            uploading = false;
            if (uploadInput) {
                uploadInput.value = '';
            }
        }
    };

    const replaceSelectedFile = async(file) => {
        if (!selected || selected.status !== 'ACTIVE' || !lastData.canreplace || uploading || !file) {
            return;
        }
        uploading = true;
        showStatus(root, '');
        const replacingUuid = selected.uuid;
        try {
            showUploadProgress(root, file, 0, file.size);
            const media = await uploadFile(
                config,
                file,
                (loaded, total) => showUploadProgress(root, file, loaded, total),
                replacingUuid
            );
            showUploadProgress(root, file, file.size, file.size, 'verifying');
            selected = {...media, status: media.status || 'ACTIVE'};
            await load(root.querySelector('[data-region="search"]')?.value || '');
            updatePreview(root, selected);
            syncSaveMode();
            await loadAdvanced(selected);
            showUploadProgress(root, file, file.size, file.size, 'done');
            showStatus(
                root,
                '<div class="alert alert-success py-2">Đã tạo phiên bản mới và chuyển Current sang phiên bản vừa tải.</div>'
            );
        } catch (error) {
            const message = error?.message || 'Thay thế file thất bại.';
            showStatus(root, `<div class="alert alert-danger py-2">${escapeHtml(message)}</div>`);
        } finally {
            uploading = false;
            if (replaceInput) {
                replaceInput.value = '';
            }
        }
    };

    root.addEventListener('click', async(event) => {
        const nav = event.target.closest('[data-tab]');
        if (nav) {
            tab = nav.dataset.tab;
            selected = null;
            beginNewReference();
            syncTab();
            updatePreview(root, selected);
            syncSaveMode();
            await load(root.querySelector('[data-region="search"]')?.value || '');
            return;
        }
        const item = event.target.closest('[data-action="select-media"]');
        if (item) {
            const keepEditing = Boolean(editingReference && editingReference.mediauuid === item.dataset.mediaUuid);
            if (!keepEditing) {
                beginNewReference();
            }
            purgeConfirming = false;
            selected = {
                uuid: item.dataset.mediaUuid,
                name: item.dataset.mediaName,
                mediatype: item.dataset.mediaType,
                size: Number(item.dataset.mediaSize || 0),
                modified: Number(item.dataset.mediaModified || 0),
                visibility: item.dataset.mediaVisibility || '',
                status: item.dataset.mediaStatus || 'ACTIVE',
            };
            rerender();
            updatePreview(root, selected);
            syncSaveMode();
            void loadAdvanced(selected);
            return;
        }
        if (event.target.closest('[data-action="replace-media"]')) {
            if (selected?.status === 'ACTIVE') {
                replaceInput?.click();
            }
            return;
        }
        if (event.target.closest('[data-action="trash-media"]') && selected) {
            try {
                const uuid = selected.uuid;
                showStatus(root, '<div class="alert alert-info py-2">Đang đưa học liệu vào thùng rác…</div>');
                await trashMedia(config, uuid, 'Moved to Trash from TinyMCE media library');
                beginNewReference();
                await refreshSelected(uuid, 'trash');
                showStatus(
                    root,
                    '<div class="alert alert-success py-2">Đã đưa học liệu vào thùng rác. File R2 chưa bị xóa.</div>'
                );
            } catch (error) {
                showStatus(root, `<div class="alert alert-danger py-2">${escapeHtml(error?.message || 'Không thể đưa vào thùng rác.')}</div>`);
            }
            return;
        }
        if (event.target.closest('[data-action="restore-media"]') && selected) {
            try {
                const uuid = selected.uuid;
                showStatus(root, '<div class="alert alert-info py-2">Đang khôi phục học liệu…</div>');
                await restoreMedia(config, uuid);
                beginNewReference();
                await refreshSelected(uuid, 'library');
                showStatus(root, '<div class="alert alert-success py-2">Đã khôi phục học liệu.</div>');
            } catch (error) {
                showStatus(root, `<div class="alert alert-danger py-2">${escapeHtml(error?.message || 'Khôi phục thất bại.')}</div>`);
            }
            return;
        }
        if (event.target.closest('[data-action="request-purge"]') && selected) {
            purgeConfirming = true;
            await loadAdvanced(selected);
            return;
        }
        if (event.target.closest('[data-action="cancel-purge"]') && selected) {
            purgeConfirming = false;
            await loadAdvanced(selected);
            return;
        }
        const confirmPurge = event.target.closest('[data-action="confirm-purge"]');
        if (confirmPurge && selected) {
            try {
                const uuid = selected.uuid;
                const force = Number(confirmPurge.dataset.liveCount || 0) > 0;
                showStatus(root, '<div class="alert alert-danger py-2">Đang xóa vĩnh viễn các phiên bản trên R2…</div>');
                await purgeMedia(config, uuid, force);
                selected = null;
                editingReference = null;
                purgeConfirming = false;
                await load(root.querySelector('[data-region="search"]')?.value || '');
                updatePreview(root, selected);
                syncSaveMode();
                showStatus(
                    root,
                    '<div class="alert alert-success py-2">Đã xóa vĩnh viễn học liệu khỏi Cloudflare R2.</div>'
                );
            } catch (error) {
                purgeConfirming = false;
                showStatus(root, `<div class="alert alert-danger py-2">${escapeHtml(error?.message || 'Xóa vĩnh viễn thất bại.')}</div>`);
                if (selected) {
                    await loadAdvanced(selected);
                }
            }
            return;
        }
        if (event.target.closest('[data-region="upload-dropzone"]')) {
            if (config.canupload) {
                uploadInput?.click();
            } else {
                showStatus(root, '<div class="alert alert-secondary py-2">Tài khoản hiện tại không có quyền upload.</div>');
            }
            return;
        }
        if (event.target.closest('[data-action="library-only"]')) {
            modal.hide();
        }
    });

    root.addEventListener('change', (event) => {
        const mode = event.target.closest('[data-action="version-mode"]');
        if (mode && selected?.status === 'ACTIVE') {
            versionmode = mode.value === 'PINNED_VERSION' ? 'PINNED_VERSION' : 'FOLLOW_CURRENT';
            const picker = root.querySelector('[data-region="pin-version"]');
            if (picker) {
                picker.disabled = versionmode !== 'PINNED_VERSION';
                if (versionmode === 'PINNED_VERSION' && !pinnedversionid) {
                    pinnedversionid = Number(picker.value || 0);
                }
            }
            return;
        }
        if (event.target.matches('[data-region="pin-version"]') && selected?.status === 'ACTIVE') {
            pinnedversionid = Number(event.target.value || 0);
        }
    });

    uploadInput?.addEventListener('change', (event) => void uploadFiles(event.target.files));
    replaceInput?.addEventListener('change', (event) => void replaceSelectedFile(event.target.files?.[0]));
    dropzone?.addEventListener('dragover', (event) => {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'copy';
        dropzone.classList.add('is-dragover');
    });
    dropzone?.addEventListener('dragleave', () => dropzone.classList.remove('is-dragover'));
    dropzone?.addEventListener('drop', (event) => {
        event.preventDefault();
        dropzone.classList.remove('is-dragover');
        void uploadFiles(event.dataTransfer.files);
    });
    dropzone?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            if (config.canupload) {
                uploadInput?.click();
            }
        }
    });

    let searchTimer = null;
    root.querySelector('[data-region="search"]')?.addEventListener('input', (event) => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => load(event.target.value), 250);
    });
    root.querySelector('[data-region="type-filter"]')?.addEventListener('change', rerender);
    root.querySelector('[data-region="sort"]')?.addEventListener('change', rerender);

    modal.getRoot().on(ModalEvents.save, async(event) => {
        event.preventDefault();
        if (!selected || selected.status !== 'ACTIVE' || uploading) {
            return;
        }
        const save = root.querySelector('[data-action="save"]');
        if (save) {
            save.disabled = true;
        }
        try {
            const pin = versionmode === 'PINNED_VERSION' ? pinnedversionid : 0;
            if (editingReference && editingReference.mediauuid === selected.uuid) {
                const reference = await updateReferenceVersion(
                    config,
                    editingReference.referenceuuid,
                    versionmode,
                    pin
                );
                ReferenceComponent.updateReferenceState(editor, reference);
                modal.hide();
                editor.focus();
                return;
            }

            const reference = await createReference(
                config,
                selected.uuid,
                versionmode,
                pin
            );
            editor.selection.moveToBookmark(bookmark);
            ReferenceComponent.insert(editor, reference);
            modal.hide();
            editor.focus();
        } catch (error) {
            showStatus(root, '<div class="alert alert-danger">Không thể lưu cấu hình reference học liệu.</div>');
            if (save) {
                save.disabled = false;
            }
        }
    });

    updatePreview(root, selected);
    syncSaveMode();
    syncTab();
    await load();
    if (selected) {
        rerender();
        updatePreview(root, selected);
        syncSaveMode();
        await loadAdvanced(selected);
    }
};
