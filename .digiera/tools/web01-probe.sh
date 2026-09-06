#!/usr/bin/env bash
set -euo pipefail

ROOT="${1:-/var/www/moodle/public}"

echo '===== DIGIERA WEB01 READ-ONLY PROBE ====='
echo "time=$(date -Is)"
echo "host=$(hostname)"
echo "user=$(id -un)"
echo "root=$ROOT"
echo

if [ ! -d "$ROOT" ]; then
  echo "ERROR: Moodle root not found: $ROOT" >&2
  exit 2
fi

cd "$ROOT"

echo '===== HOST ====='
uname -a || true
printf 'cpus='; nproc || true
free -h || true
df -h "$ROOT" || true
findmnt -T "$ROOT" || true

echo
echo '===== PHP ====='
php -v | head -n 2 || true
php -r 'echo "php_sapi=".PHP_SAPI.PHP_EOL;' || true

echo
echo '===== MOODLE ====='
php -r 'define("CLI_SCRIPT", true); require "config.php"; require "$CFG->dirroot/version.php"; echo "wwwroot={$CFG->wwwroot}\nrelease={$release}\nversion={$version}\nbranch={$branch}\n";' 2>&1 || true

echo
echo '===== GIT ====='
if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "branch=$(git branch --show-current)"
  echo "head=$(git rev-parse HEAD)"
  echo 'remote:'
  git remote -v | sed -E 's#(https?://)[^/@]+@#\1***@#g' || true
  echo 'status:'
  git status --short --untracked-files=no || true
else
  echo 'not_a_git_worktree=1'
fi

echo
echo '===== DIGIERA MEDIA CURRENT ====='
if [ -d local/digieramedia ]; then
  find local/digieramedia -maxdepth 2 -type f | sort | sed -n '1,120p'
  if [ -f local/digieramedia/version.php ]; then
    php -l local/digieramedia/version.php || true
    php -r 'define("MOODLE_INTERNAL", true); require "local/digieramedia/version.php"; echo "plugin_component=".($plugin->component ?? "")."\nplugin_version=".($plugin->version ?? "")."\nplugin_requires=".($plugin->requires ?? "")."\n";' 2>&1 || true
  fi
else
  echo 'plugin_present=0'
fi

echo
echo '===== SERVICES ====='
for svc in nginx apache2 php8.3-fpm php8.2-fpm moodle-cron.timer moodle-cron.service; do
  printf '%-24s ' "$svc"
  systemctl is-active "$svc" 2>/dev/null || true
done

echo
echo '===== HA / PROCESS HINTS ====='
ps -eo pid,comm,args --sort=comm | grep -E 'nginx|apache2|php-fpm|haproxy|keepalived' | grep -v grep | sed -n '1,120p' || true
ip -br addr 2>/dev/null || true

echo
echo '===== SAFE CLI CHECKS ====='
php admin/cli/checks.php 2>&1 | tail -n 80 || true

echo
echo '===== END PROBE ====='
