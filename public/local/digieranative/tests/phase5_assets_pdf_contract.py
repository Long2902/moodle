from pathlib import Path

public = Path(__file__).resolve().parents[3]
dn = public / 'local' / 'digieranative'
wl = public / 'local' / 'worksheetlibrary'
wg = public / 'mod' / 'worksheetgrader'

required = [
    wl / 'native_asset.php',
    wl / 'pdf.php',
    wg / 'native_asset.php',
]
for path in required:
    assert path.exists(), f'missing Phase 5 endpoint: {path}'

teacher_asset = (wl / 'native_asset.php').read_text()
student_asset = (wg / 'native_asset.php').read_text()
pdf = (wl / 'pdf.php').read_text()
wl_bridge = (wl / 'amd' / 'src' / 'native_editor.js').read_text()
wg_bridge = (wg / 'amd' / 'src' / 'native_attempt.js').read_text()
wl_lib = (wl / 'lib.php').read_text()
wg_lib = (wg / 'lib.php').read_text()

for source in [teacher_asset, student_asset]:
    assert 'require_login' in source
    assert 'require_sesskey' in source
    assert 'finfo' in source
    assert 'image/png' in source and 'image/jpeg' in source and 'image/webp' in source
    assert 'image/svg' not in source
    assert '5 * 1024 * 1024' in source or '5242880' in source
    assert 'create_file_from_pathname' in source

assert "'nativeasset'" in wl_lib
assert "'attemptimage'" in wg_lib
assert 'digiera-native:request-image' in wl_bridge or 'uploadImage' in wl_bridge
assert 'digiera-native:request-image' in wg_bridge or 'uploadImage' in wg_bridge

assert 'require_login' in pdf
assert 'access_service::require_view' in pdf
assert 'renderer::render_json' in pdf
assert 'tcpdf.php' in pdf.lower()
assert 'application/pdf' in pdf
assert 'Content-Disposition' in pdf or "'D'" in pdf

for forbidden in ['theme/remui', 'admin/cli/upgrade.php']:
    assert forbidden not in teacher_asset + student_asset + pdf

print('PHASE5_ASSETS_PDF_CONTRACT=PASS')
