# DIGIERA Preview v0.1 — Post-Upgrade Recovery Checkpoint

Date: 2026-09-07

Status: **FILES DEPLOYED TO WEB01/WEB02 / DB UPGRADE COMPLETED ONCE / POST-UPGRADE VERIFICATION INTERRUPTED BY DEPLOY-RUNNER BUG / RECOVERY RUNNER READY / OPERATOR SMOKE PENDING**

## Evidence from controlled deploy run 20260907-103227

The controlled two-node deployment reached and passed:

```text
WEB01_HOST_GUARD=PASS
WEB02_SSH_GUARD=PASS
MOODLE_PATH_GUARD=PASS
PACKAGE_GUARD=PASS
WEB01_STAGE=PASS
WEB02_STAGE=PASS
SNAPSHOT_GATE=PASS
WEB01_FILE_DEPLOY=PASS
WEB02_FILE_DEPLOY=PASS
WEB01_PHP_LINT_FILES=107
WEB02_PHP_LINT_FILES=107
LIVE_STATIC_GATE=PASS
WEB01_TREE_SHA256=5c263eb64345a92a57d81eef4e019cc5c6d8ba081404a2a6a3a555bef28add50
WEB02_TREE_SHA256=5c263eb64345a92a57d81eef4e019cc5c6d8ba081404a2a6a3a555bef28add50
TWO_NODE_TREE_IDENTITY=PASS
DB_UPGRADE_ONCE=PASS
```

Moodle reported successful upgrades for:

```text
mod_worksheetgrader
local_digieranative
local_digieraoffice
local_worksheetlibrary
```

The run then stopped before targeted post-upgrade verification with:

```text
Could not open input file: /root/digiera-preview-controlled-deploy/20260907-103227/schema_check.php
PREVIEW_DEPLOY=STOPPED
```

## Root causes

Two deploy-runner defects were identified.

1. `maintenance_state()` invoked Moodle bootstrap as root. In this environment dataroot is not writable for root, so the function returned a fatal-error string instead of `0` or `1`. The runner then failed its integer comparison and did not reliably enable maintenance.
2. The generated schema checker was stored below `/root/...` but executed as `www-data`. Because `www-data` cannot traverse `/root`, PHP could not open the checker file.

These are deploy-harness defects; the pre-DB live source gates, two-node file identity and Moodle DB upgrade completed successfully.

## TDD regression evidence

A RED contract first failed on the old deploy runner because:

```text
maintenance_state must read Moodle state as www-data
```

After the fix, fresh local verification recorded:

```text
CONTROLLED_DEPLOY_RUNTIME_USER_PATH_CONTRACT=PASS
POST_UPGRADE_RECOVERY_CONTRACT=PASS
LOCAL_RECOVERY_VERIFICATION=PASS
```

The controlled deploy runner was fixed to:

```text
read Moodle maintenance state as www-data
reject non-0/1 maintenance state
create post-upgrade checker under /tmp
chmod checker readable before executing as www-data
```

## Recovery policy

Because DB upgrade already completed, automatic file rollback and DB downgrade are prohibited. Recovery must NOT replay `admin/cli/upgrade.php`.

The dedicated recovery runner:

```text
tools/digiera-preview-post-upgrade-recovery.sh
```

performs only:

```text
Web01/Web02 host guards
read maintenance state as www-data
ensure maintenance during recovery
quiesce cron
verify two-node plugin-tree identity
verify exact installed plugin versions
verify wslib_version Native fields
verify Native external functions
purge caches on both nodes
reload PHP-FPM where active
verify final two-node identity
restore Web01 cron timer to active
preserve Web02 cron timer as inactive
restore maintenance to its state observed before recovery
```

If recovery fails, it keeps cron stopped and maintenance enabled for safe diagnosis.

## Current next action

Run the post-upgrade recovery verifier on Web01. Only after `PREVIEW_RECOVERY=PASS` and `NEXT=OPERATOR_SMOKE_TEST` may operator smoke begin.
