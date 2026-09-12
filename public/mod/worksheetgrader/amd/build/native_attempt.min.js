define(['core/ajax', 'local_digieranative/native_editor'], function(Ajax, NativeEditor) {
    const labels = {
        saving: 'Đang lưu…',
        saved: 'Đã lưu',
        conflict: 'Xung đột phiên bản',
        error: 'Lỗi khi lưu',
        readonly: 'Chỉ xem',
        upload: 'Đang tải ảnh…',
    };

    const init = config => {
        const element = document.getElementById(config.elementid);
        const status = document.getElementById(config.statusid);
        const form = config.formid ? document.getElementById(config.formid) : null;
        if (!element || !status) {
            return;
        }

        const source = JSON.parse(config.nativejson);
        const canedit = Boolean(config.canedit);
        const assetUrls = {...(config.asseturls || {})};
        let editor = null;
        let submitting = false;

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

        const isUpstreamHttp400 = response => {
            const contentType = (response.headers.get('content-type') || '').toLowerCase();
            return response.status === 400 && !contentType.includes('application/json');
        };

        const loadAssetUrls = async () => {
            const url = `${M.cfg.wwwroot}/mod/worksheetgrader/native_asset.php?attemptid=${Number(config.attemptid)}&sesskey=${encodeURIComponent(M.cfg.sesskey)}`;
            const response = await fetch(url, {credentials: 'same-origin'});
            const result = await parseJsonResponse(response, 'Không thể tải danh sách ảnh');
            Object.assign(assetUrls, result.asseturls || {});
            editor?.refreshAssetPreviews?.(assetUrls);
            return assetUrls;
        };

        if (!canedit) {
            editor = NativeEditor.mount({
                element,
                documentJson: source,
                readonly: true,
                collaboration: false,
                save: null,
                print: () => window.print(),
                assetUrls,
            });
            editor.refreshAssetPreviews?.(assetUrls);
            loadAssetUrls().catch(error => {
                console.warn('DIGIERA Native attempt asset lookup failed', error);
            });
            setStatus('readonly');
            return;
        }

        const saveRemote = ({nativejson, revision}) => Ajax.call([{
            methodname: 'mod_worksheetgrader_save_attempt',
            args: {
                attemptid: Number(config.attemptid),
                answersjson: nativejson,
                version: Number(revision),
            },
        }])[0];

        const controller = NativeEditor.createAutosaveController({
            initialRevision: Number(config.version) || 1,
            save: saveRemote,
            onStatus: setStatus,
        });

        const canonicalJson = canonical => JSON.stringify(
            canonical || editor?.getNativeJSON?.() || source
        );

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

            const sendAsset = async uploadFile => {
                const body = new FormData();
                body.append('sesskey', M.cfg.sesskey);
                body.append('attemptid', String(config.attemptid));
                body.append('action', action);
                if (assetKey) {
                    body.append('assetkey', assetKey);
                }
                body.append('image', uploadFile, uploadFile.name);
                return fetch(`${M.cfg.wwwroot}/mod/worksheetgrader/native_asset.php`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body,
                });
            };

            try {
                let response = await sendAsset(file);
                if (isUpstreamHttp400(response)) {
                    // Retry exactly once after browser normalization to remove metadata/body signatures
                    // that can trigger an upstream WAF while preserving Moodle's JSON 400 responses.
                    const normalizedFile = await NativeEditor.normalizeImageForUpload(file);
                    if (normalizedFile.size > 5 * 1024 * 1024) {
                        throw new Error('Ảnh sau khi chuẩn hóa vẫn vượt quá 5 MiB.');
                    }
                    response = await sendAsset(normalizedFile);
                }
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

        editor = NativeEditor.mount({
            element,
            documentJson: source,
            readonly: false,
            collaboration: false,
            assetUrls,
            imageAdapter,
            save: canonical => controller.saveNow({nativejson: canonicalJson(canonical)}),
            print: () => window.print(),
            requestMath,
            onUpdate: canonical => {
                if (!controller.blocked()) {
                    controller.schedule({nativejson: canonicalJson(canonical)});
                }
            },
        });

        editor.refreshAssetPreviews?.(assetUrls);
        loadAssetUrls().catch(error => {
            console.warn('DIGIERA Native attempt asset lookup failed', error);
        });

        if (form) {
            form.addEventListener('submit', async event => {
                if (submitting) {
                    return;
                }
                event.preventDefault();
                try {
                    const result = await controller.saveNow({nativejson: canonicalJson()});
                    if (!result || result.conflict || result.blocked || result.ok !== true) {
                        setStatus('conflict');
                        return;
                    }
                    submitting = true;
                    form.submit();
                } catch (error) {
                    setStatus('error');
                }
            });
        }
        setStatus('saved');
    };

    return {init};
});
