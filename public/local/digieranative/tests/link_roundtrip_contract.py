from pathlib import Path

root = Path(__file__).resolve().parents[1]
server_schema = (root / 'classes/document/schema.php').read_text()
validator = (root / 'classes/document/validator.php').read_text()
renderer = (root / 'classes/document/renderer.php').read_text()
client_schema = (root / 'client/src/schema.js').read_text()

assert "'link'" in server_schema, 'Native schema must persist Tiptap link marks'
assert "$mark['type'] === 'link'" in validator
assert 'href' in validator and 'http' in validator
assert "'link' =>" in renderer and '<a href=' in renderer
assert 'linkMarkSpec' in client_schema and 'href' in client_schema
assert 'isSafeLinkUrl' in client_schema
print('TIPTAP_LINK_ROUNDTRIP_CONTRACT=PASS')
