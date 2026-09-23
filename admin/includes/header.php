<?php
/** Admin shell: head, sidebar and topbar. Expects require_admin() to have run. */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/crud.php';
require_once __DIR__ . '/../../includes/theme.php';

$siteName    = site_name($pdo);
$adminName   = admin_user()['name'] ?? 'Admin';
$logoImage   = theme_image($pdo, 'logo_image');
$brandColor  = theme_brand($pdo);
$accentColor = theme_accent($pdo);
$flashes   = take_flashes();

/*
 * Everything this file puts in scope is prefixed with "sidebar", because it is
 * included partway down a page that has its own variables. An earlier version
 * used $resource/$key/$groups here and silently overwrote the calling page's
 * variables of the same name -- manage.php then rendered one resource's rows
 * through another resource's column definitions.
 */
$sidebarCurrent   = $_GET['r'] ?? '';
$sidebarResources = admin_resources($pdo);

$sidebarBadges = [
    'messages'   => crud_count($pdo, 'contact_messages', 'is_read = 0'),
    'volunteers' => crud_count($pdo, 'volunteers', "status = 'new'"),
];

$sidebarGroups = [];
foreach ($sidebarResources as $sidebarKey => $sidebarResource) {
    $sidebarGroups[$sidebarResource['group'] ?? 'Content'][$sidebarKey] = $sidebarResource;
}
unset($sidebarKey, $sidebarResource);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($pageTitle ?? 'Dashboard') . ' | ' . $siteName . ' admin') ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= e(asset_url('img/logo.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(asset_url('css/admin.css')) ?>?v=3">
</head>
<body>

<div class="admin-shell">

  <aside class="admin-sidebar" id="adminSidebar">
    <a class="admin-brand" href="<?= e(base_url('admin/')) ?>">
      <?php if ($logoImage !== ''): ?>
        <img class="admin-logo admin-logo-img" src="<?= e($logoImage) ?>" alt="">
      <?php else: ?>
        <?= illu_logo('admin-logo', $brandColor, $accentColor) ?>
      <?php endif; ?>
      <span>
        <strong><?= e(get_setting($pdo, 'site_short_name', $siteName)) ?></strong>
        <small>Admin panel</small>
      </span>
    </a>

    <nav class="admin-nav" aria-label="Admin sections">
      <a class="admin-nav-link <?= basename($_SERVER['SCRIPT_NAME']) === 'index.php' ? 'is-active' : '' ?>"
         href="<?= e(base_url('admin/')) ?>">
        <?= icon('sparkle') ?><span>Dashboard</span>
      </a>

      <?php foreach ($sidebarGroups as $sidebarGroupName => $sidebarGroupItems): ?>
        <p class="admin-nav-group"><?= e($sidebarGroupName) ?></p>
        <?php foreach ($sidebarGroupItems as $navKey => $navItem): ?>
          <a class="admin-nav-link <?= $sidebarCurrent === $navKey ? 'is-active' : '' ?>"
             href="<?= e(base_url('admin/manage.php?r=' . urlencode($navKey))) ?>">
            <?= icon($navItem['icon'] ?? 'sparkle') ?>
            <span><?= e($navItem['label']) ?></span>
            <?php if (!empty($sidebarBadges[$navKey])): ?>
              <em class="admin-badge"><?= (int) $sidebarBadges[$navKey] ?></em>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>

      <p class="admin-nav-group">Site</p>
      <a class="admin-nav-link <?= basename($_SERVER['SCRIPT_NAME']) === 'settings.php' ? 'is-active' : '' ?>"
         href="<?= e(base_url('admin/settings.php')) ?>">
        <?= icon('sparkle') ?><span>Settings</span>
      </a>
      <a class="admin-nav-link <?= basename($_SERVER['SCRIPT_NAME']) === 'users.php' ? 'is-active' : '' ?>"
         href="<?= e(base_url('admin/users.php')) ?>">
        <?= icon('users') ?><span>Administrators</span>
      </a>
      <a class="admin-nav-link <?= basename($_SERVER['SCRIPT_NAME']) === 'two-factor.php' ? 'is-active' : '' ?>"
         href="<?= e(base_url('admin/two-factor.php')) ?>">
        <?= icon('heart') ?><span>Two-factor</span>
      </a>
      <a class="admin-nav-link" href="<?= e(base_url()) ?>" target="_blank" rel="noopener">
        <?= icon('external') ?><span>View the site</span>
      </a>
    </nav>
  </aside>

  <div class="admin-main">

    <header class="admin-topbar">
      <button class="admin-menu-btn" type="button" aria-controls="adminSidebar"
              aria-expanded="false" aria-label="Toggle menu"><?= icon('menu') ?></button>

      <h1 class="admin-title"><?= e($pageTitle ?? 'Dashboard') ?></h1>

      <div class="admin-user">
        <span class="admin-user-name"><?= e($adminName) ?></span>
        <a class="btn btn-ghost btn-sm" href="<?= e(base_url('admin/logout.php')) ?>">Sign out</a>
      </div>
    </header>

    <div class="admin-content">

      <?php foreach ($flashes as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" role="status">
          <?= icon($flash['type'] === 'success' ? 'check' : 'sparkle') ?>
          <span><?= e($flash['message']) ?></span>
        </div>
      <?php endforeach; ?>
