import {LEGACY_IMAGE_RUNTIME_DEFAULTS} from './native_schema_extensions.js';

const REQUIRED_METHODS = ['createAsset', 'replaceAsset', 'resolvePreview', 'onAssetRecord'];

function assetKeyOf(asset) {
    const key = asset?.assetKey ?? asset?.image_id ?? asset?.id;
    if (typeof key !== 'string' || key === '') {
        throw new TypeError('ImageAdapter asset must expose a stable assetKey');
    }
    return key;
}

export function assertImageAdapter(adapter) {
    if (adapter === null || adapter === undefined) {
        return null;
    }
    if (typeof adapter !== 'object' || Array.isArray(adapter)) {
        throw new TypeError('imageAdapter must be an object');
    }
    for (const method of REQUIRED_METHODS) {
        if (typeof adapter[method] !== 'function') {
            throw new TypeError(`imageAdapter.${method} must be a function`);
        }
    }
    return adapter;
}

export function normalizePictureEdits(edits = {}) {
    return {
        cropX: Number.isFinite(edits.cropX) ? edits.cropX : LEGACY_IMAGE_RUNTIME_DEFAULTS.cropX,
        cropY: Number.isFinite(edits.cropY) ? edits.cropY : LEGACY_IMAGE_RUNTIME_DEFAULTS.cropY,
        cropW: Number.isFinite(edits.cropW) ? edits.cropW : LEGACY_IMAGE_RUNTIME_DEFAULTS.cropW,
        cropH: Number.isFinite(edits.cropH) ? edits.cropH : LEGACY_IMAGE_RUNTIME_DEFAULTS.cropH,
        rotation: [0, 90, 180, 270].includes(edits.rotation) ? edits.rotation : LEGACY_IMAGE_RUNTIME_DEFAULTS.rotation,
        widthPercent: Number.isInteger(edits.widthPercent) ? edits.widthPercent : LEGACY_IMAGE_RUNTIME_DEFAULTS.widthPercent,
        align: ['left', 'center', 'right'].includes(edits.align) ? edits.align : LEGACY_IMAGE_RUNTIME_DEFAULTS.align,
        alt: typeof edits.alt === 'string' ? edits.alt : '',
        caption: typeof edits.caption === 'string' ? edits.caption : '',
    };
}

export function topLevelInsertPosition(editor, capturedPos) {
    const max = editor.state.doc.content.size;
    const safe = Math.max(0, Math.min(Number.isInteger(capturedPos) ? capturedPos : editor.state.selection.from, max));
    const resolved = editor.state.doc.resolve(safe);
    if (resolved.depth === 0) {
        return safe;
    }
    return resolved.after(1);
}

export async function insertPictureAsset(editor, adapter, {capturedPos, file, edits = {}}) {
    const imageAdapter = assertImageAdapter(adapter);
    if (!imageAdapter) {
        throw new Error('ImageAdapter chưa sẵn sàng.');
    }
    if (!(file instanceof editor.view.dom.ownerDocument.defaultView.File) && !(typeof File !== 'undefined' && file instanceof File)) {
        throw new TypeError('Picture file is required');
    }
    const asset = await imageAdapter.createAsset(file);
    const assetKey = assetKeyOf(asset);
    imageAdapter.onAssetRecord(asset);
    const attrs = {
        assetKey,
        title: null,
        width: null,
        ...normalizePictureEdits(edits),
    };
    const position = topLevelInsertPosition(editor, capturedPos);
    const inserted = editor.chain().focus().insertContentAt(position, {type: 'image', attrs}, {updateSelection: true}).run();
    if (!inserted) {
        throw new Error('Không thể chèn ảnh tại vị trí con trỏ đã lưu.');
    }
    try {
        const preview = await imageAdapter.resolvePreview(assetKey);
        const url = typeof preview === 'string' ? preview : preview?.url;
        if (url && typeof editor.refreshAssetPreviews === 'function') {
            editor.refreshAssetPreviews({[assetKey]: url});
        }
    } catch {
        // Asset persistence succeeded. Preview resolution can be retried by the bridge.
    }
    return {assetKey, asset, attrs};
}

export async function updatePictureAsset(editor, adapter, assetKey, edits = {}, file = null) {
    const imageAdapter = assertImageAdapter(adapter);
    if (!imageAdapter) {
        throw new Error('ImageAdapter chưa sẵn sàng.');
    }
    let effectiveKey = assetKey;
    if (file) {
        const asset = await imageAdapter.replaceAsset(assetKey, file);
        effectiveKey = assetKeyOf(asset || {assetKey});
        if (asset) {
            imageAdapter.onAssetRecord(asset);
        }
    }

    let found = null;
    editor.state.doc.descendants((node, pos) => {
        if (!found && node.type.name === 'image' && node.attrs.assetKey === assetKey) {
            found = {node, pos};
            return false;
        }
        return true;
    });
    if (!found) {
        throw new Error(`Không tìm thấy ảnh ${assetKey}.`);
    }

    const attrs = {
        ...found.node.attrs,
        ...normalizePictureEdits({...found.node.attrs, ...edits}),
        assetKey: effectiveKey,
        title: found.node.attrs.title ?? null,
        width: found.node.attrs.width ?? null,
        _nativeLegacyAttrs: null,
    };
    const transaction = editor.state.tr.setNodeMarkup(found.pos, undefined, attrs);
    editor.view.dispatch(transaction);

    if (file) {
        try {
            const preview = await imageAdapter.resolvePreview(effectiveKey);
            const url = typeof preview === 'string' ? preview : preview?.url;
            if (url && typeof editor.refreshAssetPreviews === 'function') {
                editor.refreshAssetPreviews({[effectiveKey]: url});
            }
        } catch {
            // Persistence remains authoritative; preview can retry later.
        }
    }
    return {assetKey: effectiveKey, attrs};
}
