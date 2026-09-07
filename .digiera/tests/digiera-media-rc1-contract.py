from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]

REQUIRED = [
    'public/local/digieramedia/db/services.php',
    'public/local/digieramedia/classes/external/search_media.php',
    'public/local/digieramedia/classes/external/create_reference.php',
    'public/lib/editor/tiny/plugins/digieramedia/version.php',
    'public/lib/editor/tiny/plugins/digieramedia/classes/plugininfo.php',
    'public/lib/editor/tiny/plugins/digieramedia/amd/src/plugin.js',
    'public/lib/editor/tiny/plugins/digieramedia/amd/src/modal.js',
    'public/lib/editor/tiny/plugins/digieramedia/amd/src/ui.js',
    'public/lib/editor/tiny/plugins/digieramedia/amd/src/reference_component.js',
    'public/lib/editor/tiny/plugins/digieramedia/templates/modal.mustache',
    'public/lib/editor/tiny/plugins/digieramedia/styles.css',
    'public/filter/digieramedia/classes/text_filter.php',
]

def read(rel):
    return (ROOT / rel).read_text(encoding='utf-8')

def test_required_rc1_files_exist():
    missing = [p for p in REQUIRED if not (ROOT / p).is_file()]
    assert not missing, f'Missing RC1 files: {missing}'

def test_tiny_modal_contract():
    tpl = read('public/lib/editor/tiny/plugins/digieramedia/templates/modal.mustache')
    for label in ['Tải lên & chèn', 'Thư viện', 'Đã dùng gần đây', 'Thùng rác', 'Chỉ lưu vào thư viện', 'Chèn vào bài']:
        assert label in tpl
    assert tpl.count('digiera-media-modal__column') >= 3

def test_modal_matches_approved_mockup_without_design_badge():
    tpl = read('public/lib/editor/tiny/plugins/digieramedia/templates/modal.mustache')
    css = read('public/lib/editor/tiny/plugins/digieramedia/styles.css')
    ui = read('public/lib/editor/tiny/plugins/digieramedia/amd/src/ui.js')

    assert 'Phương án A' not in tpl
    assert 'Modal 3 cột (Khuyến nghị)' not in tpl
    assert 'tiny-digieramedia__title' in tpl
    assert 'tiny-digieramedia__dropzone' in tpl
    assert 'data-region="upload-queue"' in tpl
    assert 'data-region="type-filter"' in tpl
    assert 'data-region="sort"' in tpl
    assert 'data-region="media-list"' in tpl
    assert 'data-region="preview"' in tpl
    assert 'Tùy chọn nâng cao' in tpl
    assert 'grid-template-columns: 220px minmax(0, 1fr) 320px' in css
    assert 'max-width: 1280px' in css
    assert 'tiny-digieramedia__card' in ui

def test_modal_action_separated_from_default_modal_module():
    commands = read('public/lib/editor/tiny/plugins/digieramedia/amd/src/commands.js')
    modal = read('public/lib/editor/tiny/plugins/digieramedia/amd/src/modal.js')
    ui = read('public/lib/editor/tiny/plugins/digieramedia/amd/src/ui.js')
    assert "from './ui'" in commands
    assert 'export const open' not in modal
    assert 'export default class DigieraMediaModal' in modal
    assert 'export const open' in ui
    assert "from './modal'" in ui

def test_marker_roundtrip_contract_present():
    js = read('public/lib/editor/tiny/plugins/digieramedia/amd/src/reference_component.js')
    assert '[[digiera-ref:' in js
    assert 'contenteditable="false"' in js or "contenteditable='false'" in js
    assert 'PostProcess' in js
    assert 'BeforeSetContent' in js

def test_external_services_are_registered():
    services = read('public/local/digieramedia/db/services.php')
    assert 'local_digieramedia_search_media' in services
    assert 'local_digieramedia_create_reference' in services

def test_shared_seed_visibility_is_insertable():
    search = read('public/local/digieramedia/classes/external/search_media.php')
    create = read('public/local/digieramedia/classes/external/create_reference.php')
    assert "m.visibility = 'SHARED'" in search
    assert "['GLOBAL', 'SHARED']" in create

def test_renderer_covers_rc1_media_types():
    code = read('public/filter/digieramedia/classes/text_filter.php')
    for token in ['render_pdf', 'render_video', 'render_image', 'render_audio', 'render_generic']:
        assert token in code
    assert '#zoom=page-width&scrollMode=page&spread=none' in code

def test_css_is_scoped():
    css = read('public/lib/editor/tiny/plugins/digieramedia/styles.css')
    bad = [r'(^|[},\n])\s*body\b', r'\.secondary-navigation\b', r'\.nav-tabs\b', r'(^|[},\n])\s*iframe\b']
    for pattern in bad:
        assert re.search(pattern, css, re.M) is None, f'forbidden global selector: {pattern}'
    assert '.tiny-digieramedia' in css

def test_tiny_uses_moodle_plugin_option_names():
    options = read('public/lib/editor/tiny/plugins/digieramedia/amd/src/options.js')
    assert 'getPluginOptionName' in options
    for key in ['enabled', 'contextid', 'courseid', 'canupload']:
        assert repr(key) in options or f'"{key}"' in options

if __name__ == '__main__':
    tests = [value for name, value in sorted(globals().items()) if name.startswith('test_') and callable(value)]
    for test in tests:
        test()
        print(f'PASS {test.__name__}')
    print(f'RC1_CONTRACT_PASS={len(tests)}')
