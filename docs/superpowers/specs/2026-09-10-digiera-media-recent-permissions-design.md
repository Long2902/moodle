# DIGIERA Media RC1 — True Recent + Permission Matrix Design

Date: 2026-09-10 (UTC+7)
Branch: `feature/digiera-media-v1-rc1-recent-permissions`
Status: **DESIGN APPROVED IN CHAT; SELF-REVIEWED WRITTEN SPEC AWAITING REVIEW**

## Goal

Make `Đã dùng gần đây` a true per-user usage history instead of a renamed library sort, and lock DIGIERA Media behavior to an explicit capability matrix for Admin/Manager, custom KTV, Teacher, and non-privileged users.

This batch must preserve all browser-accepted lifecycle behavior from the previous batch and must not merge to `main`.

## Current problem

`search_media(tab=recent)` currently queries the same `local_digieramedia_media` source as Library and orders by `m.timemodified`. That reflects media modification time, not when the current user actually used the media.

Capabilities already exist in `local/digieramedia/db/access.php`, but acceptance is incomplete across realistic role boundaries. KTV is not a Moodle archetype and must remain a configurable custom role rather than being hard-coded in plugin code.

## True Recent architecture

Add a focused table:

`local_digieramedia_recent`

Fields:

- `id` — primary key
- `userid` — Moodle user id
- `mediaid` — DIGIERA logical media id
- `contextid` — context of the latest successful use
- `lastusedat` — Unix timestamp of latest successful use
- `usecount` — cumulative successful-use count for this user/media pair
- `lastaction` — one of `CREATE_REFERENCE` or `UPDATE_REFERENCE`

Indexes/constraints:

- unique `(userid, mediaid)`
- index `(userid, lastusedat)`
- foreign key `mediaid -> local_digieramedia_media.id`

The table is purpose-built for recent queries; audit remains an audit log and is not repurposed as a hot user-history index.

## What counts as "used"

Recent is touched only after a successful content-use operation:

1. creating a DIGIERA reference from TinyMCE (`CREATE_REFERENCE`), or
2. successfully updating an existing DIGIERA reference's version mode (`UPDATE_REFERENCE`).

Uploading or merely previewing/selecting a media card does **not** count as use.

Touching Recent must happen only after the underlying reference operation is valid and accepted. A failed insert/update must not advance recent history.

## Recent service boundary

Create `local_digieramedia\service\recent_service` with a narrow API:

```php
public static function touch(
    int $userid,
    int $mediaid,
    int $contextid,
    string $action,
    ?int $usedat = null
): void;
```

The service owns insert/update semantics and validates `lastaction` against exactly `CREATE_REFERENCE` and `UPDATE_REFERENCE`. Calling endpoints do not hand-write recent rows.

Repeated use by the same user/media updates `contextid`, `lastusedat`, and `lastaction`, and increments `usecount` while preserving one row per user/media pair.

## Recent query semantics

For `search_media(tab=recent)`:

- always constrain recent rows to the current `$USER->id`, including users with `viewall`;
- join `local_digieramedia_recent r` to logical media;
- sort primarily by `r.lastusedat DESC`, with `m.id DESC` as a stable tie-breaker;
- include only `ACTIVE` media;
- preserve the same visibility rules already used by Library for non-`viewall` users;
- preserve text search, pagination, MIME/type presentation, and current-version metadata;
- do not expose another user's recent records.

Trash behavior:

- moving media to Trash does not delete recent history, but the item disappears from Recent because Recent only returns `ACTIVE` media;
- restoring the same media makes it eligible to reappear at its previous recent position;
- permanent purge removes all `local_digieramedia_recent` rows for that media only after the purge has successfully completed its physical/version cleanup path.

## Version and upgrade

New plugin floor for this batch:

- `local_digieramedia = 2026091001`
- Tiny/filter versions change only if their runtime files are modified; if the Tiny UI does not require code changes, do not bump them merely for symmetry.

`db/install.xml` contains the new table for fresh installs.

`db/upgrade.php` adds the table for upgrades below `2026091001` and preserves the required global `xmldb_local_digieramedia_upgrade()` entrypoint.

## Capability model

The plugin remains capability-based. No PHP or JavaScript logic may branch on role shortname/name such as `KTV`, `teacher`, or `manager`.

### Target behavior

| Capability area | Admin / Site admin | Manager | Custom KTV profile | Editing Teacher | Non-privileged user |
| --- | --- | --- | --- | --- | --- |
| View media library | yes | yes | yes | yes | no by default |
| Insert media | yes | yes | yes | yes | no by default |
| Upload media | yes | yes | yes | yes | no by default |
| Edit/trash own media | yes | yes | yes | yes | no by default |
| Replace media / manage versions | yes | yes | yes | no by default | no |
| View usage locations | yes | yes | yes | yes | no |
| Trash arbitrary media | yes | yes | yes | no | no |
| Restore media | yes | yes | yes | no | no |
| Manage visibility | yes | yes | yes | no | no |
| Permanent purge | site admin implicit / explicit capability | explicit capability | explicit capability | no | no |

KTV is represented by assigning existing capabilities to a custom Moodle role. The plugin does not create or rename site roles in this batch.

Recommended KTV capability bundle for this feature set:

- `local/digieramedia:view`
- `local/digieramedia:insert`
- `local/digieramedia:upload`
- `local/digieramedia:editown`
- `local/digieramedia:editall`
- `local/digieramedia:replace`
- `local/digieramedia:trashown`
- `local/digieramedia:trash`
- `local/digieramedia:restore`
- `local/digieramedia:viewusage`
- `local/digieramedia:managevisibility`
- `local/digieramedia:viewall`
- `local/digieramedia:manageversions`
- `local/digieramedia:manage`

`local/digieramedia:purge` is deliberately excluded from that bundle and remains an explicit opt-in capability. `overridepath` and `migrate` are also out of this KTV acceptance scope.

## Permission enforcement requirements

UI hiding is not authorization. Every external endpoint must continue to enforce its required capability server-side.

Acceptance must demonstrate both:

1. capability summaries returned to TinyMCE cause unauthorized management controls to be absent/disabled, and
2. direct backend calls by unauthorized roles are rejected.

Teacher expected behavior:

- can view/insert/upload;
- can view usage;
- can trash own media through `trashown` where ownership policy permits;
- cannot replace/manage versions unless explicitly granted;
- cannot restore, arbitrary-trash, manage visibility, or purge by default.

Non-privileged user expected behavior:

- cannot open/use DIGIERA Media through the standard capability gate by default;
- direct external calls requiring view/insert/upload/management capabilities fail.

## Tests

Automated coverage must include:

1. Recent row created only after successful create-reference.
2. Reusing same media updates one row and increments `usecount`.
3. User A Recent never contains User B's media-use history.
4. `viewall` does not bypass per-user Recent isolation.
5. Recent order is `lastusedat DESC`, not `media.timemodified`.
6. Trashed media disappears from Recent without deleting its recent row; Restore makes it eligible again.
7. Successful purge removes the media's Recent rows.
8. Editing Teacher passes view/insert/upload/viewusage expectations and is denied replace/restore/purge by default.
9. Manager passes management expectations but purge remains denied unless explicitly granted.
10. A custom test role granted the KTV capability bundle gets replace/restore/manage versions and only gets purge after explicit `purge` grant.
11. A non-privileged role is rejected by backend capability gates.

## Browser acceptance

Use at least two test accounts with different permissions.

Recent acceptance:

- User A inserts Media X, then Media Y; Recent shows Y before X.
- User B opens DIGIERA Media and does not inherit User A's Recent history.
- User A trashes one recent item; it disappears from Recent and appears again after Restore without creating a new recent event.

Permission acceptance:

- Teacher UI lacks Replace/Restore/Purge management controls that its capabilities do not permit.
- KTV test account sees the approved management controls.
- Purge remains absent/blocked for KTV until `local/digieramedia:purge` is explicitly granted.
- Direct endpoint behavior agrees with the UI matrix.

## Deployment discipline

- Build on `feature/digiera-media-v1-rc1-recent-permissions` only.
- TDD RED must be captured before product implementation.
- CI runs in parallel and must not block unrelated development, but deploy readiness requires a fresh successful RC1 verification run for the frozen product/helper revisions.
- Two-node deploy helper must snapshot Web01/Web02, run Moodle upgrade as `www-data`, verify table/index presence and node parity, purge caches, reload FPM, and restore cron/maintenance state.
- No destructive R2 operation is required to validate Recent or permissions; existing lifecycle delete behavior is regression-tested, not re-exercised unnecessarily.

## Out of scope

- creating or restructuring Moodle roles site-wide;
- changing the site's existing role taxonomy;
- automatic purge retention scheduling;
- analytics dashboards based on Recent;
- counting preview/select-only events as Recent;
- backup/restore and Course Publisher acceptance, which remains the next separate RC1 batch.
