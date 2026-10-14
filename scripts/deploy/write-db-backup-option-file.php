<?php

/**
 * Reference implementation + test subject for the database-backup credential
 * handling embedded in .github/workflows/deploy-production.yml's "backing up
 * the database before migrating" step. That step runs this script over SSH
 * on the production server (php scripts/deploy/write-db-backup-option-file.php
 * <option-file-path>, cwd = the release directory) -- keep the two in sync by
 * hand if either changes; tests/deploy/test-deploy-safety.sh tests this file
 * directly.
 *
 * Why this exists: the previous version of this step extracted DB credentials
 * with four separate `php artisan tinker --execute=...` calls and interpolated
 * the results into `MYSQL_PWD=... mysqldump -h ... -u ...` on the remote
 * bash side. That broke a real deploy ("warning: command substitution:
 * ignored null byte in input" / "syntax error near unexpected token `)'")
 * because booting PsySH/tinker four times is fragile, and any shell
 * metacharacter in the password (quotes, $, (), backslashes, spaces) that
 * survives into a bash command line or command substitution can break the
 * script or get executed as shell syntax.
 *
 * This script instead parses the release's own .env file directly with
 * phpdotenv (already a project dependency; no framework boot, no tinker, no
 * PsySH) and writes a MySQL "option file" (my.cnf-style [client] section)
 * that mysqldump reads credentials from via --defaults-extra-file. The
 * password/user/host values are written into that file by PHP's fwrite --
 * they are never interpolated into shell source and never passed as a
 * command-line argument. Only the option file's PATH crosses back into bash,
 * and only the (non-secret) database name is printed to stdout for the bash
 * wrapper to capture.
 */

function mysql_option_quote(string $value): string
{
    // my.cnf option-file quoting: wrap in double quotes, escape backslash
    // and double-quote. This makes spaces, '#', '=', single quotes, '$',
    // parentheses and backslashes in the value safe -- none of them are
    // shell metacharacters here because the value never touches a shell,
    // only MySQL's own option-file parser reads it.
    $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

    return '"' . $escaped . '"';
}

function fail(string $message): never
{
    fwrite(STDERR, "write-db-backup-option-file: {$message}\n");
    exit(1);
}

$outputPath = $argv[1] ?? null;
if ($outputPath === null || $outputPath === '') {
    fail('missing required argument: output path for the MySQL option file');
}

$releaseRoot = $argv[2] ?? getcwd();
if (!is_file($releaseRoot . '/.env')) {
    fail(".env not found in {$releaseRoot}");
}

require $releaseRoot . '/vendor/autoload.php';

// createArrayBacked returns the parsed values without touching the real
// $_ENV/$_SERVER/putenv() -- this process only ever reads the file, it
// never mutates process-wide environment state.
$env = Dotenv\Dotenv::createArrayBacked($releaseRoot)->load();

$dbName = trim((string) ($env['DB_DATABASE'] ?? ''));
$dbUser = trim((string) ($env['DB_USERNAME'] ?? ''));
$dbPass = (string) ($env['DB_PASSWORD'] ?? '');
$dbHost = trim((string) ($env['DB_HOST'] ?? '')) ?: '127.0.0.1';
$dbPort = trim((string) ($env['DB_PORT'] ?? '')) ?: '3306';

if ($dbName === '') {
    fail('DB_DATABASE is empty in .env -- refusing to back up an unspecified database');
}
if ($dbUser === '') {
    fail('DB_USERNAME is empty in .env -- refusing to connect with an unspecified user');
}
// DB_PASSWORD is intentionally allowed to be empty here: an empty value is
// still a valid (if unusual) MySQL credential, and mysqldump itself is the
// right place to reject that connection attempt if the server requires one.

$optionFileBody = "[client]\n"
    . 'user=' . mysql_option_quote($dbUser) . "\n"
    . 'password=' . mysql_option_quote($dbPass) . "\n"
    . 'host=' . mysql_option_quote($dbHost) . "\n"
    . 'port=' . mysql_option_quote($dbPort) . "\n";

if (file_put_contents($outputPath, $optionFileBody) === false) {
    fail("could not write option file to {$outputPath}");
}
if (!chmod($outputPath, 0600)) {
    fail("could not chmod option file {$outputPath} to 0600");
}

// Only the database name (not a secret) goes to stdout, for the bash caller
// to capture via $(...).
echo $dbName . "\n";
