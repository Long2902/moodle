from pathlib import Path

public = Path(__file__).resolve().parents[3]
wl = public / 'local' / 'worksheetlibrary'
wg = public / 'mod' / 'worksheetgrader'

required = [
    wl / 'native_asset.php',
    wl / 'pdf.php',
    wl / 'classes/service/native_asset_service.php',
    wg / 'native_asset.php',
    wg / 'classes/service/native_asset_service.php',
]
for path in required:
    assert path.exists(), f'missing Phase 5 endpoint/service: {path}'

teacher_asset = (wl / 'native_asset.php').read_text()
teacher_service = (wl / 'classes/service/native_asset_service.php').read_text()
student_asset = (wg / 'native_asset.php').read_text()
student_service = (wg / 'classes/service/native_asset_service.php').read_text()
pdf = (wl / 'pdf.php').read_text()
wl_bridge = (wl / 'amd' / 'src' / 'native_editor.js').read_text()
wg_bridge = (wg / 'amd' / 'src' / 'native_attempt.js').read_text()
wl_lib = (wl / 'lib.php').read_text()
wg_lib = (wg / 'lib.php').read_text()
attempt_manager = (wg / 'classes/service/attempt_manager.php').read_text()

for endpoint, service in [(teacher_asset, teacher_service), (student_asset, student_service)]:
    combined = endpoint + service
    assert 'require_login' in endpoint
    assert 'require_sesskey' in endpoint
    assert 'finfo' in combined
    assert 'image/png' in combined and 'image/jpeg' in combined and 'image/webp' in combined
    assert 'image/svg' not in combined
    assert '5 * 1024 * 1024' in combined or '5242880' in combined
    assert 'create_file_from_pathname' in service
    assert 'random_bytes' in service

assert "'nativeasset'" in wl_lib
assert "'attemptimage'" in wg_lib
assert 'uploadImage' in wl_bridge and 'FormData' in wl_bridge and 'native_asset.php' in wl_bridge
assert 'uploadImage' in wg_bridge and 'FormData' in wg_bridge and 'native_asset.php' in wg_bridge
assert 'clone_library_assets' in attempt_manager
assert 'native_asset_service::asset_urls' in attempt_manager

assert 'require_login' in pdf
assert 'access_service::require_view' in pdf
assert 'renderer::render_json' in pdf
assert 'tcpdf.php' in pdf.lower()
assert 'application/pdf' in pdf
assert 'Content-Disposition' in pdf
assert "Output($basename, 'S')" in pdf

for forbidden in ['theme/remui', 'admin/cli/upgrade.php']:
    assert forbidden not in teacher_asset + student_asset + pdf

print('PHASE5_ASSETS_PDF_CONTRACT=PASS')
