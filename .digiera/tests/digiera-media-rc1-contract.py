from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]

REQUIRED = [
    'public/local/digieramedia/db/services.php',
    'public/local/digieramedia/classes/external/search_media.php',
    'public/local/digieramedia/classes/external/create_reference.php',
    'public/local/digieramedia/classes/external/resolve_references.php',
    'public/local/digieramedia/classes/external/update_reference_version.php',
    'public/local/digieramedia/classes/r2/config.php',
    'public/local/digieramedia/classes/r2/sigv4_client.php',
    'public/local/digieramedia/classes/service/file_policy.php',
    'public/local/digieramedia/classes/service/recent_service.php',
    'public/local/digieramedia/classes/service/upload_session_service.php',
    'public/local/digieramedia/classes/service/upload_finalize_service.php',
    'public/local/digieramedia/classes/external/create_upload_session.php',
    'public/local/digieramedia/classes/external/finalize_upload.php',
    'public/lib/editor/tiny/plugins/digieramedia/version.php',
    'public/lib/editor/tiny/plugins/digieramedia/classes/plugininfo.php',
    'public/lib/editor/tiny/plugins/digieramedia/amd/src/plugin.js',
    'public/lib/editor/tiny/plugins/digieramedia/amd/src/modal.js',
    'public/lib/editor/tiny/plugins/digieramedia/amd/src/ui.js',
    'public/lib/editor/tiny/plugins/digieramedia/amd/src/upload_client.js',
    'public/lib/editor/tiny/plugins/digieramedia/amd/src/reference_component.js',
    'public/lib/editor/tiny/plugins/digieramedia/amd/build/ui.min.js',
    'public/lib/editor/tiny/plugins/digieramedia/amd/build/upload_client.min.js',
    'public/lib/editor/tiny/plugins/digieramedia/amd/build/reference_component.min.js',
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

def test_reopen_rehydrates_real_media_metadata():
    services = read('public/local/digieramedia/db/services.php')
    resolver = read('public/local/digieramedia/classes/external/resolve_references.php')
    js = read('public/lib/editor/tiny/plugins/digieramedia/amd/src/reference_component.js')

    assert 'local_digieramedia_resolve_references' in services
    assert 'class resolve_references' in resolver
    assert 'local_digieramedia_reference' in resolver
    assert 'local_digieramedia_media' in resolver
    assert 'hydrateReferenceLabels' in js
    assert 'local_digieramedia_resolve_references' in js
    assert "name: 'Học liệu DIGIERA'" not in js
    assert "name: 'Đang tải…'" in js

def test_live_r2_single_put_contract():
    services = read('public/local/digieramedia/db/services.php')
    r2config = read('public/local/digieramedia/classes/r2/config.php')
    r2client = read('public/local/digieramedia/classes/r2/sigv4_client.php')
    session = read('public/local/digieramedia/classes/service/upload_session_service.php')
    finalize = read('public/local/digieramedia/classes/service/upload_finalize_service.php')
    uploadjs = read('public/lib/editor/tiny/plugins/digieramedia/amd/src/upload_client.js')
    ui = read('public/lib/editor/tiny/plugins/digieramedia/amd/src/ui.js')
    tpl = read('public/lib/editor/tiny/plugins/digieramedia/templates/modal.mustache')

    assert 'local_digieramedia_create_upload_session' in services
    assert 'local_digieramedia_finalize_upload' in services
    assert 'DIGIERA_R2_SECRET_ACCESS_KEY' in r2config
    assert '/etc/digiera/r2.php' in r2config
    assert 'presign_put' in r2client
    assert 'head_object' in r2client
    assert 'UNSIGNED-PAYLOAD' in r2client
    assert 'targetkey' in session and 'targetbucket' in session
    assert 'committedmediaid' in finalize
    assert 'local_digieramedia_media' in finalize
    assert 'local_digieramedia_version' in finalize
    assert 'XMLHttpRequest' in uploadjs
    assert 'local_digieramedia_create_upload_session' in uploadjs
    assert 'local_digieramedia_finalize_upload' in uploadjs
    assert "from './upload_client'" in ui
    assert 'data-region="upload-input"' in tpl
    assert 'Live R2 upload sẽ được bật sau bước cấu hình/verify R2 của RC1.' not in tpl

    joined = '\n'.join([r2config, r2client, session, finalize, uploadjs])
    assert 'SECRET_ACCESS_KEY=' not in joined
    assert 'Authorization: Bearer' not in joined

def test_live_r2_runtime_build_is_committed_and_wired():
    ui = read('public/lib/editor/tiny/plugins/digieramedia/amd/build/ui.min.js')
    upload = read('public/lib/editor/tiny/plugins/digieramedia/amd/build/upload_client.min.js')
    assert 'tiny_digieramedia/ui' in ui
    assert './upload_client' in ui
    assert '.uploadFile' in ui
    assert 'tiny_digieramedia/upload_client' in upload
    assert 'local_digieramedia_create_upload_session' in upload
    assert 'local_digieramedia_finalize_upload' in upload
    assert 'XMLHttpRequest' in upload

def test_external_services_are_registered():
    services = read('public/local/digieramedia/db/services.php')
    assert 'local_digieramedia_search_media' in services
    assert 'local_digieramedia_create_reference' in services
    assert 'local_digieramedia_resolve_references' in services
    assert 'local_digieramedia_get_media_versions' in services
    assert 'local_digieramedia_update_reference_version' in services

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

def test_replace_and_versioning_contract():
    services = read('public/local/digieramedia/db/services.php')
    install = read('public/local/digieramedia/db/install.xml')
    session = read('public/local/digieramedia/classes/service/upload_session_service.php')
    finalize = read('public/local/digieramedia/classes/service/upload_finalize_service.php')
    create = read('public/local/digieramedia/classes/external/create_reference.php')
    uploadjs = read('public/lib/editor/tiny/plugins/digieramedia/amd/src/upload_client.js')
    ui = read('public/lib/editor/tiny/plugins/digieramedia/amd/src/ui.js')

    versions = ROOT / 'public/local/digieramedia/classes/external/get_media_versions.php'
    assert versions.is_file(), 'get_media_versions endpoint is required'
    assert 'local_digieramedia_get_media_versions' in services
    assert 'targetmediaid' in install
    assert 'replacemediauuid' in session
    assert 'targetmediaid' in finalize
    assert 'versionno' in finalize
    assert 'currentversionid' in finalize
    assert 'PINNED_VERSION' in create
    assert 'FOLLOW_CURRENT' in create
    assert 'replacemediauuid' in uploadjs
    assert 'Thay thế file' in ui
    assert 'Lịch sử phiên bản' in ui
    assert 'PINNED_VERSION' in ui
    assert 'FOLLOW_CURRENT' in ui

def test_reference_version_state_reopens_and_updates_existing_reference():
    services = read('public/local/digieramedia/db/services.php')
    resolver = read('public/local/digieramedia/classes/external/resolve_references.php')
    updater = read('public/local/digieramedia/classes/external/update_reference_version.php')
    component = read('public/lib/editor/tiny/plugins/digieramedia/amd/src/reference_component.js')
    ui = read('public/lib/editor/tiny/plugins/digieramedia/amd/src/ui.js')
    component_build = read('public/lib/editor/tiny/plugins/digieramedia/amd/build/reference_component.min.js')
    ui_build = read('public/lib/editor/tiny/plugins/digieramedia/amd/build/ui.min.js')

    assert 'local_digieramedia_update_reference_version' in services
    assert 'r.versionmode' in resolver
    assert 'r.pinnedversionid' in resolver
    assert 'class update_reference_version' in updater
    assert 'getSelectedReference' in component
    assert 'data-digiera-version-mode' in component
    assert 'updateReferenceState' in component
    assert 'local_digieramedia_update_reference_version' in ui
    assert 'Cập nhật reference' in ui
    assert 'getSelectedReference' in component_build
    assert 'local_digieramedia_update_reference_version' in ui_build

def test_true_recent_and_permission_contract():
    install = read('public/local/digieramedia/db/install.xml')
    version = read('public/local/digieramedia/version.php')
    recent = read('public/local/digieramedia/classes/service/recent_service.php')
    create = read('public/local/digieramedia/classes/external/create_reference.php')
    update = read('public/local/digieramedia/classes/external/update_reference_version.php')
    search = read('public/local/digieramedia/classes/external/search_media.php')
    lifecycle = read('public/local/digieramedia/classes/service/lifecycle_service.php')

    assert 'local_digieramedia_recent' in install
    assert 'NAME="user-media"' in install and 'FIELDS="userid, mediaid"' in install
    assert 'NAME="user-lastused"' in install and 'FIELDS="userid, lastusedat"' in install
    assert '2026091001' in version
    assert 'class recent_service' in recent and 'function touch' in recent
    assert 'recent_service' in create and 'CREATE_REFERENCE' in create
    assert 'recent_service' in update and 'UPDATE_REFERENCE' in update
    assert 'local_digieramedia_recent' in search
    assert 'r.userid' in search and 'recentuserid' in search
    assert 'r.lastusedat DESC' in search
    assert "delete_records('local_digieramedia_recent'" in lifecycle

    runtime_paths = list((ROOT / 'public/local/digieramedia/classes').rglob('*.php'))
    runtime_paths += list((ROOT / 'public/lib/editor/tiny/plugins/digieramedia/amd/src').rglob('*.js'))
    runtime = '\n'.join(path.read_text(encoding='utf-8') for path in runtime_paths)
    forbidden = ['editingteacher', 'digiera_ktv_test', 'role_shortname', 'get_user_roles(']
    for token in forbidden:
        assert token not in runtime, f'Role-name authorization is forbidden in runtime code: {token}'

def test_moodle_upgrade_entrypoint_is_global():
    upgrade = read('public/local/digieramedia/db/upgrade.php')
    assert re.search(r'(?m)^\s*namespace\s+', upgrade) is None, (
        'Moodle db/upgrade.php must not namespace xmldb_local_digieramedia_upgrade'
    )
    assert re.search(r'function\s+xmldb_local_digieramedia_upgrade\s*\(', upgrade)

if __name__ == '__main__':
    tests = [value for name, value in sorted(globals().items()) if name.startswith('test_') and callable(value)]
    for test in tests:
        test()
        print(f'PASS {test.__name__}')
    print(f'RC1_CONTRACT_PASS={len(tests)}')
