# Course Publisher 1.1.1 — Topology Auto-Discovery & Target Groups Design

## Status

Approved design for implementation.

## Baseline

This design extends the currently deployed Course Publisher 1.1.0 production line:

```text
component: local_coursepublisher
version:   2026082901
release:   1.1.0-production
Moodle:    5.1
requires:  2025100600
```

The 1.1.0 multi-target Batch engine remains the publishing engine. Version 1.1.1 adds a configuration/discovery subsystem above it and generalizes the current hard-coded Grade model into configurable Target Groups.

The existing runtime safety model remains unchanged:

```text
one child job -> one target course
per-target lock
execution-time revalidation
source fingerprint
logical placement resolver
Moodle Core backup/restore
mutation-state tracking
origin mapping
audit
controlled dispatcher
```

No discovery operation publishes course content.

---

# 1. Problem

Course Publisher 1.1.0 can fan-out one source Activity/Section to many target courses, but only after every target has already been configured manually as:

```text
Region
-> School/Unit
-> Program + Grade target binding
```

For a deployment tree with many provinces/regions, schools, inter-level schools and centres, manual registration becomes operationally expensive.

The Moodle category tree already contains most of the required topology. The new subsystem should discover that existing topology, classify courses using administrator-configurable rules, preview the result, and bulk-register only safe targets.

The system must preserve the existing binding layer. Publishing must never directly scan arbitrary Moodle courses at publish time.

The intended flow is:

```text
Moodle category/course tree
        ↓
Topology Auto-Discovery
        ↓
Configurable recognition rules
        ↓
Preview / conflict detection
        ↓
Bulk registration
        ↓
Normal Course Publisher bindings
        ↓
Existing 1.1.0 Batch fan-out
```

---

# 2. Scope

## 2.1 Included

Version 1.1.1 will provide:

```text
✓ configurable Target Groups
✓ automatic migration of existing 10/11/12 Grade data
✓ configurable master course per Target Group
✓ configurable course-recognition rules
✓ configurable deployment root
✓ configurable allowed container patterns
✓ scan Khối THPT
✓ scan Khối Liên Cấp
✓ scan Trung tâm / equivalent configured containers
✓ Region discovery
✓ School/Unit discovery
✓ course-to-Target-Group classification
✓ fullname + shortname matching
✓ contains / starts_with / regex matching
✓ preview before registration
✓ READY / EXISTING / UNMATCHED / CONFLICT results
✓ bulk registration
✓ idempotent re-scan
✓ existing wrong-binding detection
✓ audit
✓ full compatibility with 1.1.0 Batch fan-out
```

## 2.2 Explicitly excluded

Version 1.1.1 will not:

```text
✗ create Moodle categories
✗ clone/create Moodle courses
✗ rename or move Moodle courses
✗ delete Moodle courses
✗ publish content immediately after scanning
✗ automatically repair conflicts
✗ automatically overwrite existing bindings
✗ use fuzzy/AI matching
```

---

# 3. Observed Moodle deployment topology

The deployment tree is not a simple fixed-depth tree. The scanner must not assume that every school is at an absolute category depth.

The observed pattern is:

```text
02_TRIEN_KHAI_TINH
├─ <Region>
│  ├─ Khối THCS
│  ├─ Khối THPT
│  │  ├─ <School/Unit>
│  │  │  ├─ Course
│  │  │  └─ Course
│  │  └─ ...
│  ├─ Khối Liên Cấp
│  │  └─ <School/Unit>
│  │     └─ Course
│  └─ Trung tâm / Trung Tâm / Trung tâm <Region>
│     └─ <Centre/Unit>
│        └─ Course
└─ <Region>
   └─ ...
```

Rules:

```text
Region = direct child of the explicitly configured deployment root.

Container = a descendant/direct child under a Region whose name matches an
enabled container rule.

Unit = a direct child of a matched container.

Courses = courses located in the Unit category or allowed descendants
beneath that Unit.
```

The scanner must never treat the container itself as a School/Unit.

The scanner must never scan outside the configured deployment root.

---

# 4. Target Group model

## 4.1 Generalization

The current implementation uses:

```text
Program + Grade
```

where Grade is hard-coded to:

```text
10
11
12
```

Version 1.1.1 generalizes this to:

```text
Program + Target Group
```

Examples:

```text
Program AI_2627
├─ group 10
│  └─ Master: LỚP 10 [AI_L10]
├─ group 11
│  └─ Master: LỚP 11 [AI_L11]
├─ group 12
│  └─ Master: LỚP 12 [AI_L12]
├─ group DMST
│  └─ Master: AI Và Đổi Mới Sáng Tạo Trẻ
└─ group CDS
   └─ Master: Công dân số và Hướng nghiệp trong kỷ nguyên AI
```

Every special course family intended for independent publishing gets its own Target Group and its own master course.

## 4.2 New table: `local_cp_target_group`

Fields:

```text
id
programid
groupkey          char(64)
name              char(255)
enabled           int(1)
sortorder         int
timecreated
timemodified
```

Constraints:

```text
UNIQUE(programid, groupkey)
FK programid -> local_cp_program.id
```

`groupkey` is a stable technical identifier such as:

```text
10
11
12
DMST
CDS
AI_CLUB
```

The display name may be changed without changing the key.

## 4.3 Seed migration

For every existing Program, upgrade creates:

```text
10 -> Lớp 10
11 -> Lớp 11
12 -> Lớp 12
```

Existing master/target bindings are mapped to those records.

---

# 5. Compatibility strategy for existing `gradekey`

The 1.1.0 codebase uses `gradekey` broadly in forms, routes, jobs and Batch records. Version 1.1.1 must not perform a destructive rename.

## 5.1 Compatibility field

Existing `gradekey` columns remain, but their width is expanded from:

```text
char(2)
```

to:

```text
char(64)
```

They become a compatibility key that may contain any Target Group key.

Examples:

```text
10
11
12
DMST
CDS
```

## 5.2 Canonical identity

New/active bindings additionally carry:

```text
targetgroupid
```

The canonical relationship is Target Group ID.

`gradekey` remains populated with the group's `groupkey` so:

```text
old URL parameters
old service signatures
existing 1.1.0 entrypoints
historical Batch screens
existing audit logs
```

remain readable.

## 5.3 Tables receiving `targetgroupid`

Additive `targetgroupid` fields are added to:

```text
local_cp_master
local_cp_target
local_cp_batch
```

Where practical, new runtime records resolve by `targetgroupid`; compatibility helpers can still accept a group key string.

Historical records are not rewritten beyond deterministic migration of 10/11/12.

---

# 6. Master and target bindings after 1.1.1

## 6.1 Master

Before:

```text
Program + gradekey -> master course
```

After:

```text
Program + Target Group -> master course
```

Existing example:

```text
AI_2627 + group 10 -> course #158
AI_2627 + group 11 -> course #159
AI_2627 + group 12 -> course #160
```

## 6.2 Target

Before:

```text
Program + School + gradekey -> target course
```

After:

```text
Program + School/Unit + Target Group -> target course
```

The existing target uniqueness invariant remains:

```text
one Program + one Unit + one Target Group -> at most one active target course
```

An active Moodle course may not be reused by another active target binding.

---

# 7. Recognition rules

## 7.1 New table: `local_cp_discovery_rule`

Fields:

```text
id
targetgroupid
name
field             fullname | shortname
matchtype         contains | starts_with | regex
pattern           text
priority          int
enabled           int(1)
sortorder         int
timecreated
timemodified
```

Foreign key:

```text
targetgroupid -> local_cp_target_group.id
```

A Target Group may have multiple rules.

Example:

```text
Group 10:
  fullname contains "LỚP 10"
  fullname contains "LOP 10"
  shortname contains "_L10_"
  shortname contains "_AI_L10_"
```

Example special group:

```text
Group DMST:
  fullname contains "AI Và Đổi Mới Sáng Tạo Trẻ"
  shortname contains "_dmst_"
```

## 7.2 Matching normalization

Before non-regex comparison:

```text
trim
collapse repeated whitespace
Unicode-aware case folding where supported
case-insensitive comparison
```

The original Moodle fullname/shortname is always retained for preview/audit.

Regex rules are administrator-authored and must be validated before save.

Invalid regex cannot be enabled.

## 7.3 Rule semantics

Rules are OR conditions within the same Target Group.

If multiple enabled rules from the same Target Group match one course:

```text
still one Target Group match
```

Priority may order/evaluate rules within the same group, but priority must not silently resolve cross-group ambiguity.

If one course matches rules belonging to more than one Target Group:

```text
CONFLICT_GROUP
```

No automatic registration is allowed.

---

# 8. Container recognition rules

The scanner must not permanently hard-code:

```text
Khối THPT
Khối Liên Cấp
Trung tâm
```

## 8.1 New table: `local_cp_discovery_container`

Fields:

```text
id
name
matchtype         contains | starts_with | regex
pattern
enabled
sortorder
timecreated
timemodified
```

Seed defaults:

```text
KHỐI THPT
KHỐI LIÊN CẤP
TRUNG TÂM
```

Matching is case-insensitive and whitespace-normalized for non-regex types.

This allows future additions such as:

```text
TRUNG TÂM GDTX
TRƯỜNG NGHỀ
ĐƠN VỊ ĐỐI TÁC
```

without code modification.

---

# 9. Deployment root

## 9.1 Program-level configuration

Add to `local_cp_program`:

```text
discoveryrootcategoryid int(10) default 0
```

The discovery UI requires an explicit root before scanning.

For the current deployment this points to:

```text
02_TRIEN_KHAI_TINH
```

The scanner must reject:

```text
root = 0
root category missing
root outside a valid Moodle category
```

It never auto-selects a root by name.

## 9.2 Safety boundary

Only descendants of `discoveryrootcategoryid` are eligible.

Therefore source/master areas such as:

```text
01_KHO_NOI_DUNG_GOC
demo courses
other site categories
```

cannot be accidentally registered by discovery.

---

# 10. Topology scanner

## 10.1 Region discovery

For the configured deployment root:

```text
Region candidate = direct child of root
```

For every Region category:

1. look for an existing `local_cp_region` with the same `categoryid`;
2. if found, reuse it;
3. if none exists, preview it as a new Region candidate.

Region identity is primarily Moodle `categoryid`, not the display name.

## 10.2 Container discovery

Under each Region, find category candidates matching one enabled container rule.

Only enabled container rules participate.

A container is organizational structure; it is not registered as a School/Unit.

## 10.3 Unit discovery

For each matched container:

```text
direct child category = Unit candidate
```

Examples:

```text
THPT Chuyên Thái Nguyên
Trường THPT Đồng Hỷ
Trường THPT A Kim Bảng
Trung tâm GDNN-GDTX ...
```

For each Unit category:

1. reuse `local_cp_school` by exact `categoryid` when present;
2. otherwise preview a new Unit candidate.

The existing table name `local_cp_school` remains for backward compatibility; UI terminology changes to:

```text
Trường/Đơn vị
```

## 10.4 Course discovery

Within each Unit, scanner reads Moodle courses that are:

```text
directly in the Unit category
or in descendants of the Unit category
```

subject to the existing invariant that the course is inside that Unit category subtree.

Master courses are explicitly excluded.

Deleted/nonexistent records are excluded by normal Moodle queries.

---

# 11. Deterministic auto-generated codes

New Region/Unit records require stable unique `code` values.

Discovery must not derive uniqueness solely from names because Vietnamese transliteration/name collisions can occur.

For auto-created records:

```text
Region code: AUTO_R_<categoryid>
Unit code:   AUTO_U_<categoryid>
```

Examples:

```text
AUTO_R_10
AUTO_U_34
```

Display names come from Moodle category names.

Existing manually configured Region/Unit records are always reused by exact `categoryid` and their existing codes are preserved.

The discovery engine never renames/re-codes an existing configured entity.

---

# 12. Course classification

Every discovered Moodle course is evaluated against enabled discovery rules for the Target Groups selected in the scan.

Possible course-level outcomes:

```text
MATCHED(group)
UNMATCHED
CONFLICT_GROUP
EXCLUDED_MASTER
```

A course is not registered merely because its name resembles a known grade.

No fuzzy matching is allowed.

---

# 13. Unit + Target Group collision rule

After course-level classification, results are grouped by:

```text
Region
Unit
Target Group
```

Rules:

```text
0 matching courses -> MISSING (informational)
1 matching course  -> candidate
>1 matching course -> CONFLICT_DUPLICATE_COURSE
```

Example:

```text
LỚP 10 - 2627
LỚP 10 - COPY
```

both matching Group 10 within one Unit yields:

```text
CONFLICT_DUPLICATE_COURSE
```

The scanner must not select by:

```text
newest course
lowest ID
visibility
first result
```

---

# 14. Existing binding reconciliation

For a classified candidate:

## 14.1 Correct existing binding

If an existing target binding already maps:

```text
Program
+ Unit
+ Target Group
-> same Moodle course
```

status:

```text
EXISTING
```

No write is required.

## 14.2 Missing binding

If there is no binding:

```text
READY_NEW
```

It may be selected for bulk registration.

## 14.3 Existing wrong binding

If the same Program + Unit + Target Group points to a different course:

```text
CONFLICT_EXISTING_BINDING
```

No automatic overwrite.

## 14.4 Course reused elsewhere

If the course is already an active target under another binding:

```text
CONFLICT_COURSE_REUSED
```

No automatic reassignment.

---

# 15. Discovery preview UI

Add navigation entry:

```text
Khám phá topology
```

Example form:

```text
Chương trình:
  AI 2026-2027

Root triển khai:
  02_TRIEN_KHAI_TINH

Containers:
  ☑ Khối THPT
  ☑ Khối Liên Cấp
  ☑ Trung tâm

Nhóm đích / Khối:
  ☑ Lớp 10
  ☑ Lớp 11
  ☑ Lớp 12
  ☐ DMST
  ☐ CDS

[Quét topology]
```

The UI may default to all enabled container rules and all enabled Target Groups, but the server must revalidate submitted IDs.

---

# 16. Preview result model

Summary:

```text
Regions discovered
Units discovered
Courses scanned

READY_NEW
EXISTING
UNMATCHED
MISSING
CONFLICT_GROUP
CONFLICT_DUPLICATE_COURSE
CONFLICT_EXISTING_BINDING
CONFLICT_COURSE_REUSED
INVALID_TOPOLOGY
```

Detailed table columns:

```text
Region
Container
Trường/Đơn vị
Moodle course
Shortname
Target Group
Matched rule(s)
Existing configuration
Status
Reason
Selectable
```

Only `READY_NEW` rows are selectable for registration.

`EXISTING`, `UNMATCHED`, `MISSING` and conflicts remain visible for operator understanding.

---

# 17. Bulk registration

## 17.1 Explicit confirmation

The scanner is read-only until the administrator selects rows and confirms:

```text
Đăng ký các mục đã chọn
```

No discovery scan automatically writes topology.

## 17.2 Server-side revalidation

On POST, the server must not trust preview state.

For every selected candidate it revalidates:

```text
Program still exists/enabled
Target Group still exists/enabled
root still valid
Region category still under root
container still matches enabled rule
Unit still under container
course still inside Unit
course still matches exactly one Target Group
no duplicate candidate now exists
no conflicting existing binding appeared
course not reused/master
```

Only then may it write.

## 17.3 Write path

Bulk registration must reuse existing production invariants.

Create/reuse:

```text
service::save_region()
service::save_school()
service::save_target()
```

No direct alternate target insertion logic.

A thin bulk-registration service may coordinate these calls transactionally per candidate/unit.

## 17.4 Partial success model

One bad candidate must not corrupt valid candidates.

Registration results are recorded per selected row:

```text
CREATED
BECAME_EXISTING
FAILED_REVALIDATION
FAILED_WRITE
```

The UI shows an explicit summary after processing.

No silent skipping.

---

# 18. Idempotency

Running discovery repeatedly must be safe.

Example:

```text
First scan:
  21 READY_NEW
  register 21

Second scan:
  21 EXISTING
  0 READY_NEW
```

If one new school/course is later added:

```text
21 EXISTING
1 READY_NEW
```

Only the new candidate is registered.

Identity is based primarily on stable Moodle IDs:

```text
Region -> categoryid
Unit   -> categoryid
Target -> courseid + Program + Target Group
```

not mutable display names.

---

# 19. UI: Target Groups

Add navigation:

```text
Nhóm đích / Khối
```

List:

```text
Key
Tên
Chương trình
Master configured?
Enabled
Order
Actions
```

Admin actions:

```text
Add
Edit display name
Enable/disable
Change sort order
```

Deleting a Target Group with bindings/history is not allowed in 1.1.1. Disable it instead.

`groupkey` cannot be changed after it has active bindings/history unless a later dedicated migration feature is designed.

---

# 20. UI: Recognition Rules

Add navigation:

```text
Quy tắc nhận diện
```

Admin can:

```text
add rule
edit rule
enable/disable
delete unused rule
set priority/order
choose fullname/shortname
choose contains/starts_with/regex
assign Target Group
```

Rule preview/test should show whether the pattern is syntactically valid.

Regex rules require validation before save.

No rule editor operation changes existing bindings automatically.

---

# 21. UI terminology changes

Replace operator-facing hard-coded label:

```text
Khối
```

with:

```text
Nhóm đích / Khối
```

where the value represents a publishing family.

Existing groups still display:

```text
Lớp 10
Lớp 11
Lớp 12
```

Special groups display their configured names.

The underlying compatibility parameter may remain named `gradekey` in 1.1.1 to avoid breaking routes.

---

# 22. Service/API compatibility

## 22.1 Replace hard-coded Grade assertion

Current 1.1.0 uses:

```php
service::assert_grade($gradekey)
```

against a fixed `GRADES` list.

1.1.1 introduces a Target Group resolver such as:

```text
resolve_target_group(programid, groupkey)
assert_target_group(programid, groupkey)
```

The old `assert_grade()` may remain as a compatibility wrapper but must no longer enforce only 10/11/12.

## 22.2 Existing public entrypoints

These must remain operational:

```text
single-target preview
single-target publish
Batch fan-out
queue.php
publish.php
preview.php
batch.php
jobs.php
batch_view.php
```

Existing `gradekey=10/11/12` URLs remain valid.

---

# 23. Batch compatibility

Existing Batch #1 and all historical 1.1.0 jobs remain readable.

Historical `gradekey` fields are not rewritten for presentation-only purposes.

New Batches resolve and persist:

```text
targetgroupid
gradekey = target group groupkey
```

The Batch dispatcher, child-job model, mutation-state handling, origin mapping and logical placement engine are unchanged.

No historical Batch is re-run or altered during upgrade.

---

# 24. Upgrade plan

Upgrade target version:

```text
1.1.1 release version to be assigned at implementation time
greater than 2026082901
```

Migration sequence:

```text
1. Create local_cp_target_group.
2. Create local_cp_discovery_rule.
3. Create local_cp_discovery_container.
4. Add discoveryrootcategoryid to local_cp_program.
5. Expand gradekey columns from char(2) to char(64) where required.
6. Add targetgroupid to local_cp_master.
7. Add targetgroupid to local_cp_target.
8. Add targetgroupid to local_cp_batch.
9. Seed Target Groups 10/11/12 for every existing Program.
10. Map every existing master 10/11/12 binding to exactly one Target Group.
11. Map every existing target 10/11/12 binding to exactly one Target Group.
12. Map existing Batch 10/11/12 rows when deterministic; preserve gradekey regardless.
13. Seed default container rules:
       KHỐI THPT
       KHỐI LIÊN CẤP
       TRUNG TÂM
14. Seed default recognition rules for Groups 10/11/12.
15. Validate migration counts and unresolved active bindings.
16. Only then save the Moodle upgrade savepoint.
```

If an active existing master/target has an unsupported/unmappable old key:

```text
upgrade must fail explicitly
```

rather than guessing.

No course content mutation occurs during upgrade.

---

# 25. Default recognition rules

Initial seed should be conservative.

Suggested rules:

## Group 10

```text
fullname contains "LỚP 10"
shortname contains "_L10_"
```

## Group 11

```text
fullname contains "LỚP 11"
shortname contains "_L11_"
```

## Group 12

```text
fullname contains "LỚP 12"
shortname contains "_L12_"
```

The scanner may show unmatched special courses until the administrator creates corresponding Target Groups/rules.

Seed rules must not assume `DMST`, `CDS` or other special groups automatically.

---

# 26. Audit

Reuse `local_cp_audit`.

Add event/action vocabulary sufficient to distinguish discovery activity:

```text
discovery_scan
discovery_register_region
discovery_register_unit
discovery_register_target
discovery_conflict
target_group_create
target_group_update
discovery_rule_create
discovery_rule_update
container_rule_create
container_rule_update
```

Audit payloads should include relevant IDs:

```text
userid
programid
targetgroupid
ruleid
region categoryid
unit categoryid
courseid
created/reused entity id
status/reason
```

Discovery preview itself is read-only; summary scans may be audited without storing the entire result set.

---

# 27. Permissions

Existing configuration capabilities should be reused where semantically correct.

Recommended mapping:

```text
view discovery preview:
  local/coursepublisher:view
  + local/coursepublisher:preview

manage Target Groups / rules / root:
  local/coursepublisher:configureprograms
  or a dedicated configuration capability if implementation proves necessary

bulk register Region/Unit/Target:
  local/coursepublisher:configuretopology
  + local/coursepublisher:bindcourses
```

Publishing capability is not required for discovery registration because discovery does not mutate Moodle course content.

All write actions require sesskey.

---

# 28. Performance

Discovery is read-heavy.

Expected safe approach:

```text
load category subtree metadata in bounded queries
load courses for discovered Unit subtrees
load enabled rules once
classify in PHP
load existing CP bindings in batches
```

Avoid one DB query per course/rule where possible.

Discovery must run synchronously only while bounded to the configured subtree and configuration-sized workloads. If production topology grows large enough to create unacceptable HTTP runtime, a future async scan job can be added without changing the classification model.

Bulk registration writes configuration rows only and should be substantially lighter than content publishing.

---

# 29. Failure handling

Fail closed.

Examples:

```text
Root missing                     -> scanner blocked
Region no longer under root      -> candidate invalid
Container no longer matches      -> candidate invalid
Unit moved                        -> candidate invalid
Course moved outside Unit        -> candidate invalid
0 rule matches                   -> UNMATCHED
>1 Target Group matches          -> CONFLICT_GROUP
>1 course for Unit+Group         -> CONFLICT_DUPLICATE_COURSE
existing wrong binding           -> CONFLICT_EXISTING_BINDING
course already reused            -> CONFLICT_COURSE_REUSED
invalid regex                    -> rule cannot be enabled
```

No force-register button is included in 1.1.1.

Manual corrections remain available through the existing Region/School/Target screens.

---

# 30. Regression requirements

Before an RC is produced, verify at minimum:

```text
1. Existing Program AI_2627 receives Target Groups 10/11/12.
2. Existing master #158 maps to Group 10.
3. Existing master #159 maps to Group 11.
4. Existing master #160 maps to Group 12.
5. Existing target #165 maps to Group 10.
6. Existing target #167 maps to Group 11.
7. Historical Batch #1 remains readable.
8. Historical Job #8 remains readable.
9. Existing single-target preview still accepts 10/11/12.
10. Existing single-target publish still works.
11. Existing Multi-target Batch flow still works.
12. Scanner never leaves configured deployment root.
13. Scanner sees THPT containers.
14. Scanner sees Liên Cấp containers.
15. Scanner sees Trung tâm containers.
16. Existing Region/Unit is reused by categoryid.
17. First discovery registration creates missing bindings.
18. Second identical scan creates zero duplicates.
19. Unmatched course creates no binding.
20. Cross-group rule ambiguity creates no binding.
21. Two courses matching one Unit+Group create no binding.
22. Existing wrong binding is not overwritten.
23. Master course is never proposed as target.
24. Special Target Group can be added entirely through UI.
25. Special recognition rule can be added entirely through UI.
26. A new group key longer than two characters works end-to-end.
```

---

# 31. Acceptance scenario

A representative production acceptance test:

```text
Program: AI_2627
Root: 02_TRIEN_KHAI_TINH

Containers:
  Khối THPT
  Khối Liên Cấp
  Trung tâm

Enabled groups:
  10
  11
  12
```

Expected:

1. Regions such as Thái Nguyên/Ninh Bình/Hà Nội are discovered as direct children of root.
2. Unit categories beneath the three enabled container families are discovered.
3. `LỚP 10/11/12` courses are classified according to configured rules.
4. Existing THPT Chuyên Thái Nguyên bindings appear as `EXISTING`.
5. Unconfigured valid schools appear as `READY_NEW`.
6. Special courses with no configured rule appear as `UNMATCHED`.
7. Admin selects only `READY_NEW`.
8. Bulk registration creates/reuses Region and Unit rows and creates Target bindings.
9. Running the same scan again shows those rows as `EXISTING`.
10. Course Publisher Batch fan-out can immediately use the newly registered bindings without any scanner-specific publish path.

---

# 32. Future extension path

The architecture intentionally supports later additions without scanner code changes:

```text
DMST
CDS
AI_CLUB
GDTX_L10
other named programs
```

The administrator workflow is:

```text
Create Target Group
-> bind its master course
-> add recognition rule(s)
-> run discovery
-> bulk-register targets
-> use normal Batch fan-out
```

Potential future work, outside 1.1.1:

```text
automatic course cloning
automatic category provisioning
CSV rule import/export
async large-site scanner
dry-run comparison reports across school years
rule test sandbox
automatic detection of renamed/moved configured units
```

---

# 33. Final engineering principles

```text
Binding remains the safety boundary.
Discovery never publishes.
Root scope is explicit.
Moodle IDs define discovered topology identity.
Names/patterns classify courses, not topology ownership.
Rules are configurable in UI.
Cross-group ambiguity is blocked.
Duplicate Unit+Group courses are blocked.
Existing bindings are never silently rewritten.
10/11/12 are migrated, not special-cased forever.
Special course families get independent Target Groups and masters.
1.1.0 publishing safety remains unchanged.
```

This design is the approved implementation basis for Course Publisher 1.1.1.
