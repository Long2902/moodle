from pathlib import Path

repo = Path(__file__).resolve().parents[2]
deploy = (repo / 'tools/digiera-preview-controlled-deploy.sh').read_text()

body = deploy.split('maintenance_state() {', 1)[1].split('\n}', 1)[0]
assert 'runuser -u www-data' in body or 'run_moodle_local' in body, (
    'maintenance_state must read Moodle state as www-data'
)
assert 'SCHEMA_CHECK="$RUNROOT/schema_check.php"' not in deploy, (
    'schema checker cannot live under /root when executed as www-data'
)
assert ('SCHEMA_CHECK="/tmp/' in deploy or 'mktemp /tmp/' in deploy), (
    'schema checker must be in a www-data-traversable path'
)

recovery_path = repo / 'tools/digiera-preview-post-upgrade-recovery.sh'
assert recovery_path.exists(), 'post-upgrade recovery runner must exist'
recovery = recovery_path.read_text()
for marker in [
    'DB_UPGRADE_REPLAY=NO',
    'SOURCE_SCHEMA_SERVICE_CHECK=PASS',
    'FINAL_TWO_NODE_TREE_IDENTITY=PASS',
    'PREVIEW_RECOVERY=PASS',
    'runuser -u www-data',
    'maintenance.php" --enable',
    'maintenance.php" --disable',
    'moodle-cron.timer',
    '/tmp/digiera-preview-recovery-check-',
]:
    assert marker in recovery, f'missing recovery contract marker: {marker}'
assert 'admin/cli/upgrade.php' not in recovery, 'recovery runner must not replay DB upgrade'

print('DIGIERA_PREVIEW_DEPLOY_RECOVERY_CONTRACT=PASS')
