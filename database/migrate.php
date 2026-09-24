<?php
/**
 * Idempotent, portable migration runner.
 *
 * Adds the admin_users v2 columns and the v2 performance indexes to an
 * EXISTING database that predates them, without relying on database-specific
 * DDL syntax.
 *
 * Why this exists rather than plain SQL: "ALTER TABLE ... ADD COLUMN IF NOT
 * EXISTS" and "ADD INDEX IF NOT EXISTS" are accepted by MariaDB (this
 * project's local dev server) but rejected as a syntax error by plain MySQL
 * 8.0 (this project's first production host, cPanel build 8.0.46-cll-lve).
 * That exact statement is what silently truncated the first live deploy of
 * this schema -- every table before it in file order was created, everything
 * after it in file order (v2 settings, faq/milestone/story seed data) never
 * ran, and admin login itself 500'd because it writes to last_login_ip, one
 * of the columns that was never added.
 *
 * This script checks information_schema before every write instead, which
 * works identically on MySQL and MariaDB and is safe to run any number of
 * times on any version.
 *
 *   php database/migrate.php          CLI (preferred)
 *
 * Also runnable over HTTP if CLI access is not available on a host, but only
 * when visited with the admin session already signed in, so nobody else can
 * trigger it.
 */

require_once __DIR__ . '/../includes/db.php';

$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    require_once __DIR__ . '/../admin/includes/auth.php';
    require_admin();
    header('Content-Type: text/plain; charset=utf-8');
}

function migrate_out(string $line): void
{
    echo $line . (php_sapi_name() === 'cli' ? "\n" : "\n");
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);
    return (int) $stmt->fetchColumn() > 0;
}

function apply(PDO $pdo, string $sql, string $label): void
{
    try {
        $pdo->exec($sql);
        migrate_out("  [done] {$label}");
    } catch (PDOException $e) {
        migrate_out("  [FAILED] {$label} -- " . $e->getMessage());
    }
}

migrate_out('Migration: v2 admin_users columns + performance indexes');
migrate_out('Database: ' . DB_NAME . "\n");

$columns = [
    'last_login_ip' => "VARCHAR(60) NOT NULL DEFAULT ''",
    'is_active'     => 'TINYINT(1) NOT NULL DEFAULT 1',
    'totp_secret'   => "VARCHAR(64) NOT NULL DEFAULT ''",
    'totp_enabled'  => 'TINYINT(1) NOT NULL DEFAULT 0',
    'reset_token'   => "VARCHAR(64) NOT NULL DEFAULT ''",
    'reset_expires' => 'DATETIME NULL',
];

migrate_out('admin_users columns:');
foreach ($columns as $col => $def) {
    if (column_exists($pdo, 'admin_users', $col)) {
        migrate_out("  [ok] {$col} already present");
    } else {
        apply($pdo, "ALTER TABLE admin_users ADD COLUMN {$col} {$def}", "add admin_users.{$col}");
    }
}

$indexes = [
    ['gallery_items', 'idx_gallery_state',    'ALTER TABLE gallery_items ADD INDEX idx_gallery_state (state)'],
    ['outreaches',     'idx_outreach_state',   'ALTER TABLE outreaches ADD INDEX idx_outreach_state (state)'],
    ['audit_log',      'idx_audit_created',    'ALTER TABLE audit_log ADD INDEX idx_audit_created (created_at)'],
    ['volunteers',     'idx_volunteers_email', 'ALTER TABLE volunteers ADD INDEX idx_volunteers_email (email)'],
];

migrate_out("\nIndexes:");
foreach ($indexes as [$table, $idx, $sql]) {
    if (index_exists($pdo, $table, $idx)) {
        migrate_out("  [ok] {$table}.{$idx} already present");
    } else {
        apply($pdo, $sql, "add {$table}.{$idx}");
    }
}

migrate_out("\nDone. Re-running this script is always safe.");
