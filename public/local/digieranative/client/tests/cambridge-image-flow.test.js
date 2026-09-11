/* @vitest-environment jsdom */
import {describe, expect, it, vi} from 'vitest';

import {mount} from '../src/index.js';

describe('Phase 5 CambridgePlus image workflow contract', () => {
    it('uses a single ImageAdapter-style integration and preserves captured insertion position', async () => {
        const element = document.createElement('div');
        document.body.append(element);

        const createAsset = vi.fn(async () => ({
            assetKey: 'asset_cambridge_1',
            name: 'picture.png',
            mime: 'image/png',
            width: 1200,
            height: 800,
        }));
        const replaceAsset = vi.fn();
        const resolvePreview = vi.fn(async () => '/pluginfile.php/cambridge-picture.png');
        const onAssetRecord = vi.fn();

        const editor = mount({
            element,
            imageAdapter: {createAsset, replaceAsset, resolvePreview, onAssetRecord},
            documentJson: {
                type: 'worksheet',
                version: 1,
                content: [
                    {type: 'paragraph', content: [{type: 'text', text: 'Before'}]},
                    {type: 'paragraph', content: [{type: 'text', text: 'After'}]},
                ],
            },
        });

        expect(editor.openPictureEditor).toBeTypeOf('function');
        expect(editor.insertPictureAsset).toBeTypeOf('function');

        const capturedPos = editor.state.selection.from;
        await editor.insertPictureAsset({
            capturedPos,
            file: new File(['image'], 'picture.png', {type: 'image/png'}),
            edits: {
                cropX: 0.1,
                cropY: 0.1,
                cropW: 0.8,
                cropH: 0.8,
                rotation: 90,
                widthPercent: 70,
                align: 'center',
                alt: 'Mô tả',
                caption: 'Chú thích',
            },
        });

        expect(createAsset).toHaveBeenCalledTimes(1);
        expect(onAssetRecord).toHaveBeenCalledTimes(1);

        const native = editor.getNativeJSON();
        const images = native.content.filter(node => node.type === 'image');
        expect(images).toHaveLength(1);
        expect(images[0].attrs).toMatchObject({
            assetKey: 'asset_cambridge_1',
            cropX: 0.1,
            cropY: 0.1,
            cropW: 0.8,
            cropH: 0.8,
            rotation: 90,
            widthPercent: 70,
            align: 'center',
            alt: 'Mô tả',
            caption: 'Chú thích',
        });
        expect(JSON.stringify(native)).not.toMatch(/blob:|data:image\//);

        editor.destroy();
        element.remove();
    });

    it('updates one existing image node on crop/replace instead of duplicating it', async () => {
        const element = document.createElement('div');
        document.body.append(element);

        const replaceAsset = vi.fn(async assetKey => ({assetKey}));
        const editor = mount({
            element,
            imageAdapter: {
                createAsset: vi.fn(),
                replaceAsset,
                resolvePreview: vi.fn(async () => '/pluginfile.php/p.png'),
                onAssetRecord: vi.fn(),
            },
            documentJson: {
                type: 'worksheet',
                version: 1,
                content: [{
                    type: 'image',
                    attrs: {assetKey: 'asset_existing', alt: 'old'},
                }],
            },
        });

        expect(editor.updatePictureAsset).toBeTypeOf('function');
        await editor.updatePictureAsset('asset_existing', {
            caption: 'new caption',
            widthPercent: 60,
            cropX: 0,
            cropY: 0,
            cropW: 1,
            cropH: 1,
            rotation: 0,
            align: 'left',
            alt: 'new alt',
        });

        const native = editor.getNativeJSON();
        const images = native.content.filter(node => node.type === 'image');
        expect(images).toHaveLength(1);
        expect(images[0].attrs.caption).toBe('new caption');
        expect(images[0].attrs.alt).toBe('new alt');

        editor.destroy();
        element.remove();
    });
});
