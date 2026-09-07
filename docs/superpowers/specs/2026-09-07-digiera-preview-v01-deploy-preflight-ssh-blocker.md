# DIGIERA Preview v0.1 — Controlled Deploy Preflight SSH Blocker

Date: 2026-09-07

Status: **PREVIEW DEPLOY NOT STARTED / NO LIVE MUTATION / SSH PREFLIGHT BLOCKED**

## Evidence

Controlled deployment was launched on Web01 `vm-c47e0dd9` with package/source gates already complete. The runner stopped during the Web02 SSH preflight before maintenance mode, cron quiesce, plugin tree mutation, or database upgrade.

Observed blocker:

```text
WEB01_HOST_GUARD=PASS
ssh: Could not resolve hostname moodle-web02: Name or service not known
PREVIEW_DEPLOY=STOPPED
```

A direct connectivity test using Web02 private IP succeeded:

```text
ssh root@10.0.10.12
HOST=moodle-web02
SSH_WEB02=PASS
```

However, the connection currently prompts for the Web02 root password. The controlled deploy runner intentionally uses `BatchMode=yes` for the preflight guard, so password-based SSH is insufficient for the automated two-node deploy path.

## Safety conclusion

```text
LIVE_MUTATION_STARTED=NO
MAINTENANCE_CHANGED=NO
CRON_CHANGED=NO
PLUGIN_FILES_CHANGED=NO
DB_UPGRADE_STARTED=NO
PRODUCTION_MUTATION=NO
```

## Exact continuation

1. Configure passwordless SSH key authentication from Web01 root to `root@10.0.10.12`.
2. Verify:

```text
ssh -o BatchMode=yes root@10.0.10.12 'hostname'
```

Expected: `moodle-web02` with exit code 0 and no password prompt.

3. Re-run:

```text
WEB02=root@10.0.10.12 EXPECTED_WEB02_HOST=moodle-web02 bash /root/digiera-preview-controlled-deploy.sh
```

Only after SSH preflight passes may the runner enter maintenance and begin controlled mutation.
