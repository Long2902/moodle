from pathlib import Path

root = Path(__file__).resolve().parents[3]
teacher = (root / 'local' / 'worksheetlibrary' / 'amd' / 'src' / 'native_editor.js').read_text()
student = (root / 'mod' / 'worksheetgrader' / 'amd' / 'src' / 'native_attempt.js').read_text()
teacher_endpoint = (root / 'local' / 'worksheetlibrary' / 'native_asset.php').read_text()
student_endpoint = (root / 'mod' / 'worksheetgrader' / 'native_asset.php').read_text()

for name, bridge in [('teacher', teacher), ('student', student)]:
    assert 'response.text()' in bridge, f'{name} image bridge must inspect raw response text'
    assert 'content-type' in bridge.lower(), f'{name} image bridge must inspect response content-type'
    assert '413' in bridge or 'Request Entity Too Large' in bridge or 'Payload Too Large' in bridge, \
        f'{name} image bridge must expose upstream upload-size rejection clearly'
    post_asset = bridge.split('const postAsset', 1)[1].split('const imageAdapter', 1)[0]
    assert 'await response.json()' not in post_asset, f'{name} postAsset must not blindly parse HTML as JSON'

for name, endpoint in [('teacher', teacher_endpoint), ('student', student_endpoint)]:
    header_pos = endpoint.find("header('Content-Type: application/json")
    try_pos = endpoint.find('try {')
    assert header_pos != -1 and try_pos != -1 and header_pos < try_pos, f'{name} endpoint must declare JSON before request validation'

print('NATIVE_IMAGE_UPLOAD_ERROR_CONTRACT=PASS')
