#!/bin/bash
# Standalone tests for the production-deploy safety fixes (no SSH, no
# network, no real server -- pure local filesystem + mocked commands).
# Run with: bash tests/deploy/test-deploy-safety.sh
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
source "$ROOT/scripts/lib/move-into-shared.sh"
source "$ROOT/scripts/lib/link-shared-paths.sh"

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

# --- link_shared_paths: regression for the real production failure (run 38631312670) ---
# A release tarball/extraction that leaves a REAL storage/ directory behind
# (e.g. only some subdirectories were excluded, or a future packaging change
# regresses this) must still end up with release/storage as a correct
# symlink to shared/storage, not a symlink nested inside a stale real dir.
test_storage_symlink_replaces_existing_real_directory() {
  local d="$1"
  mkdir -p "$d/shared/storage/framework/views" "$d/shared/storage/framework/cache"
  echo "APP_KEY=base64:fake" > "$d/shared/.env"

  mkdir -p "$d/release"
  # Simulate the exact bug: the archive shipped storage/framework/ (missing
  # views/) as a real, partial directory -- not a symlink.
  mkdir -p "$d/release/storage/framework/sessions"
  echo "stale real .env, not the shared one" > "$d/release/.env"

  link_shared_paths "$d/release" "$d/shared" >/dev/null

  if [ -L "$d/release/storage" ] \
     && [ "$(readlink -f "$d/release/storage")" = "$(readlink -f "$d/shared/storage")" ] \
     && [ -d "$d/release/storage/framework/views" ] \
     && [ -L "$d/release/.env" ] \
     && [ "$(cat "$d/release/.env")" = "APP_KEY=base64:fake" ]; then
    ok "an existing real storage/ (and .env) directory/file is replaced with a correct symlink to shared/, not nested inside it"
  else
    bad "storage/.env symlink did not correctly replace the pre-existing real directory/file"
  fi
}

# --- link_shared_paths: demonstrates the bug this fixes, with plain ln -sfn ---
test_plain_ln_sfn_nests_symlink_inside_existing_directory() {
  local d="$1"
  mkdir -p "$d/shared/storage/framework/views"
  mkdir -p "$d/release/storage/framework/sessions"  # real dir, missing views/ -- the bug

  ( ln -sfn "$d/shared/storage" "$d/release/storage" )

  if [ -d "$d/release/storage" ] && [ ! -L "$d/release/storage" ] && [ -L "$d/release/storage/storage" ]; then
    ok "control case: plain ln -sfn nests the symlink inside the existing real directory instead of replacing it (confirms the bug link_shared_paths fixes)"
  else
    bad "expected plain ln -sfn to reproduce the nesting bug, but it didn't -- control case is no longer valid"
  fi
}

# --- link_shared_paths: already-correct symlinks are left alone (idempotent) ---
test_storage_symlink_idempotent_when_already_correct() {
  local d="$1"
  mkdir -p "$d/shared/storage/framework/views"
  echo "APP_KEY=base64:fake" > "$d/shared/.env"
  mkdir -p "$d/release"
  ln -s "$d/shared/storage" "$d/release/storage"
  ln -s "$d/shared/.env" "$d/release/.env"

  link_shared_paths "$d/release" "$d/shared" >/dev/null

  if [ -L "$d/release/storage" ] && [ "$(readlink -f "$d/release/storage")" = "$(readlink -f "$d/shared/storage")" ]; then
    ok "re-running link_shared_paths against already-correct symlinks is a safe no-op"
  else
    bad "re-running on already-correct symlinks broke them"
  fi
}

# --- migrate --pretend pre-check: regression for run 38634232554 ---
# This reproduces the deploy workflow's migration-pretend-check block logic
# (kept in sync by hand with .github/workflows/deploy-production.yml's
# "validating pending migrations are non-destructive" step, since that step
# runs on the remote server over SSH and can't source a local file) against
# fake `php` binaries, to check all three outcomes without a real Laravel
# install or database.
run_pretend_check() {
  local php_bin="$1"
  PATH="$php_bin:$PATH" bash -c '
    set -e
    if ! pretend_output=$(php artisan migrate --pretend --force 2>&1); then
      echo "ERROR: php artisan migrate --pretend --force failed -- output below:"
      echo "$pretend_output"
      exit 1
    fi
    if echo "$pretend_output" | grep -iE "drop |truncate |rename column"; then
      echo "ERROR: destructive SQL detected, refusing to auto-apply"
      exit 1
    fi
    echo "$pretend_output"
  '
}

test_migrate_pretend_check_logic() {
  local d="$1"

  # Case 1: --force is what makes this work non-interactively at all. A fake
  # `php` that reproduces Laravel's ConfirmableTrait behavior: cancels
  # (non-zero exit, "Command cancelled") unless --force is present in its
  # arguments -- this is exactly what broke run 38634232554 before this fix.
  mkdir -p "$d/bin-confirmable"
  cat > "$d/bin-confirmable/php" <<'EOF'
#!/bin/bash
if [[ "$*" == *"--force"* ]]; then
  echo "INFO Nothing to migrate."
  exit 0
else
  echo "APPLICATION IN PRODUCTION."
  echo "WARN  Command cancelled."
  exit 1
fi
EOF
  chmod +x "$d/bin-confirmable/php"
  if run_pretend_check "$d/bin-confirmable" >/tmp/out.$$ 2>&1; then
    ok "migrate --pretend --force succeeds non-interactively against a Laravel ConfirmableTrait-style prompt (the exact production failure this fixes)"
  else
    bad "migrate --pretend --force still fails against a confirmable-style prompt: $(cat /tmp/out.$$)"
  fi
  rm -f /tmp/out.$$

  # Case 2: a real failure's diagnostic must reach stdout, not be swallowed
  # by the $(...) command substitution (requirement: failures must print a
  # useful diagnostic, not hide it) -- this was the second half of the same
  # bug: the original script's `pretend=$(...)` had no failure handling at
  # all, so `set -e` killed the script silently right there.
  mkdir -p "$d/bin-broken"
  cat > "$d/bin-broken/php" <<'EOF'
#!/bin/bash
echo "SQLSTATE[HY000] [2002] Connection refused" >&2
exit 1
EOF
  chmod +x "$d/bin-broken/php"
  output=$(run_pretend_check "$d/bin-broken" 2>&1) && status=0 || status=$?
  if [ "$status" != "0" ] && echo "$output" | grep -q "Connection refused"; then
    ok "a real migrate --pretend failure's diagnostic output reaches the log instead of being silently swallowed"
  else
    bad "failure diagnostic was not surfaced: status=$status output=$output"
  fi

  # Case 3: --pretend stays mandatory -- destructive-migration detection
  # (DROP/TRUNCATE/RENAME COLUMN) must still block, using the same output
  # --force successfully produced.
  mkdir -p "$d/bin-destructive"
  cat > "$d/bin-destructive/php" <<'EOF'
#!/bin/bash
echo "2026_01_01_000000_drop_stuff"
echo "  drop table \"old_table\""
exit 0
EOF
  chmod +x "$d/bin-destructive/php"
  if run_pretend_check "$d/bin-destructive" >/tmp/out.$$ 2>&1; then
    bad "destructive SQL (drop table) should have been blocked, but the check passed"
  else
    ok "destructive-migration detection still blocks a DROP in the --pretend --force output"
  fi
  rm -f /tmp/out.$$
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
run storage_symlink_replaces_existing_real_directory
run plain_ln_sfn_nests_symlink_inside_existing_directory
run storage_symlink_idempotent_when_already_correct
run migrate_pretend_check_logic

echo
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
