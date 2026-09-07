import Ajax from 'core/ajax';
import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import {getConfig} from './options';
import * as ReferenceComponent from './reference_component';

export default class DigieraMediaModal extends Modal {
    static TYPE = 'tiny_digieramedia/modal';
    static TEMPLATE = 'tiny_digieramedia/modal';

    configure(config) {
        config.large = true;
        config.show = true;
        config.removeOnClose = true;
        super.configure(config);
    }

    registerEventListeners() {
        super.registerEventListeners();
        this.registerCloseOnCancel();
    }
}

DigieraMediaModal.registerModalType();

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
    return `${(value / (1024 * 1024)).toFixed(1)} MB`;
};

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

const renderItems = (root, data, selectedUuid) => {
    const target = root.querySelector('[data-region="media-list"]');
    if (!target) {
        return;
    }
    if (!data.items.length) {
        target.innerHTML = '<div class="tiny-digieramedia__empty">Không có học liệu phù hợp.</div>';
        return;
    }
    target.innerHTML = data.items.map((item) => `
        <button type="button" class="tiny-digieramedia__item ${item.uuid === selectedUuid ? 'is-selected' : ''}"
            data-action="select-media" data-media-uuid="${escapeHtml(item.uuid)}"
            data-media-name="${escapeHtml(item.name)}"
            data-media-type="${escapeHtml(item.mediatype)}" data-media-size="${Number(item.size || 0)}">
            <span class="tiny-digieramedia__type">${escapeHtml(item.mediatype.toUpperCase())}</span>
            <span class="tiny-digieramedia__name">${escapeHtml(item.name)}</span>
            <span class="tiny-digieramedia__meta">${formatBytes(item.size)}</span>
        </button>`).join('');
};

const updatePreview = (root, selected) => {
    const preview = root.querySelector('[data-region="preview"]');
    const save = root.querySelector('[data-action="save"]');
    if (!preview) {
        return;
    }
    if (!selected) {
        preview.innerHTML = '<div class="tiny-digieramedia__empty">Chọn một học liệu để xem thông tin.</div>';
        if (save) {
            save.disabled = true;
        }
        return;
    }
    preview.innerHTML = `
        <div class="tiny-digieramedia__previewbadge">${escapeHtml(selected.mediatype.toUpperCase())}</div>
        <h4>${escapeHtml(selected.name)}</h4>
        <dl><dt>Loại</dt><dd>${escapeHtml(selected.mediatype)}</dd><dt>Dung lượng</dt><dd>${formatBytes(selected.size)}</dd></dl>`;
    if (save) {
        save.disabled = false;
    }
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

    const load = async(query = '') => {
        const list = root.querySelector('[data-region="media-list"]');
        if (list) {
            list.innerHTML = '<div class="tiny-digieramedia__empty">Đang tải…</div>';
        }
        try {
            lastData = await search(config, tab, query);
            renderItems(root, lastData, selected?.uuid || '');
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
            };
            renderItems(root, lastData, selected.uuid);
            updatePreview(root, selected);
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
