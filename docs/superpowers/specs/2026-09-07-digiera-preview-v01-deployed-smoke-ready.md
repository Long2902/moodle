# DIGIERA Preview v0.1 — Deployed / Smoke-Ready Checkpoint

Date: 2026-09-07

Status: **PREVIEW DEPLOYED / POST-UPGRADE RECOVERY VERIFIED / SMOKE READY / NOT YET OPERATOR VERIFIED**

## 1. Deployment outcome

Controlled two-node deployment executed from Web01 (`vm-c47e0dd9`) against Web02 (`moodle-web02`, 10.0.10.12).

Verified during deployment/recovery:

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
TWO_NODE_TREE_IDENTITY=PASS
DB_UPGRADE_ONCE=PASS
```

The shared Moodle DB upgrade ran exactly once on Web01 and completed successfully for:

```text
mod_worksheetgrader
local_digieranative
local_digieraoffice
local_worksheetlibrary
```

## 2. Deployment runner defect and recovery

The first post-upgrade check stopped because the runner attempted to execute a generated schema checker under `/root` as `www-data`, and the original maintenance-state probe also used root context. No automatic DB rollback was attempted.

Recovery runner was created specifically to avoid replaying `admin/cli/upgrade.php`.

Recovery evidence:

```text
PREVIEW_RECOVERY=PASS
DB_UPGRADE_REPLAY=NO
PLUGIN_VERSION_local_digieranative=PASS:2026090501
PLUGIN_VERSION_local_digieraoffice=PASS:2026090501
PLUGIN_VERSION_local_worksheetlibrary=PASS:2026090701
PLUGIN_VERSION_mod_worksheetgrader=PASS:2026090701
FIELD_nativejson=PASS
FIELD_schemaversion=PASS
FIELD_revision=PASS
FIELD_renderedhtml=PASS
EXTERNAL_FUNCTION_local_worksheetlibrary_save_native_draft=PASS
EXTERNAL_FUNCTION_mod_worksheetgrader_save_attempt=PASS
SOURCE_SCHEMA_SERVICE_CHECK=PASS
CACHE_RELOAD_GATE=PASS
FINAL_TWO_NODE_TREE_IDENTITY=PASS
RUNTIME_STATE_RESTORED=PASS
```

Two-node final tree hash:

```text
WEB01_TREE_SHA256=5c263eb64345a92a57d81eef4e019cc5c6d8ba081404a2a6a3a555bef28add50
WEB02_TREE_SHA256=5c263eb64345a92a57d81eef4e019cc5c6d8ba081404a2a6a3a555bef28add50
```

Runtime recovery:

```text
MAINT_BEFORE_RECOVERY=0
RESTORE_CRON1=1
RESTORE_CRON2=0
```

Maintenance was disabled again at the end and Web01 cron timer was restored.

## 3. Current acceptance state

The following gates are complete:

```text
DIRECT_GITHUB_SOURCE=PASS
FAST_CI=PASS
PACKAGE_GATE=PASS
TWO_NODE_DEPLOY=PASS
DB_UPGRADE_ONCE=PASS
TARGETED_SCHEMA_SERVICE_CHECK=PASS
CACHE_RELOAD_GATE=PASS
FINAL_TWO_NODE_TREE_IDENTITY=PASS
PREVIEW_DEPLOY=READY
```

The following remain operator acceptance gates:

```text
NATIVE_LIBRARY_EDITOR=SMOKE_PENDING
AUTOSAVE=SMOKE_PENDING
MANUAL_SAVE=SMOKE_PENDING
OPTIMISTIC_REVISION=SMOKE_PENDING
SESSION_PICKER=SMOKE_PENDING
TEAM_FLOW=SMOKE_PENDING
ATTEMPT_SAVE=SMOKE_PENDING
SUBMIT_FREEZE=SMOKE_PENDING
GRADING_FLOW=SMOKE_PENDING
LEGACY_HTML_REGRESSION=SMOKE_PENDING
OFFICE_REGRESSION=SMOKE_PENDING
PDF_REGRESSION=SMOKE_PENDING
```

Do not claim final RC1 operator verification until these smoke gates have raw operator evidence.

## 4. Next exact action

Run operator smoke on the live Preview path:

```text
Worksheet Library
→ create Native worksheet
→ edit content
→ autosave
→ manual Save
→ publish
→ create/select teaching session
→ attach published worksheet
→ prepare/open team session
→ student Native attempt
→ autosave/manual Save
→ submit
→ verify frozen submission
→ teacher grade + feedback
→ regression HTML / Office / PDF
```

If a smoke step fails, capture the exact URL/page, visible error, browser console/network evidence where relevant, and server/PHP logs before applying a fix.
