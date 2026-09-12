from pathlib import Path
import re

root = Path(__file__).resolve().parents[3]
bridge = (root / 'local' / 'worksheetlibrary' / 'amd' / 'src' / 'native_editor.js').read_text()
pdf = (root / 'local' / 'worksheetlibrary' / 'pdf.php').read_text()
print_route = root / 'local' / 'worksheetlibrary' / 'print.php'
css = (root / 'local' / 'digieranative' / 'styles.css').read_text()

# PDF must download without navigating the editor page away. This prevents
# stale optimistic-revision state when the browser returns to the editor.
assert 'window.location.assign' not in bridge, 'PDF must not navigate the editor page'
assert re.search(r'fetch\([^\n]*pdf\.php\?versionid=', bridge), 'PDF must be fetched in-place'
assert 'URL.createObjectURL' in bridge, 'PDF download must use an object URL'
assert '.download =' in bridge or "setAttribute('download'" in bridge, 'PDF download must use a download anchor'

# Print must run in a separate print-only window, not print the Moodle detail page.
assert 'window.open' in bridge, 'Print must open a dedicated print window'
assert 'print.php?versionid=' in bridge, 'Print must use the dedicated Native print route'
assert print_route.exists(), 'Native print route is missing'
pt = print_route.read_text()
for token in ['renderer::render_json', 'native_asset_service::asset_urls', '@page', 'window.print']:
    assert token in pt, f'Native print route missing {token}'

# Moodle's bundled TCPDF fonts include FreeSans; deployed package does not ship DejaVuSans.
assert "SetFont('freesans'" in pdf, 'PDF must use bundled Unicode FreeSans'
assert "SetFont('dejavusans'" not in pdf, 'PDF must not use unavailable DejaVuSans'

# Compact Cambridge ribbon stays on one row and scrolls horizontally when needed.
match = re.search(r'\.dgn-editor \.dgn-cambridge-toolbar\s*\{(?P<body>.*?)\}', css, re.S)
assert match, 'Cambridge toolbar CSS block missing'
body = match.group('body')
assert re.search(r'display\s*:\s*flex', body), 'Cambridge toolbar must be a flex row'
assert re.search(r'flex-wrap\s*:\s*nowrap', body), 'Cambridge toolbar must not wrap'
assert re.search(r'overflow-x\s*:\s*auto', body), 'Cambridge toolbar must scroll horizontally'

print('NATIVE_OUTPUT_BROWSER_CONTRACT=PASS')
print('PDF_IN_PLACE_DOWNLOAD=PASS')
print('PRINT_ISOLATED_WINDOW=PASS')
print('TCPDF_UNICODE_FONT=PASS')
print('CAMBRIDGE_SINGLE_ROW=PASS')
