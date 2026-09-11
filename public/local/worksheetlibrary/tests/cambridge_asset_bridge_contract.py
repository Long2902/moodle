from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BRIDGE = (ROOT / 'amd/src/native_editor.js').read_text(encoding='utf-8')
ENDPOINT = (ROOT / 'native_asset.php').read_text(encoding='utf-8')
SERVICE = (ROOT / 'classes/service/native_asset_service.php').read_text(encoding='utf-8')
PDF = (ROOT / 'pdf.php').read_text(encoding='utf-8')

# Teacher editor must consume the new context-neutral ImageAdapter contract,
# not the retired uploadImage callback/file-picker path.
assert 'imageAdapter' in BRIDGE, 'teacher bridge must pass imageAdapter to NativeEditor.mount'
for method in ('createAsset', 'replaceAsset', 'resolvePreview', 'onAssetRecord'):
    assert method in BRIDGE, f'teacher ImageAdapter missing {method}'
assert 'uploadImage,' not in BRIDGE, 'retired uploadImage callback is still active in teacher bridge'
assert 'pickImage' not in BRIDGE, 'teacher bridge must let Cambridge PictureEditor own file selection'

# Endpoint/service need explicit create + stable-key replace semantics.
assert "optional_param('action'" in ENDPOINT or "required_param('action'" in ENDPOINT, 'teacher asset endpoint must accept an explicit action'
assert "'replace'" in ENDPOINT, 'teacher asset endpoint must expose replace action'
assert "PARAM_ALPHANUMEXT" in ENDPOINT and 'assetkey' in ENDPOINT, 'teacher replace must validate assetkey'
assert 'replace_upload' in SERVICE, 'teacher asset service must implement replace_upload'
assert 'resolve_preview' in SERVICE or 'asset_url' in SERVICE, 'teacher asset service must expose preview resolution'

# Stable logical key: replace must not mint a new a_<random> key.
replace_section = SERVICE[SERVICE.find('function replace_upload'):]
assert replace_section, 'teacher replace_upload implementation not found'
assert "random_bytes" not in replace_section.split('function ', 1)[0], 'teacher replace must preserve logical assetKey'

# PDF remains server-side Native rendering and must render managed asset metadata.
assert 'renderer::render_json' in PDF, 'PDF must render canonical Native JSON'
assert "'nativeasset'" in PDF, 'PDF must resolve worksheet Moodle assets'

print('CAMBRIDGE_TEACHER_ASSET_BRIDGE_CONTRACT=PASS')
