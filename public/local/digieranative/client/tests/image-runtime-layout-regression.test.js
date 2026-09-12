/* @vitest-environment jsdom */
import {describe, expect, it, vi} from 'vitest';

import {mount} from '../src/index.js';

describe('Native managed image runtime layout regression', () => {
    it('renders a managed image at its requested width instead of a 6rem background placeholder', async () => {
        const element = document.createElement('div');
        document.body.append(element);

        const editor = mount({
            element,
            assetUrls: {asset_1: '/pluginfile.php/native/asset_1.png'},
            imageAdapter: {
                createAsset: vi.fn(),
                replaceAsset: vi.fn(),
                resolvePreview: vi.fn(async () => '/pluginfile.php/native/asset_1.png'),
                onAssetRecord: vi.fn(),
            },
            documentJson: {
                type: 'worksheet',
                version: 1,
                content: [{
                    type: 'image',
                    attrs: {
                        assetKey: 'asset_1',
                        alt: 'Ảnh kiểm thử',
                        caption: 'Chú thích',
                        widthPercent: 100,
                        align: 'center',
                        cropX: 0,
                        cropY: 0,
                        cropW: 1,
                        cropH: 1,
                        rotation: 0,
                    },
                }],
            },
        });

        editor.refreshAssetPreviews();

        const figure = element.querySelector('figure[data-asset-key="asset_1"]');
        expect(figure).not.toBeNull();
        expect(figure.style.width).toBe('100%');
        expect(figure.style.marginLeft).toBe('auto');
        expect(figure.style.marginRight).toBe('auto');

        const image = figure.querySelector('img.dgn-image__preview');
        expect(image).not.toBeNull();
        expect(image.getAttribute('src')).toContain('/pluginfile.php/native/asset_1.png');
        expect(image.style.width).toBe('100%');
        expect(image.style.height).toBe('auto');
        expect(figure.style.backgroundImage).toBe('');

        editor.destroy();
        element.remove();
    });
});
