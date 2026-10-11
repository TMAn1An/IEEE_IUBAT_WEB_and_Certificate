#!/bin/bash
# One-time migration of the production cPanel account from the old flat
# "repositories/ieee-web" checkout into the releases/shared/current layout
# that .github/workflows/deploy-production.yml expects.
#
# This is NOT run automatically by any workflow. Run it once, manually, over
# SSH, with a human watching, after confirming a fresh backup exists. It is
# designed to be safe to re-run (idempotent) if interrupted, and it never
# deletes the original repositories/ieee-web checkout -- that stays in place
# as a fallback until you've verified the new layout serves traffic
# correctly, and can be removed manually afterwards.
#
# What it does, in order:
#   1. Creates releases/, shared/, and backups/ if missing.
#   2. Moves the existing .env and storage/ out of repositories/ieee-web and
#      into shared/, then symlinks them back so the old checkout keeps
#      working untouched during the transition.
#   3. Creates releases/<current-head-sha>/ as a copy of the current
#      checkout (not a move), symlinks its .env/storage to shared/.
#   4. Creates the `current` symlink pointing at that release.
#   5. Edits public_html/index.php's absolute bootstrap paths from
#      repositories/ieee-web to current -- after taking a timestamped
#      backup copy, and only if the expected path string is found verbatim
#      (refuses to guess otherwise). Validates the result with `php -l`
#      before leaving it in place; restores the backup automatically if
#      validation fails.
#
# Usage: ssh onto the cPanel account, then:
#   bash production-bootstrap.sh

set -euo pipefail

BASE="/home/ieeeiubat"
OLD_REPO="$BASE/repositories/ieee-web"
RELEASES="$BASE/releases"
SHARED="$BASE/shared"
CURRENT="$BASE/current"
PUBLIC_HTML="$BASE/public_html"
BACKUPS="$BASE/backups/bootstrap"

echo "=== Production layout bootstrap ==="
echo "This will NOT delete $OLD_REPO or touch the database."
read -r -p "Have you taken a fresh full backup (files + database) in the last hour? [y/N] " confirm
[ "$confirm" = "y" ] || { echo "Aborting -- take a backup first."; exit 1; }

[ -d "$OLD_REPO" ] || { echo "ERROR: $OLD_REPO not found -- is this the right account?"; exit 1; }

mkdir -p "$RELEASES" "$SHARED" "$BACKUPS"

echo "--- Moving .env and storage/ into shared/ (if not already done) ---"
if [ -f "$OLD_REPO/.env" ] && [ ! -L "$OLD_REPO/.env" ]; then
  cp -a "$OLD_REPO/.env" "$SHARED/.env"
  mv "$OLD_REPO/.env" "$BACKUPS/.env.pre-bootstrap"
  ln -s "$SHARED/.env" "$OLD_REPO/.env"
  echo "moved .env -> shared/, symlinked back"
else
  echo ".env already a symlink or missing, skipping"
fi

if [ -d "$OLD_REPO/storage" ] && [ ! -L "$OLD_REPO/storage" ]; then
  cp -a "$OLD_REPO/storage" "$SHARED/storage"
  mv "$OLD_REPO/storage" "$BACKUPS/storage.pre-bootstrap"
  ln -s "$SHARED/storage" "$OLD_REPO/storage"
  echo "moved storage/ -> shared/, symlinked back"
else
  echo "storage/ already a symlink or missing, skipping"
fi

HEAD_SHA="$(git -C "$OLD_REPO" rev-parse HEAD)"
RELEASE_DIR="$RELEASES/$HEAD_SHA"

echo "--- Creating initial release $HEAD_SHA (copy, not move) ---"
if [ ! -d "$RELEASE_DIR" ]; then
  rsync -a --exclude='.env' --exclude='storage' "$OLD_REPO/" "$RELEASE_DIR/"
  ln -sfn "$SHARED/.env" "$RELEASE_DIR/.env"
  ln -sfn "$SHARED/storage" "$RELEASE_DIR/storage"
  echo "created $RELEASE_DIR"
else
  echo "$RELEASE_DIR already exists, skipping copy"
fi

echo "--- Activating current symlink ---"
ln -sfn "$RELEASE_DIR" "$CURRENT.new"
mv -Tf "$CURRENT.new" "$CURRENT"
echo "current -> $(readlink "$CURRENT")"

echo "--- Updating public_html/index.php bootstrap path (backed up first) ---"
INDEX="$PUBLIC_HTML/index.php"
OLD_PATH_STRING="$OLD_REPO"
NEW_PATH_STRING="$CURRENT"
INDEX_BACKUP="$BACKUPS/index.php.$(date -u +%Y%m%dT%H%M%SZ)"
cp -a "$INDEX" "$INDEX_BACKUP"

if grep -qF "$OLD_PATH_STRING" "$INDEX"; then
  sed -i "s#$OLD_PATH_STRING#$NEW_PATH_STRING#g" "$INDEX"
  if php -l "$INDEX" >/dev/null 2>&1; then
    echo "index.php updated and passed php -l"
  else
    echo "ERROR: edited index.php failed php -l -- restoring backup"
    cp -a "$INDEX_BACKUP" "$INDEX"
    exit 1
  fi
else
  echo "WARNING: did not find the literal string '$OLD_PATH_STRING' in index.php."
  echo "index.php was NOT modified -- it may already point elsewhere, or use a"
  echo "different path format. Edit it manually to point at $CURRENT instead of"
  echo "$OLD_REPO, then re-run this script (it is safe to re-run)."
fi

echo "=== Bootstrap complete ==="
echo "current -> $(readlink "$CURRENT")"
echo "Verify https://ieee.iubat.edu/ and /admin/login before removing $OLD_REPO."
echo "$OLD_REPO was left in place untouched (its own .env/storage now symlink to shared/)."
