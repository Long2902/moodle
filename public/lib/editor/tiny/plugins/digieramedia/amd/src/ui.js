import Ajax from 'core/ajax';
import ModalEvents from 'core/modal_events';
import DigieraMediaModal from './modal';
import {getConfig} from './options';
import * as ReferenceComponent from './reference_component';

const search = async(config, tab, query = '') => Ajax.call([{
    methodname: 'local_digieramedia_search_media',
    args: {contextid: config.contextid, tab, query, page: 0, pagesize: 24},
}])[0];

const createReference = async(config, mediauuid) => Ajax.call([{
    methodname: 'local_digieramedia_create_reference',
    args: {contextid: config.contextid, mediauuid, displayprofile: 'embedded'},
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
    return new Intl.DateTimeFormat('vi-VN', {day: '2-digit', month: '2-digit', year: 'numeric'}).format(new Date(value * 1000));
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
        return `
            <button type="button" class="tiny-digieramedia__card ${selected ? 'is-selected' : ''}"
                data-action="select-media" data-media-uuid="${escapeHtml(item.uuid)}"
                data-media-name="${escapeHtml(item.name)}"
                data-media-type="${escapeHtml(type)}"
                data-media-size="${Number(item.size || 0)}"
                data-media-modified="${Number(item.modified || 0)}"
                data-media-visibility="${escapeHtml(item.visibility || '')}">
                <span class="tiny-digieramedia__selectedmark" aria-hidden="true">✓</span>
                <span class="tiny-digieramedia__thumb">
                    <span class="tiny-digieramedia__fileicon" data-type="${escapeHtml(type)}">${typeLabel(type)}</span>
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
        preview.innerHTML = `
            <div class="tiny-digieramedia__empty">Chọn một học liệu để xem thông tin.</div>
            <details class="tiny-digieramedia__advanced">
                <summary>Tùy chọn nâng cao (Admin/KTV)</summary>
                <div class="tiny-digieramedia__advancedbody">Các thao tác quản trị nâng cao sẽ xuất hiện khi quyền và API tương ứng khả dụng.</div>
            </details>`;
        if (save) {
            save.disabled = true;
        }
        return;
    }

    const type = normaliseType(selected.mediatype);
    preview.innerHTML = `
        <div class="tiny-digieramedia__previewbox">
            <div class="tiny-digieramedia__previewfile" data-type="${escapeHtml(type)}">${typeLabel(type)}</div>
        </div>
        <h4>${escapeHtml(selected.name)}</h4>
        <dl>
            <dt>Loại</dt><dd>${escapeHtml(typeLabel(type))}</dd>
            <dt>Dung lượng</dt><dd>${formatBytes(selected.size)}</dd>
            <dt>Cập nhật</dt><dd>${formatDate(selected.modified)}</dd>
            <dt>Phạm vi</dt><dd>${escapeHtml(selected.visibility || '—')}</dd>
            <dt>Trạng thái</dt><dd>Sẵn sàng</dd>
        </dl>
        <details class="tiny-digieramedia__advanced">
            <summary>Tùy chọn nâng cao (Admin/KTV)</summary>
            <div class="tiny-digieramedia__advancedbody">Phiên bản, vị trí sử dụng, thay thế và quản trị vòng đời sẽ được nối vào panel này theo API quản trị.</div>
        </details>`;

    if (save) {
        save.disabled = false;
    }
};

const showUploadNotice = (root, canupload) => {
    const queue = root.querySelector('[data-region="upload-queue"]');
    if (!queue) {
        return;
    }
    queue.innerHTML = canupload
        ? '<div class="alert alert-info mb-0">RC1 hiện ưu tiên luồng chọn/chèn học liệu. Live R2 upload sẽ được bật sau khi cấu hình R2 được verify.</div>'
        : '<div class="alert alert-secondary mb-0">Tài khoản hiện tại không có quyền upload.</div>';
};

export const open = async(editor) => {
    const config = getConfig(editor);
    if (!config.enabled || !config.contextid) {
        return;
    }

    const bookmark = editor.selection.getBookmark();
    let tab = 'library';
    let selected = null;
    let lastData = {items: []};

    const modal = await DigieraMediaModal.create({templateContext: {canupload: Boolean(config.canupload)}});
    const root = modal.getRoot()[0];
    root.querySelector('.modal-dialog')?.classList.add('tiny-digieramedia-dialog');

    const rerender = () => renderItems(root, lastData, selected?.uuid || '');

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

    root.addEventListener('click', (event) => {
        const nav = event.target.closest('[data-tab]');
        if (nav) {
            tab = nav.dataset.tab;
            selected = null;
            root.querySelectorAll('[data-tab]').forEach((button) => button.classList.toggle('is-active', button === nav));
            updatePreview(root, selected);
            if (nav.dataset.view === 'upload') {
                showUploadNotice(root, Boolean(config.canupload));
            } else {
                const queue = root.querySelector('[data-region="upload-queue"]');
                if (queue) {
                    queue.innerHTML = '';
                }
            }
            load(root.querySelector('[data-region="search"]')?.value || '');
            return;
        }

        const item = event.target.closest('[data-action="select-media"]');
        if (item) {
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
            return;
        }

        const dropzone = event.target.closest('[data-region="upload-dropzone"]');
        if (dropzone) {
            showUploadNotice(root, Boolean(config.canupload));
            return;
        }

        const libraryOnly = event.target.closest('[data-action="library-only"]');
        if (libraryOnly) {
            modal.hide();
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
        if (!selected) {
            return;
        }
        const save = root.querySelector('[data-action="save"]');
        if (save) {
            save.disabled = true;
        }
        try {
            const reference = await createReference(config, selected.uuid);
            editor.selection.moveToBookmark(bookmark);
            ReferenceComponent.insert(editor, reference);
            modal.hide();
            editor.focus();
        } catch (error) {
            const status = root.querySelector('[data-region="status"]');
            if (status) {
                status.innerHTML = '<div class="alert alert-danger">Không thể tạo tham chiếu học liệu.</div>';
            }
            if (save) {
                save.disabled = false;
            }
        }
    });

    updatePreview(root, selected);
    await load();
};
