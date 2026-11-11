#!/usr/bin/env bash
# Runs deploy/server/jbspraxis-deploy.sh against a throwaway home: a local stand-in for GitHub with a
# `production` branch, a live tree at release A, and stubs for php (artisan and composer.phar), curl, uapi
# and, where missing, flock. Checks that Composer only ever runs in the staging folder, that the new vendor
# folder is swapped in, and that a failure before or after the swap leaves release A live with its own vendor.
# CI runs it (.github/workflows/pipeline.yml); locally: bash deploy/server/tests/deploy-test.sh
set -euo pipefail

DEPLOYER="$(cd "$(dirname "$0")/.." && pwd)/jbspraxis-deploy.sh"
ROOT=$(mktemp -d)
trap 'rm -rf "$ROOT"' EXIT
export GIT_AUTHOR_NAME=deploy-test GIT_AUTHOR_EMAIL=deploy-test@example.com
export GIT_COMMITTER_NAME=deploy-test GIT_COMMITTER_EMAIL=deploy-test@example.com
export GIT_CONFIG_COUNT=1 GIT_CONFIG_KEY_0=core.autocrlf GIT_CONFIG_VALUE_0=false

# Stubs. composer.phar install "builds" vendor/ stamped with the composer.lock it read; artisan does nothing.
BIN=$ROOT/bin
mkdir -p "$BIN"
cat > "$BIN/php" <<'EOF'
#!/usr/bin/env bash
echo "$PWD :: $*" >> "$STUB_LOG"
case "$1" in
  *composer.phar)
    [ "${COMPOSER_FAIL:-0}" = 1 ] && exit 1
    mkdir -p vendor/composer && cp composer.lock vendor/stamp && echo '{}' > vendor/composer/installed.json ;;
  artisan)
    [ "$2" = migrate ] && [ "${MIGRATE_FAIL:-0}" = 1 ] && exit 1
    [ "$2" = optimize ] && [ "${OPTIMIZE_FAIL:-0}" = 1 ] && exit 1
    # What the live code was when the caches were dropped ahead of the copy
    [ "$2" = optimize:clear ] && [ -n "${3:-}" ] && [ -f app/Thing.php ] && cat app/Thing.php >> "$STUB_LOG.code-at-clear" ;;
esac
exit 0
EOF
printf '#!/usr/bin/env bash\nprintf %%s "${HEALTH_CODE:-200}"\n' > "$BIN/curl"
printf '#!/usr/bin/env bash\nexit 0\n' > "$BIN/uapi"
command -v flock >/dev/null || printf '#!/usr/bin/env bash\nexit 0\n' > "$BIN/flock"
chmod +x "$BIN"/*

FAILS=0
check() { local what=$1; shift; if "$@"; then echo "  ok    $what"; else echo "  FAIL  $what"; FAILS=$((FAILS + 1)); fi; }
same() { [ "$1" = "$2" ]; }
absent() { local f; for f in "$@"; do [ -e "$f" ] && return 1; done; return 0; }
logged() { grep -qxF -- "$1" "$STUB_LOG"; }
# The folders composer.phar ran in, one per line
composer_dirs() { { grep -F 'composer.phar' "$STUB_LOG" || true; } | sed 's/ :: .*//' | sort -u; }

# A fresh home for one case: release A is live (its vendor folder built from lock-1, with Laravel's package
# cache), and $H/work is the developer's clone to make release B in.
setup() {
  H=$ROOT/$1
  mkdir -p "$H/deploy"
  export STUB_LOG=$H/stub.log
  : > "$STUB_LOG"
  git init -q --bare "$H/origin.git"
  git init -q -b production "$H/work"
  (cd "$H/work"
    mkdir -p praxis_core/app praxis_site
    echo v1 > praxis_core/app/Thing.php
    echo '{}' > praxis_core/composer.json
    echo lock-1 > praxis_core/composer.lock
    echo site-1 > praxis_site/index.php
    git add -A && git commit -qm A && git push -q "$H/origin.git" production)
  A=$(git -C "$H/work" rev-parse HEAD)
  mkdir -p "$H/praxis_core/app" "$H/praxis_core/vendor" "$H/praxis_core/bootstrap/cache" "$H/praxis_site"
  touch "$H/praxis_core/artisan" "$H/praxis_site/artisan"
  echo APP_ENV=production > "$H/praxis_core/.env"
  cp "$H/work/praxis_core/app/Thing.php" "$H/praxis_core/app/"
  cp "$H/work/praxis_core/composer.json" "$H/work/praxis_core/composer.lock" "$H/praxis_core/"
  cp "$H/work/praxis_site/index.php" "$H/praxis_site/"
  echo lock-1 > "$H/praxis_core/vendor/stamp"
  echo cached > "$H/praxis_core/bootstrap/cache/packages.php"
  touch "$H/composer.phar"
  git clone -q -b production "$H/origin.git" "$H/repositories/jbspraxis"
  echo "$A" > "$H/deploy/LIVE"
}
# Commit what the case changed in $H/work as release B and publish it
publish() {
  (cd "$H/work" && git add -A && git commit -qm B && git push -q "$H/origin.git" production)
  B=$(git -C "$H/work" rev-parse HEAD)
}
# What the stubs were asked to do in one app, in order: "optimize:clear", "optimize", ...
artisan_in() { { grep -F "$H/$1 :: artisan " "$STUB_LOG" || true; } | sed 's/.* :: artisan //'; }
# Run the deployer once; extra NAME=value pairs go to its environment (and so to the stubs)
deploy() { env "$@" DEPLOY_HOME="$H" DEPLOY_PHP="$BIN/php" PATH="$BIN:$PATH" bash "$DEPLOYER"; }
run() { RC=0; deploy "$@" > "$H/out.txt" 2>&1 || RC=$?; }

echo "New packages, healthy release"
setup packages
(cd "$H/work" && echo lock-2 > praxis_core/composer.lock && echo v2 > praxis_core/app/Thing.php)
publish
run
check "the deploy succeeded" same "$RC" 0
check "release B is live" same "$(cat "$H/deploy/LIVE")" "$B"
check "the new code is live" same "$(cat "$H/praxis_core/app/Thing.php")" v2
check "the live vendor folder was built from the new lock" same "$(cat "$H/praxis_core/vendor/stamp")" lock-2
check "composer ran once, only in the staging folder" same "$(composer_dirs)" "$H/deploy/stage/praxis_core"
check "Laravel's package cache was cleared" absent "$H/praxis_core/bootstrap/cache/packages.php"
check "package discovery ran in the live app" logged "$H/praxis_core :: artisan package:discover --ansi"
check "no staging folder or old vendor folder is left" absent "$H/deploy/stage" "$H"/deploy/backups/*/vendor-*

echo "Composer fails in the staging folder"
setup composer-fails
(cd "$H/work" && echo lock-2 > praxis_core/composer.lock && echo v2 > praxis_core/app/Thing.php)
publish
run COMPOSER_FAIL=1
check "the deploy stopped" same "$RC" 1
check "release A is still live" same "$(cat "$H/deploy/LIVE")" "$A"
check "release B is marked failed" same "$(cat "$H/deploy/FAILED")" "$B"
check "no live file changed" same "$(cat "$H/praxis_core/app/Thing.php") $(cat "$H/praxis_core/composer.lock")" "v1 lock-1"
check "the live vendor folder is untouched" same "$(cat "$H/praxis_core/vendor/stamp")" lock-1
check "Laravel's package cache is untouched" same "$(cat "$H/praxis_core/bootstrap/cache/packages.php")" cached
check "no staging folder is left" absent "$H/deploy/stage"

echo "A health check fails after the swap"
setup unhealthy
(cd "$H/work" && echo lock-2 > praxis_core/composer.lock && echo v2 > praxis_core/app/Thing.php)
publish
run HEALTH_CODE=500
check "the deploy rolled back" same "$RC" 1
check "release A is still live" same "$(cat "$H/deploy/LIVE")" "$A"
check "release B is marked failed" same "$(cat "$H/deploy/FAILED")" "$B"
check "the old files are back" same "$(cat "$H/praxis_core/app/Thing.php") $(cat "$H/praxis_core/composer.lock")" "v1 lock-1"
check "the old vendor folder is back" same "$(cat "$H/praxis_core/vendor/stamp")" lock-1
check "no staging folder or spare vendor folder is left" absent "$H/deploy/stage" "$H"/deploy/backups/*/vendor-*

echo "A release without new packages"
setup no-packages
(cd "$H/work" && echo v2 > praxis_core/app/Thing.php)
publish
run
check "the deploy succeeded" same "$RC" 0
check "the new code is live" same "$(cat "$H/praxis_core/app/Thing.php")" v2
check "composer did not run" same "$(composer_dirs)" ""
check "the vendor folder and package cache are untouched" same "$(cat "$H/praxis_core/vendor/stamp") $(cat "$H/praxis_core/bootstrap/cache/packages.php")" "lock-1 cached"

echo "Laravel's caches are built again after a release"
setup caches
run
: > "$STUB_LOG"
(cd "$H/work" && echo v2 > praxis_core/app/Thing.php)
publish
run
check "the deploy succeeded" same "$RC" 0
check "praxis_core dropped its route and config caches first, then built them from the new files" \
  same "$(artisan_in praxis_core | tr '\n' ' ')" "optimize:clear --except=cache,views optimize:clear optimize "
check "the caches were dropped before any file was copied" same "$(cat "$STUB_LOG.code-at-clear")" v1
check "praxis_site's caches were left alone (nothing of it changed)" same "$(artisan_in praxis_site)" ""

echo "Building the caches fails"
setup caches-fail
run
: > "$STUB_LOG"
(cd "$H/work" && echo v2 > praxis_core/app/Thing.php)
publish
run OPTIMIZE_FAIL=1
check "the release still went live" same "$RC $(cat "$H/deploy/LIVE")" "0 $B"
check "the caches were cleared again so the app runs without them" same "$(artisan_in praxis_core | grep -v "^tinker" | tail -1)" "optimize:clear"
check "the failure was logged" grep -qF "praxis_core: artisan optimize failed" "$H/deploy/logs/deploy-$(date +%Y%m).log"
check "and reported in HQ" grep -qF "artisan optimize failed for praxis_core" "$H/deploy/logs/deploy-$(date +%Y%m).log"

echo "A hand edit of .env rebuilds that app's caches, once"
setup env-edit
run
check "the first run builds the caches it has never built" same "$(artisan_in praxis_core | tr '\n' ' ')" "optimize:clear optimize "
: > "$STUB_LOG"
run
check "an unchanged .env builds nothing" same "$(artisan_in praxis_core)$(artisan_in praxis_site)" ""
echo MAIL_HOST=mail.example.test >> "$H/praxis_core/.env"
run
check "the edited .env rebuilt praxis_core's caches" same "$(artisan_in praxis_core | tr '\n' ' ')" "optimize:clear optimize "
check "and only praxis_core's" same "$(artisan_in praxis_site)" ""
check "no release was deployed by it" same "$(cat "$H/deploy/LIVE")" "$A"

echo "Dry run"
setup dry-run
(cd "$H/work" && echo lock-2 > praxis_core/composer.lock)
publish
run DRY=1
check "the dry run succeeded" same "$RC" 0
check "it lists the staged composer install" grep -qF "composer install for praxis_core in $H/deploy/stage/praxis_core" "$H/out.txt"
check "nothing changed" same "$(cat "$H/deploy/LIVE") $(cat "$H/praxis_core/vendor/stamp")" "$A lock-1"
check "composer did not run" same "$(composer_dirs)" ""

if [ "$FAILS" -gt 0 ]; then echo "$FAILS deployer check(s) failed"; exit 1; fi
echo "All deployer checks passed"
