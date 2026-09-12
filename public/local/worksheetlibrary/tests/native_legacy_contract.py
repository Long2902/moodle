import re
from pathlib import Path

root = Path(__file__).resolve().parents[1]
detail = (root / 'detail.php').read_text()
office = (root / 'office.php').read_text()
manage = (root / 'manage.php').read_text()

# Legacy HTML authoring must remain reachable from the detail UI and handled server-side.
assert re.search(r"\$item->kind\s*===\s*['\"]html['\"]", detail), \
    'legacy HTML detail route missing'
assert re.search(r"name=['\"]action['\"]\s+value=['\"]savehtml['\"]", detail), \
    'legacy HTML save action marker missing'
assert re.search(r"case\s+['\"]savehtml['\"]\s*:", manage), \
    'legacy HTML save backend action missing'

# Legacy Office/PDF draft replacement must remain reachable and handled server-side.
assert re.search(
    r"in_array\s*\(\s*\$item->kind\s*,\s*\[\s*['\"]office['\"]\s*,\s*['\"]pdf['\"]\s*\]",
    detail,
), 'legacy Office/PDF upload route missing'
assert re.search(r"name=['\"]action['\"]\s+value=['\"]upload['\"]", detail), \
    'legacy upload action marker missing'
assert re.search(r"case\s+['\"]upload['\"]\s*:", manage), \
    'legacy upload backend action missing'

assert 'local_digieraoffice' in office or 'DocsAPI' in office or 'office' in office.lower(), \
    'Office route unexpectedly missing'

print('LEGACY_ROUTE_MARKERS=PASS')
