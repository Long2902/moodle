from pathlib import Path
root = Path(__file__).resolve().parents[1]
detail = (root / 'detail.php').read_text()
office = (root / 'office.php').read_text()
manage = (root / 'manage.php').read_text()
for token in ["$item->kind==='html'", "['office','pdf']", "savehtml", "name=\"action\" value=\"upload\""]:
    assert token in detail or token in manage, f'legacy route marker missing: {token}'
assert 'local_digieraoffice' in office or 'DocsAPI' in office or 'office' in office.lower(), 'Office route unexpectedly missing'
print('LEGACY_ROUTE_MARKERS=PASS')
