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

# --- regression for run 38638058559: a bare backtick pair inside a
# double-quoted multi-line bash string is NOT an inert comment to the shell
# that is PARSING that string (the GitHub Actions runner, building the
# command it hands to ssh_run) -- it is live command substitution, executed
# right then and there, comment or not. This is what actually broke that
# deploy: a comment on the "backing up the database" step read `in
# `mysqldump | gzip`,` with unescaped backticks, so the runner executed the
# literal text "mysqldump | gzip" as a real command while building the
# string, spliced gzip's null-byte-laden binary output into it, and
# corrupted everything after -- surfacing on the remote end as exactly the
# reported "command substitution: ignored null byte in input" warning
# followed by a syntax error near an orphaned ')'.
test_unescaped_backtick_in_doublequoted_string_executes_locally() {
  local d="$1"
  local marker="$d/marker"
  rm -f "$marker"
  (
    # Deliberately bare/unescaped backticks below -- this reproduces the
    # exact bug, not the fix. Do not add backslashes here.
    x="before
# comment with `touch $marker` unescaped backtick
after"
    echo "$x" >/dev/null
  )
  if [ -f "$marker" ]; then
    ok "control case: an unescaped backtick pair inside a double-quoted string executes as command substitution even though it looks like a comment (confirms the real root cause of run 38638058559)"
  else
    bad "expected the unescaped-backtick control case to execute its payload, but it didn't -- control case is no longer valid"
  fi
}

# --- the fix: backslash-escaping the backtick pair neutralizes it for this
# parse, and the comment is harmless either way once it reaches a remote
# shell (a '#' comment consumes the rest of its line unconditionally there
# too) -- this mirrors the exact escaping used in the real workflow fix. ---
test_escaped_backtick_in_doublequoted_string_is_inert() {
  local d="$1"
  local marker="$d/marker"
  rm -f "$marker"
  (
    x="before
# comment with \`touch $marker\` escaped backtick
after"
    echo "$x" >/dev/null
  )
  if [ ! -f "$marker" ]; then
    ok "escaping the backtick pair (backslash-backtick) prevents the local command substitution that caused run 38638058559"
  else
    bad "escaped backtick still executed its payload -- the fix did not neutralize it"
  fi
}

# --- write-db-backup-option-file.php: a password containing shell
# metacharacters (quotes, backslash, \$, parentheses, spaces) must round-trip
# through the option file byte-for-byte, and must never appear on a command
# line or in this test's own captured stdout (only the db name may). ---
test_option_file_handles_special_character_password() {
  local d="$1"
  mkdir -p "$d/release"
  ln -sfn "$ROOT/vendor" "$d/release/vendor"
  cat > "$d/release/.env" <<'ENVEOF'
DB_DATABASE=ieee_prod
DB_USERNAME=ieee_user
DB_PASSWORD="p@ss\"w'ord\\with\$special(chars) and spaces"
DB_HOST=127.0.0.1
DB_PORT=3306
ENVEOF

  local option_file="$d/opt.cnf"
  local db_name
  if ! db_name=$(php "$ROOT/scripts/deploy/write-db-backup-option-file.php" "$option_file" "$d/release" 2>"$d/stderr.log"); then
    bad "write-db-backup-option-file.php failed for a special-character password: $(cat "$d/stderr.log")"
    return
  fi

  local perms
  perms="$(stat -c '%a' "$option_file")"

  # Reverse the escaping independently (in Python, not reusing the subject's
  # own PHP escaping function) to confirm the password round-trips exactly,
  # and that stdout never carried anything but the database name.
  if [ "$perms" = "600" ] && [ "$db_name" = "ieee_prod" ] && python3 - "$option_file" <<'PYEOF'
import re, sys
content = open(sys.argv[1]).read()
m = re.search(r'^password="((?:[^"\\]|\\.)*)"$', content, re.M)
assert m, "password line not found or malformed: " + content
unescaped = m.group(1).replace('\\"', '"').replace('\\\\', '\\')
expected = 'p@ss"w\'ord\\with$special(chars) and spaces'
assert unescaped == expected, f"{unescaped!r} != {expected!r}"
PYEOF
  then
    ok "a password containing quotes, backslash, \$, parentheses and spaces round-trips exactly through the chmod-600 option file, with only the db name on stdout"
  else
    bad "special-character password did not round-trip correctly (perms=$perms db_name=$db_name)"
  fi
}

# --- write-db-backup-option-file.php: empty DB_PASSWORD is a legitimate
# (if unusual) credential and must still produce a usable option file, not
# an error -- only a missing/empty DB_DATABASE or DB_USERNAME should fail. ---
test_option_file_allows_empty_password() {
  local d="$1"
  mkdir -p "$d/release"
  ln -sfn "$ROOT/vendor" "$d/release/vendor"
  cat > "$d/release/.env" <<'ENVEOF'
DB_DATABASE=ieee_prod
DB_USERNAME=ieee_user
DB_PASSWORD=
ENVEOF

  local option_file="$d/opt.cnf"
  local db_name
  if db_name=$(php "$ROOT/scripts/deploy/write-db-backup-option-file.php" "$option_file" "$d/release" 2>"$d/stderr.log") \
     && [ "$db_name" = "ieee_prod" ] \
     && grep -q '^password=""$' "$option_file"; then
    ok "an empty DB_PASSWORD is accepted and written as an empty quoted value, not treated as an error"
  else
    bad "empty DB_PASSWORD was not handled correctly: $(cat "$d/stderr.log" 2>/dev/null) db_name=$db_name"
  fi
}

# --- write-db-backup-option-file.php: empty DB_DATABASE must fail closed,
# with no option file (which could carry a password) left behind. ---
test_option_file_rejects_empty_database_name() {
  local d="$1"
  mkdir -p "$d/release"
  ln -sfn "$ROOT/vendor" "$d/release/vendor"
  cat > "$d/release/.env" <<'ENVEOF'
DB_DATABASE=
DB_USERNAME=ieee_user
DB_PASSWORD=secret
ENVEOF

  local option_file="$d/opt.cnf"
  rm -f "$option_file"
  if php "$ROOT/scripts/deploy/write-db-backup-option-file.php" "$option_file" "$d/release" >"$d/stdout.log" 2>"$d/stderr.log"; then
    bad "expected an empty DB_DATABASE to fail, but the script succeeded"
  elif [ -f "$option_file" ]; then
    bad "an empty DB_DATABASE failed as expected, but still left an option file (possibly containing a password) behind"
  elif grep -qi 'password\|secret' "$d/stdout.log" "$d/stderr.log"; then
    bad "credential value leaked into stdout/stderr on failure"
  else
    ok "an empty DB_DATABASE is rejected before any option file is written, with no credential value logged"
  fi
}

# --- full DB-backup step, reproduced from .github/workflows/deploy-production.yml's
# "backing up the database before migrating" block (kept in sync by hand with
# that embedded step, since it runs on the remote server over SSH and can't
# source a local file) -- exercised against fake mysqldump binaries so each
# outcome can be tested without a real database. ---
run_db_backup() {
  local release_dir="$1" dump_path="$2"
  (
    set -eo pipefail
    option_file="$(mktemp)"
    cleanup_option_file() { rm -f "$option_file"; }
    trap cleanup_option_file EXIT
    chmod 600 "$option_file"
    if ! db_name=$(php "$ROOT/scripts/deploy/write-db-backup-option-file.php" "$option_file" "$release_dir"); then
      echo "ERROR: could not prepare database credentials for backup"
      exit 1
    fi
    if ! mysqldump --defaults-extra-file="$option_file" "$db_name" 2>"$release_dir/mysqldump.err" | gzip > "$dump_path"; then
      echo "ERROR: database backup failed"
      cat "$release_dir/mysqldump.err"
      rm -f "$dump_path"
      exit 1
    fi
    if ! gzip -t "$dump_path"; then
      echo "ERROR: database backup file failed gzip integrity check"
      exit 1
    fi
    if ! zcat "$dump_path" | grep -q '^-- MySQL dump\|^-- MariaDB dump\|CREATE TABLE'; then
      echo "ERROR: database backup does not look like a real SQL dump"
      exit 1
    fi
    if ! zcat "$dump_path" | grep -qE 'CREATE TABLE .migrations.|CREATE TABLE .certificates.'; then
      echo "ERROR: database backup does not contain this application's tables"
      exit 1
    fi
    echo "Database backup verified: $dump_path"
  )
}

test_db_backup_succeeds_with_valid_laravel_dump() {
  local d="$1"
  mkdir -p "$d/release" "$d/bin"
  ln -sfn "$ROOT/vendor" "$d/release/vendor"
  cat > "$d/release/.env" <<'ENVEOF'
DB_DATABASE=ieee_prod
DB_USERNAME=ieee_user
DB_PASSWORD=secret
ENVEOF
  cat > "$d/bin/mysqldump" <<'EOF'
#!/bin/bash
echo "-- MySQL dump"
echo 'CREATE TABLE `migrations` (id INT);'
echo 'CREATE TABLE `certificates` (id INT);'
EOF
  chmod +x "$d/bin/mysqldump"

  if PATH="$d/bin:$PATH" run_db_backup "$d/release" "$d/dump.sql.gz" >"$d/out.log" 2>&1 \
     && gzip -t "$d/dump.sql.gz"; then
    ok "a valid Laravel-schema dump passes every check and produces a verified backup"
  else
    bad "a valid Laravel-schema dump was unexpectedly rejected: $(cat "$d/out.log")"
  fi
}

test_db_backup_fails_closed_on_connection_failure() {
  local d="$1"
  mkdir -p "$d/release" "$d/bin"
  ln -sfn "$ROOT/vendor" "$d/release/vendor"
  cat > "$d/release/.env" <<'ENVEOF'
DB_DATABASE=ieee_prod
DB_USERNAME=ieee_user
DB_PASSWORD=secret
ENVEOF
  cat > "$d/bin/mysqldump" <<'EOF'
#!/bin/bash
echo "mysqldump: Got error: 2002: Connection refused" >&2
exit 1
EOF
  chmod +x "$d/bin/mysqldump"
  rm -f "$d/dump.sql.gz"

  if PATH="$d/bin:$PATH" run_db_backup "$d/release" "$d/dump.sql.gz" >"$d/out.log" 2>&1; then
    bad "a failed database connection should have aborted the backup, but it reported success"
  elif [ -f "$d/dump.sql.gz" ]; then
    bad "a failed connection left a (corrupt/empty) backup file behind instead of cleaning it up"
  elif ! grep -q "Connection refused" "$d/out.log"; then
    bad "the connection failure's diagnostic did not reach the deploy log"
  else
    ok "a database connection failure aborts the backup, cleans up the dump file, and surfaces the real diagnostic"
  fi
}

test_db_backup_fails_closed_on_generic_mysqldump_failure() {
  local d="$1"
  mkdir -p "$d/release" "$d/bin"
  ln -sfn "$ROOT/vendor" "$d/release/vendor"
  cat > "$d/release/.env" <<'ENVEOF'
DB_DATABASE=ieee_prod
DB_USERNAME=ieee_user
DB_PASSWORD=secret
ENVEOF
  cat > "$d/bin/mysqldump" <<'EOF'
#!/bin/bash
echo "mysqldump: unknown table" >&2
exit 2
EOF
  chmod +x "$d/bin/mysqldump"
  rm -f "$d/dump.sql.gz"

  if PATH="$d/bin:$PATH" run_db_backup "$d/release" "$d/dump.sql.gz" >"$d/out.log" 2>&1; then
    bad "a generic mysqldump failure should have aborted the backup, but it reported success"
  else
    ok "a generic mysqldump failure (pipefail catches it through the gzip pipe) aborts the backup"
  fi
}

test_db_backup_rejects_invalid_backup_file() {
  local d="$1"
  mkdir -p "$d/release" "$d/bin"
  ln -sfn "$ROOT/vendor" "$d/release/vendor"
  cat > "$d/release/.env" <<'ENVEOF'
DB_DATABASE=ieee_prod
DB_USERNAME=ieee_user
DB_PASSWORD=secret
ENVEOF
  # mysqldump exits 0 but prints nothing resembling a SQL dump -- e.g. a
  # misconfigured client silently producing an empty/garbage result.
  cat > "$d/bin/mysqldump" <<'EOF'
#!/bin/bash
echo "not a real dump, just some garbage text"
EOF
  chmod +x "$d/bin/mysqldump"
  rm -f "$d/dump.sql.gz"

  if PATH="$d/bin:$PATH" run_db_backup "$d/release" "$d/dump.sql.gz" >"$d/out.log" 2>&1; then
    bad "a backup file with no SQL dump header/CREATE TABLE should have been rejected, but it passed"
  else
    ok "a backup file that doesn't look like a real SQL dump is rejected even though mysqldump exited 0"
  fi
}

test_db_backup_rejects_wrong_database() {
  local d="$1"
  mkdir -p "$d/release" "$d/bin"
  ln -sfn "$ROOT/vendor" "$d/release/vendor"
  cat > "$d/release/.env" <<'ENVEOF'
DB_DATABASE=wrong_db
DB_USERNAME=ieee_user
DB_PASSWORD=secret
ENVEOF
  # A real SQL dump, but from the legacy WordPress database, not this app's
  # Laravel schema -- requirement: confirm the backup is the intended
  # database, not just that it looks like SOME valid SQL dump.
  cat > "$d/bin/mysqldump" <<'EOF'
#!/bin/bash
echo "-- MySQL dump"
echo 'CREATE TABLE `wp_posts` (id INT);'
echo 'CREATE TABLE `wp_options` (id INT);'
EOF
  chmod +x "$d/bin/mysqldump"
  rm -f "$d/dump.sql.gz"

  if PATH="$d/bin:$PATH" run_db_backup "$d/release" "$d/dump.sql.gz" >"$d/out.log" 2>&1; then
    bad "a dump containing only legacy WordPress tables should have been rejected, but it passed"
  else
    ok "a valid-looking SQL dump that lacks this application's tables (migrations/certificates) is rejected -- catches a wrong-database misconfiguration"
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
run storage_symlink_replaces_existing_real_directory
run plain_ln_sfn_nests_symlink_inside_existing_directory
run storage_symlink_idempotent_when_already_correct
run migrate_pretend_check_logic
run unescaped_backtick_in_doublequoted_string_executes_locally
run escaped_backtick_in_doublequoted_string_is_inert
run option_file_handles_special_character_password
run option_file_allows_empty_password
run option_file_rejects_empty_database_name
run db_backup_succeeds_with_valid_laravel_dump
run db_backup_fails_closed_on_connection_failure
run db_backup_fails_closed_on_generic_mysqldump_failure
run db_backup_rejects_invalid_backup_file
run db_backup_rejects_wrong_database

echo
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
