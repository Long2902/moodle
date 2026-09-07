from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def text(rel):
    return (ROOT / rel).read_text(encoding='utf-8')

# Student runtime must have a dedicated Native attempt AMD bridge.
native_attempt = ROOT / 'amd/src/native_attempt.js'
assert native_attempt.exists(), 'native_attempt.js must exist'
js = native_attempt.read_text(encoding='utf-8')
for marker in [
    'local_digieranative/native_editor',
    'createAutosaveController',
    'mod_worksheetgrader_save_attempt',
    'controller.saveNow',
    'Xung đột phiên bản',
    'form.submit()',
]:
    assert marker in js, f'native attempt bridge missing {marker}'

attempt = text('attempt.php')
for marker in [
    "$isnative = ($session->worksheetkind ?? '') === 'native';",
    "mod_worksheetgrader/native_attempt",
    "data-region' => 'native-attempt-editor'",
    "submissionhtml",
    "/local/digieranative/styles.css",
]:
    assert marker in attempt, f'attempt.php missing {marker}'

save = text('classes/external/save_attempt.php')
for marker in [
    "'conflict' => true",
    "'conflict' => false",
    "new external_value(PARAM_BOOL, 'Revision conflict')",
]:
    assert marker in save, f'save_attempt.php missing structured conflict contract: {marker}'

grade = text('grade.php')
for marker in [
    "$isnative = ($session->worksheetkind ?? '') === 'native';",
    "echo (string)$attempt->submissionhtml;",
    "$PAGE->requires->css('/local/digieranative/styles.css');",
]:
    assert marker in grade, f'grade.php missing immutable Native preview: {marker}'

picker = text('v12/content.php')
assert "$item->kind==='native'?'N'" in picker, 'picker must label Native worksheets explicitly'

version = text('version.php')
assert "$plugin->version = 2026090701;" in version, 'worksheetgrader preview version bump missing'
assert "'local_digieranative' => 2026090501" in version, 'worksheetgrader must depend on Native runtime'

upgrade = text('db/upgrade.php')
assert "upgrade_mod_savepoint(true, 2026090701, 'worksheetgrader');" in upgrade, 'no-schema Preview savepoint missing'

print('NATIVE_PREVIEW_UI_CONTRACT=PASS')
