# DIGIERA Media RC1 — Recent + Permissions Deploy-Ready Checkpoint

Date: 2026-09-11

## Status

This checkpoint records the deploy-ready state for the bounded RC1 batch `true per-user Recent + permission matrix` on the isolated branch:

`feature/digiera-media-v1-rc1-recent-permissions`

Do not merge this branch to `main` as part of this checkpoint.

## Frozen product / helper / verification

- Product payload pin used by the two-node deploy helper: `9919b0801ab2e3a1dd29bd9fc25506ccecd07c28`
- Deploy helper commit: `6774bdca937de28b4591466bd774a95ce6a8dfd6`
- Final verification head: `7098894dd5f8d306b422d427b377e62b973b2c7f`
- Final RC1 workflow run: `34559940218`
- Final RC1 job: `103140412781`
- RC1 artifact: `DIGIERA_MEDIA_MOODLE51_RC1_FASTTRACK`
- Artifact ID: `10183995214`
- Uploaded artifact ZIP SHA256: `1525bf6d73a41ad823644412bd15a1f3a8898330bbc08b9e72021a7b64d8a1af`

The difference between product pin `9919...` and final verification head `709889...` is test-contract-only. The later commit makes the historical lifecycle source contract forward-compatible with local plugin version `2026091001`; it does not change the deployed plugin payload.

## Product version

`local_digieramedia`:

- version: `2026091001`
- release: `1.0.0-rc1-fasttrack-recent-permissions`

This batch intentionally does not bump TinyMCE or filter plugin versions because their runtime payload is unchanged by this bounded feature.

## True Recent invariants

The new table `local_digieramedia_recent` stores per-user media usage with:

- unique `(userid, mediaid)` row identity
- `(userid, lastusedat)` ordering index
- `contextid`, `lastusedat`, `usecount`, and `lastaction`

Behavior frozen for RC1:

1. Successful `create_reference` touches Recent with action `CREATE_REFERENCE`.
2. Successful `update_reference_version` touches Recent with action `UPDATE_REFERENCE`.
3. Upload/select/preview alone do not create a Recent row.
4. `tab=recent` is always scoped to the current user, including users with `viewall`.
5. Recent order is `lastusedat DESC`, not media modification time.
6. Reuse updates the same `(userid, mediaid)` row rather than creating duplicates.
7. Trash preserves Recent metadata but the media is hidden while not ACTIVE.
8. Restore exposes the preserved row again without fabricating a new use event.
9. Partial R2 purge failure keeps Recent metadata so PURGING remains retryable.
10. Recent rows are deleted only after permanent purge completes successfully.

## Permission matrix frozen for RC1

Editing teacher default:

- allowed: view, insert, upload, view usage, trash own media
- denied by default: replace, manage versions, restore, purge

Manager default:

- allowed: view, insert, upload, view usage, replace, manage versions, trash, restore, manage visibility, view all, manage
- purge remains opt-in/default denied

DIGIERA KTV capability bundle used by acceptance tests:

- allowed: view, insert, upload, view usage, replace, manage versions, trash, restore, manage visibility
- purge remains denied until explicitly granted

Ordinary enrolled user:

- no DIGIERA Media operational capabilities by default

Authorization remains capability-based. Runtime code must not authorize by Moodle role shortname or a literal KTV/teacher role name.

## TDD / verification evidence

Task 1 schema/service: focused run `34468519664` PASS.

Task 2 RED: run `34468749244` failed on missing create/update Recent writes and incorrect user/order semantics. Product was then fixed.

Task 3 RED: run `34469344802` failed because successful purge did not yet remove Recent rows. Product was then fixed so cleanup occurs only after purge completion. Focused run `34559267265` PASS.

Task 4 initial failure was a test fixture issue: the ordinary user could not enter the course context, so Moodle context validation stopped before DIGIERA capability checks. Fixture was corrected by enrolling the test users without granting extra DIGIERA capabilities. Focused run `34559665975` PASS.

Final RC1 run `34559940218`, job `103140412781`, completed successfully with:

- `RC1_CONTRACT_PASS=17`
- `LIFECYCLE_SOURCE_CONTRACT=PASS`
- PHP syntax gate PASS
- JavaScript syntax gate PASS
- new deploy helper `bash -n` PASS
- TinyMCE AMD ESLint/Rollup build PASS
- full DIGIERA Moodle PHPUnit runtime suite PASS
- reproducible RC1 bundle build PASS
- archive checksum verification PASS
- artifact upload PASS

Runtime suite included schema, Recent service/external behavior, permission matrix, backup/restore, reference version state, lifecycle, usage, and filter rendering/lifecycle tests. There were PHPUnit deprecation/notices and upstream npm dependency warnings, but no test/build failure in the accepted RC1 run. These warnings remain technical debt and are not silently treated as fixed.

## Two-node deploy helper

Path:

`.digiera/tools/digiera-media-rc1-recent-permissions-deploy.sh`

Frozen helper commit:

`6774bdca937de28b4591466bd774a95ce6a8dfd6`

The helper:

- preflights Web02 SSH and Moodle CLI on both nodes
- downloads only files frozen at product commit `9919...`
- validates the Recent source contract and PHP syntax
- checks staged SHA identity on Web02
- snapshots `local/digieramedia` on Web01 and Web02
- stops `moodle-cron.timer` only if it was active
- enables Moodle maintenance mode
- installs the bounded payload on both nodes
- runs Moodle upgrade as `www-data`
- verifies database version/table/index/global-upgrade-function contract
- purges Moodle caches on both nodes
- reloads PHP-FPM on both nodes
- verifies deployed file parity between Web01 and Web02
- disables maintenance mode and restores previous cron state

The helper itself performs no R2 object mutation: `R2_MUTATION=NONE`.

## Required live acceptance after deployment

Deployment is not considered production-verified until the helper ends with `DIGIERA_RECENT_PERMISSIONS_DEPLOY=PASS` and browser smoke is completed.

Browser smoke sequence:

1. User A inserts media A. Media A appears in User A `Đã dùng gần đây`.
2. User B opens Recent and must not see User A's Recent history.
3. User A reuses media A after another media; order changes by last use and media A remains one logical Recent row.
4. Trash media A: it disappears from Recent while trashed; Restore: it reappears without a synthetic new-use touch.
5. Successful permanent purge removes media A from Recent permanently.
6. Editing teacher can view/insert/upload/view usage/trash own, but cannot replace/manage versions/restore/purge by default.
7. KTV can replace/manage versions/trash/restore/manage visibility, but purge remains denied unless explicitly granted.
8. Manager purge remains opt-in/default denied.

Only after evidence from the two-node helper and browser acceptance may this checkpoint be followed by a `deployed/verified` checkpoint.
