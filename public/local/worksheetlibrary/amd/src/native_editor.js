define(['core/ajax', 'local_digieranative/native_editor'], function(Ajax, NativeEditor) {
    const labels = {
        saving: 'Đang lưu…',
        saved: 'Đã lưu',
        conflict: 'Xung đột phiên bản',
        error: 'Lỗi khi lưu',
        upload: 'Đang tải ảnh…',
    };

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

        const parseJsonResponse = async (response, fallback) => {
            const contentType = (response.headers.get('content-type') || '').toLowerCase();
            const raw = await response.text();
            let result = null;
            if (contentType.includes('application/json')) {
                try {
                    result = JSON.parse(raw);
                } catch (error) {
                    // Fall through to a diagnostic message below.
                }
            }
            if (!result) {
                const clean = raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                if (response.status === 413) {
                    throw new Error('HTTP 413: ảnh vượt giới hạn upload của máy chủ/proxy.');
                }
                throw new Error(
                    `HTTP ${response.status || 0}: ${clean.slice(0, 240) || fallback}`
                );
            }
            if (!response.ok || result.ok !== true) {
                throw new Error(result.error || `${fallback} (HTTP ${response.status || 0})`);
            }
            return result;
        };

        const saveRemote = ({nativejson, revision}) => Ajax.call([{
            methodname: 'local_worksheetlibrary_save_native_draft',
            args: {
                versionid: Number(config.versionid),
                expectedrevision: Number(revision),
                nativejson,
            },
        }])[0];

        const controller = NativeEditor.createAutosaveController({
            initialRevision: Number(config.revision) || 0,
            save: saveRemote,
            onStatus: setStatus,
        });

        const canonicalJson = canonical => JSON.stringify(
            canonical || editor?.getNativeJSON?.() || source
        );

        const publishCanonical = canonical => {
            element._wslibNativeJson = canonical;
            element._wslibAssetUrls = {...assetUrls};
            const EventClass = element.ownerDocument.defaultView.CustomEvent;
            element.dispatchEvent(new EventClass('wslib:native-document', {
                bubbles: true,
                detail: {
                    nativejson: canonical,
                    asseturls: {...assetUrls},
                },
            }));
        };

        const loadAssetUrls = async () => {
            const url = `${M.cfg.wwwroot}/local/worksheetlibrary/native_asset.php?versionid=${Number(config.versionid)}&sesskey=${encodeURIComponent(M.cfg.sesskey)}`;
            const response = await fetch(url, {credentials: 'same-origin'});
            const result = await parseJsonResponse(response, 'Không thể tải danh sách ảnh');
            Object.assign(assetUrls, result.asseturls || {});
            element._wslibAssetUrls = {...assetUrls};
            editor?.refreshAssetPreviews?.(assetUrls);
            return assetUrls;
        };

        const postAsset = async (action, file, assetKey = null) => {
            setStatus('upload');
            if (!file || file.size <= 0) {
                setStatus('error');
                throw new Error('Tệp ảnh không hợp lệ.');
            }
            if (file.size > 5 * 1024 * 1024) {
                setStatus('error');
                throw new Error('Ảnh phải nhỏ hơn hoặc bằng 5 MiB.');
            }
            const body = new FormData();
            body.append('sesskey', M.cfg.sesskey);
            body.append('versionid', String(config.versionid));
            body.append('action', action);
            if (assetKey) {
                body.append('assetkey', assetKey);
            }
            body.append('image', file, file.name);

            const response = await fetch(`${M.cfg.wwwroot}/local/worksheetlibrary/native_asset.php`, {
                method: 'POST',
                credentials: 'same-origin',
                body,
            });
            try {
                return await parseJsonResponse(response, 'Tải ảnh thất bại');
            } catch (error) {
                setStatus('error');
                throw error;
            }
        };

        const imageAdapter = {
            createAsset: file => postAsset('create', file),
            replaceAsset: (assetKey, file) => postAsset('replace', file, assetKey),
            resolvePreview: async assetKey => {
                if (!assetUrls[assetKey]) {
                    await loadAssetUrls();
                }
                if (!assetUrls[assetKey]) {
                    throw new Error('Không tìm thấy ảnh đã lưu.');
                }
                return assetUrls[assetKey];
            },
            onAssetRecord: asset => {
                if (asset?.assetKey && asset?.url) {
                    assetUrls[asset.assetKey] = asset.url;
                    element._wslibAssetUrls = {...assetUrls};
                    editor?.refreshAssetPreviews?.(assetUrls);
                }
                setStatus('saved');
            },
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
        const saveBeforeOutput = async label => {
            const result = await saveNow();
            if (!result || result.conflict || result.blocked || result.ok !== true) {
                failVisible(`Không thể ${label} vì bản nháp chưa lưu thành công.`);
                return false;
            }
            return true;
        };

        const filenameFromDisposition = (header, fallback) => {
            const encoded = (header || '').match(/filename\*=UTF-8''([^;]+)/i);
            if (encoded) {
                try {
                    return decodeURIComponent(encoded[1]);
                } catch (error) {
                    // Fall back to the simple filename below.
                }
            }
            const plain = (header || '').match(/filename="?([^";]+)"?/i);
            return plain?.[1] ? decodeURIComponent(plain[1]) : fallback;
        };

        const downloadPdf = async () => {
            try {
                if (!await saveBeforeOutput('tải PDF')) {
                    return;
                }
                const response = await fetch(
                    `${M.cfg.wwwroot}/local/worksheetlibrary/pdf.php?versionid=${Number(config.versionid)}`,
                    {credentials: 'same-origin', cache: 'no-store'}
                );
                const contentType = (response.headers.get('content-type') || '').toLowerCase();
                if (!response.ok || !contentType.includes('application/pdf')) {
                    const body = await response.text();
                    const clean = body.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                    throw new Error(clean.slice(0, 240) || 'Máy chủ không tạo được PDF.');
                }
                const blob = await response.blob();
                const objectUrl = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = objectUrl;
                link.download = filenameFromDisposition(
                    response.headers.get('content-disposition'),
                    'worksheet.pdf'
                );
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.setTimeout(() => window.URL.revokeObjectURL(objectUrl), 1500);
                setStatus('saved');
            } catch (error) {
                failVisible(error.message || 'Không thể chuẩn bị PDF.');
            }
        };

        const printWorksheet = () => {
            const popup = window.open('', '_blank');
            if (!popup) {
                failVisible('Trình duyệt đang chặn cửa sổ in. Hãy cho phép popup cho trang LMS rồi thử lại.');
                return;
            }
            popup.opener = null;
            popup.document.open();
            popup.document.write('<!doctype html><meta charset="utf-8"><title>Đang chuẩn bị bản in</title><p style="font-family:Arial,sans-serif;padding:24px">Đang chuẩn bị bản in…</p>');
            popup.document.close();

            (async () => {
                try {
                    if (!await saveBeforeOutput('in')) {
                        popup.close();
                        return;
                    }
                    popup.location.replace(
                        `${M.cfg.wwwroot}/local/worksheetlibrary/print.php?versionid=${Number(config.versionid)}`
                    );
                    setStatus('saved');
                } catch (error) {
                    popup.close();
                    failVisible(error.message || 'Không thể chuẩn bị bản in.');
                }
            })();
        };

        publishCanonical(source);
        editor = NativeEditor.mount({
            element,
            documentJson: source,
            readonly: false,
            collaboration: false,
            assetUrls,
            imageAdapter,
            save: saveNow,
            print: printWorksheet,
            downloadPdf,
            requestMath,
            onUpdate: canonical => {
                publishCanonical(canonical);
                if (!controller.blocked()) {
                    controller.schedule({nativejson: canonicalJson(canonical)});
                }
            },
        });

        editor.refreshAssetPreviews?.(assetUrls);
        loadAssetUrls().catch(error => {
            console.warn('DIGIERA Native asset lookup failed', error);
        });
        setStatus('saved');
    };

    return {init};
});