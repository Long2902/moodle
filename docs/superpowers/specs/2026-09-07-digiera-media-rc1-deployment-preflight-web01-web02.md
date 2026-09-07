# DIGIERA Media RC1 — Web01/Web02 Deployment Preflight Snapshot

Date: 2026-09-07
Branch: `feature/digiera-media-v1-rc1-fasttrack`
Artifact source commit: `6134eabe8fe28a7245beef995fa380161cec93b4`
Workflow run: `34092280998`
Status: two-node preflight complete; no plugin code installed and no DB upgrade executed yet.

## Artifact identity

- ZIP SHA256: `7247b82a722d231b0c455a50a8745d5729e264dadd7f9c6bc89e0eecb62b66ee`
- Inner TGZ checksum verification: PASS on Web01 and Web02.
- Build provenance on Web02 matched source commit and workflow run above.
- ZIP integrity PASS on Web02.
- Seed helper PHP lint PASS on Web02.

## Web01

- Host: `vm-c47e0dd9`
- Established topology IP: `10.0.10.11`
- Moodle root: `/var/www/moodle/public`
- PHP CLI: `8.3.6`
- `local/digieramedia`: NOT_INSTALLED
- `filter/digieramedia`: NOT_INSTALLED
- `lib/editor/tiny/plugins/digieramedia`: NOT_INSTALLED
- Cron timer: active during preflight.
- Cron service was observed activating during the first probe.
- Rollback snapshot: `/root/DIGIERA_MEDIA_PRE_RC1_20260907-135653` (manifest only because plugins were absent).

## Web02

- Host: `moodle-web02`
- IP: `10.0.10.12`
- Moodle root verified present.
- `local/digieramedia`: NOT_INSTALLED
- `filter/digieramedia`: NOT_INSTALLED
- `lib/editor/tiny/plugins/digieramedia`: NOT_INSTALLED
- Cron timer: inactive.
- Cron service: inactive.
- Rollback snapshot: `/root/DIGIERA_MEDIA_PRE_RC1_WEB02_20260907-140349` (manifest only because plugins were absent).
- Exact verified smoke-ready artifact was copied from Web01 to `/root/` on Web02.
- Web02 outer artifact SHA verification: PASS.
- Web02 inner TGZ verification: PASS.
- `BUILD_INFO.txt` matched `source_commit=6134eabe8fe28a7245beef995fa380161cec93b4` and `workflow_run=34092280998`.
- Required artifact files present: TGZ, checksum, guide, seed helper.

## Important operator note

The hostname alias `moodle-web02` is not resolvable from Web01, so administration uses `ssh root@10.0.10.12`. A previous multiline paste allowed the SSH process to consume subsequent stdin and ended with a local `fi` syntax error; this did not modify Moodle. Subsequent commands use `ssh -n` to prevent stdin capture.

## Next controlled action

Fresh-install the identical three plugin trees on both nodes inside one Moodle maintenance window. Stop the Web01 cron timer and wait for any active cron service to become inactive first. Install code on both nodes, compare deterministic content hashes, run Moodle upgrade exactly once on Web01, set `local_digieramedia/cdnbaseurl` and `pdfviewerurl`, enable the `digieramedia` text filter globally, purge caches, reload PHP-FPM, then reopen the site only if every gate succeeds.
