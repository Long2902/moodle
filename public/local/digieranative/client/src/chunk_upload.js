const DEFAULT_CHUNK_SIZE = 64 * 1024;

function randomUploadId() {
    const bytes = new Uint8Array(16);
    if (globalThis.crypto?.getRandomValues) {
        globalThis.crypto.getRandomValues(bytes);
    } else {
        for (let index = 0; index < bytes.length; index++) {
            bytes[index] = Math.floor(Math.random() * 256);
        }
    }
    return Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('');
}

export async function uploadFileInChunks(file, sendChunk, options = {}) {
    if (!file || typeof file.size !== 'number' || file.size <= 0) {
        throw new TypeError('Image file is required');
    }
    if (typeof sendChunk !== 'function') {
        throw new TypeError('Chunk sender is required');
    }

    const requestedChunkSize = Number(options.chunkSize || DEFAULT_CHUNK_SIZE);
    const chunkSize = Number.isFinite(requestedChunkSize) && requestedChunkSize > 0
        ? Math.min(DEFAULT_CHUNK_SIZE, Math.floor(requestedChunkSize))
        : DEFAULT_CHUNK_SIZE;
    const chunkTotal = Math.ceil(file.size / chunkSize);
    const uploadId = typeof options.uploadId === 'string' && /^[a-f0-9]{32}$/i.test(options.uploadId)
        ? options.uploadId.toLowerCase()
        : randomUploadId();

    let finalResult = null;
    for (let chunkIndex = 0; chunkIndex < chunkTotal; chunkIndex++) {
        const start = chunkIndex * chunkSize;
        const end = Math.min(file.size, start + chunkSize);
        const chunk = file.slice(start, end, 'application/octet-stream');
        const result = await sendChunk({
            uploadId,
            chunkIndex,
            chunkTotal,
            fileSize: file.size,
            fileName: file.name || 'image',
            fileType: file.type || '',
            chunk,
        });
        if (!result || result.ok !== true) {
            throw new Error('Máy chủ không xác nhận phần ảnh đã tải lên.');
        }
        if (chunkIndex < chunkTotal - 1 && result.complete === true) {
            throw new Error('Máy chủ kết thúc upload ảnh quá sớm.');
        }
        finalResult = result;
    }

    if (!finalResult || finalResult.complete !== true) {
        throw new Error('Upload ảnh theo phần chưa hoàn tất.');
    }
    return finalResult;
}

export {DEFAULT_CHUNK_SIZE};
