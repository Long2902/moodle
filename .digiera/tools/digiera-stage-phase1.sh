#!/usr/bin/env bash
set -euo pipefail

WEB02="${DIGIERA_WEB02:-root@10.0.10.12}"
NODE_TOOL_COMMIT="fa3a4898437c4521b049ed55451b0721f6dd372b"
NODE_TOOL_URL="https://raw.githubusercontent.com/Long2902/moodle/${NODE_TOOL_COMMIT}/.digiera/tools/digiera-stage-node.sh"
LOCAL_TOOL="/tmp/digiera-stage-node.sh"
SSH_CONTROL="/tmp/digiera-phase1-ssh-%C"
SSH_OPTS=(-o ControlMaster=auto -o ControlPersist=300 -o ControlPath="$SSH_CONTROL")

fail() {
    echo "ERROR: $*" >&2
    exit 1
}

echo "===== PHASE 1: DOWNLOAD PINNED TOOL ON WEB01 ====="
curl -fL --retry 3 --connect-timeout 10 "$NODE_TOOL_URL" -o "$LOCAL_TOOL"
chmod 0700 "$LOCAL_TOOL"
bash -n "$LOCAL_TOOL"

echo
echo "===== PHASE 1: WEB01 PREFLIGHT (READ-ONLY) ====="
bash "$LOCAL_TOOL" preflight

echo
echo "===== PHASE 1: WEB02 PREFLIGHT (READ-ONLY) ====="
echo "Opening/reusing SSH connection to $WEB02 ..."
ssh "${SSH_OPTS[@]}" "$WEB02" true
ssh "${SSH_OPTS[@]}" "$WEB02" \
    "curl -fL --retry 3 --connect-timeout 10 '$NODE_TOOL_URL' -o /tmp/digiera-stage-node.sh && chmod 0700 /tmp/digiera-stage-node.sh && bash -n /tmp/digiera-stage-node.sh && bash /tmp/digiera-stage-node.sh preflight"

echo
echo "===== PHASE 1: INSTALL VERIFIED CODE ON WEB01 ====="
bash "$LOCAL_TOOL" install-code

echo
echo "===== PHASE 1: INSTALL VERIFIED CODE ON WEB02 ====="
ssh "${SSH_OPTS[@]}" "$WEB02" "bash /tmp/digiera-stage-node.sh install-code"

echo
echo "===== PHASE 1: COMPARE INSTALLED CODE ====="
localstatus="$(bash "$LOCAL_TOOL" status)"
remotestatus="$(ssh "${SSH_OPTS[@]}" "$WEB02" "bash /tmp/digiera-stage-node.sh status")"
printf '%s\n' "$localstatus"
printf '%s\n' "$remotestatus"

localsha="$(printf '%s\n' "$localstatus" | sed -n 's/^CODESET_SHA256=//p' | tail -n1)"
remotesha="$(printf '%s\n' "$remotestatus" | sed -n 's/^CODESET_SHA256=//p' | tail -n1)"
[ -n "$localsha" ] || fail "Web01 code-set hash missing"
[ -n "$remotesha" ] || fail "Web02 code-set hash missing"
[ "$localsha" = "$remotesha" ] || fail "Web01/Web02 installed code differs"

echo
echo "PHASE1=PASS"
echo "CODESET_SHA256=$localsha"
echo "DB_CHANGED=NO"
echo "FILTER_ENABLED=NO"
echo "NEXT=Review this output, then run Phase 2 activation from Web01."
