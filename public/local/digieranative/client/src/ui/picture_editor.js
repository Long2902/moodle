import React from 'react';
import ReactCrop from 'react-image-crop';

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

function toPercentCrop(value) {
    return {
        unit: '%',
        x: value.cropX * 100,
        y: value.cropY * 100,
        width: value.cropW * 100,
        height: value.cropH * 100,
    };
}

function cropPatch(percentCrop) {
    const x = clamp(Number(percentCrop?.x) || 0, 0, 99);
    const y = clamp(Number(percentCrop?.y) || 0, 0, 99);
    const width = clamp(Number(percentCrop?.width) || 100, 1, 100 - x);
    const height = clamp(Number(percentCrop?.height) || 100, 1, 100 - y);
    return {
        cropX: x / 100,
        cropY: y / 100,
        cropW: width / 100,
        cropH: height / 100,
    };
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
    }, [initial, open]);

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
    const percentCrop = toPercentCrop(value);
    const rotateLeft = () => patch({rotation: (value.rotation + 270) % 360});
    const rotateRight = () => patch({rotation: (value.rotation + 90) % 360});

    return h('div', {
        className: 'dgn-picture-dialog-backdrop',
        role: 'presentation',
        onMouseDown: event => {
            if (event.target === event.currentTarget && !busy) {
                onCancel?.();
            }
        },
    }, h('div', {
        className: 'dgn-picture-dialog',
        role: 'dialog',
        'aria-modal': 'true',
        'aria-label': 'Chỉnh sửa ảnh',
    }, [
        h('div', {className: 'dgn-picture-dialog__header', key: 'header'}, [
            h('div', {key: 'copy'}, [
                h('strong', {key: 'title'}, 'Chỉnh sửa ảnh'),
                h('div', {key: 'hint', className: 'dgn-picture-dialog__hint'}, 'Kéo khung để crop, sau đó chỉnh kích thước và căn ảnh.'),
            ]),
            h('button', {
                key: 'close', type: 'button', className: 'dgn-picture-icon-button',
                onClick: onCancel, disabled: busy, 'aria-label': 'Đóng',
            }, '×'),
        ]),
        h('div', {className: 'dgn-picture-dialog__body', key: 'body'}, [
            h('section', {className: 'dgn-picture-stage', key: 'stage'}, [
                source ? h('div', {className: 'dgn-picture-crop-stage', key: 'crop-stage'},
                    h(ReactCrop, {
                        crop: percentCrop,
                        minWidth: 5,
                        minHeight: 5,
                        keepSelection: true,
                        ruleOfThirds: true,
                        onChange: (_pixelCrop, nextPercentCrop) => patch(cropPatch(nextPercentCrop)),
                    }, h('img', {
                        src: source,
                        alt: value.alt || '',
                        className: 'dgn-picture-source',
                        style: {transform: `rotate(${value.rotation}deg)`},
                    }))) : h('div', {className: 'dgn-picture-preview__empty'}, 'Không có bản xem trước'),
                h('div', {className: 'dgn-picture-stage__summary', key: 'summary'}, [
                    h('span', {key: 'crop'}, `Crop ${Math.round(value.cropW * 100)} × ${Math.round(value.cropH * 100)}%`),
                    h('span', {key: 'rotate'}, `Xoay ${value.rotation}°`),
                ]),
            ]),
            h('aside', {className: 'dgn-picture-controls', key: 'controls'}, [
                h('div', {className: 'dgn-picture-control-section', key: 'rotate'}, [
                    h('span', {className: 'dgn-picture-control-title', key: 'title'}, 'Xoay'),
                    h('div', {className: 'dgn-picture-button-row', key: 'buttons'}, [
                        h('button', {key: 'left', type: 'button', onClick: rotateLeft, disabled: busy}, '↶ Trái'),
                        h('button', {key: 'right', type: 'button', onClick: rotateRight, disabled: busy}, 'Phải ↷'),
                        h('button', {key: 'reset', type: 'button', onClick: () => patch({rotation: 0}), disabled: busy}, '0°'),
                    ]),
                ]),
                h('div', {className: 'dgn-picture-control-section', key: 'size'}, [
                    h('span', {className: 'dgn-picture-control-title', key: 'title'}, 'Kích thước'),
                    h('label', {className: 'dgn-picture-field', key: 'width'}, [
                        h('span', {key: 'label'}, `Độ rộng ${value.widthPercent}%`),
                        h('input', {
                            key: 'input', type: 'range', min: 10, max: 100, step: 5,
                            value: value.widthPercent, disabled: busy,
                            onChange: event => patch({widthPercent: Number(event.target.value)}),
                        }),
                    ]),
                ]),
                h('div', {className: 'dgn-picture-control-section', key: 'align'}, [
                    h('span', {className: 'dgn-picture-control-title', key: 'title'}, 'Căn ảnh'),
                    h('div', {className: 'dgn-picture-button-row', key: 'buttons'}, ['left', 'center', 'right'].map(align =>
                        h('button', {
                            key: align,
                            type: 'button',
                            className: value.align === align ? 'is-active' : '',
                            'aria-pressed': value.align === align ? 'true' : 'false',
                            disabled: busy,
                            onClick: () => patch({align}),
                        }, align === 'left' ? 'Trái' : align === 'right' ? 'Phải' : 'Giữa'))),
                ]),
                h('div', {className: 'dgn-picture-control-section', key: 'text'}, [
                    h('label', {className: 'dgn-picture-field', key: 'alt'}, [
                        h('span', {key: 'label'}, 'Alt text'),
                        h('input', {
                            key: 'input', type: 'text', maxLength: 2048,
                            value: value.alt, disabled: busy,
                            onChange: event => patch({alt: event.target.value}),
                        }),
                    ]),
                    h('label', {className: 'dgn-picture-field', key: 'caption'}, [
                        h('span', {key: 'label'}, 'Chú thích'),
                        h('textarea', {
                            key: 'input', maxLength: 2048, rows: 3,
                            value: value.caption, disabled: busy,
                            onChange: event => patch({caption: event.target.value}),
                        }),
                    ]),
                ]),
                error ? h('div', {className: 'dgn-picture-error', role: 'alert', key: 'error'}, error) : null,
            ]),
        ]),
        h('div', {className: 'dgn-picture-dialog__footer', key: 'footer'}, [
            h('button', {key: 'cancel', type: 'button', className: 'dgn-picture-secondary', onClick: onCancel, disabled: busy}, 'Hủy'),
            h('button', {
                key: 'apply', type: 'button', className: 'dgn-picture-primary',
                onClick: () => onSubmit?.(normalize(value)), disabled: busy || !source,
            }, busy ? 'Đang lưu…' : 'Áp dụng'),
        ]),
    ]));
}

export {DEFAULTS as PICTURE_DEFAULTS};
