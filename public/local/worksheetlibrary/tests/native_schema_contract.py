from pathlib import Path
import re
import xml.etree.ElementTree as ET

root = Path(__file__).resolve().parents[1]
install = root / 'db' / 'install.xml'
upgrade = root / 'db' / 'upgrade.php'
version = root / 'version.php'
service = root / 'classes' / 'service' / 'version_service.php'

tree = ET.parse(install)
version_table = next(t for t in tree.findall('.//TABLE') if t.attrib.get('NAME') == 'wslib_version')
fields = {f.attrib['NAME']: f.attrib for f in version_table.findall('./FIELDS/FIELD')}
required = {'nativejson', 'schemaversion', 'revision', 'renderedhtml'}
missing = sorted(required - fields.keys())
assert not missing, f'missing Native XMLDB fields: {missing}'
assert fields['revision'].get('TYPE') == 'int'
assert fields['revision'].get('NOTNULL') == 'true'
assert fields['revision'].get('DEFAULT') == '0'
assert fields['nativejson'].get('TYPE') == 'text'
assert fields['renderedhtml'].get('TYPE') == 'text'

assert upgrade.exists(), 'db/upgrade.php must exist'
up = upgrade.read_text()
for name in ['nativejson', 'schemaversion', 'revision', 'renderedhtml']:
    assert f"'{name}'" in up or f'"{name}"' in up, f'upgrade.php missing {name}'
assert 'xmldb_field' in up, 'upgrade must use XMLDB APIs'
assert 'upgrade_plugin_savepoint' in up, 'upgrade must use savepoint'
assert 'ALTER TABLE' not in up.upper(), 'raw ALTER TABLE forbidden'

vtext = version.read_text()
m = re.search(r'\$plugin->version\s*=\s*(\d+)', vtext)
assert m and int(m.group(1)) >= 2026090701, 'plugin version must be bumped for schema upgrade'

svc = service.read_text()
assert "['html', 'office', 'pdf', 'native']" in svc or "['html','office','pdf','native']" in svc, 'create_item must accept native kind'
assert "'nativejson'" in svc, 'Native initial/copy draft state must be handled'
assert "'revision'" in svc, 'Native draft revision must be initialized/copied intentionally'

print('NATIVE_XMLDB_FIELDS=PASS')
print('NATIVE_UPGRADE_FIELDS=PASS')
print('NATIVE_KIND_CONTRACT=PASS')
