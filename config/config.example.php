<?php
/**
 * Gilgal Reward Outreach — configuration
 *
 * Copy this file to config.php and fill in the real values.
 * config.php is git-ignored so local credentials never leave this machine.
 */

// --- Database -------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'gilgalrewardoutreach');
define('DB_USER', 'root');
define('DB_PASS', '');

// --- Site -----------------------------------------------------------------
// BASE_URL is the root-relative path the site is served from.
// XAMPP sub-folder: '/gilgalrewardoutreach'   |   real domain at document root: ''
define('BASE_URL', '/gilgalrewardoutreach');

// Fallback brand name. The live value lives in site_settings.site_name and is
// editable from Admin -> Settings; this is only used before the DB is seeded.
define('SITE_NAME', 'Gilgal Reward Outreach');

// Redirect http -> https. Leave false on local XAMPP, switch on in production.
define('FORCE_HTTPS', false);

// --- Outgoing mail --------------------------------------------------------
// Leave MAIL_HOST as 'smtp.example.com' to keep mail disabled: every form still
// saves to the database and shows a success message, it just doesn't try to send.
define('MAIL_HOST', 'smtp.example.com');
define('MAIL_PORT', 587);
define('MAIL_USER', '');
define('MAIL_PASS', '');
define('MAIL_FROM', 'no-reply@example.com');

// --- Security -------------------------------------------------------------
// Failed admin logins allowed before the account locks, and lockout length.
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);
