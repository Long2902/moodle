define(['core/ajax', 'local_digieranative/native_editor'], function(Ajax, NativeEditor) {
    const labels = {
        saving: 'Đang lưu…',
        saved: 'Đã lưu',
        conflict: 'Xung đột phiên bản',
        error: 'Lỗi khi lưu',
        readonly: 'Chỉ xem',
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
            setStatus('readonly');
            return;
        }

        const saveRemote = ({nativejson, revision}) => Ajax.call([{
            methodname: 'mod_worksheetgrader_save_attempt',
            args: {attemptid: Number(config.attemptid), answersjson: nativejson, version: Number(revision)},
        }])[0];
        const controller = NativeEditor.createAutosaveController({
            initialRevision: Number(config.version) || 1,
            save: saveRemote,
            onStatus: setStatus,
        });
        const canonicalJson = canonical => JSON.stringify(
            canonical || editor?.getNativeJSON?.() || NativeEditor.toNativeDocument(
                editor.getJSON(),
                {version: source.version || 1, meta: source.meta}
            )
        );

        const uploadImage = async ({insert}) => {
            const file = await pickImage(element.ownerDocument);
            if (!file) {
                return;
            }
            try {
                setStatus('upload');
                const body = new FormData();
                body.append('sesskey', M.cfg.sesskey);
                body.append('attemptid', String(config.attemptid));
                body.append('image', file, file.name);
                const response = await fetch(`${M.cfg.wwwroot}/mod/worksheetgrader/native_asset.php`, {
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

        editor = NativeEditor.mount({
            element,
            documentJson: source,
            readonly: false,
            collaboration: false,
            assetUrls,
            save: canonical => controller.saveNow({nativejson: canonicalJson(canonical)}),
            print: () => window.print(),
            uploadImage,
            requestMath,
            onUpdate: canonical => {
                if (!controller.blocked()) {
                    controller.schedule({nativejson: canonicalJson(canonical)});
                }
            },
        });
        editor.refreshAssetPreviews?.(assetUrls);

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
