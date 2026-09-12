from pathlib import Path
import re

root = Path(__file__).resolve().parents[3]
bridge = (root / 'local' / 'worksheetlibrary' / 'amd' / 'src' / 'native_editor.js').read_text()
build = (root / 'local' / 'worksheetlibrary' / 'amd' / 'build' / 'native_editor.min.js').read_text()
pdf = (root / 'local' / 'worksheetlibrary' / 'pdf.php').read_text()
print_route = root / 'local' / 'worksheetlibrary' / 'print.php'
css = (root / 'local' / 'digieranative' / 'styles.css').read_text()
editor = (root / 'local' / 'digieranative' / 'client' / 'src' / 'editor.js').read_text()
toolbar = (root / 'local' / 'digieranative' / 'client' / 'src' / 'ui' / 'cambridge_toolbar.js').read_text()

# The production AMD artifact must match the source contract for this hotfix.
assert bridge == build, 'Worksheet Native AMD build is stale versus src'

# PDF must download without navigating the editor page away. This prevents
# stale optimistic-revision state when the browser returns to the editor.
assert 'window.location.assign' not in bridge, 'PDF must not navigate the editor page'
assert 'pdf.php?versionid=' in bridge and 'fetch(' in bridge, 'PDF must be fetched in-place'
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

# User-approved layout: exactly two explicit Cambridge ribbon rows. Keep output
# logic untouched; only the toolbar presentation changes.
assert "cambridgeToolbar.style.flexWrap = 'nowrap'" not in editor, 'Editor runtime must not force the whole ribbon into one row'
assert toolbar.count("data-dgn-toolbar-row") == 2, 'Cambridge toolbar must expose exactly two explicit rows'
assert "'data-dgn-toolbar-row': 'primary'" in toolbar, 'Primary ribbon row missing'
assert "'data-dgn-toolbar-row': 'secondary'" in toolbar, 'Secondary ribbon row missing'
match = re.search(r'\.dgn-editor \.dgn-cambridge-row\s*\{(?P<body>.*?)\}', css, re.S)
assert match, 'Cambridge row CSS block missing'
rowbody = match.group('body')
assert re.search(r'display\s*:\s*flex', rowbody), 'Each Cambridge row must use flex layout'
assert re.search(r'flex-wrap\s*:\s*nowrap', rowbody), 'Each Cambridge row must remain a single line'
match = re.search(r'\.dgn-editor \.dgn-cambridge-toolbar\s*\{(?P<body>.*?)\}', css, re.S)
assert match and re.search(r'overflow-x\s*:\s*auto', match.group('body')), 'Two-row ribbon must retain horizontal overflow protection'

print('NATIVE_OUTPUT_BROWSER_CONTRACT=PASS')
print('AMD_SRC_BUILD_PARITY=PASS')
print('PDF_IN_PLACE_DOWNLOAD=PASS')
print('PRINT_ISOLATED_WINDOW=PASS')
print('TCPDF_UNICODE_FONT=PASS')
print('CAMBRIDGE_TWO_ROW=PASS')
