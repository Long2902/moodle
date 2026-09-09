# DIGIERA Media RC1 — Moodle Upgrade Entrypoint Recovery

Date: 2026-09-09
Branch: `feature/digiera-media-v1-rc1-versioning`

## Deployment failure observed on Web01

The two-node reference-state deploy reached installation on Web01/Web02 and then failed at Moodle CLI upgrade:

```text
-->local_digieramedia
Default exception handler: Ngoại lệ - Call to undefined function xmldb_local_digieramedia_upgrade()
...
DIGIERA_REFERENCE_STATE_DEPLOY=FAIL LINE=141
```

The helper had already installed the reference-state payload on both web nodes before the DB upgrade step. Snapshots created immediately before installation were:

- Web01: `/root/DIGIERA_MEDIA_PRE_REFERENCE_STATE_WEB01_20260909-134502.tgz`
- Web02: `/root/DIGIERA_MEDIA_PRE_REFERENCE_STATE_WEB02_20260909-134502.tgz`

The helper error trap restores maintenance/cron service state but does not roll back installed files.

## Root cause

`public/local/digieramedia/db/upgrade.php` declared:

```php
namespace local_digieramedia;
```

and therefore defined `local_digieramedia\xmldb_local_digieramedia_upgrade()` instead of the global Moodle upgrade entrypoint `xmldb_local_digieramedia_upgrade()` expected by `lib/upgradelib.php`.

This escaped the previous CI suite because fresh PHPUnit initialization installs the current `install.xml` directly and did not exercise an upgrade from the deployed older plugin version.

## TDD regression

A source contract was added:

`test_moodle_upgrade_entrypoint_is_global`

It requires:

- no namespace declaration in `db/upgrade.php`;
- a global `xmldb_local_digieramedia_upgrade(...)` function.

RED evidence:

- commit `4d9516459df98fbca02eb9c25470d1a5e271cae3`
- RC1 workflow run `34320718828`
- source contract gate failed as intended.

## Product fix

Commit:

`e67278ffdddb61ff7b63108c01c113096485fd4f`

Change:

- remove namespace from `db/upgrade.php`;
- keep Moodle XMLDB classes referenced from the global namespace;
- expose the required global `xmldb_local_digieramedia_upgrade()` function.

GREEN verification:

RC1 workflow run `34320824704` completed SUCCESS:

- source contract and syntax gate: PASS
- TinyMCE AMD build: PASS
- Moodle PHPUnit setup: PASS
- DIGIERA runtime suite: PASS
- reproducible RC1 bundle: PASS
- release archive verification: PASS
- artifact upload: PASS

## Recovery helper

Dedicated partial-deploy recovery helper:

`.digiera/tools/digiera-media-rc1-upgrade-entrypoint-recovery.sh`

Helper commit:

`0696ebdcdb3696d7af07675256c9e219c79b91c2`

The helper is pinned to product fix commit `e67278ffdddb61ff7b63108c01c113096485fd4f` and:

1. verifies both nodes already contain the `2026090901` reference-state payload;
2. downloads only the corrected `db/upgrade.php`;
3. rejects any namespace declaration and verifies the global function exists;
4. snapshots the currently deployed upgrade file on both nodes;
5. enters maintenance and stops cron if active;
6. installs the fixed file on Web01/Web02;
7. verifies the global upgrade function on both nodes;
8. resumes Moodle CLI upgrade as `www-data`;
9. verifies `local_digieramedia=2026090901`, `tiny_digieramedia=2026090901`, and the update-reference external service;
10. purges caches, reloads PHP-FPM, checks two-node parity and R2 runtime;
11. returns to service only after all recovery checks pass.

If recovery fails after maintenance has been enabled, the helper deliberately leaves maintenance ON for safety and reports the exact failing line.

## Next acceptance

After recovery PASS:

- hard refresh browser;
- select an existing DIGIERA TinyMCE component;
- open DIGIERA modal;
- verify persisted `PINNED_VERSION` and the exact pinned version are restored in UI;
- toggle PIN -> FOLLOW -> PIN and reopen each time;
- confirm the same reference UUID is updated in place and no duplicate marker/reference is created.
