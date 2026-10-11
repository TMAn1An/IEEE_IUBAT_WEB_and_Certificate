#!/bin/bash
# Standalone tests for the production-deploy safety fixes (no SSH, no
# network, no real server -- pure local filesystem + mocked commands).
# Run with: bash tests/deploy/test-deploy-safety.sh
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
source "$ROOT/scripts/lib/move-into-shared.sh"

pass=0
fail=0

ok() { pass=$((pass + 1)); echo "ok - $1"; }
bad() { fail=$((fail + 1)); echo "FAIL - $1"; }

with_tmp() {
  local d
  d="$(mktemp -d)"
  "$@" "$d"
  rm -rf "$d"
}

# --- move_into_shared: fresh move succeeds, source becomes a verified symlink ---
test_fresh_move() {
  local d="$1"
  echo "hello" > "$d/env.txt"
  if move_into_shared "$d/env.txt" "$d/shared.env" ".env" "$d/backup.env" >/dev/null \
     && [ -L "$d/env.txt" ] \
     && [ "$(readlink -f "$d/env.txt")" = "$(readlink -f "$d/shared.env")" ] \
     && [ "$(cat "$d/env.txt")" = "hello" ]; then
    ok "fresh move: source becomes a symlink to a verified shared copy"
  else
    bad "fresh move did not produce the expected symlink/content"
  fi
}

# --- move_into_shared: already a symlink to the right place -> no-op, no error ---
test_idempotent_already_symlinked() {
  local d="$1"
  echo "hello" > "$d/env.txt"
  move_into_shared "$d/env.txt" "$d/shared.env" ".env" "$d/backup.env" >/dev/null
  if move_into_shared "$d/env.txt" "$d/shared.env" ".env" "$d/backup.env" >/dev/null; then
    ok "re-running after a symlink already exists is a safe no-op"
  else
    bad "re-running on an already-correct symlink should not fail"
  fi
}

# --- move_into_shared: interrupted-after-copy resume (shared/ has matching content, source still a real file) ---
test_resume_after_interrupted_copy() {
  local d="$1"
  echo "hello" > "$d/env.txt"
  cp "$d/env.txt" "$d/shared.env"   # simulate: cp succeeded, then the script was killed before mv+symlink
  if move_into_shared "$d/env.txt" "$d/shared.env" ".env" "$d/backup.env" >/dev/null \
     && [ -L "$d/env.txt" ] \
     && [ -f "$d/backup.env" ]; then
    ok "resumes correctly when shared/ already has a byte-identical copy from an interrupted run"
  else
    bad "did not resume correctly from an interrupted copy"
  fi
}

# --- move_into_shared: shared/ exists but DIFFERS -> refuses, leaves source untouched ---
test_refuses_on_conflicting_shared_content() {
  local d="$1"
  echo "original" > "$d/env.txt"
  echo "something else entirely" > "$d/shared.env"
  if move_into_shared "$d/env.txt" "$d/shared.env" ".env" "$d/backup.env" >/dev/null 2>&1; then
    bad "should have refused when shared/ content differs from source"
  elif [ -f "$d/env.txt" ] && [ ! -L "$d/env.txt" ] && [ "$(cat "$d/env.txt")" = "original" ]; then
    ok "refuses to overwrite conflicting shared/ content, and leaves the source file untouched"
  else
    bad "refused correctly but source was mutated anyway"
  fi
}

# --- move_into_shared: already a symlink to the WRONG place -> refuses ---
test_refuses_symlink_to_wrong_target() {
  local d="$1"
  echo "elsewhere" > "$d/elsewhere.env"
  ln -s "$d/elsewhere.env" "$d/env.txt"
  if move_into_shared "$d/env.txt" "$d/shared.env" ".env" "$d/backup.env" >/dev/null 2>&1; then
    bad "should have refused: source is a symlink pointing somewhere other than shared_path"
  else
    ok "refuses to touch a symlink that already points somewhere unexpected"
  fi
}

# --- release verification: missing vendor/autoload.php must block activation ---
test_incomplete_release_blocks_activation() {
  local d="$1"
  mkdir -p "$d/release/public"
  touch "$d/release/artisan" "$d/release/composer.json" "$d/release/public/index.php"
  # vendor/autoload.php intentionally missing -- simulates an interrupted rsync
  local missing=0
  for required in artisan vendor/autoload.php public/index.php composer.json; do
    [ -e "$d/release/$required" ] || missing=1
  done
  if [ "$missing" = "1" ]; then
    ok "incomplete release (missing vendor/autoload.php) is correctly detected as not activatable"
  else
    bad "incomplete release was not detected"
  fi
}

# --- mysqldump backup: set -o pipefail must catch a failing mysqldump even though gzip succeeds ---
test_mysqldump_pipefail() {
  local d="$1"
  local fake_bin="$d/bin"
  mkdir -p "$fake_bin"
  cat > "$fake_bin/mysqldump" <<'EOF'
#!/bin/bash
echo "simulated connection failure" >&2
exit 1
EOF
  chmod +x "$fake_bin/mysqldump"

  (
    set -eo pipefail
    PATH="$fake_bin:$PATH" mysqldump fakeargs 2>/dev/null | gzip > "$d/dump.sql.gz"
  )
  local status=$?
  if [ "$status" -ne 0 ]; then
    ok "set -o pipefail correctly propagates a failing mysqldump through the gzip pipe"
  else
    bad "a failing mysqldump piped into gzip was NOT detected (pipefail missing or broken)"
  fi
}

# --- mysqldump backup: WITHOUT pipefail, the same failure is masked (demonstrates the bug this fixes) ---
test_mysqldump_without_pipefail_is_masked() {
  local d="$1"
  local fake_bin="$d/bin"
  mkdir -p "$fake_bin"
  cat > "$fake_bin/mysqldump" <<'EOF'
#!/bin/bash
exit 1
EOF
  chmod +x "$fake_bin/mysqldump"

  (
    # Explicitly turn pipefail OFF: it's inherited from this test file's own
    # `set -uo pipefail` at the top, so without this the subshell would
    # silently still have it, defeating the point of this control case.
    set +o pipefail
    set -e
    PATH="$fake_bin:$PATH" mysqldump fakeargs 2>/dev/null | gzip > "$d/dump.sql.gz"
  )
  local status=$?
  if [ "$status" -eq 0 ]; then
    ok "demonstrates the original bug: without pipefail, a failing mysqldump|gzip pipeline reports success"
  else
    bad "expected this control case to reproduce the masked failure, but it was caught anyway"
  fi
}

# --- release pruning: active release and rollback target must survive regardless of mtime order ---
test_pruning_protects_active_and_rollback() {
  local d="$1"
  mkdir -p "$d/releases"
  cd "$d/releases"
  for i in a b c d_ e f g; do mkdir -p "$i"; sleep 0.01; done
  # Oldest-by-mtime is "a"; simulate it being both the active release and the
  # rollback target, which a naive mtime-based "keep newest 5" would prune.
  active="a"
  rollback_target="a"
  result=$(ls -1dt */ 2>/dev/null \
    | sed 's:/$::' \
    | grep -vxF "$active" \
    | grep -vxF "$rollback_target" \
    | tail -n +4)
  for dir in $result; do rm -rf -- "$dir"; done
  cd "$d"
  if [ -d "$d/releases/a" ]; then
    ok "pruning never removes the active release even when it's the oldest by mtime"
  else
    bad "pruning incorrectly removed the active/rollback release"
  fi
}

run() {
  local name="$1"
  with_tmp "test_$name"
}

run fresh_move
run idempotent_already_symlinked
run resume_after_interrupted_copy
run refuses_on_conflicting_shared_content
run refuses_symlink_to_wrong_target
run incomplete_release_blocks_activation
run mysqldump_pipefail
run mysqldump_without_pipefail_is_masked
run pruning_protects_active_and_rollback

echo
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
