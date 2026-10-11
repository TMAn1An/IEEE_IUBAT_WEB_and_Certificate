#!/bin/bash
# Shared by scripts/production-bootstrap.sh and its tests
# (tests/deploy/test-deploy-safety.sh). Moves $src into $shared_path,
# symlinking $src back to it -- only after verifying a byte-identical copy
# landed in shared/ first, and refusing to silently overwrite shared/ if it
# already holds different content from an earlier partial run.
move_into_shared() {
  local src="$1" shared_path="$2" label="$3" backup_path="$4"

  if [ -L "$src" ]; then
    local target
    target="$(readlink -f "$src")"
    if [ "$target" = "$(readlink -f "$shared_path")" ]; then
      echo "$label already a symlink to shared/, skipping"
      return 0
    else
      echo "ERROR: $src is already a symlink, but points at '$target', not '$shared_path'."
      echo "Not touching it -- resolve this by hand before re-running."
      return 1
    fi
  fi

  if [ ! -e "$src" ]; then
    echo "WARNING: $src does not exist -- nothing to move for $label. Skipping."
    return 0
  fi

  if [ -e "$shared_path" ]; then
    if diff -rq "$src" "$shared_path" >/dev/null 2>&1; then
      echo "$label: shared/ copy already matches $src (from an earlier interrupted run) -- reusing it."
    else
      echo "ERROR: $shared_path already exists and DIFFERS from $src."
      echo "Compare them by hand and move/remove $shared_path yourself before re-running."
      return 1
    fi
  else
    cp -a "$src" "$shared_path"
    if ! diff -rq "$src" "$shared_path" >/dev/null 2>&1; then
      echo "ERROR: copy of $src into $shared_path did not verify as identical -- aborting without touching $src."
      rm -rf "$shared_path"
      return 1
    fi
  fi

  mv "$src" "$backup_path"
  ln -s "$shared_path" "$src"
  echo "moved $label -> shared/, verified, symlinked back"
}
