define(['core/ajax', 'local_digieranative/native_editor'], function(Ajax, NativeEditor) {
    const labels = {
        saving: 'Đang lưu…',
        saved: 'Đã lưu',
        conflict: 'Xung đột phiên bản',
        error: 'Lỗi khi lưu',
        readonly: 'Chỉ xem',
    };

    const init = (config) => {
        const element = document.getElementById(config.elementid);
        const status = document.getElementById(config.statusid);
        const form = config.formid ? document.getElementById(config.formid) : null;
        if (!element || !status) {
            return;
        }

        const source = JSON.parse(config.nativejson);
        const canedit = Boolean(config.canedit);
        let view = null;
        let submitting = false;

        const setStatus = (state) => {
            status.dataset.state = state;
            status.textContent = labels[state] || state;
        };

        const canonicalJson = (canonical = null) => JSON.stringify(
            canonical || NativeEditor.toNativeDocument(
                view.state.doc.toJSON(),
                {version: source.version || 1, meta: source.meta},
            ),
        );

        if (!canedit) {
            view = NativeEditor.mount({
                element,
                documentJson: source,
                readonly: true,
                collaboration: false,
                save: null,
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

        view = NativeEditor.mount({
            element,
            documentJson: source,
            readonly: false,
            collaboration: false,
            save: (canonical) => controller.saveNow({nativejson: canonicalJson(canonical)}),
        });

        view.setProps({
            dispatchTransaction(transaction) {
                const nextState = view.state.apply(transaction);
                view.updateState(nextState);
                if (transaction.docChanged && !controller.blocked()) {
                    controller.schedule({nativejson: canonicalJson()});
                }
            },
        });

        if (form) {
            form.addEventListener('submit', async(event) => {
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
