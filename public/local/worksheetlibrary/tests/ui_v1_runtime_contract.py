#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

index = (ROOT / 'index.php').read_text(encoding='utf-8')
detail = (ROOT / 'detail.php').read_text(encoding='utf-8')
styles = (ROOT / 'styles.css').read_text(encoding='utf-8')
browser = (ROOT / 'amd/src/browser.js').read_text(encoding='utf-8')

required_index = [
    'wslib-v1-shell',
    'wslib-v1-header',
    'wslib-v1-table',
    'wslib-v1-inspector',
    'data-action="new-item"',
    'data-action="new-folder"',
    'data-modal="create-item"',
    'data-modal="create-folder"',
]
required_detail = [
    'wslib-editor-workspace',
    'wslib-editor-toolbar',
    'wslib-editor-inspector',
    'data-action="open-publish"',
    'data-modal="publish"',
    'data-modal="publish-success"',
]
required_styles = [
    '.wslib-v1-shell',
    '.wslib-v1-table',
    '.wslib-v1-inspector',
    '.wslib-modal',
    '.wslib-paper-stage',
]
required_browser = [
    'openModal',
    'closeModal',
    'data-action="new-folder"',
    'data-action="open-publish"',
]

for label, text, required in [
    ('index.php', index, required_index),
    ('detail.php', detail, required_detail),
    ('styles.css', styles, required_styles),
    ('amd/src/browser.js', browser, required_browser),
]:
    missing = [needle for needle in required if needle not in text]
    if missing:
        raise SystemExit(f'{label}: missing visual V1 contract markers: {missing}')

for forbidden in ('cdn.tailwindcss.com', 'cdnjs.cloudflare.com', 'fonts.googleapis.com'):
    for label, text in [('index.php', index), ('detail.php', detail), ('styles.css', styles), ('amd/src/browser.js', browser)]:
        if forbidden in text:
            raise SystemExit(f'{label}: production CDN forbidden: {forbidden}')

print('WSLIB_UI_V1_RUNTIME_CONTRACT=PASS')
