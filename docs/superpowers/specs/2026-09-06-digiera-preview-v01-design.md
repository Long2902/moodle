# DIGIERA Worksheet Preview v0.1 — Vertical Slice Design

## Status

Approved by operator on 2026-09-06. This spec supersedes the previous sequencing strategy only; it does **not** replace the approved Native Editor, Worksheet Library, WorksheetGrader, backup/restore, Course Publisher, collaboration, import, or visual contracts.

## Goal

Deliver the earliest safe end-to-end build that the operator can test across the complete teaching workflow:

```text
Teacher
  Kho phiếu
  -> tạo/mở Native worksheet
  -> lưu draft
  -> tạo buổi học
  -> chọn phiếu
  -> tạo nhóm
  -> mở buổi

Student
  -> mở buổi
  -> làm bài
  -> lưu tiến độ
  -> nộp bài

Teacher
  -> xem bài nộp
  -> chấm điểm
  -> phản hồi
```

The preview is intentionally a vertical slice. It does not wait for 445-DOCX batch import, realtime Yjs collaboration, Office fallback hardening, Course Publisher acceptance, large-load testing, or 10-mockup pixel acceptance.

## Architecture

Preview v0.1 combines the already verified Native Editor M1C code with the Worksheet Platform V12 RC1 components:

```text
local_digieranative
  Native JSON schema / renderer / editor / Ribbon

local_worksheetlibrary
  global worksheet repository
  Native draft persistence
  publish/version selection

mod_worksheetgrader
  sessions / worksheet snapshot / teams
  attempts / submit / grading

local_digieraoffice
  retained as compatibility infrastructure
  not a dependency for Native preview path
```

### Source of truth

GitHub repository: `Long2902/moodle`

Baseline: `v5.1.0`

Preview branch: `digiera/preview-v01`

Moodle 5.1 plugin paths use the split public layout:

```text
public/local/digieranative
public/local/worksheetlibrary
public/local/digieraoffice
public/mod/worksheetgrader
```

No base64 transport/bootstrap chunks are used. Source is committed directly through Git blobs/trees/commits.

## Preview data contracts

### Native document

Canonical JSON root remains:

```json
{
  "type": "worksheet",
  "version": 1,
  "content": []
}
```

Server-side validation remains authoritative. Browser state is never trusted as the sole validator.

### Worksheet Library Native version

`wslib_version` adds:

```text
nativejson      TEXT nullable
schemaversion  INT nullable
revision       INT default 0
renderedhtml   TEXT nullable
```

Native draft save uses optimistic concurrency:

```text
client sends expected revision N
server row-locks draft
if current revision != N -> conflict
else validate canonical Native JSON
     render server-side immutable preview HTML
     revision = N + 1
     commit
```

The editor shows at minimum:

```text
Đang lưu…
Đã lưu
Xung đột phiên bản
```

Manual Save and autosave use the same server endpoint/service contract.

### Session snapshot

Preview sessions select a published Worksheet Library version. Session configuration stores a frozen worksheet snapshot sufficient to keep the learning activity stable if the library item later changes.

### Attempt/submission

For Preview v0.1, collaboration defaults to OFF. Each learner/team attempt owns its own Native answer state. Save progress may update an in-progress attempt; Submit freezes an immutable submission revision. Grading reads the frozen submitted state, not the mutable draft.

## Preview feature scope

### Included

- Native worksheet creation/editing in Worksheet Library.
- Native draft autosave + manual Save.
- Optimistic revision conflict protection.
- Native publish action.
- Session creation.
- Worksheet picker using published Native versions.
- Team/group creation using existing V12 team services.
- Open/ready lifecycle.
- Student Native attempt editor.
- Save progress.
- Submit/freeze.
- Teacher grading and feedback using existing grading service where possible.
- HTML/Office/PDF library routes remain reachable as regression paths.
- Vietnamese-first UI with EN/VI key parity.
- Plugin-scoped CSS only; RemUI navigation/header/theme chrome unchanged.

### Deferred but interfaces preserved

- Yjs/Hocuspocus realtime collaboration.
- 445-file DOCX batch importer.
- complex DOCX/Office fallback acceptance.
- 100/300/500/1000 collaboration load tests.
- full visual overlay/diff against all 10 approved mockups.
- production Course Publisher acceptance.
- final backup/restore acceptance.

## Collaboration preview mode

A preview flag controls collaboration:

```text
collaboration.mode = off
```

The Native attempt interface must remain compatible with later Yjs integration, but Preview v0.1 must not depend on a websocket service to function.

## Safety and portability

- Production Moodle and production DB are not mutated during GitHub/CI implementation.
- Moodle DML/XMLDB APIs only; no raw broad `ALTER TABLE` scripts.
- No secrets in repository, browser payloads, logs, or Spec Kit.
- `FEATURE_BACKUP_MOODLE2` remains a gate, not proof. Preview can carry existing V12 backup code, but final portability is not claimed until real cross-course restore + Course Publisher smoke passes.
- Submitted state is immutable from the grading path.
- Existing RemUI global shell remains unchanged.

## CI strategy

### Fast gate

Runs on every Preview source change:

```text
PHP lint
XMLDB parse/contracts
Native standalone validator/renderer
Native JS Vitest
AMD deterministic build
source/path/secret guards
```

### E2E preview gate

Disposable Moodle + DB:

```text
install plugins
create fixture course/users
create Native worksheet
save draft
publish
create WorksheetGrader session
select worksheet
create team
save attempt
submit
create grade/feedback
assert frozen submitted snapshot
```

### Full hardening gate

Deferred from Preview acceptance:

```text
445 DOCX corpus
realtime collaboration
backup/restore + Course Publisher
100/300/500/1000 load
10-mockup visual diff
```

## Preview deployment gate

Preview v0.1 may be deployed to a controlled Moodle preview/pilot only after Fast + E2E gates pass. Production-wide enablement is explicitly out of scope.

Operator smoke after controlled deployment:

```text
1. open Worksheet Library
2. create/edit Native worksheet
3. confirm autosave/manual Save
4. publish
5. create session
6. select worksheet
7. create teams
8. open session
9. student edits + saves + submits
10. teacher grades + feedback
11. confirm global RemUI shell unchanged
```

## Acceptance

Preview v0.1 is considered testable when:

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

It is **not** called production-ready until the deferred hardening gates pass.
