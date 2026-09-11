from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BRIDGE = (ROOT / 'amd/src/native_attempt.js').read_text(encoding='utf-8')
ENDPOINT = (ROOT / 'native_asset.php').read_text(encoding='utf-8')
SERVICE = (ROOT / 'classes/service/native_asset_service.php').read_text(encoding='utf-8')

# Student attempt must consume the same ImageAdapter contract as teacher editing.
assert 'imageAdapter' in BRIDGE, 'attempt bridge must pass imageAdapter to NativeEditor.mount'
for method in ('createAsset', 'replaceAsset', 'resolvePreview', 'onAssetRecord'):
    assert method in BRIDGE, f'attempt ImageAdapter missing {method}'
assert 'uploadImage,' not in BRIDGE, 'retired uploadImage callback is still active in attempt bridge'
assert 'pickImage' not in BRIDGE, 'attempt bridge must let Cambridge PictureEditor own file selection'

# Asset mutation must be explicit and attempt-editable only.
assert "optional_param('action'" in ENDPOINT or "required_param('action'" in ENDPOINT, 'attempt asset endpoint must accept action'
assert "'replace'" in ENDPOINT, 'attempt asset endpoint must expose replace action'
assert 'attemptnoteditable' in ENDPOINT, 'attempt asset mutation must remain blocked after submit/close'
assert 'assetkey' in ENDPOINT and 'PARAM_ALPHANUMEXT' in ENDPOINT, 'attempt replace must validate assetkey'
assert 'replace_upload' in SERVICE, 'attempt asset service must implement stable-key replace_upload'

# Submitted/reviewed content must only resolve assets, never mutate them.
assert "($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'" in ENDPOINT, 'attempt asset GET resolution path missing'
assert 'asset_urls' in SERVICE, 'teacher/student readonly review needs attempt asset resolution'

# Stable logical key is mandatory for Native JSON references.
replace_section = SERVICE[SERVICE.find('function replace_upload'):]
assert replace_section, 'attempt replace_upload implementation not found'
assert 'random_bytes' not in replace_section.split('function ', 1)[0], 'attempt replace must preserve logical assetKey'

print('CAMBRIDGE_ATTEMPT_ASSET_BRIDGE_CONTRACT=PASS')
