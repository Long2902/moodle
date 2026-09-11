define(['local_digieranative/native_editor'], function(NativeEditor) {
    const modalSelector = name => `[data-modal="${name}"]`;
    let latestNativeJson = null;
    let latestAssetUrls = {};

    const openModal = name => {
        const modal = document.querySelector(modalSelector(name));
        if (!modal) {
            return;
        }
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        const focusable = modal.querySelector('input:not([type="hidden"]), button, select, textarea, a[href]');
        focusable?.focus();
    };

    const closeModal = target => {
        const modal = typeof target === 'string'
            ? document.querySelector(modalSelector(target))
            : target?.closest?.('.wslib-modal');
        if (!modal) {
            return;
        }
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    };

    const escapeHtml = value => {
        const node = document.createElement('div');
        node.textContent = String(value ?? '');
        return node.innerHTML;
    };

    const selectRow = row => {
        document.querySelectorAll('.wslib-v1-tablerow').forEach(item => item.classList.remove('is-selected'));
        row.closest('.wslib-v1-tablerow')?.classList.add('is-selected');

        const body = document.querySelector('[data-region="detail-body"]');
        const empty = document.querySelector('[data-region="detail-empty"]');
        if (!body) {
            return;
        }
        empty?.classList.add('d-none');

        const id = Number(row.dataset.item || 0);
        const name = escapeHtml(row.dataset.name);
        const kind = escapeHtml((row.dataset.kind || '').toUpperCase());
        const version = escapeHtml(row.dataset.version || '—');
        const state = escapeHtml(row.dataset.state || 'Chưa xuất bản');
        body.innerHTML = `
            <div class="wslib-preview-card">
                <span class="wslib-v1-kind wslib-kind-${escapeHtml(row.dataset.kind || '')}">${kind}</span>
                <h4>${name}</h4>
                <div class="wslib-preview-meta">
                    <div><small>Phiên bản</small><strong>${version}</strong></div>
                    <div><small>Trạng thái</small><strong>${state}</strong></div>
                </div>
                <a class="btn btn-primary btn-sm w-100" href="detail.php?itemid=${id}">Mở phiếu</a>
            </div>`;
    };

    const editorElement = () => document.querySelector('[data-region="native-editor"]');
    const currentNativeJson = () => latestNativeJson || editorElement()?._wslibNativeJson || null;
    const currentAssetUrls = () => {
        const mounted = editorElement()?._wslibAssetUrls || {};
        return {...mounted, ...latestAssetUrls};
    };

    const populatePublishPreview = () => {
        const target = document.querySelector('[data-region="publish-preview"]');
        const nativejson = currentNativeJson();
        if (!target || !nativejson) {
            return;
        }
        NativeEditor.renderPreview(nativejson, target, currentAssetUrls());
    };

    const init = () => {
        document.addEventListener('wslib:native-document', event => {
            if (event.detail?.nativejson) {
                latestNativeJson = event.detail.nativejson;
            }
            if (event.detail?.asseturls) {
                latestAssetUrls = {...event.detail.asseturls};
            }
        });

        document.querySelector('[data-action="new-item"]')?.addEventListener('click', () => openModal('create-item'));
        document.querySelector('[data-action="new-folder"]')?.addEventListener('click', () => openModal('create-folder'));
        document.querySelector('[data-action="open-publish"]')?.addEventListener('click', () => {
            populatePublishPreview();
            openModal('publish');
        });

        document.querySelectorAll('[data-close-modal]').forEach(button => {
            button.addEventListener('click', () => closeModal(button));
        });
        document.querySelectorAll('.wslib-modal').forEach(modal => {
            modal.addEventListener('click', event => {
                if (event.target === modal) {
                    closeModal(modal);
                }
            });
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                document.querySelectorAll('.wslib-modal.is-open').forEach(closeModal);
            }
        });

        document.querySelectorAll('.wslib-row').forEach(row => {
            row.addEventListener('click', () => selectRow(row));
        });

        document.querySelector('[data-publish-form]')?.addEventListener('submit', event => {
            const itemid = event.currentTarget.dataset.itemid || '';
            const versionid = event.currentTarget.dataset.versionid || '';
            try {
                sessionStorage.setItem('wslib-publish-pending', `${itemid}:${versionid}`);
            } catch (error) {
                // Storage can be unavailable in hardened browsers; publish itself must still proceed.
            }
        });

        const detailRoot = document.querySelector('[data-wslib-itemid]');
        if (detailRoot) {
            try {
                const pending = sessionStorage.getItem('wslib-publish-pending');
                const published = new Set((detailRoot.dataset.publishedVersions || '').split(',').filter(Boolean));
                if (pending) {
                    const [itemid, versionid] = pending.split(':');
                    if (itemid === detailRoot.dataset.wslibItemid && published.has(versionid)) {
                        sessionStorage.removeItem('wslib-publish-pending');
                        openModal('publish-success');
                    } else if (itemid === detailRoot.dataset.wslibItemid) {
                        sessionStorage.removeItem('wslib-publish-pending');
                    }
                }
            } catch (error) {
                // Ignore storage failures; the published state is still visible in the version inspector.
            }
        }

        document.querySelector('[data-action="copy-link"]')?.addEventListener('click', async event => {
            const button = event.currentTarget;
            try {
                await navigator.clipboard.writeText(window.location.href);
                const previous = button.textContent;
                button.textContent = 'Đã sao chép';
                window.setTimeout(() => { button.textContent = previous; }, 1600);
            } catch (error) {
                // Clipboard permission is browser-controlled; leave the button usable without throwing.
            }
        });
    };

    return {init, openModal, closeModal};
});
