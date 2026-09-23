<?php
/** CSV export for the inbox-style resources (volunteer sign-ups, messages). */

require_once __DIR__ . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/includes/crud.php';

$key      = (string) ($_GET['r'] ?? '');
$resource = admin_resource($pdo, $key);

if (!$resource || empty($resource['export'])) {
    http_response_code(404);
    flash('error', 'That section cannot be exported.');
    redirect(base_url('admin/'));
}

$rows = crud_list($pdo, $resource, '', 100000);

audit($pdo, admin_id(), $key . '.export', count($rows) . ' rows');

$filename = $key . '-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');

// BOM so Excel opens the UTF-8 correctly rather than mangling accents.
fwrite($out, "\xEF\xBB\xBF");

if ($rows) {
    fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $row) {
        fputcsv($out, array_map(static function ($value) {
            $value = (string) $value;
            // Neutralise spreadsheet formula injection in exported text.
            return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
        }, $row));
    }
} else {
    fputcsv($out, ['No records']);
}

fclose($out);
