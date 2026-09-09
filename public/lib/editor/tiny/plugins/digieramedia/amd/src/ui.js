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
        return `<button type="button" class="tiny-digieramedia__card ${selected ? 'is-selected' : ''}"
            data-action="select-media" data-media-uuid="${escapeHtml(item.uuid)}"
            data-media-name="${escapeHtml(item.name)}" data-media-type="${escapeHtml(type)}"
            data-media-size="${Number(item.size || 0)}" data-media-modified="${Number(item.modified || 0)}"
            data-media-visibility="${escapeHtml(item.visibility || '')}">
            <span class="tiny-digieramedia__selectedmark" aria-hidden="true">✓</span>
            <span class="tiny-digieramedia__thumb">
                <span class="tiny-digieramedia__fileicon" data-type="${escapeHtml(type)}">
                    ${typeLabel(type)}
                </span>
            </span>
            <span class="tiny-digieramedia__name">${escapeHtml(item.name)}</span>
            <span class="tiny-digieramedia__meta">${formatBytes(item.size)} · ${formatDate(item.modified)}</span>
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
        <dt>Trạng thái</dt><dd>Sẵn sàng</dd></dl>
        <details class="tiny-digieramedia__advanced" open>
            <summary>Tùy chọn nâng cao (Admin/KTV)</summary>
            <div class="tiny-digieramedia__advancedbody" data-region="advanced-body">
                <div class="text-muted small">Đang tải lịch sử phiên bản…</div>
            </div>
        </details>`;
    if (save) {
        save.disabled = false;
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

const renderAdvanced = (root, data, versionmode, pinnedversionid, editingReference) => {
    const body = root.querySelector('[data-region="advanced-body"]');
    if (!body) {
        return;
    }
    const versions = data.versions || [];
    const pinValue = Number(pinnedversionid || data.currentversionid || 0);
    const history = versions.length ? versions.map((version) => `
        <div class="border rounded px-2 py-1 mb-1 small">
            <div class="d-flex justify-content-between gap-2">
                <strong>v${Number(version.versionno)}</strong>
                ${version.iscurrent ? '<span class="badge text-bg-primary">Hiện hành</span>' : ''}
            </div>
            <div class="text-truncate">${escapeHtml(version.displayfilename)}</div>
            <div class="text-muted">${formatBytes(version.filesize)} · ${formatDate(version.timecreated)}</div>
        </div>`).join('') : '<div class="text-muted small">Chưa có phiên bản.</div>';
    const options = versions.map((version) => `
        <option value="${Number(version.id)}" ${Number(version.id) === pinValue ? 'selected' : ''}>
            v${Number(version.versionno)} · ${escapeHtml(version.displayfilename)}
        </option>`).join('');
    const canManage = Boolean(data.canmanageversions || data.canreplace);
    const replaceButton = data.canreplace ?
        '<button type="button" class="btn btn-outline-primary btn-sm w-100" ' +
            'data-action="replace-media">Thay thế file</button>' :
        '';
    const versionLabel = editingReference ? 'Cấu hình reference đang chọn' : 'Cách reference mới chọn phiên bản';
    body.innerHTML = `<div class="mb-2"><strong>Lịch sử phiên bản</strong></div>
        ${history}
        ${canManage ? `<hr>
        <div class="small fw-semibold mb-1">${escapeHtml(versionLabel)}</div>
        ${editingReference ? '<div class="small text-primary mb-2">Đang chỉnh reference đã chèn.</div>' : ''}
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
        ${replaceButton}` :
        '<div class="text-muted small">Bạn không có quyền quản trị phiên bản.</div>'}`;
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
                };
                versionmode = item.versionmode === 'PINNED_VERSION' ? 'PINNED_VERSION' : 'FOLLOW_CURRENT';
                pinnedversionid = Number(item.pinnedversionid || 0);
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
        }
    };
    const beginNewReference = () => {
        editingReference = null;
        versionmode = 'FOLLOW_CURRENT';
        pinnedversionid = 0;
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
        if (!lastData.canreplace && !lastData.canmanageversions) {
            body.innerHTML = '<div class="text-muted small">Bạn không có quyền quản trị phiên bản.</div>';
            return;
        }
        try {
            const data = await getMediaVersions(config, media.uuid);
            if (requestId !== advancedRequest || selected?.uuid !== media.uuid) {
                return;
            }
            if (versionmode === 'PINNED_VERSION' && !pinnedversionid) {
                pinnedversionid = Number(data.currentversionid || 0);
            }
            const isEditing = Boolean(editingReference && editingReference.mediauuid === media.uuid);
            renderAdvanced(root, data, versionmode, pinnedversionid, isEditing);
        } catch (error) {
            if (requestId === advancedRequest && body) {
                body.innerHTML = '<div class="alert alert-danger py-1 small">Không tải được lịch sử phiên bản.</div>';
            }
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
                selected = media;
                beginNewReference();
                tab = 'library';
                await load('');
                updatePreview(root, selected);
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
        if (!selected || !lastData.canreplace || uploading || !file) {
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
            selected = media;
            await load(root.querySelector('[data-region="search"]')?.value || '');
            updatePreview(root, selected);
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

    root.addEventListener('click', (event) => {
        const nav = event.target.closest('[data-tab]');
        if (nav) {
            tab = nav.dataset.tab;
            selected = null;
            beginNewReference();
            root.querySelectorAll('[data-tab]').forEach((button) => button.classList.toggle('is-active', button === nav));
            updatePreview(root, selected);
            load(root.querySelector('[data-region="search"]')?.value || '');
            return;
        }
        const item = event.target.closest('[data-action="select-media"]');
        if (item) {
            const keepEditing = Boolean(editingReference && editingReference.mediauuid === item.dataset.mediaUuid);
            if (!keepEditing) {
                beginNewReference();
            }
            selected = {
                uuid: item.dataset.mediaUuid,
                name: item.dataset.mediaName,
                mediatype: item.dataset.mediaType,
                size: Number(item.dataset.mediaSize || 0),
                modified: Number(item.dataset.mediaModified || 0),
                visibility: item.dataset.mediaVisibility || '',
            };
            rerender();
            updatePreview(root, selected);
            void loadAdvanced(selected);
            return;
        }
        if (event.target.closest('[data-action="replace-media"]')) {
            replaceInput?.click();
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
        if (mode) {
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
        if (event.target.matches('[data-region="pin-version"]')) {
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
        if (!selected || uploading) {
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
    await load();
    if (selected) {
        rerender();
        updatePreview(root, selected);
        syncSaveMode();
        await loadAdvanced(selected);
    }
};
