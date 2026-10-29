#!/usr/bin/env bash
# One build at a time (several people or agents may build at once; Vite empties public/build first).
# Usage: bash scripts/build-locked.sh
cd "$(dirname "$0")/.." || exit 1
LOCK=storage/framework/build.lock
for i in $(seq 1 120); do mkdir "$LOCK" 2>/dev/null && break; sleep 2; done
trap 'rmdir "$LOCK" 2>/dev/null' EXIT
npm run build --silent
