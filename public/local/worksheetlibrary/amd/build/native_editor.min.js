define(['core/ajax', 'local_digieranative/native_editor'], function(Ajax, NativeEditor) {
    const labels = {
        saving: 'Đang lưu…',
        saved: 'Đã lưu',
        conflict: 'Xung đột phiên bản',
        error: 'Lỗi khi lưu',
        upload: 'Đang tải ảnh…',
    };

    const pickImage = document => new Promise(resolve => {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/png,image/jpeg,image/webp';
        input.hidden = true;
        document.body.append(input);
        const finish = file => {
            input.remove();
            resolve(file || null);
        };
        input.addEventListener('change', () => finish(input.files?.[0] || null), {once: true});
        input.addEventListener('cancel', () => finish(null), {once: true});
        input.click();
    });

    const init = config => {
        const element = document.getElementById(config.elementid);
        const status = document.getElementById(config.statusid);
        if (!element || !status) {
            return;
        }

        const source = JSON.parse(config.nativejson);
        const assetUrls = {...(config.asseturls || {})};
        let editor = null;
        const setStatus = state => {
            status.dataset.state = state;
            status.textContent = labels[state] || state;
        };
        const failVisible = message => {
            setStatus('error');
            window.alert(message || 'Không thể hoàn thành thao tác.');
        };
        const saveRemote = ({nativejson, revision}) => Ajax.call([{
            methodname: 'local_worksheetlibrary_save_native_draft',
            args: {versionid: Number(config.versionid), expectedrevision: Number(revision), nativejson},
        }])[0];
        const controller = NativeEditor.createAutosaveController({
            initialRevision: Number(config.revision) || 0,
            save: saveRemote,
            onStatus: setStatus,
        });
        const canonicalJson = canonical => JSON.stringify(canonical || editor?.getNativeJSON?.() || source);
        const publishCanonical = canonical => {
            element._wslibNativeJson = canonical;
            element._wslibAssetUrls = {...assetUrls};
            const EventClass = element.ownerDocument.defaultView.CustomEvent;
            element.dispatchEvent(new EventClass('wslib:native-document', {
                bubbles: true,
                detail: {nativejson: canonical, asseturls: {...assetUrls}},
            }));
        };
        const loadAssetUrls = async () => {
            try {
                const url = `${M.cfg.wwwroot}/local/worksheetlibrary/native_asset.php?versionid=${Number(config.versionid)}&sesskey=${encodeURIComponent(M.cfg.sesskey)}`;
                const response = await fetch(url, {credentials: 'same-origin'});
                const result = await response.json();
                if (!response.ok || !result.ok) {
                    throw new Error(result.error || 'Không thể tải danh sách ảnh');
                }
                Object.assign(assetUrls, result.asseturls || {});
                element._wslibAssetUrls = {...assetUrls};
                editor?.refreshAssetPreviews?.(assetUrls);
            } catch (error) {
                // Existing text editing must remain available even if asset preview lookup fails.
                console.warn('DIGIERA Native asset lookup failed', error);
            }
        };

        const uploadImage = async ({insert}) => {
            const file = await pickImage(element.ownerDocument);
            if (!file) {
                return;
            }
            try {
                setStatus('upload');
                const body = new FormData();
                body.append('sesskey', M.cfg.sesskey);
                body.append('versionid', String(config.versionid));
                body.append('image', file, file.name);
                const response = await fetch(`${M.cfg.wwwroot}/local/worksheetlibrary/native_asset.php`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body,
                });
                const result = await response.json();
                if (!response.ok || !result.ok) {
                    throw new Error(result.error || 'Tải ảnh thất bại');
                }
                assetUrls[result.assetKey] = result.url;
                insert({assetKey: result.assetKey, alt: result.alt || file.name, title: null, width: null, align: null});
                editor?.refreshAssetPreviews?.(assetUrls);
                element._wslibAssetUrls = {...assetUrls};
                setStatus('saved');
            } catch (error) {
                failVisible(error.message || 'Tải ảnh thất bại');
            }
        };

        const requestMath = ({insert}) => {
            const value = window.prompt('Nhập công thức (ví dụ: x^2 + y^2 = z^2)', '');
            if (value === null) {
                return;
            }
            const sourceText = value.trim();
            if (!sourceText) {
                failVisible('Công thức không được để trống.');
                return;
            }
            insert({source: sourceText});
        };

        const saveNow = canonical => controller.saveNow({nativejson: canonicalJson(canonical)});
        const downloadPdf = async () => {
            try {
                const result = await saveNow();
                if (result?.conflict || result?.blocked || result?.ok === false) {
                    failVisible('Không thể tải PDF vì bản nháp chưa lưu thành công.');
                    return;
                }
                window.location.assign(`${M.cfg.wwwroot}/local/worksheetlibrary/pdf.php?versionid=${Number(config.versionid)}`);
            } catch (error) {
                failVisible(error.message || 'Không thể chuẩn bị PDF.');
            }
        };

        publishCanonical(source);
        editor = NativeEditor.mount({
            element,
            documentJson: source,
            readonly: false,
            collaboration: false,
            assetUrls,
            save: saveNow,
            print: () => window.print(),
            downloadPdf,
            uploadImage,
            requestMath,
            onUpdate: canonical => {
                publishCanonical(canonical);
                if (!controller.blocked()) {
                    controller.schedule({nativejson: canonicalJson(canonical)});
                }
            },
        });
        editor.refreshAssetPreviews?.(assetUrls);
        loadAssetUrls();
        setStatus('saved');
    };

    return {init};
});
