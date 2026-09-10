from pathlib import Path

root = Path(__file__).resolve().parents[1]
schema = (root / 'classes/document/schema.php').read_text()
validator = (root / 'classes/document/validator.php').read_text()
renderer = (root / 'classes/document/renderer.php').read_text()
specs = (root / 'client/src/schema_specs.js').read_text()

assert "'link'" in schema, 'Native schema must persist Tiptap link marks'
assert "mark['type'] === 'link'" in validator or "$mark['type'] === 'link'" in validator
assert 'href' in validator and 'http' in validator
assert "'link' =>" in renderer and '<a href=' in renderer
assert 'link:' in specs and 'href' in specs
print('TIPTAP_LINK_ROUNDTRIP_CONTRACT=PASS')
