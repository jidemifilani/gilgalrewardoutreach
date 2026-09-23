<?php
/** Deletes one record. POST only, CSRF protected. */

require_once __DIR__ . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/includes/crud.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    redirect(base_url('admin/'));
}

if (!verify_csrf()) {
    flash('error', 'Your session expired. Nothing was deleted.');
    redirect(base_url('admin/'));
}

$key      = (string) ($_POST['r'] ?? '');
$id       = (int) ($_POST['id'] ?? 0);
$resource = admin_resource($pdo, $key);

if (!$resource || !$id) {
    flash('error', 'Nothing was deleted — that record could not be found.');
    redirect(base_url('admin/'));
}

$row = crud_find($pdo, $resource, $id);

if (!$row) {
    flash('error', 'That record had already been deleted.');
    redirect(base_url('admin/manage.php?r=' . urlencode($key)));
}

$describe = $row['title'] ?? $row['full_name'] ?? $row['name'] ?? $row['label'] ?? ('#' . $id);

crud_delete($pdo, $resource, $id);
audit($pdo, admin_id(), $key . '.delete', $resource['singular'] . ' #' . $id . ': ' . $describe);

flash('success', $resource['singular'] . ' deleted.');
redirect(base_url('admin/manage.php?r=' . urlencode($key)));
