<?php
/** Sign out. */

require_once __DIR__ . '/includes/auth.php';

admin_logout($pdo);
flash('success', 'You have been signed out.');
redirect(base_url('admin/login.php'));
