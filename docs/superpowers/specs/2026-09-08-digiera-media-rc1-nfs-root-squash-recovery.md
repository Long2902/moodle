# DIGIERA Media RC1 — NFS root-squash recovery checkpoint

Date: 2026-09-08
Branch: `feature/digiera-media-v1-rc1-fasttrack`

## Evidence

- `/var/www/moodle/public/config.php` is only a loader and requires `/var/www/moodle/config.php`.
- Moodle runtime dataroot resolved under the web user is `/mnt/moodledata`.
- `/mnt/moodledata` is mounted from `10.0.20.21:/srv/moodledata` over NFSv4.2.
- Dataroot ownership/mode: `www-data:www-data`, `0770`.
- PHP CLI bootstrap as `root` fails with: `$CFG->dataroot is not writable`.
- PHP CLI bootstrap as `www-data` succeeds.
- Root write probe fails.
- `www-data` write probe creates the file successfully. The initial probe reported `WEBUSER_WRITE=FAIL` only because the cleanup `rm` was accidentally executed as root; on a root-squashed NFS mount root cannot remove the file created by `www-data`.

## Root cause

The shared Moodle dataroot is on NFS with root-squash semantics. Moodle CLI commands must not run as root in this topology because `is_writable($CFG->dataroot)` fails for the squashed root identity. They must run as the Moodle web user (`www-data`).

This is not a moodledata ownership/mode defect. Do not chmod/chown the shared dataroot to accommodate root.

## Impact on insert-event-fix deploy

The product payload `b9a54d768b9ac3550c9b6f94e531c5cbbeab2138` was copied to Web01/Web02 and file parity passed. Tiny source revision is `2026090801`.

The first deploy helper produced false-positive sentinel lines because its ERR trap did not terminate execution after Moodle CLI failures. Therefore the following were not validly confirmed on that run:

- maintenance transition,
- Moodle DB upgrade to `2026090801`,
- cache purge.

## Corrective action

The deploy helper was corrected to:

1. run all Moodle CLI commands through `runuser -u www-data -- php ...`,
2. purge caches on both Web01 and Web02,
3. make the ERR trap exit non-zero after restoring service,
4. preserve root only for OS-level work such as `systemctl`, code copy, SSH and PHP-FPM reload.

Helper fix commit: `20f1e62ad4cd2fdd4e288fb9f33be0cdcb9a303e`.

## Immediate recovery path

Do not redeploy the four Tiny files. The next action is a finalize-only recovery on Web01:

- remove the leftover probe as `www-data`,
- stop cron timer,
- enable maintenance as `www-data`,
- run Moodle upgrade as `www-data`,
- verify DB plugin version equals `2026090801`,
- purge caches as `www-data` on both nodes,
- reload PHP-FPM on both nodes,
- disable maintenance as `www-data`,
- restore cron timer,
- browser smoke `select -> insert -> save -> render -> reopen`.

## Status

- Modal A visual smoke: PASS
- Tiny code file revision on Web01: `2026090801`
- Two-node insert-event payload parity: PASS
- NFS root-squash diagnosis: CONFIRMED
- DB upgrade/cache finalize: PENDING
- Browser insert smoke after event fix: PENDING
