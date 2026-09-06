# DIGIERA Moodle Spec Kit — Preview v0.1 Resume Point

**Date:** 2026-09-07  
**Type:** Versioned continuation / resume point  
**Project:** DIGIERA Worksheet Platform / Moodle 5.1  
**Current strategy:** Preview v0.1 vertical slice / Fastlane  
**Status:** **DESIGN + PLAN COMMITTED / FASTLANE PREPARED / MATERIALIZATION BLOCKED BY `GH_CLI_MISSING` / NOT DEPLOYED / NOT OPERATOR VERIFIED**

---

## 1. Purpose

This is the authoritative handoff point for starting a new ChatGPT conversation.

The operator approved a strategy change from completing every subsystem sequentially to a **Preview v0.1 vertical slice**, so the complete teaching workflow can be tested much earlier:

```text
Teacher
  Kho phiếu
  -> create/edit Native worksheet
  -> save draft
  -> publish
  -> create session
  -> select worksheet
  -> create teams
  -> open session

Student
  -> open session
  -> edit
  -> save progress
  -> submit

Teacher
  -> view immutable submission
  -> grade
  -> feedback
```

Preview v0.1 deliberately does not wait for full DOCX import, realtime Yjs collaboration, Office hardening, final Backup/Restore + Course Publisher acceptance, load testing, or all 10 mockup pixel comparisons.

## 2. Production topology and permanent rules

```text
Moodle:       5.1
Moodle root:  /var/www/moodle/public
PHP:          8.3
Theme:        Edwiser RemUI 5.2.2

Web01:        vm-c47e0dd9 / 10.0.10.11
Web02:        10.0.10.12
DB:           10.0.20.31 / moodle / shared
DB prefix:    mdl7w_
Redis:        10.0.20.41
NFS:          10.0.20.21
Cron:         Web01 only
```

Permanent engineering rules:

```text
- Do not develop directly in /var/www/moodle/public.
- Production Moodle/DB stays untouched during GitHub/CI implementation.
- Shared DB mutation runs once only.
- Controlled deployment must keep Web01/Web02 plugin trees identical.
- RemUI global navigation/header/theme/floating controls remain unchanged.
- All DIGIERA CSS remains plugin-scoped.
- Never expose secrets/tokens/JWT/private keys in source, logs, Spec Kit or chat.
- Never mark runtime behavior VERIFIED without direct runtime/operator evidence.
- Preserve historical Spec Kit snapshots.
- FEATURE_BACKUP_MOODLE2 is a gate, not proof.
- Custom mod_* needs real Moodle 2 Backup/Restore before Course Publisher compatibility is claimed.
```

## 3. Architecture retained

```text
local_digieranative
  -> Native JSON schema/validator/renderer
  -> ProseMirror editor
  -> Word-like Ribbon
  -> canonical worksheet <-> ProseMirror adapter
  -> save/autosave hooks

local_worksheetlibrary
  -> global worksheet repository
  -> folders / versions / publish
  -> course/section virtual bindings
  -> Native draft persistence

mod_worksheetgrader
  -> sessions
  -> frozen worksheet snapshots
  -> teams/members
  -> attempts/submissions
  -> grading/feedback

local_digieraoffice
  -> Office compatibility infrastructure
  -> not required by Native Preview path
```

Canonical Native root:

```json
{"type":"worksheet","version":1,"content":[]}
```

Server-side Native validation remains authoritative.

## 4. Native Editor M1C verified baseline

```text
DIGIERA_NATIVE_CORE_CHECKPOINT_M1C_2026-09-06.zip
SHA256:
b1b79f465e118ac8bf8326dd0a9137e11d8c45616ab2a60b3bf5ca4bc07e927b
```

Task 4 final Web01 evidence:

```text
DOM_CODEC=PASS
RIBBON_SHELL=PASS
READONLY_CONTRACT=PASS
SAVE_HOOK=PASS
KEYBOARD_BINDINGS=PASS
PASTE_SANITIZATION=PASS
BROWSER_LIKE_EDITOR_SMOKE=PASS
SCOPED_CSS_SAFETY=PASS
SOURCE_SYNTAX=PASS
FINAL_FULL_TEST_SUITE=PASS
DETERMINISTIC_BUILD=PASS
AMD_WRAPPER=PASS
AMD_COMMAND_EXPORT=PASS
SOURCE_MAP_REFERENCE=NONE
BUILD_PATH_LEAK=NONE
PRODUCTION_DEP_AUDIT=PASS
PACKAGE_PRIVATE_CONTRACT=PASS
JSDOM_PACKAGE_CONTRACT=PASS
```

Final Task 4 AMD:

```text
SHA256:
21047e235892b6db90ea07afe30938bb2e4eda6d07db7ec4535876ba0c635e6b
Bytes: 619707
```

## 5. Historical Task 5–6 code-ready lineage

Previous isolated M1 Finish implementation:

```text
a739238 feat(native): finish M1 persistence and library integration
```

Recorded implementation includes Native DB fields, `native_version_service::save_draft()`, optimistic revision protection, server-side validation/rendering, External API, autosave/manual Save, Vietnamese save/conflict states, Native worksheet kind, Native detail-page editor and legacy route preservation.

Qualification:

```text
a739238 is historical/local implementation lineage.
Do NOT assume it exists on the current Preview GitHub branch.
Use it as implementation reference/evidence unless source is explicitly recovered.
```

Reference resume point:

```text
DIGIERA_MOODLE_SPECKIT_2026-09-06_NATIVE_M1_CODE_READY_CI_PENDING.md
```

## 6. Worksheet Platform V12 RC1 artifacts

```text
local_digieraoffice_moodle51_1.0.0-rc1.zip
SHA256: ad9948e7ee7e9a37e6e968cee0e81d8adc043342f225c802e52bb9e1ed4a3a1d

local_worksheetlibrary_moodle51_1.0.0-rc1.zip
SHA256: b897c5eeae1e0195c2637d9151cad1afb57823c39bbf8b37ca15d98b6bcfa433

mod_worksheetgrader_moodle51_12.0.0-rc1.zip
SHA256: c87a41535c9f4f5f3fcd0ef3ca92dde1fb1c4a0895d8b2bea87271d4735b75d7
```

RC1 engineering evidence previously included 90 PHP lint passes, 18 JavaScript syntax passes, XMLDB parse PASS, EN/VI parity PASS, V12/ONLYOFFICE contracts PASS, CSS scope PASS, Team Studio no-reload PASS and secret/IP scans PASS. RC1 is engineering RC only; it is not production-verified.

## 7. Preview v0.1 approved strategy

Operator approval:

```text
duyệt preview v0.1
```

Included in earliest testable slice: Native Worksheet Library editing, draft autosave/manual Save, optimistic revision, publish, session creation, published worksheet selection, Team Studio/grouping, readiness/open, Native attempt, save progress, submit/freeze, grading/feedback and HTML/Office/PDF regression reachability.

Deferred: 445 DOCX batch importer, Yjs/Hocuspocus realtime collaboration, complex Office fallback hardening, 100/300/500/1000 load tests, full 10-mockup visual diff, final Backup/Restore, final Course Publisher real-copy and global production rollout.

Preview collaboration mode:

```text
collaboration.mode = off
```

## 8. GitHub source of truth

```text
Repository: Long2902/moodle
Baseline:   v5.1.0
Branch:     digiera/preview-v01
```

Baseline `v5.1.0` must remain untouched.

Verified Preview branch HEAD before this checkpoint commit:

```text
31dfb37d9b18759fd74dd0f72067c7a78994ddfd
ci(preview): disable full Moodle Core lane on DIGIERA branches
```

Recent Preview commits:

```text
722629c9d88187a5863da4d90a68922f1fa7fc8c
docs(spec): approve DIGIERA Preview v0.1 vertical slice

02b3b01f6d502dbb2d976c4feb9a601993e0b689
docs(plan): add DIGIERA Preview v0.1 implementation plan

0da3dbe2a6ca681dfa3bc356390dc41687004919
chore(preview): normalize Web01 fastlane materializer

ce97d49fa6ef790d216e6491474d97d8537a845b
ci(preview): add fast gate for materialized worksheet source

31dfb37d9b18759fd74dd0f72067c7a78994ddfd
ci(preview): disable full Moodle Core lane on DIGIERA branches
```

Approved files:

```text
docs/superpowers/specs/2026-09-06-digiera-preview-v01-design.md
docs/superpowers/plans/2026-09-06-digiera-preview-v01.md
```

Macro plan:

```text
Task 1  Direct materialization + Fast CI
Task 2  Native draft persistence + publish bridge
Task 3  Session -> attempt -> submit -> grade vertical slice
Task 4  Disposable Preview CI + package gate
Task 5  Controlled operator Preview smoke
```

## 9. Current Fastlane tooling

Committed script:

```text
tools/digiera-preview-web01-fastlane.sh
Git blob SHA:
fd01aa45a6c973c5bfa62a7aa926764c77da5ef4
```

Intended flow: locate Native + V12 RC1 verified sources, assemble `/root/digiera-preview-fastlane/stage`, run PHP/XMLDB/Native JS/AMD gates, then use authenticated `gh api` Git Data blobs/tree/commit to atomically materialize source into `digiera/preview-v01`, writing `/root/digiera-preview-fastlane/summary.txt`.

This path replaces the earlier failed base64/chunk bootstrap transport.

## 10. Latest operator evidence and blocker

```text
===== DIGIERA PREVIEW V0.1 FASTLANE LAUNCHER =====
FASTLANE_LAUNCH=BLOCKED
REASON=GH_CLI_MISSING
```

Exact interpretation:

```text
GH CLI on Web01:                MISSING
Fastlane script fetched:        NO
Fastlane stage assembled:       NO
Plugin baseline materialized:   NO
Fast CI on materialized source: NO
GitHub plugin source commit:    NO
Production mutation:            NO
```

This is a tooling blocker, not a source/test failure. The launcher exits before script download when `gh` is missing. Possible later blockers `GH_NOT_AUTHENTICATED` and `FASTLANE_SOURCE_SET=INCOMPLETE` must not be assumed until observed.

## 11. Exact continuation instructions

### Step 1 — Check GitHub before changing anything

Verify `Long2902/moodle`, branch `digiera/preview-v01`. If HEAD is newer than the snapshot, inspect later commits first. Do not redesign Preview v0.1 again.

### Step 2 — Resolve `GH_CLI_MISSING` on Web01

```bash
apt-get update
apt-get install -y gh
gh --version
```

Authenticate without pasting a long-lived token into chat/logs:

```bash
gh auth login -h github.com -p https -w
gh auth status
```

### Step 3 — Rerun the exact Fastlane launcher

Use the same launcher from the operator's previous turn. It fetches `tools/digiera-preview-web01-fastlane.sh` from `digiera/preview-v01`, runs it, then prints `/root/digiera-preview-fastlane/summary.txt`.

### Step 4 — Branch on observed result

If `FASTLANE=PASS`: verify the new GitHub HEAD/materialization commit and Fast CI, then continue Preview Task 2.

If `FASTLANE_SOURCE_SET=INCOMPLETE`: do not touch production; supply the exact three RC1 ZIPs in `/root/`, verify hashes from §6 and rerun.

For another failure inspect only:

```text
/root/digiera-preview-fastlane/fastlane.log
/root/digiera-preview-fastlane/summary.txt
```

Do not rollback automatically unless an actual mutation requires it. The stage is isolated.

## 12. Preview completion gates

```text
DIRECT_GITHUB_SOURCE=PASS
FAST_CI=PASS
E2E_VERTICAL_SLICE=PASS
NATIVE_LIBRARY_EDITOR=PASS
AUTOSAVE=PASS
MANUAL_SAVE=PASS
OPTIMISTIC_REVISION=PASS
SESSION_PICKER=PASS
TEAM_FLOW=PASS
ATTEMPT_SAVE=PASS
SUBMIT_FREEZE=PASS
GRADING_FLOW=PASS
PREVIEW_DEPLOY=READY
```

Operator smoke after controlled deployment:

```text
Kho phiếu -> Native edit/save -> publish
-> create session -> select worksheet -> teams -> open
-> student edit/save/submit
-> teacher grade/feedback
-> confirm RemUI global shell unchanged
```

## 13. Explicit non-claims

```text
PREVIEW SOURCE MATERIALIZED TO GITHUB:  NO
FAST CI ON MATERIALIZED SOURCE:         NO
DISPOSABLE E2E CI:                      NO
PREVIEW DEPLOYED:                       NO
OPERATOR END-TO-END VERIFIED:           NO
445 DOCX BATCH IMPORT:                  NOT COMPLETE
REALTIME YJS COLLABORATION:             NOT COMPLETE
OFFICE FALLBACK FINAL ACCEPTANCE:       NOT COMPLETE
BACKUP/RESTORE FINAL ACCEPTANCE:        NOT COMPLETE
COURSE PUBLISHER FINAL ACCEPTANCE:      NOT COMPLETE
100/300/500/1000 LOAD TEST:             NOT COMPLETE
10-MOCKUP PIXEL ACCEPTANCE:             NOT COMPLETE
GLOBAL PRODUCTION ENABLEMENT:           NO
```

The failed launcher performed no production mutation.

## 14. Recommended first message in a new chat

```text
Tiếp tục dự án DIGIERA Worksheet Preview v0.1 từ Spec Kit
`DIGIERA_MOODLE_SPECKIT_2026-09-07_PREVIEW_V01_RESUME_FASTLANE_BLOCKED_GH_CLI.md`.

Hãy đọc trạng thái hiện tại và tiếp tục đúng mục “Exact continuation instructions”.
GitHub source of truth là `Long2902/moodle`, branch `digiera/preview-v01`.
Latest observed blocker trên Web01 là `REASON=GH_CLI_MISSING`.
Không thiết kế lại từ đầu, không đụng production, và tiếp tục theo Fastlane/Vertical Slice cho tới khi tôi có thể test end-to-end.
```

## 15. Snapshot status

```text
PREVIEW V0.1 DESIGN APPROVED:           YES
IMPLEMENTATION PLAN COMMITTED:          YES
GITHUB PREVIEW BRANCH:                  YES
FASTLANE SCRIPT COMMITTED:              YES
FAST CI WORKFLOW PREPARED:              YES
FULL MOODLE CORE LANE DISABLED
  FOR DIGIERA BRANCHES:                 YES
LATEST BLOCKER:                         GH_CLI_MISSING
BLOCKER CLASS:                          TOOLING / WEB01
SOURCE FAILURE OBSERVED:                NO
PRODUCTION MUTATION:                    NO
NEXT ACTION:
Install/authenticate GitHub CLI on Web01,
rerun the exact Fastlane launcher,
then continue from observed summary.
```