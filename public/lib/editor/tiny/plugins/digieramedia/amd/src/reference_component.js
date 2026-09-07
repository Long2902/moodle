const markerPattern = /\[\[digiera-ref:([0-9a-fA-F-]{36})\]\]/g;
const componentPattern = /<span\b[^>]*data-digiera-reference-uuid=["']([0-9a-fA-F-]{36})["'][^>]*>[\s\S]*?<\/span>/gi;

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

export const marker = (uuid) => `[[digiera-ref:${uuid}]]`;

export const componentHtml = ({referenceuuid, name, mediatype}) => (
    `<span class="digiera-media-reference" contenteditable="false" ` +
    `data-digiera-reference-uuid="${escapeHtml(referenceuuid)}" ` +
    `data-digiera-media-type="${escapeHtml(mediatype)}">` +
    `<strong>Học liệu DIGIERA:</strong> ${escapeHtml(name)}` +
    `</span>`
);

export const markersToComponents = (content) => String(content ?? '').replace(
    markerPattern,
    (match, uuid) => componentHtml({referenceuuid: uuid, name: 'Học liệu DIGIERA', mediatype: 'media'})
);

export const componentsToMarkers = (content) => String(content ?? '').replace(
    componentPattern,
    (match, uuid) => marker(uuid)
);

export const setup = (editor) => {
    editor.on('BeforeSetContent', (event) => {
        event.content = markersToComponents(event.content);
    });
    editor.on('PostProcess', (event) => {
        if (event.get) {
            event.content = componentsToMarkers(event.content);
        }
    });
};

export const insert = (editor, reference) => {
    editor.execCommand('mceInsertContent', false, componentHtml(reference));
};
