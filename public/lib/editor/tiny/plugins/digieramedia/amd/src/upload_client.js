import Ajax from 'core/ajax';

const createUploadSession = async(config, file, replacemediauuid = '') => Ajax.call([{
    methodname: 'local_digieramedia_create_upload_session',
    args: {
        contextid: config.contextid,
        filename: file.name,
        mimetype: file.type || 'application/octet-stream',
        filesize: file.size,
        replacemediauuid,
    },
}])[0];

const finalizeUpload = async(config, sessionuuid) => Ajax.call([{
    methodname: 'local_digieramedia_finalize_upload',
    args: {contextid: config.contextid, sessionuuid},
}])[0];

const putFile = (file, session, onProgress) => new Promise((resolve, reject) => {
    const request = new XMLHttpRequest();
    request.open('PUT', session.uploadurl, true);
    (session.requiredheaders || []).forEach((header) => request.setRequestHeader(header.name, header.value));
    request.upload.addEventListener('progress', (event) => {
        if (event.lengthComputable && typeof onProgress === 'function') {
            onProgress(event.loaded, event.total);
        }
    });
    request.addEventListener('load', () => {
        if (request.status >= 200 && request.status < 300) {
            resolve();
            return;
        }
        reject(new Error(`R2 PUT failed with HTTP ${request.status}`));
    });
    request.addEventListener('error', () => reject(new Error('Không thể kết nối Cloudflare R2. Kiểm tra CORS/mạng.')));
    request.addEventListener('abort', () => reject(new Error('Upload đã bị hủy.')));
    request.send(file);
});

export const uploadFile = async(config, file, onProgress = null, replacemediauuid = '') => {
    const session = await createUploadSession(config, file, replacemediauuid);
    if (session.uploadtype !== 'single') {
        throw new Error('Máy chủ yêu cầu multipart nhưng RC hiện tại chưa bật multipart.');
    }
    await putFile(file, session, onProgress);
    return finalizeUpload(config, session.sessionuuid);
};
