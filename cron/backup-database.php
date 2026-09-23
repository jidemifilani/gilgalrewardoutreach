<?php
/**
 * Database backup.
 *
 *   php cron/backup-database.php
 *
 * Writes a timestamped .sql dump into backups/ and prunes anything older than
 * the retention window. Intended for a scheduled task (Windows Task Scheduler
 * or cron), but it is safe to run by hand any time.
 *
 * CLI only -- refuses to run over HTTP so a database dump can never be
 * triggered, or downloaded, from the web.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("This script runs from the command line only.\n");
}

require_once __DIR__ . '/../config/config.php';

const KEEP_DAYS = 14;

$backupDir = __DIR__ . '/../backups';

if (!is_dir($backupDir) && !@mkdir($backupDir, 0775, true)) {
    fwrite(STDERR, "Could not create the backups directory.\n");
    exit(1);
}

$stamp = date('Y-m-d_His');
$file  = $backupDir . '/' . DB_NAME . '-' . $stamp . '.sql';

// mysqldump lives beside the mysql client in a XAMPP install.
$candidates = [
    'C:/xampp/mysql/bin/mysqldump.exe',
    '/usr/bin/mysqldump',
    '/usr/local/bin/mysqldump',
    'mysqldump',
];

$dump = null;
foreach ($candidates as $candidate) {
    if ($candidate === 'mysqldump' || is_file($candidate)) {
        $dump = $candidate;
        break;
    }
}

if ($dump === null) {
    fwrite(STDERR, "mysqldump was not found. Edit the candidates list in this script.\n");
    exit(1);
}

/*
 * The password goes in via MYSQL_PWD rather than --password= so it never
 * appears in the process list, where any other user on the machine could read
 * it. An empty password (the XAMPP default) is left unset entirely.
 */
/*
 * getenv() rather than $_ENV: on a default Windows PHP build variables_order
 * leaves $_ENV empty, and handing proc_open an empty environment strips PATH
 * and the networking variables -- mysqldump then cannot even resolve
 * "localhost". Passing null keeps the parent environment when there is no
 * password to inject.
 */
$env = null;
if (DB_PASS !== '') {
    $env = getenv();
    $env['MYSQL_PWD'] = DB_PASS;
}

$command = escapeshellarg($dump)
    . ' --host=' . escapeshellarg(DB_HOST)
    . ' --user=' . escapeshellarg(DB_USER)
    . ' --single-transaction --quick --default-character-set=utf8mb4'
    . ' --routines --events --add-drop-table'
    . ' ' . escapeshellarg(DB_NAME);

$handle = @fopen($file, 'wb');
if (!$handle) {
    fwrite(STDERR, "Could not open {$file} for writing.\n");
    exit(1);
}

$process = proc_open(
    $command,
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
    null,
    $env
);

if (!is_resource($process)) {
    fclose($handle);
    @unlink($file);
    fwrite(STDERR, "Could not start mysqldump.\n");
    exit(1);
}

stream_copy_to_stream($pipes[1], $handle);
$errors = stream_get_contents($pipes[2]);

fclose($pipes[1]);
fclose($pipes[2]);
fclose($handle);

$status = proc_close($process);

if ($status !== 0 || filesize($file) === 0) {
    @unlink($file);
    fwrite(STDERR, "mysqldump failed: " . trim($errors) . "\n");
    exit(1);
}

printf("Wrote %s (%s)\n", basename($file), format_size(filesize($file)));

// --- Prune old dumps --------------------------------------------------------
$removed = 0;
$cutoff  = time() - (KEEP_DAYS * 86400);

foreach (glob($backupDir . '/' . DB_NAME . '-*.sql') ?: [] as $old) {
    if (filemtime($old) < $cutoff) {
        @unlink($old);
        $removed++;
    }
}

if ($removed > 0) {
    printf("Removed %d backup(s) older than %d days.\n", $removed, KEEP_DAYS);
}

function format_size(int $bytes): string
{
    foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
        if ($bytes < 1024) {
            return round($bytes, 1) . ' ' . $unit;
        }
        $bytes = (int) ($bytes / 1024);
    }
    return $bytes . ' TB';
}
