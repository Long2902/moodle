import {describe, expect, it} from 'vitest';

import {fromNativeDocument, toNativeDocument} from '../src/document_adapter.js';

describe('Phase 5 legacy Native compatibility contract', () => {
    it('mounts old image nodes with safe runtime defaults without rewriting untouched canonical attrs', () => {
        const legacy = {
            type: 'worksheet',
            version: 1,
            content: [{
                type: 'image',
                attrs: {
                    assetKey: 'legacy_asset',
                    alt: 'Legacy',
                    title: null,
                    width: null,
                    align: null,
                },
            }],
        };

        const tiptap = fromNativeDocument(legacy);
        const image = tiptap.content[0];

        expect(image.attrs).toMatchObject({
            assetKey: 'legacy_asset',
            cropX: 0,
            cropY: 0,
            cropW: 1,
            cropH: 1,
            rotation: 0,
            widthPercent: 100,
            align: 'center',
        });

        const reopened = toNativeDocument(tiptap);
        expect(reopened.content[0].attrs).toEqual(legacy.content[0].attrs);
    });
});
