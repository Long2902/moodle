# DIGIERA Media RC1 — Insert event fix deploy blocked by dataroot permissions

Date: 2026-09-08
Branch: feature/digiera-media-v1-rc1-fasttrack
Target product pin: b9a54d768b9ac3550c9b6f94e531c5cbbeab2138

## Observed deployment state

The insert-event fix payload was downloaded and copied successfully to both Web01 and Web02. Source contract and two-node file parity passed.

However all Moodle CLI operations reported:

`Fatal error: $CFG->dataroot is not writable, admin has to fix directory permissions! Exiting.`

Affected operations included maintenance enable/disable, upgrade, purge_caches and direct DB-version read via PHP bootstrap.

Therefore the helper's final PASS sentinels are invalid for this run. The following must NOT yet be treated as verified:
- DB plugin version 2026090801
- Moodle cache purge
- maintenance state transitions performed by Moodle CLI

Confirmed from the run:
- Web01/Web02 insert-event payload files were installed.
- Two-node file parity passed.
- cron timer was reported active at the end.

## Root-cause workflow

Before changing permissions, collect evidence for the configured dataroot path, owner/mode/ACL, mount type/options, filesystem writeability, PHP CLI effective user, and write tests as root and the web/PHP-FPM user.

Do not rerun the upgrade until dataroot writeability is proven and the helper is hardened to fail on Moodle fatal-output even if the PHP process returns exit code 0.
