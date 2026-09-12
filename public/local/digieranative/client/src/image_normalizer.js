const DEFAULT_MAX_DIMENSION = 2560;
const DEFAULT_JPEG_QUALITY = 0.85;

function loadHtmlImage(file) {
    return new Promise((resolve, reject) => {
        const objectUrl = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () => resolve({image, objectUrl});
        image.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            reject(new Error('Không thể giải mã ảnh để thử tải lại.'));
        };
        image.src = objectUrl;
    });
}

function canvasToBlob(canvas, quality) {
    return new Promise((resolve, reject) => {
        canvas.toBlob(blob => {
            if (!blob) {
                reject(new Error('Không thể chuẩn hóa ảnh để thử tải lại.'));
                return;
            }
            resolve(blob);
        }, 'image/jpeg', quality);
    });
}

export async function normalizeImageForUpload(file, options = {}) {
    if (!file || typeof file.size !== 'number' || file.size <= 0) {
        throw new TypeError('Image file is required');
    }

    const maxDimension = Number.isFinite(Number(options.maxDimension))
        ? Math.max(640, Math.min(4096, Number(options.maxDimension)))
        : DEFAULT_MAX_DIMENSION;
    const quality = Number.isFinite(Number(options.quality))
        ? Math.max(0.6, Math.min(0.95, Number(options.quality)))
        : DEFAULT_JPEG_QUALITY;

    let source = null;
    let objectUrl = null;
    let bitmap = null;

    try {
        if (typeof globalThis.createImageBitmap === 'function') {
            bitmap = await globalThis.createImageBitmap(file);
            source = bitmap;
        } else {
            const loaded = await loadHtmlImage(file);
            source = loaded.image;
            objectUrl = loaded.objectUrl;
        }

        const sourceWidth = Number(source.width || source.naturalWidth || 0);
        const sourceHeight = Number(source.height || source.naturalHeight || 0);
        if (sourceWidth <= 0 || sourceHeight <= 0) {
            throw new Error('Kích thước ảnh không hợp lệ.');
        }

        const scale = Math.min(1, maxDimension / Math.max(sourceWidth, sourceHeight));
        const width = Math.max(1, Math.round(sourceWidth * scale));
        const height = Math.max(1, Math.round(sourceHeight * scale));
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;

        const context = canvas.getContext('2d');
        if (!context) {
            throw new Error('Trình duyệt không hỗ trợ chuẩn hóa ảnh.');
        }

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, width, height);
        context.drawImage(source, 0, 0, width, height);

        const blob = await canvasToBlob(canvas, quality);
        const originalName = typeof file.name === 'string' && file.name ? file.name : 'image';
        const stem = originalName.replace(/\.[^.]*$/, '') || 'image';
        return new File([blob], `${stem}-normalized.jpg`, {
            type: 'image/jpeg',
            lastModified: Date.now(),
        });
    } finally {
        if (bitmap && typeof bitmap.close === 'function') {
            bitmap.close();
        }
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
        }
    }
}
