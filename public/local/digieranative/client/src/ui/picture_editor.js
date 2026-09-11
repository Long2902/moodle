import React from 'react';

const h = React.createElement;

const DEFAULTS = Object.freeze({
    cropX: 0,
    cropY: 0,
    cropW: 1,
    cropH: 1,
    rotation: 0,
    widthPercent: 100,
    align: 'center',
    alt: '',
    caption: '',
});

function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
}

function normalize(initial = {}) {
    const next = {...DEFAULTS, ...(initial || {})};
    next.cropX = clamp(Number(next.cropX) || 0, 0, 1);
    next.cropY = clamp(Number(next.cropY) || 0, 0, 1);
    next.cropW = clamp(Number(next.cropW) || 1, 0.01, 1 - next.cropX);
    next.cropH = clamp(Number(next.cropH) || 1, 0.01, 1 - next.cropY);
    next.rotation = [0, 90, 180, 270].includes(Number(next.rotation)) ? Number(next.rotation) : 0;
    next.widthPercent = clamp(Number.parseInt(next.widthPercent, 10) || 100, 10, 100);
    next.align = ['left', 'center', 'right'].includes(next.align) ? next.align : 'center';
    next.alt = typeof next.alt === 'string' ? next.alt : '';
    next.caption = typeof next.caption === 'string' ? next.caption : '';
    return next;
}

function percentInput(label, value, onChange, max = 100) {
    return h('label', {className: 'dgn-picture-field', key: label}, [
        h('span', {key: 'label'}, label),
        h('input', {
            key: 'input',
            type: 'number',
            min: 0,
            max,
            step: 1,
            value: Math.round(value * 100),
            onChange: event => onChange(Number(event.target.value) / 100),
        }),
    ]);
}

export function PictureEditorDialog({
    open = false,
    file = null,
    previewUrl = '',
    initial = DEFAULTS,
    busy = false,
    error = '',
    onCancel,
    onSubmit,
}) {
    const [value, setValue] = React.useState(() => normalize(initial));
    const [objectUrl, setObjectUrl] = React.useState('');

    React.useEffect(() => {
        setValue(normalize(initial));
    }, [initial]);

    React.useEffect(() => {
        if (!file || typeof URL === 'undefined' || typeof URL.createObjectURL !== 'function') {
            setObjectUrl('');
            return undefined;
        }
        const url = URL.createObjectURL(file);
        setObjectUrl(url);
        return () => URL.revokeObjectURL(url);
    }, [file]);

    if (!open) {
        return null;
    }

    const source = objectUrl || previewUrl;
    const patch = next => setValue(current => normalize({...current, ...next}));
    const imageStyle = {
        width: `${value.widthPercent}%`,
        maxWidth: '100%',
        transform: `rotate(${value.rotation}deg)`,
        clipPath: `inset(${value.cropY * 100}% ${(1 - value.cropX - value.cropW) * 100}% ${(1 - value.cropY - value.cropH) * 100}% ${value.cropX * 100}%)`,
    };

    return h('div', {className: 'dgn-picture-dialog-backdrop', role: 'presentation'},
        h('div', {className: 'dgn-picture-dialog', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Chỉnh sửa ảnh'}, [
            h('div', {className: 'dgn-picture-dialog__header', key: 'header'}, [
                h('strong', {key: 'title'}, 'Chỉnh sửa ảnh'),
                h('button', {key: 'close', type: 'button', onClick: onCancel, disabled: busy, 'aria-label': 'Đóng'}, '×'),
            ]),
            h('div', {className: 'dgn-picture-dialog__body', key: 'body'}, [
                h('div', {className: 'dgn-picture-preview', key: 'preview'}, source
                    ? h('img', {src: source, alt: value.alt || '', style: imageStyle})
                    : h('div', {className: 'dgn-picture-preview__empty'}, 'Không có bản xem trước')),
                h('div', {className: 'dgn-picture-controls', key: 'controls'}, [
                    h('label', {className: 'dgn-picture-field', key: 'width'}, [
                        h('span', {key: 'label'}, `Độ rộng ${value.widthPercent}%`),
                        h('input', {
                            key: 'input', type: 'range', min: 10, max: 100, step: 5,
                            value: value.widthPercent,
                            onChange: event => patch({widthPercent: Number(event.target.value)}),
                        }),
                    ]),
                    h('div', {className: 'dgn-picture-button-row', key: 'rotation'}, [0, 90, 180, 270].map(rotation =>
                        h('button', {
                            key: rotation,
                            type: 'button',
                            className: value.rotation === rotation ? 'is-active' : '',
                            onClick: () => patch({rotation}),
                        }, `${rotation}°`))),
                    h('div', {className: 'dgn-picture-button-row', key: 'align'}, ['left', 'center', 'right'].map(align =>
                        h('button', {
                            key: align,
                            type: 'button',
                            className: value.align === align ? 'is-active' : '',
                            onClick: () => patch({align}),
                        }, align === 'left' ? 'Trái' : align === 'right' ? 'Phải' : 'Giữa'))),
                    h('fieldset', {className: 'dgn-picture-crop', key: 'crop'}, [
                        h('legend', {key: 'legend'}, 'Crop (%)'),
                        percentInput('X', value.cropX, cropX => patch({cropX, cropW: Math.min(value.cropW, 1 - cropX)})),
                        percentInput('Y', value.cropY, cropY => patch({cropY, cropH: Math.min(value.cropH, 1 - cropY)})),
                        percentInput('W', value.cropW, cropW => patch({cropW: Math.min(cropW, 1 - value.cropX)})),
                        percentInput('H', value.cropH, cropH => patch({cropH: Math.min(cropH, 1 - value.cropY)})),
                    ]),
                    h('label', {className: 'dgn-picture-field', key: 'alt'}, [
                        h('span', {key: 'label'}, 'Alt text'),
                        h('input', {key: 'input', type: 'text', maxLength: 2048, value: value.alt, onChange: event => patch({alt: event.target.value})}),
                    ]),
                    h('label', {className: 'dgn-picture-field', key: 'caption'}, [
                        h('span', {key: 'label'}, 'Chú thích'),
                        h('textarea', {key: 'input', maxLength: 2048, value: value.caption, onChange: event => patch({caption: event.target.value})}),
                    ]),
                    error ? h('div', {className: 'dgn-picture-error', role: 'alert', key: 'error'}, error) : null,
                ]),
            ]),
            h('div', {className: 'dgn-picture-dialog__footer', key: 'footer'}, [
                h('button', {key: 'cancel', type: 'button', onClick: onCancel, disabled: busy}, 'Hủy'),
                h('button', {key: 'apply', type: 'button', className: 'is-primary', onClick: () => onSubmit?.(normalize(value)), disabled: busy}, busy ? 'Đang lưu…' : 'Áp dụng'),
            ]),
        ]));
}

export {DEFAULTS as PICTURE_DEFAULTS};
