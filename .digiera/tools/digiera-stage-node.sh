#!/usr/bin/env bash
set -euo pipefail

ROOT="${MOODLE_ROOT:-/var/www/moodle/public}"
ACTION="${1:-preflight}"
BUNDLE_COMMIT="6a94b79d33f58e34329f3e700bf0af5aca934902"
BUNDLE_SHA256="059e9058fd2e173aefbc59ae1f7d5721facc694946f01432a26e0405cd0e17b8"
BUNDLE_URL="https://raw.githubusercontent.com/Long2902/moodle/${BUNDLE_COMMIT}/.digiera/dist/digieramedia-stage.tgz"
EXPECTED_BRANCH="501"
MIN_CORE_VERSION="2025100600"
BACKUP_ROOT="/var/backups/digiera-media-stage"

fail() {
    echo "ERROR: $*" >&2
    exit 1
}

core_value() {
    local name="$1"
    case "$name" in
        version)
            sed -nE 's/^\$version[[:space:]]*=[[:space:]]*([0-9]+)(\.[0-9]+)?;.*/\1/p' "$ROOT/version.php" | head -n1
            ;;
        branch)
            sed -nE "s/^\$branch[[:space:]]*=[[:space:]]*'([^']+)'.*/\1/p" "$ROOT/version.php" | head -n1
            ;;
        release)
            sed -nE "s/^\$release[[:space:]]*=[[:space:]]*'([^']+)'.*/\1/p" "$ROOT/version.php" | head -n1
            ;;
    esac
}

codeset_sha() {
    local base="$1"
    (
        cd "$base"
        find local/digieramedia filter/digieramedia -type f -print0 \
            | sort -z \
            | xargs -0 sha256sum
    ) | sha256sum | awk '{print $1}'
}

prepare_bundle() {
    test -f "$ROOT/version.php" || fail "Moodle version.php not found at $ROOT"
    test -f "$ROOT/config.php" || fail "Moodle config.php not found at $ROOT"
    id www-data >/dev/null 2>&1 || fail "www-data user not found"

    local coreversion branch release
    coreversion="$(core_value version)"
    branch="$(core_value branch)"
    release="$(core_value release)"
    test -n "$coreversion" || fail "Could not parse Moodle core version"
    test -n "$branch" || fail "Could not parse Moodle branch"
    if [ "$branch" != "$EXPECTED_BRANCH" ]; then
        fail "Expected Moodle branch $EXPECTED_BRANCH, found $branch ($release)"
    fi
    if [ "$coreversion" -lt "$MIN_CORE_VERSION" ]; then
        fail "Moodle core $coreversion is older than required $MIN_CORE_VERSION"
    fi

    local dataroot
    dataroot="$(cd /tmp && runuser -u www-data -- php -r '
        define("CLI_SCRIPT", true);
        require $argv[1];
        echo $CFG->dataroot;
    ' "$ROOT/config.php" 2>/dev/null)"
    test -n "$dataroot" || fail "Could not resolve effective Moodle dataroot"
    test -d "$dataroot" || fail "Dataroot does not exist: $dataroot"
    runuser -u www-data -- test -w "$dataroot" || fail "www-data cannot write dataroot: $dataroot"

    STAGE_DIR="$(mktemp -d /tmp/digiera-media-stage.XXXXXX)"
    trap 'rm -rf "${STAGE_DIR:-}"' EXIT
    local bundle="$STAGE_DIR/digieramedia-stage.tgz"

    curl -fL --retry 3 --connect-timeout 10 "$BUNDLE_URL" -o "$bundle"
    echo "$BUNDLE_SHA256  $bundle" | sha256sum -c - >/dev/null \
        || fail "Stage bundle SHA256 mismatch"

    local unsafe=0 entry
    while IFS= read -r entry; do
        case "$entry" in
            /*|*../*) unsafe=1 ;;
            local/digieramedia/*|filter/digieramedia/*) ;;
            *) unsafe=1 ;;
        esac
    done < <(tar -tzf "$bundle")
    [ "$unsafe" -eq 0 ] || fail "Stage bundle contains an unexpected path"

    mkdir -p "$STAGE_DIR/extract"
    tar -xzf "$bundle" -C "$STAGE_DIR/extract"
    test -f "$STAGE_DIR/extract/local/digieramedia/version.php" || fail "local_digieramedia missing from bundle"
    test -f "$STAGE_DIR/extract/local/digieramedia/db/install.xml" || fail "local_digieramedia install.xml missing from bundle"
    test -f "$STAGE_DIR/extract/filter/digieramedia/version.php" || fail "filter_digieramedia missing from bundle"
    test -f "$STAGE_DIR/extract/filter/digieramedia/classes/text_filter.php" || fail "PDF renderer missing from bundle"

    find "$STAGE_DIR/extract/local/digieramedia" "$STAGE_DIR/extract/filter/digieramedia" \
        -type f -name '*.php' -print0 | sort -z | xargs -0 -n1 php -l >/dev/null

    EXPECTED_CODESET_SHA="$(codeset_sha "$STAGE_DIR/extract")"

    echo "HOST=$(hostname)"
    echo "MOODLE_RELEASE=$release"
    echo "MOODLE_BRANCH=$branch"
    echo "MOODLE_VERSION=$coreversion"
    echo "DATAROOT=$dataroot"
    echo "BUNDLE_SHA256=$BUNDLE_SHA256"
    echo "EXPECTED_CODESET_SHA256=$EXPECTED_CODESET_SHA"
}

status() {
    echo "HOST=$(hostname)"
    if [ -d "$ROOT/local/digieramedia" ] && [ -d "$ROOT/filter/digieramedia" ]; then
        echo "PLUGIN_CODE_PRESENT=YES"
        echo "CODESET_SHA256=$(codeset_sha "$ROOT")"
        grep -E '^\$plugin->(version|release)[[:space:]]*=' "$ROOT/local/digieramedia/version.php" || true
        grep -E '^\$plugin->(version|release)[[:space:]]*=' "$ROOT/filter/digieramedia/version.php" || true
    else
        echo "PLUGIN_CODE_PRESENT=NO"
    fi
}

install_code() {
    prepare_bundle

    local stamp backupdir rel src target
    stamp="$(date +%Y%m%d-%H%M%S)"
    backupdir="$BACKUP_ROOT/$stamp-$(hostname)"
    mkdir -p "$backupdir"

    for rel in local/digieramedia filter/digieramedia; do
        src="$STAGE_DIR/extract/$rel"
        target="$ROOT/$rel"
        mkdir -p "$backupdir/$(dirname "$rel")"
        if [ -e "$target" ]; then
            cp -a "$target" "$backupdir/$rel"
            echo "PRESENT" > "$backupdir/${rel//\//_}.state"
        else
            echo "ABSENT" > "$backupdir/${rel//\//_}.state"
        fi

        tmp="${target}.digiera-new-$$"
        rm -rf "$tmp"
        cp -a "$src" "$tmp"
        chown -R root:root "$tmp"
        find "$tmp" -type d -exec chmod 0755 {} +
        find "$tmp" -type f -exec chmod 0644 {} +
        rm -rf "$target"
        mv "$tmp" "$target"
    done

    local installedsha
    installedsha="$(codeset_sha "$ROOT")"
    [ "$installedsha" = "$EXPECTED_CODESET_SHA" ] \
        || fail "Installed code hash $installedsha differs from verified bundle $EXPECTED_CODESET_SHA"

    printf '%s\n' "$backupdir" > "$BACKUP_ROOT/LAST_CODE_BACKUP_$(hostname)"
    echo "CODE_INSTALL=PASS"
    echo "BACKUP_DIR=$backupdir"
    echo "CODESET_SHA256=$installedsha"
    echo "NEXT=Run status on both nodes, then activate from Web01."
}

case "$ACTION" in
    preflight)
        prepare_bundle
        echo "PREFLIGHT=PASS"
        ;;
    install-code)
        install_code
        ;;
    status)
        status
        ;;
    *)
        echo "Usage: $0 {preflight|install-code|status}" >&2
        exit 2
        ;;
esac
