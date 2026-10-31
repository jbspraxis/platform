#!/usr/bin/env bash
# JBS Praxis auto-deploy. Cron runs it every minute on the cPanel server:
#   * * * * * /home/smartqaq/deploy/jbspraxis-deploy.sh >/dev/null 2>&1
# When origin/production moves (GitHub releases a green main there, .github/workflows/pipeline.yml), it
#   1. diffs the live commit (~/deploy/LIVE) against the new one,
#   2. refuses to touch a live file that someone changed by hand since the last deploy (conflict),
#   3. backs up every file it replaces or removes (~/deploy/backups/<time>_<sha>/), then copies the new files,
#   4. runs composer (only if a composer.lock changed) and migrations (only if one was added or changed),
#      clears the caches, and checks HQ, the portal and the site answer 200,
#   5. on any failure restores the backup and logs an error in HQ (System health emails it).
# Pause:  touch ~/deploy/PAUSED      Dry run:  DRY=1 ~/deploy/jbspraxis-deploy.sh
# A failed release is not retried; the next release (a new push to main) is.
set -uo pipefail

H=/home/smartqaq
REPO=$H/repositories/jbspraxis
STATE=$H/deploy
PHP=/usr/local/bin/ea-php83
DRY=${DRY:-0}
STAMP=$(date +%Y%m%d-%H%M%S)
mkdir -p "$STATE/logs" "$STATE/backups"
LOG="$STATE/logs/deploy-$(date +%Y%m).log"
log() { echo "$(date '+%F %T') $*" >> "$LOG"; [ "$DRY" = 1 ] && echo "$*"; return 0; }

exec 9>"$STATE/deploy.lock"
flock -n 9 || exit 0
[ -f "$STATE/PAUSED" ] && [ "$DRY" != 1 ] && exit 0

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
if [ "$DRY" = 1 ]; then for p in "${PLAN[@]}"; do echo "  $p"; done; exit 0; fi

# Apply, with a backup of everything replaced or removed and a list of what was added
mkdir -p "$BK"; : > "$BK/added.txt"
CORE=0; SITE=0; MIGRATE=0; COMPOSER_CORE=0; COMPOSER_SITE=0
for p in "${PLAN[@]}"; do
  IFS='|' read -r op path d <<< "$p"
  case "$path" in praxis_core/*|public_html_PRAXIS/*) CORE=1 ;; praxis_site/*) SITE=1 ;; esac
  case "$path" in praxis_core/database/migrations/*) MIGRATE=1 ;; esac
  [ "$path" = praxis_core/composer.lock ] && COMPOSER_CORE=1
  [ "$path" = praxis_site/composer.lock ] && COMPOSER_SITE=1
  if [ "$op" != A ]; then mkdir -p "$BK/files$(dirname "$d")" && cp -p "$d" "$BK/files$d"; fi
  if [ "$op" = D ]; then rm -f "$d"; continue; fi
  mkdir -p "$(dirname "$d")"
  git show "$NEW:$path" > "$d.deploy-tmp" && mv -f "$d.deploy-tmp" "$d"
  [ "$op" = A ] && echo "$d" >> "$BK/added.txt"
done
log "applied ${#PLAN[@]} file(s); backup $BK"

rollback() {
  log "ROLLBACK: $1"
  while read -r f; do [ -n "$f" ] && rm -f "$f"; done < "$BK/added.txt"
  (cd "$BK/files" 2>/dev/null && find . -type f | while read -r f; do mkdir -p "$(dirname "/${f#./}")"; cp -p "$f" "/${f#./}"; done)
  (cd "$H/praxis_core" && $PHP artisan optimize:clear >/dev/null 2>&1)
  (cd "$H/praxis_site" && $PHP artisan optimize:clear >/dev/null 2>&1)
  uapi NginxCaching clear_cache >/dev/null 2>&1
  echo "$NEW" > "$STATE/FAILED"
  alert "release ${NEW:0:7} failed and was rolled back to ${OLD:0:7}: $1. Log: ~/deploy/logs."
  exit 1
}

composer_install() { (cd "$H/$1" && $PHP "$H/composer.phar" install --no-dev --optimize-autoloader --no-interaction --no-progress \
  --ignore-platform-req=ext-fileinfo --ignore-platform-req=ext-intl >> "$LOG" 2>&1); }
[ "$COMPOSER_CORE" = 1 ] && { composer_install praxis_core || rollback "composer install (HQ) failed"; }
[ "$COMPOSER_SITE" = 1 ] && { composer_install praxis_site || rollback "composer install (site) failed"; }
if [ "$MIGRATE" = 1 ]; then
  (cd "$H/praxis_core" && $PHP artisan migrate --force >> "$LOG" 2>&1) || rollback "migrations failed (check the database by hand)"
fi
[ "$CORE" = 1 ] && (cd "$H/praxis_core" && $PHP artisan optimize:clear >/dev/null 2>&1)
[ "$SITE" = 1 ] && (cd "$H/praxis_site" && $PHP artisan optimize:clear >/dev/null 2>&1)
uapi NginxCaching clear_cache >/dev/null 2>&1

ok() { local code; for i in 1 2 3; do code=$(curl -s -o /dev/null -w '%{http_code}' -m 20 "$1"); [ "$code" = 200 ] && return 0; sleep 3; done; log "  $1 answered $code"; return 1; }
for u in https://hq.jbspraxis.com/login https://portal.jbspraxis.com/login https://jbspraxis.com/ https://jbspraxis.com/fr; do
  ok "$u" || rollback "$u did not answer 200 after the deploy"
done

echo "$NEW" > "$STATE/LIVE"; rm -f "$STATE/FAILED"
printf '{"live":"%s","previous":"%s","deployed_at":"%s","files":%d,"backup":"%s"}\n' "$NEW" "$OLD" "$(date -Is)" "${#PLAN[@]}" "$BK" > "$STATE/status.json"
log "LIVE ${NEW:0:7}: ${#PLAN[@]} file(s), migrations=$MIGRATE, composer core/site=$COMPOSER_CORE/$COMPOSER_SITE"
exit 0
