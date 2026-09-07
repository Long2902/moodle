# DIGIERA Media System — RC1 Deployment Live Resume Point

Date: 2026-09-07
Branch: `feature/digiera-media-v1-rc1-fasttrack`
Artifact source commit: `6134eabe8fe28a7245beef995fa380161cec93b4`
Workflow run: `34092280998`
Artifact ZIP SHA256: `7247b82a722d231b0c455a50a8745d5729e264dadd7f9c6bc89e0eecb62b66ee`
Plugin TGZ SHA256: `64925842c37833029399d5a795bfdf773561a635b9d359f325f253b839d0d152`

## Verified pre-deployment state

Web01:
- hostname `vm-c47e0dd9`
- Moodle root `/var/www/moodle/public`
- PHP 8.3.6 CLI
- artifact ZIP and inner TGZ verified
- all three RC1 plugin trees not installed
- rollback snapshot `/root/DIGIERA_MEDIA_PRE_RC1_20260907-135653`
- Moodle cron timer active before deployment

Web02:
- IP `10.0.10.12`
- hostname `moodle-web02`
- Moodle root verified
- all three RC1 plugin trees not installed
- cron timer inactive and cron service inactive
- rollback snapshot `/root/DIGIERA_MEDIA_PRE_RC1_WEB02_20260907-140349`
- exact RC1 ZIP copied from Web01
- Web02 outer ZIP SHA matched expected
- ZIP integrity passed
- build provenance matched source commit/workflow run
- inner TGZ checksum passed
- bundled seed helper PHP lint passed
- `WEB02_PREFLIGHT=PASS`

## First deployment attempt

The first two-node deployment script failed safely in gate 1 while reading the remote TGZ SHA. Error:

`awk: cmd. line:1: {print \\$1}`
`awk: cmd. line:1:        ^ backslash not last character on line`

The script reported:

- `RC1_DEPLOY=FAILED rc=1`
- `MAINT_FLAG=0`

Therefore this attempt failed before cron stop, before maintenance enable, before plugin code installation, and before any Moodle database upgrade. No rollback action is required. The failure is an SSH/awk quoting bug in the deployment helper only, not a product RC1 defect.

## Immediate next action

Patch the deployment helper to avoid remote `awk` quoting entirely when extracting SHA/hash values (use `cut`/`sed` or remote single-purpose shell), then rerun from gate 1. Also patch analogous remote hash parsing paths proactively so the deployment does not stop repeatedly for shell quoting issues.

Do not merge to `main`. Do not claim deployment complete until both nodes pass code hash equality, Moodle upgrade, filter/config checks, cache purge, PHP-FPM reload, maintenance exit, and cron role checks.
