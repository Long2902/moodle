# DIGIERA Media RC1 — R2 preflight umask/permission root cause

Date: 2026-09-08
Status: root cause confirmed on Web01; corrected deploy wrapper prepared; production deploy verification pending.

## Confirmed live evidence

Web01 interactive shell had `umask 0077` after secure R2 credential setup.

The R2 deploy helper created the payload root with mode 0755, but nested directories inherited 0700 and downloaded PHP files inherited 0600. Live evidence showed:

- payload root: 0755 root:root
- `local/`, `local/digieramedia/`, `classes/`, `classes/r2/`: 0700 root:root
- `config.php`, `client_interface.php`, `sigv4_client.php`: 0600 root:root
- `www-data` could read `r2-preflight.php` but could not read the R2 class files
- `namei -l` confirmed traversal was blocked at the nested 0700 directories
- direct preflight as `www-data` failed opening `client_interface.php`

Therefore the previous `R2_SINGLEPUT_DEPLOY=FAIL LINE=167` was not an R2 credential, token, SigV4, Cloudflare, or CORS failure. It was a local staging permission failure caused by the helper inheriting a restrictive interactive-shell umask.

## Additional hardening

The corrected wrapper also avoids relying on environment inheritance across `runuser`. It invokes `env` inside the target `www-data` process for the two preflight path variables.

## Corrected wrapper

Prepared wrapper:

`.digiera/tools/digiera-media-rc1-r2-singleput-permissionfix-deploy.sh`

Wrapper commit:

`2852b6dfd2217e567a6a031327e4de5144ef7155`

Product payload remains pinned to:

`465b2c884681d9179c2465c4d8dcf21797e99f4e`

The wrapper:

1. forces `umask 022` inside the generated inner helper;
2. normalizes staged payload directories to 0755 and files to 0644 before the `www-data` preflight;
3. keeps the corrected server-side Content-Type contract assertion;
4. keeps line-number failure reporting;
5. passes `DIGIERA_MOODLE_ROOT` and `DIGIERA_PAYLOAD_ROOT` with `env` inside `runuser` locally and remotely;
6. leaves `/etc/digiera/r2.php` untouched;
7. preserves the original two-node snapshot/maintenance/upgrade/parity flow.

## Production state before corrected rerun

The failed preflight occurred before snapshot, maintenance mode, plugin installation, or Moodle upgrade, so no live product files were changed by that failed run.

R2 server configuration had already been installed identically on Web01/Web02 and independently validated as present with bucket `digiera`, TTL 600, and 100 MiB single-PUT limit. Secrets are not recorded here.

## Next action

Run the corrected wrapper on Web01 and require fresh evidence for:

- `R2_PERMISSIONFIX_WRAPPER=PASS`
- `R2_SINGLEPUT_SOURCE_CONTRACT=PASS`
- `WEB01_R2_PREFLIGHT=PASS`
- `WEB02_R2_PREFLIGHT=PASS`
- CORS preflight PASS or non-blocking WARN
- final two-node deploy/DB/runtime parity sentinels

Do not mark production R2 upload as passed until browser smoke verifies create-session -> direct R2 PUT -> finalize -> insert -> save -> render -> reopen metadata round-trip.
