import Ajax from 'core/ajax';
import {getConfig} from './options';

const markerPattern = /\[\[digiera-ref:([0-9a-fA-F-]{36})\]\]/g;
const componentPattern = /<span\b[^>]*data-digiera-reference-uuid=["']([0-9a-fA-F-]{36})["'][^>]*>[\s\S]*?<\/span>/gi;

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

const resolveReferences = async(config, referenceuuids) => Ajax.call([{
    methodname: 'local_digieramedia_resolve_references',
    args: {contextid: config.contextid, referenceuuids},
}])[0];

const updateComponentLabel = (node, item) => {
    node.dataset.digieraMediaUuid = item.mediauuid || '';
    node.dataset.digieraMediaType = item.mediatype || 'media';
    node.dataset.digieraVersionMode = item.versionmode || 'FOLLOW_CURRENT';
    node.dataset.digieraPinnedVersionId = String(Number(item.pinnedversionid || 0));
    node.dataset.digieraHydrated = '1';
    node.innerHTML = `<strong>Học liệu DIGIERA:</strong> ${escapeHtml(item.name)}`;
};

const updateComponentError = (node, message) => {
    node.dataset.digieraHydrated = 'error';
    node.innerHTML = `<strong>Học liệu DIGIERA:</strong> ${escapeHtml(message)}`;
};

export const marker = (uuid) => `[[digiera-ref:${uuid}]]`;

export const componentHtml = ({
    referenceuuid,
    mediauuid = '',
    name,
    mediatype,
    versionmode = 'FOLLOW_CURRENT',
    pinnedversionid = 0,
}) => (
    `<span class="digiera-media-reference" contenteditable="false" ` +
    `data-digiera-reference-uuid="${escapeHtml(referenceuuid)}" ` +
    `data-digiera-media-uuid="${escapeHtml(mediauuid)}" ` +
    `data-digiera-media-type="${escapeHtml(mediatype)}" ` +
    `data-digiera-version-mode="${escapeHtml(versionmode)}" ` +
    `data-digiera-pinned-version-id="${Number(pinnedversionid || 0)}">` +
    `<strong>Học liệu DIGIERA:</strong> ${escapeHtml(name)}` +
    `</span>`
);

export const markersToComponents = (content) => String(content ?? '').replace(
    markerPattern,
    (match, uuid) => componentHtml({referenceuuid: uuid, name: 'Đang tải…', mediatype: 'media'})
);

export const componentsToMarkers = (content) => String(content ?? '').replace(
    componentPattern,
    (match, uuid) => marker(uuid)
);

export const getSelectedReference = (editor) => {
    const node = editor.selection.getNode();
    const component = node?.closest?.('.digiera-media-reference[data-digiera-reference-uuid]');
    if (!component) {
        return null;
    }
    return {
        referenceuuid: component.dataset.digieraReferenceUuid || '',
        mediauuid: component.dataset.digieraMediaUuid || '',
        mediatype: component.dataset.digieraMediaType || 'media',
        versionmode: component.dataset.digieraVersionMode || 'FOLLOW_CURRENT',
        pinnedversionid: Number(component.dataset.digieraPinnedVersionId || 0),
    };
};

export const updateReferenceState = (editor, reference) => {
    const body = editor.getBody();
    if (!body || !reference?.referenceuuid) {
        return;
    }
    const node = body.querySelector(
        `.digiera-media-reference[data-digiera-reference-uuid="${reference.referenceuuid}"]`
    );
    if (node) {
        updateComponentLabel(node, reference);
    }
};

export const hydrateReferenceLabels = async(editor) => {
    const config = getConfig(editor);
    if (!config.enabled || !config.contextid) {
        return;
    }

    const body = editor.getBody();
    if (!body) {
        return;
    }

    const nodes = [...body.querySelectorAll('.digiera-media-reference[data-digiera-reference-uuid]')];
    if (!nodes.length) {
        return;
    }

    const referenceuuids = [...new Set(nodes
        .map((node) => node.dataset.digieraReferenceUuid)
        .filter(Boolean))];

    if (!referenceuuids.length) {
        return;
    }

    try {
        const items = await resolveReferences(config, referenceuuids);
        const byUuid = new Map((items || []).map((item) => [item.referenceuuid, item]));

        nodes.forEach((node) => {
            const item = byUuid.get(node.dataset.digieraReferenceUuid);
            if (item) {
                updateComponentLabel(node, item);
            } else {
                updateComponentError(node, 'Không tìm thấy học liệu');
            }
        });
    } catch (error) {
        nodes.forEach((node) => updateComponentError(node, 'Không tải được thông tin học liệu'));
    }
};

export const setup = (editor) => {
    editor.on('BeforeSetContent', (event) => {
        event.content = markersToComponents(event.content);
    });
    editor.on('SetContent', () => {
        void hydrateReferenceLabels(editor);
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
