from pathlib import Path

root = Path(__file__).resolve().parents[1]
external = root / 'classes' / 'external' / 'save_native_draft.php'
services = root / 'db' / 'services.php'
detail = root / 'detail.php'
index = root / 'index.php'
wrapper = root / 'amd' / 'src' / 'native_editor.js'

assert external.exists(), 'save_native_draft external class missing'
text = external.read_text()
for token in ['versionid', 'expectedrevision', 'nativejson', 'native_version_service::save_draft', 'native_revision_conflict_exception']:
    assert token in text, f'external Native save contract missing {token}'

assert services.exists(), 'db/services.php missing'
st = services.read_text()
assert 'local_worksheetlibrary_save_native_draft' in st
assert "'ajax' => true" in st or "'ajax'=>true" in st

it = index.read_text()
assert '<option value="native">' in it, 'Worksheet Library create form must offer Native'

dt = detail.read_text()
for token in ['local_worksheetlibrary/native_editor', 'data-region="native-editor"', 'data-region="native-save-status"', 'Đang lưu', 'Đã lưu', 'Xung đột phiên bản']:
    assert token in dt, f'Native detail contract missing {token}'

assert wrapper.exists(), 'Worksheet Library Native AMD wrapper missing'
wt = wrapper.read_text()
for token in ['core/ajax', 'local_digieranative/native_editor', 'local_worksheetlibrary_save_native_draft', 'createAutosaveController']:
    assert token in wt, f'Native wrapper missing {token}'

print('NATIVE_EXTERNAL_API_CONTRACT=PASS')
print('NATIVE_DETAIL_CONTRACT=PASS')
print('NATIVE_KIND_UI=PASS')
