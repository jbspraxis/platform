#!/usr/bin/env bash
# JBS Praxis auto-deploy. Cron runs it every minute on the cPanel server:
#   * * * * * /home/smartqaq/deploy/jbspraxis-deploy.sh >/dev/null 2>&1
# When origin/production moves (GitHub releases a green main there, .github/workflows/pipeline.yml), it
#   1. diffs the live commit (~/deploy/LIVE) against the new one,
#   2. refuses to touch a live file that someone changed by hand since the last deploy (conflict),
#   3. backs up every file it replaces or removes (~/deploy/backups/<time>_<sha>/), then copies the new files,
#   4. builds new packages (only if a composer.lock changed) in a staging folder before any live change, and
#      swaps that vendor folder in next to the file copy; runs migrations (only if one was added or changed),
#      clears Laravel's caches and builds them again from the new files (config, routes, events, views:
#      `artisan optimize`), and checks HQ, the portal and the site answer 200,
#   5. on any failure restores the backup (and the old vendor folder) and logs an error in HQ (System health emails it).
# With the config cached, Laravel reads .env only when the cache is built: so on every run (also when nothing was
# released) an app whose .env changed since its caches were built (an edit in cPanel's File Manager) gets them
# built again, and the edit takes effect within a minute, as it did without the cache.
# Pause:  touch ~/deploy/PAUSED      Dry run:  DRY=1 ~/deploy/jbspraxis-deploy.sh
# A failed release is not retried; the next release (a new push to main) is.
# Test: deploy/server/tests/deploy-test.sh runs this against a throwaway home with stubs (DEPLOY_HOME, DEPLOY_PHP).
set -uo pipefail

H=${DEPLOY_HOME:-/home/smartqaq}
REPO=$H/repositories/jbspraxis
STATE=$H/deploy
PHP=${DEPLOY_PHP:-/usr/local/bin/ea-php83}
DRY=${DRY:-0}
STAMP=$(date +%Y%m%d-%H%M%S)
mkdir -p "$STATE/logs" "$STATE/backups"
LOG="$STATE/logs/deploy-$(date +%Y%m).log"
log() { echo "$(date '+%F %T') $*" >> "$LOG"; [ "$DRY" = 1 ] && echo "$*"; return 0; }

exec 9>"$STATE/deploy.lock"
flock -n 9 || exit 0
[ -f "$STATE/PAUSED" ] && [ "$DRY" != 1 ] && exit 0

# Laravel's caches for one app (praxis_core or praxis_site): cleared, then built again from the files now live, so
# no request pays to read every config file, register every route, scan app/Listeners or compile a Blade view.
# If building them fails the app runs without them (slower, as before this step existed) and the run says so.
# The .env it was built from is remembered (~/deploy/env-<app>.sha1) for the check below.
warm() {
  local app=$1 dir="$H/$1"
  [ -f "$dir/artisan" ] || return 0
  (cd "$dir" && $PHP artisan optimize:clear >/dev/null 2>&1)
  if (cd "$dir" && $PHP artisan optimize >> "$LOG" 2>&1); then
    env_sum "$app" > "$STATE/env-$app.sha1"
    return 0
  fi
  (cd "$dir" && $PHP artisan optimize:clear >/dev/null 2>&1)
  # Remembered all the same: without caches the app reads this .env itself, and a failing build isn't retried every minute
  env_sum "$app" > "$STATE/env-$app.sha1"
  log "  $app: artisan optimize failed; it runs without Laravel's caches until the next release or .env change"
  return 1
}
env_sum() { if [ -f "$H/$1/.env" ]; then sha1sum < "$H/$1/.env" | cut -c1-40; else echo none; fi; }
if [ "$DRY" != 1 ]; then
  for app in praxis_core praxis_site; do
    [ -f "$H/$app/artisan" ] || continue
    if [ "$(env_sum "$app")" != "$(cat "$STATE/env-$app.sha1" 2>/dev/null)" ]; then
      log "$app: .env changed since its caches were built (or they never were): building them"
      warm "$app" || true
    fi
  done
fi

cd "$REPO" || { log "repo missing: $REPO"; exit 1; }
git fetch -q origin production 2>>"$LOG" || { log "fetch failed"; exit 1; }
NEW=$(git rev-parse origin/production)
OLD=$(cat "$STATE/LIVE" 2>/dev/null || true)
[ -n "$OLD" ] || { log "no ~/deploy/LIVE baseline: refusing to guess what is live"; exit 1; }
[ "$NEW" = "$OLD" ] && [ "$DRY" != 1 ] && exit 0
[ "$NEW" = "$(cat "$STATE/FAILED" 2>/dev/null)" ] && [ "$DRY" != 1 ] && exit 0
git cat-file -e "$OLD^{commit}" 2>/dev/null || { log "live commit $OLD not in the server clone"; exit 1; }

# The deployer updates itself from the release before deploying it, so fixes to this script ship through the
# pipeline too. The new version must pass a syntax check; it then runs once in place of this one.
SELF="$STATE/jbspraxis-deploy.sh"
if [ "$DRY" != 1 ] && [ -z "${DEPLOYER_UPDATED:-}" ] && git cat-file -e "$NEW:deploy/server/jbspraxis-deploy.sh" 2>/dev/null; then
  git show "$NEW:deploy/server/jbspraxis-deploy.sh" > "$SELF.new"
  if cmp -s "$SELF.new" "$SELF"; then rm -f "$SELF.new"
  elif bash -n "$SELF.new" 2>>"$LOG"; then
    chmod 700 "$SELF.new" && mv -f "$SELF.new" "$SELF" && log "deployer updated from release ${NEW:0:7}"
    exec 9>&-
    DEPLOYER_UPDATED=1 exec "$SELF"
  else
    rm -f "$SELF.new"; log "the deployer in release ${NEW:0:7} has a syntax error: kept the current one"
  fi
fi

# Repository path -> live path ('' = not deployed)
dest() {
  case "$1" in
    praxis_core/tests/*|praxis_core/scripts/*|praxis_site/tests/*|praxis_site/scripts/*) echo '' ;;
    praxis_site/resources/js/*|praxis_site/resources/css/*) echo '' ;;
    */package.json|*/package-lock.json|*/phpunit.xml|*/vite.config.js|*/.env.example|*/.gitignore|*/.gitattributes|*/.editorconfig) echo '' ;;
    praxis_core/public/build/assets/*) echo "$H/public_html/PRAXIS/build/assets/${1#praxis_core/public/build/assets/}" ;;
    praxis_core/*|praxis_site/*) echo "$H/$1" ;;
    public_html_PRAXIS/*) echo "$H/public_html/PRAXIS/${1#public_html_PRAXIS/}" ;;
    *) echo '' ;;
  esac
}
# Built files are named by content (or are the manifests that point to them): replace without a base check
is_build() { case "$1" in praxis_core/public/build/*|praxis_site/public/build/*) return 0 ;; esac; return 1; }
# The blob id of a live file, also with CRLF line ends removed (files uploaded by hand from Windows)
live_ids() { git hash-object -- "$1" 2>/dev/null; tr -d '\r' < "$1" | git hash-object --stdin 2>/dev/null; }
# Whether a blob id is among them. Compared in memory: piping into `grep -q` under pipefail fails at random
# (grep stops reading after the first match and the writer dies of SIGPIPE), which flagged unchanged files.
has_id() { [[ $'\n'"$1"$'\n' == *$'\n'"$2"$'\n'* ]]; }

BK="$STATE/backups/${STAMP}_${NEW:0:7}"
CHANGES=$(git diff --name-status --no-renames "$OLD" "$NEW")
declare -a PLAN=() CONFLICTS=()
while IFS=$'\t' read -r st path; do
  [ -n "$path" ] || continue
  d=$(dest "$path"); [ -n "$d" ] || continue
  if [ "$st" = D ]; then
    [ -e "$d" ] || continue
    # Old built files stay: pages already open in a browser (or cached) still ask for them
    is_build "$path" && continue
    want=$(git rev-parse "$OLD:$path")
    if has_id "$(live_ids "$d")" "$want"; then PLAN+=("D|$path|$d")
    else CONFLICTS+=("$path (changed by hand before its removal)"); fi
    continue
  fi
  new_id=$(git rev-parse "$NEW:$path")
  if [ -e "$d" ]; then
    ids=$(live_ids "$d")
    if has_id "$ids" "$new_id"; then continue; fi                                  # already live
    if is_build "$path"; then PLAN+=("R|$path|$d"); continue; fi
    old_id=$(git rev-parse -q --verify "$OLD:$path" 2>/dev/null || true)
    if [ -n "$old_id" ] && has_id "$ids" "$old_id"; then PLAN+=("R|$path|$d")
    else CONFLICTS+=("$path (live differs from the last deploy)"); fi
  else
    PLAN+=("A|$path|$d")
  fi
done <<< "$CHANGES"

log "release ${NEW:0:7} (live ${OLD:0:7}): ${#PLAN[@]} file(s) to apply, ${#CONFLICTS[@]} conflict(s)"
alert() {
  log "ALERT: $1"
  [ "$DRY" = 1 ] && return 0
  (cd "$H/praxis_core" && $PHP artisan tinker --execute="\\Illuminate\\Support\\Facades\\Log::error('Deploy: ' . base64_decode('$(printf %s "$1" | base64 -w0)'));" >/dev/null 2>&1) || true
}
if [ "${#CONFLICTS[@]}" -gt 0 ]; then
  for c in "${CONFLICTS[@]}"; do log "  conflict: $c"; done
  echo "$NEW" > "$STATE/FAILED"
  alert "release ${NEW:0:7} stopped before any change: ${#CONFLICTS[@]} live file(s) were changed outside the pipeline (first: ${CONFLICTS[0]}). Commit those changes to the repository, then push again. Log: ~/deploy/logs."
  exit 1
fi
# The apps whose packages change (their composer.lock is in the plan)
declare -a STAGED=()
for p in "${PLAN[@]}"; do
  case "$p" in *"|praxis_core/composer.lock|"*) STAGED+=(praxis_core) ;; *"|praxis_site/composer.lock|"*) STAGED+=(praxis_site) ;; esac
done
if [ "$DRY" = 1 ]; then
  for p in "${PLAN[@]}"; do echo "  $p"; done
  for app in "${STAGED[@]}"; do echo "  composer install for $app in $STATE/stage/$app, then its vendor folder is swapped in"; done
  exit 0
fi

# Composer never runs in the live app. In place (release 570aa23, 31 Oct 2026) the old autoloader went on
# loading packages Composer was removing, and every HQ page answered 500 for about a minute. So the new vendor
# folder is built under ~/deploy/stage/<app>, from the release's own files and a copy of the live vendor (only
# the difference is downloaded), while the live release keeps serving. Composer's scripts are skipped there
# (the app can't boot in the stage); package discovery runs in the live app after the swap.
STAGE="$STATE/stage"
stage_vendor() {
  local dir="$STAGE/$1"
  rm -rf "$dir" && mkdir -p "$STAGE" || return 1
  git archive "$NEW" "$1" | tar -x -C "$STAGE" || return 1
  if [ -d "$H/$1/vendor" ]; then cp -a "$H/$1/vendor" "$dir/vendor" || return 1; fi
  (cd "$dir" && $PHP "$H/composer.phar" install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts \
    --ignore-platform-req=ext-fileinfo --ignore-platform-req=ext-intl >> "$LOG" 2>&1)
}
for app in "${STAGED[@]}"; do
  log "composer install for $app in $STAGE/$app (nothing live has changed)"
  if ! stage_vendor "$app"; then
    rm -rf "$STAGE"; echo "$NEW" > "$STATE/FAILED"
    alert "release ${NEW:0:7} stopped before any change: composer install for $app failed in the staging folder. Log: ~/deploy/logs."
    exit 1
  fi
done

mkdir -p "$BK"; : > "$BK/added.txt"
declare -a SWAPPED=()
rollback() {
  log "ROLLBACK: $1"
  # The old vendor folder goes back first (it waited in the backup), then the files
  for app in "${SWAPPED[@]}"; do
    rm -rf "$BK/vendor-$app-failed"
    mv "$H/$app/vendor" "$BK/vendor-$app-failed" && mv "$BK/vendor-$app" "$H/$app/vendor"
    rm -f "$H/$app/bootstrap/cache/packages.php" "$H/$app/bootstrap/cache/services.php"
    rm -rf "$BK/vendor-$app-failed"
  done
  rm -rf "$STAGE"
  while read -r f; do [ -n "$f" ] && rm -f "$f"; done < "$BK/added.txt"
  (cd "$BK/files" 2>/dev/null && find . -type f | while read -r f; do mkdir -p "$(dirname "/${f#./}")"; cp -p "$f" "/${f#./}"; done)
  # The old release's caches, built again from its restored files (cleared and left so if that fails)
  warm praxis_core; warm praxis_site
  uapi NginxCaching clear_cache >/dev/null 2>&1
  echo "$NEW" > "$STATE/FAILED"
  alert "release ${NEW:0:7} failed and was rolled back to ${OLD:0:7}: $1. Log: ~/deploy/logs."
  exit 1
}

# Before any file changes: the apps about to change drop their config, route and event caches (not the app cache or
# compiled views), so that while files, packages and migrations change they run on the files themselves, as without
# caches, and never on caches of the release going out (a new route the layout links to would fail on the old route
# cache). warm() builds them again once everything is in place.
declare -A CHANGING=()
for p in "${PLAN[@]}"; do
  case "${p#*|}" in praxis_core/*|public_html_PRAXIS/*) CHANGING[praxis_core]=1 ;; praxis_site/*) CHANGING[praxis_site]=1 ;; esac
done
for app in "${!CHANGING[@]}"; do
  [ -f "$H/$app/artisan" ] && (cd "$H/$app" && $PHP artisan optimize:clear --except=cache,views >> "$LOG" 2>&1)
done

# Apply, with a backup of everything replaced or removed and a list of what was added
CORE=0; SITE=0; MIGRATE=0
for p in "${PLAN[@]}"; do
  IFS='|' read -r op path d <<< "$p"
  case "$path" in praxis_core/*|public_html_PRAXIS/*) CORE=1 ;; praxis_site/*) SITE=1 ;; esac
  case "$path" in praxis_core/database/migrations/*) MIGRATE=1 ;; esac
  if [ "$op" != A ]; then mkdir -p "$BK/files$(dirname "$d")" && cp -p "$d" "$BK/files$d"; fi
  if [ "$op" = D ]; then rm -f "$d"; continue; fi
  mkdir -p "$(dirname "$d")"
  git show "$NEW:$path" > "$d.deploy-tmp" && mv -f "$d.deploy-tmp" "$d"
  [ "$op" = A ] && echo "$d" >> "$BK/added.txt"
done
log "applied ${#PLAN[@]} file(s); backup $BK"

# The staged vendor folder goes live with two renames (same disk, so instant); the old one waits in the backup
# until the health checks pass. Laravel's package caches describe the old folder: removed, they are rebuilt.
swap_vendor() {
  local live="$H/$1/vendor"
  if [ -d "$live" ]; then mv "$live" "$BK/vendor-$1" || return 1; fi
  if ! mv "$STAGE/$1/vendor" "$live"; then
    [ -d "$BK/vendor-$1" ] && mv "$BK/vendor-$1" "$live"
    return 1
  fi
  SWAPPED+=("$1")
  rm -f "$H/$1/bootstrap/cache/packages.php" "$H/$1/bootstrap/cache/services.php"
}
for app in "${STAGED[@]}"; do
  swap_vendor "$app" || rollback "the new vendor folder for $app could not be moved in"
  (cd "$H/$app" && $PHP artisan package:discover --ansi >> "$LOG" 2>&1) || rollback "package discovery ($app) failed"
done
rm -rf "$STAGE"

if [ "$MIGRATE" = 1 ]; then
  (cd "$H/praxis_core" && $PHP artisan migrate --force >> "$LOG" 2>&1) || rollback "migrations failed (check the database by hand)"
fi
# Caches built from the new files; a failure leaves that app without them (it still works) and is reported
WARM_FAILED=()
if [ "$CORE" = 1 ]; then warm praxis_core || WARM_FAILED+=(praxis_core); fi
if [ "$SITE" = 1 ]; then warm praxis_site || WARM_FAILED+=(praxis_site); fi
uapi NginxCaching clear_cache >/dev/null 2>&1

ok() { local code; for i in 1 2 3; do code=$(curl -s -o /dev/null -w '%{http_code}' -m 20 "$1"); [ "$code" = 200 ] && return 0; sleep 3; done; log "  $1 answered $code"; return 1; }
for u in https://hq.jbspraxis.com/login https://portal.jbspraxis.com/login https://jbspraxis.com/ https://jbspraxis.com/fr; do
  ok "$u" || rollback "$u did not answer 200 after the deploy"
done

# Healthy: the old vendor folders are no longer needed (they are the size of the app's packages)
for app in "${SWAPPED[@]}"; do rm -rf "$BK/vendor-$app"; done
echo "$NEW" > "$STATE/LIVE"; rm -f "$STATE/FAILED"
if [ "${#WARM_FAILED[@]}" -gt 0 ]; then
  alert "release ${NEW:0:7} is live, but artisan optimize failed for ${WARM_FAILED[*]}: it answers without Laravel's caches (slower). Log: ~/deploy/logs."
fi
printf '{"live":"%s","previous":"%s","deployed_at":"%s","files":%d,"backup":"%s"}\n' "$NEW" "$OLD" "$(date -Is)" "${#PLAN[@]}" "$BK" > "$STATE/status.json"
log "LIVE ${NEW:0:7}: ${#PLAN[@]} file(s), migrations=$MIGRATE, new packages: ${STAGED[*]:-none}"
exit 0
