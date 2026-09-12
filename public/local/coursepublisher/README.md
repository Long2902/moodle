# local_coursepublisher — 1.1.1 production

Course Publisher for Moodle 5.1.

## Production capabilities

## 1.1.1 topology auto-discovery

Production-final baseline promoted after live validation on 2026-08-29. The validated production scenarios include 8-target CDS fan-out with all child jobs committed, and a fail-closed duplicate-origin scenario with 7 targets committed and 1 target blocked before mutation.

Course Publisher keeps configured target bindings as the publishing safety boundary, but can now discover and register that topology in bulk.

- `Nhóm đích / Khối` generalises the former fixed Grade model. Existing `10`, `11`, `12` bindings are migrated automatically; administrators can add keys such as `DMST`, `CDS`, or other publishing families without code changes.
- Every Target Group belongs to one Program and can have its own master course. Its technical key is immutable after creation.
- `Quy tắc nhận diện` maps Moodle course `fullname` or `shortname` to a Target Group with `contains`, `starts_with`, or administrator-authored `regex` rules. Cross-group ambiguity fails closed.
- Global container rules identify deployment branches such as `Khối THPT`, `Khối Liên Cấp`, and `Trung tâm`. These rules are configurable in the UI.
- Each Program has an explicit deployment discovery root. The scanner never reads eligible topology outside that category subtree.
- Within the configured root, direct children are Regions; matching direct child containers under each Region contain direct-child School/Unit categories. Courses in the Unit category or its descendants are eligible for classification.
- Discovery is preview-first. Results distinguish `READY_NEW`, `EXISTING`, `UNMATCHED`, `MISSING`, and fail-closed conflict/invalid states. Only `READY_NEW` rows can be selected for registration.
- The preview includes a **Select all READY** control for large topology onboarding.
- Registration re-scans and revalidates selected candidates before writing configuration, then reuses the existing `save_region()`, `save_school()`, and `save_target()` invariants.
- Re-scanning is idempotent: already registered routes appear as `EXISTING`; the scanner never silently rewrites an existing conflicting binding.
- Auto-generated topology codes are stable Moodle-ID based values: `AUTO_R_<categoryid>` and `AUTO_U_<categoryid>`.
- Discovery never creates Moodle categories/courses and never publishes learning content. The 1.1.0 Batch fan-out engine remains the only multi-target publishing path.
- Fresh Programs receive conservative `10/11/12` Target Groups and recognition rules; special groups remain explicitly administrator-created.

### Multi-target fan-out (1.1.0)

- One master Activity or Section can be distributed to many configured target bindings across multiple schools and regions.
- Target discovery is limited to configured `Program + Target Group` bindings; the plugin never guesses arbitrary courses.
- Every selected target is snapshotted and preflighted independently; blocked targets remain in Batch history and are never forced.
- Placement is portable: automatic structural matching is the default, with a manual logical override for the whole Batch. Cross-course Section IDs/CMIDs are never reused as logical identifiers.
- One child job still mutates exactly one target course using the proven 1.0.0 backup/restore engine and per-target lock.
- The scheduled Batch dispatcher exposes at most 10 Batch child jobs in `queued + running` state globally and exits quickly; no daemon and no second Moodle cron node are introduced.
- Safe retry is limited to jobs proven to have `mutationstate=none`; uncertain mutation requires manual review.
- Successful publication records durable source-to-target origin mappings. A current mapping blocks duplicate publication in 1.1.0; update/sync semantics are intentionally deferred.


- Program, source-course, region, school and target-course routing.
- Topology health checks and route preflight.
- Durable background publish jobs.
- Target-course locking.
- Idempotency keys and execution-time source/topology revalidation.
- Activity publishing to an exact target section/position.
- Section publishing at course start/end or immediately before/after an existing peer section.
- Moodle delegated `mod_subsection` support.
- Job/item audit and persistent target mappings.
- Real content mutation delegated to Moodle Core backup/restore and course APIs.

## Source IDs

- Section source: `course_sections.id`.
- Activity source: `course_modules.id` / CMID.

## Generic activity support

Course Publisher does not keep a hard-coded allow-list of activity types.

An installed activity/resource is eligible for the generic publisher when Moodle reports:

```php
plugin_supports('mod', $modname, FEATURE_BACKUP_MOODLE2, false)
```

Custom activity modules therefore must implement working Moodle 2 backup **and restore** support, and declare:

```php
FEATURE_BACKUP_MOODLE2 => true
```

in their `[modname]_supports()` callback.

Declaring the feature flag alone is not enough. The module must also provide valid backup/restore classes under:

```text
mod/[modname]/backup/moodle2/
```

including the activity task and steps files required by Moodle's Backup/Restore APIs.

## Capabilities

- `local/coursepublisher:view`
- `local/coursepublisher:configureprograms`
- `local/coursepublisher:configuretopology`
- `local/coursepublisher:bindcourses`
- `local/coursepublisher:preview`
- `local/coursepublisher:publish`

## Runtime model

Real publishing is executed through Moodle adhoc tasks:

```text
preflight
-> durable Batch/target snapshot (multi-target)
-> waiting child job per READY target
-> controlled dispatcher
-> queue
-> target lock
-> execution-time revalidation
-> Moodle Core backup/restore
-> exact placement
-> postconditions
-> audit
-> unlock
```

Real-mutation jobs are not blindly auto-retried after an uncertain outcome because doing so could create duplicates.

## Platform

- Moodle 5.1 build `2025100600` or newer in the 5.1 branch.
- Plugin directory: `local/coursepublisher`.
- UI assets stay inside this plugin; no RemUI core modification is required.
