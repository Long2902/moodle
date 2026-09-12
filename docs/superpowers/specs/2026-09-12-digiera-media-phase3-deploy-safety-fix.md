# DIGIERA Media V1 Phase 3 — Deploy Safety Fix Checkpoint

Date: 2026-09-12

Branch: `feature/digiera-media-v1-rc1-backup-coursepublisher`

Status: **DEPLOY HELPER SAFETY FIXED / PRODUCTION NOT YET EXECUTED / OPERATOR OUTPUT PENDING**

## 1. Why this checkpoint exists

The first Phase 3 deploy helper version staged the full repository `public/` tree and later executed:

```bash
cp -a "$TMP/public/." "$MOODLE/"
```

That behavior could overwrite unrelated Moodle core/theme/plugin files even though the intended Phase 3 scope is only:

```text
local/digieramedia
lib/editor/tiny/plugins/digieramedia
filter/digieramedia
local/coursepublisher
```

The runtime snapshot in that helper covered only those four plugin trees, so overwriting broader Moodle code would not have been fully recoverable from the helper snapshots. Production deployment with that helper is therefore rejected.

## 2. Safety correction

The deploy helper at:

```text
.digiera/tools/digiera-media-phase3-production-deploy.sh
```

was corrected in commit:

```text
e8ee65781de5b374b83b9e9ad11535daef07f957
```

The corrected helper still pins product runtime to:

```text
PRODUCT_COMMIT=f8d825352d4bea24db80717c0ccd274dfdf16733
```

The helper itself and the product payload intentionally use different immutable commits:

- deploy helper commit: `e8ee65781de5b374b83b9e9ad11535daef07f957`
- frozen product runtime commit: `f8d825352d4bea24db80717c0ccd274dfdf16733`

## 3. New invariants

The corrected helper now:

1. extracts only the four target runtime trees from the frozen GitHub tarball;
2. asserts all four expected trees exist before maintenance mode;
3. rejects staging if Moodle core/theme/module roots such as `admin`, `course`, `theme`, `mod`, or `lib/classes` appear;
4. replaces only the four target runtime trees on Web01 and Web02;
5. snapshots the exact four target trees before mutation;
6. verifies byte-level parity for the four deployed trees;
7. enforces the production cron topology: Web01 active, Web02 inactive;
8. performs no R2 mutation during deployment.

Local shell syntax verification of the corrected helper:

```text
bash -n = PASS
```

## 4. Previous operator command is superseded

Do **not** use a mutable branch URL for the deploy helper, and do not use the product commit as the helper URL because the helper was added after the frozen product commit.

Use the immutable helper commit below. The script itself then fetches the immutable frozen product commit.

## 5. Approved operator command

Run on Web01 as `root`:

```bash
curl -fsSL \
  "https://raw.githubusercontent.com/Long2902/moodle/e8ee65781de5b374b83b9e9ad11535daef07f957/.digiera/tools/digiera-media-phase3-production-deploy.sh" \
  -o /root/digiera-media-phase3-production-deploy.sh

chmod 0755 /root/digiera-media-phase3-production-deploy.sh
bash -n /root/digiera-media-phase3-production-deploy.sh
bash /root/digiera-media-phase3-production-deploy.sh
```

Expected final flags before browser acceptance begins:

```text
DIGIERA_PHASE3_DEPLOY=PASS
PRODUCT_COMMIT=f8d825352d4bea24db80717c0ccd274dfdf16733
TARGETED_PAYLOAD_ONLY=PASS
WEB01_WEB02_PARITY=PASS
MOODLE_UPGRADE=PASS
AMD_RUNTIME=PASS
MAINTENANCE=OFF
CRON_TIMER=active
WEB02_CRON_TIMER=inactive
R2_DEPLOY_MUTATION=NONE
```

## 6. Acceptance state

At this checkpoint:

```text
PRODUCT CODE FROZEN:          YES
DEPLOY HELPER IMMUTABLE:      YES
TARGETED-ONLY DEPLOY:         YES
BASH SYNTAX:                  PASS
PRODUCTION DEPLOY EXECUTED:   NO
BROWSER ACCEPTANCE:           PENDING
COURSE PUBLISHER LIVE SMOKE:  PENDING
```

Do not mark Phase 3 deployed or production-verified until real Web01 output and browser acceptance evidence are recorded.
