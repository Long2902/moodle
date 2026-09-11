# DIGIERA Media RC1 — Recent + Permissions Browser Acceptance PASS

Date: 2026-09-11

## Status

This checkpoint records live browser acceptance for the bounded RC1 batch `true per-user Recent + permission matrix` on:

`feature/digiera-media-v1-rc1-recent-permissions`

Do not merge this branch to `main` as part of this checkpoint.

## Deployed payload

- Product payload pin: `9919b0801ab2e3a1dd29bd9fc25506ccecd07c28`
- Local plugin version: `2026091001`
- Release: `1.0.0-rc1-fasttrack-recent-permissions`
- Two-node deploy helper terminal status: `DIGIERA_RECENT_PERMISSIONS_DEPLOY=PASS`
- R2 mutation during deploy: `NONE`

## Live browser acceptance evidence

The operator confirmed direct browser testing of all required acceptance steps 1 through 5 and reported them working correctly.

Observed/confirmed acceptance:

1. User A creates/uses a DIGIERA Media reference and that Media appears in `Đã dùng gần đây`.
2. User B does not see User A's Recent history, confirming per-user isolation.
3. User A reuses Media after another Media; ordering follows last use and the same Media remains one logical Recent entry rather than duplicating.
4. Trash hides the Media from Recent while trashed; Restore makes it visible again without requiring a fabricated new-use event.
5. Permission behavior is accepted in browser: the intended Teacher/KTV restrictions and allowed actions operate correctly for the tested matrix.

The supplied browser screenshot also shows the `Đã dùng gần đây` tab active with the expected Media card present.

## Acceptance result

`RECENT_PER_USER = PASS`

`RECENT_ISOLATION_USER_A_USER_B = PASS`

`RECENT_REUSE_ORDERING = PASS`

`RECENT_NO_DUPLICATE_LOGICAL_ROW = PASS`

`TRASH_RESTORE_RECENT_BEHAVIOR = PASS`

`PERMISSION_MATRIX_BROWSER = PASS`

## Final batch state

This bounded batch is now:

`DEPLOYED = YES`

`BROWSER_VERIFIED = YES`

`PRODUCTION_ACCEPTANCE = PASS`

The next recommended bounded batch is production acceptance for `Backup/Restore + Course Publisher`, preserving the project requirement that custom Moodle activity/module compatibility must be demonstrated by real backup/restore behavior rather than a feature flag alone.
