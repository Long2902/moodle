#!/usr/bin/env bash
set -euo pipefail

ROOT="${1:-/var/www/moodle/public}"
CONFIG="$ROOT/config.php"

echo '===== DIGIERA WEB01 DATAROOT / CRON READ-ONLY PROBE ====='
echo "time=$(date -Is)"
echo "host=$(hostname)"
echo "root=$ROOT"
echo

if [ ! -f "$CONFIG" ]; then
  echo "ERROR: config.php not found: $CONFIG" >&2
  exit 2
fi

echo '===== SAFE CONFIG LINES ====='
grep -nE '\$CFG->(dataroot|wwwroot)[[:space:]]*=' "$CONFIG" || true

echo
DATAROOT=$(php -r '
$c = file_get_contents($argv[1]);
if (preg_match("/\\$CFG->dataroot\\s*=\\s*([\x27\x22])([^\x27\x22]+)\\1\\s*;/", $c, $m)) {
    echo $m[2];
}
' "$CONFIG" 2>/dev/null || true)
echo "dataroot=${DATAROOT:-UNPARSED}"

if [ -n "$DATAROOT" ] && [ -e "$DATAROOT" ]; then
  echo
  echo '===== DATAROOT FILESYSTEM ====='
  ls -ld "$DATAROOT" || true
  stat -c 'owner=%U group=%G mode=%A uid=%u gid=%g type=%F' "$DATAROOT" || true
  command -v namei >/dev/null 2>&1 && namei -l "$DATAROOT" || true
  findmnt -T "$DATAROOT" || true

  echo
  echo '===== WRITE PERMISSION CHECK (NO FILE CREATED) ====='
  if test -w "$DATAROOT"; then echo 'root_writable=YES'; else echo 'root_writable=NO'; fi
  if id www-data >/dev/null 2>&1; then
    if runuser -u www-data -- test -w "$DATAROOT"; then
      echo 'www-data_writable=YES'
    else
      echo 'www-data_writable=NO'
    fi
  else
    echo 'www-data_user=ABSENT'
  fi
else
  echo 'DATAROOT filesystem inspection skipped because path could not be parsed or does not exist.'
fi

echo
echo '===== MOODLE CODE OWNERSHIP ====='
ls -ld "$ROOT" "$ROOT/config.php" || true
stat -c '%n owner=%U group=%G mode=%A uid=%u gid=%g' "$ROOT" "$ROOT/config.php" 2>/dev/null || true

echo
echo '===== CRON SERVICE IDENTITY ====='
systemctl show moodle-cron.service \
  -p FragmentPath -p User -p Group -p ExecStart -p ActiveState -p SubState --no-pager || true

echo
echo '===== CRON SERVICE UNIT ====='
systemctl cat moodle-cron.service --no-pager 2>/dev/null | sed -n '1,180p' || true

echo
echo '===== CRON TIMER UNIT ====='
systemctl cat moodle-cron.timer --no-pager 2>/dev/null | sed -n '1,140p' || true

echo
echo '===== RECENT CRON LOG ====='
journalctl -u moodle-cron.service -n 40 --no-pager 2>/dev/null || true

echo
echo '===== END DATAROOT / CRON PROBE ====='
