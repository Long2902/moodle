import Ajax from 'core/ajax';
import Notification from 'core/notification';

/** Autosave text/checkbox fields and preview inline image files locally. */
export const init = config => {
    const form = document.getElementById('wsg-attempt-form');
    if (!form) {
        return;
    }
    let version = Number(config.version || 1);
    let dirty = false;
    let filesDirty = false;
    let saving = false;

    form.querySelectorAll('[data-wsg-code]').forEach(element => {
        element.addEventListener('input', () => { dirty = true; });
        element.addEventListener('change', () => { dirty = true; });
    });

    form.querySelectorAll('[data-wsg-image-input]').forEach(input => input.addEventListener('change', () => {
        filesDirty = Boolean(input.files?.length) || filesDirty;
        const file = input.files?.[0];
        const panel = input.closest('.wsg-image-upload');
        const preview = panel?.querySelector('.wsg-image-preview');
        if (!file || !preview) {
            return;
        }
        const url = URL.createObjectURL(file);
        preview.innerHTML = '';
        const image = document.createElement('img');
        image.className = 'wsg-uploaded-image';
        image.src = url;
        image.alt = `Ảnh vừa chọn ${input.dataset.wsgImageInput}`;
        image.addEventListener('load', () => URL.revokeObjectURL(url), {once: true});
        preview.appendChild(image);
        Notification.addNotification({
            message: 'Ảnh đã được chọn. Bấm “Lưu bài” để tải ảnh lên Moodle.',
            type: 'info',
        });
    }));

    const collect = () => {
        const result = {};
        form.querySelectorAll('[data-wsg-code]').forEach(element => {
            result[element.dataset.wsgCode] = element.type === 'checkbox'
                ? (element.checked ? '1' : '0')
                : element.value;
        });
        return result;
    };

    const save = async() => {
        if (!dirty || saving) {
            return;
        }
        saving = true;
        try {
            const requests = Ajax.call([{
                methodname: 'mod_worksheetgrader_save_attempt',
                args: {attemptid: config.attemptid, answersjson: JSON.stringify(collect()), version},
            }]);
            const result = await requests[0];
            version = Number(result.version);
            dirty = false;
        } catch (error) {
            Notification.exception(error);
        } finally {
            saving = false;
        }
    };

    window.setInterval(save, Math.max(5000, Number(config.interval || 10000)));
    window.addEventListener('beforeunload', event => {
        if (dirty || filesDirty) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    form.addEventListener('submit', () => { filesDirty = false; dirty = false; });
};
