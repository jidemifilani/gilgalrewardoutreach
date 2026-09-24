<?php
/**
 * Shared page head, header bar and (optionally) the inner-page banner.
 *
 * A page sets these before including this file:
 *   $pageTitle        string  browser title, without the site name
 *   $pageDescription  string  meta description
 *   $bannerTitle      string  when set, renders the dark curved page banner
 *   $bannerEyebrow    string
 *   $bannerLede       string
 *   $breadcrumbs      array   [['label' => 'Gallery', 'url' => null], ...]
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/illustrations.php';
require_once __DIR__ . '/photo-wave.php';
require_once __DIR__ . '/theme.php';

start_session_if_needed();
maintenance_guard($pdo);

$siteName  = site_name($pdo);
$shortName = get_setting($pdo, 'site_short_name', $siteName);
$tagline   = get_setting($pdo, 'tagline', '');

$pageTitle       = $pageTitle       ?? '';
$pageDescription = $pageDescription ?? get_setting($pdo, 'mission', $tagline);

$fullTitle = $pageTitle !== '' ? $pageTitle . ' | ' . $siteName : $siteName . ' | ' . $tagline;

$flashes = take_flashes();

// Branding images fall back to the drawn mark when nothing has been uploaded.
$logoImage    = theme_image($pdo, 'logo_image');
$faviconImage = theme_image($pdo, 'favicon_image');
$socialImage  = theme_image($pdo, 'social_image');

$brandColor   = theme_brand($pdo);
$accentColor  = theme_accent($pdo);
$defaultTheme = theme_default($pdo);
$themeToggle  = theme_toggle_allowed($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e(excerpt($pageDescription, 158)) ?>">

<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($fullTitle) ?>">
<meta property="og:description" content="<?= e(excerpt($pageDescription, 158)) ?>">
<meta property="og:url" content="<?= e(absolute_url($_SERVER['REQUEST_URI'] ?? '/')) ?>">
<meta property="og:image" content="<?= e($socialImage !== '' ? absolute_url($socialImage) : full_base_url('image.php?kind=education&seed=' . urlencode($siteName))) ?>">
<meta name="twitter:card" content="summary_large_image">

<?php if ($faviconImage !== ''): ?>
  <link rel="icon" href="<?= e($faviconImage) ?>">
  <link rel="apple-touch-icon" href="<?= e($faviconImage) ?>">
<?php else: ?>
  <link rel="icon" href="<?= e(asset_url('img/logo.svg')) ?>" type="image/svg+xml">
<?php endif; ?>
<link rel="canonical" href="<?= e(absolute_url(strtok($_SERVER['REQUEST_URI'] ?? '/', '?'))) ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,400&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(asset_url('css/style.css')) ?>?v=4">
<meta name="theme-color" content="<?= e($brandColor) ?>">

<?= theme_style_block($pdo) ?>

<script nonce="<?= e(csp_nonce()) ?>">
  /* Applied before first paint so a dark-mode visitor never sees a white flash.
     Everything else about the toggle lives in assets/js/main.js. */
  (function () {
    var configured = <?= json_encode($defaultTheme) ?>;
    var allowChoice = <?= $themeToggle ? 'true' : 'false' ?>;
    var chosen = null;

    try {
      if (allowChoice) {
        var saved = localStorage.getItem('theme');
        if (saved === 'dark' || saved === 'light') chosen = saved;
      }
    } catch (e) { /* private mode: fall through */ }

    // The visitor's own choice wins; otherwise the site default; otherwise
    // no attribute at all, which lets the CSS follow their device setting.
    var theme = chosen || (configured === 'system' ? null : configured);
    if (theme) document.documentElement.setAttribute('data-theme', theme);
  })();
</script>
</head>
<body>

<a class="skip-link" href="#main">Skip to content</a>

<div class="scroll-progress" aria-hidden="true"><span></span></div>

<header class="site-header">
  <div class="container header-inner">

    <a class="brand" href="<?= e(base_url()) ?>">
      <?php if ($logoImage !== ''): ?>
        <img class="brand-logo" src="<?= e($logoImage) ?>" alt="<?= e($siteName) ?>">
      <?php else: ?>
        <?= illu_logo('brand-mark', $brandColor, $accentColor) ?>
        <span class="brand-text">
          <span class="brand-name"><?= e($shortName) ?></span>
          <span class="brand-tag">Outreach</span>
        </span>
      <?php endif; ?>
    </a>

    <nav class="main-nav" id="mainNav" aria-label="Main navigation">
      <?php foreach (nav_items() as $navIndex => $item): ?>
        <?php if (empty($item['children'])): ?>

          <a href="<?= e(base_url($item['path'])) ?>"
             <?= nav_is_active($item) ? 'aria-current="page"' : '' ?>><?= e($item['label']) ?></a>

        <?php else: ?>
          <?php $panelId = 'navpanel' . $navIndex; ?>
          <div class="nav-group">
            <a class="nav-group-link" href="<?= e(base_url($item['path'])) ?>"
               <?= nav_is_active($item) ? 'aria-current="page"' : '' ?>><?= e($item['label']) ?></a>

            <button class="nav-group-toggle" type="button"
                    aria-expanded="false" aria-controls="<?= e($panelId) ?>"
                    aria-label="Show <?= e($item['label']) ?> pages">
              <?= icon('chevron') ?>
            </button>

            <div class="nav-panel" id="<?= e($panelId) ?>">
              <?php foreach ($item['children'] as $child): ?>
                <a href="<?= e(base_url($child['path'])) ?>"
                   <?= is_current($child['path']) ? 'aria-current="page"' : '' ?>><?= e($child['label']) ?></a>
              <?php endforeach; ?>
            </div>
          </div>

        <?php endif; ?>
      <?php endforeach; ?>

      <span class="nav-cta">
        <a class="btn btn-primary btn-sm" href="<?= e(base_url('volunteer.php#register')) ?>">Become a volunteer</a>
      </span>
    </nav>

    <div class="header-tools">
      <a class="icon-btn" href="<?= e(base_url('search.php')) ?>" aria-label="Search the site">
        <?= icon('search') ?>
      </a>

      <?php if ($themeToggle): ?>
        <button class="icon-btn" type="button" id="themeToggle"
                aria-label="Switch between light and dark mode" aria-pressed="false">
          <?= icon('sun', 'icon-sun') ?>
          <?= icon('moon', 'icon-moon') ?>
        </button>
      <?php endif; ?>
    </div>

    <a class="btn btn-primary btn-sm header-cta" href="<?= e(base_url('volunteer.php#register')) ?>">
      Become a volunteer
    </a>

    <button class="nav-toggle" type="button" aria-expanded="false"
            aria-controls="mainNav" aria-label="Toggle navigation menu">
      <?= icon('menu', 'icon-menu') ?>
      <?= icon('close', 'icon-close') ?>
    </button>

  </div>
</header>

<main id="main">

<?php if (!empty($bannerTitle)): ?>
  <section class="page-banner">
    <div class="container">
      <?php if (!empty($breadcrumbs)): ?>
        <ol class="breadcrumb">
          <li><a href="<?= e(base_url()) ?>">Home</a></li>
          <?php foreach ($breadcrumbs as $crumb): ?>
            <li>
              <?php if (!empty($crumb['url'])): ?>
                <a href="<?= e($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
              <?php else: ?>
                <?= e($crumb['label']) ?>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>

      <?php if (!empty($bannerEyebrow)): ?>
        <p class="eyebrow"><?= e($bannerEyebrow) ?></p>
      <?php endif; ?>

      <h1 class="h-xl"><?= e($bannerTitle) ?></h1>

      <?php if (!empty($bannerLede)): ?>
        <p class="lede"><?= e($bannerLede) ?></p>
      <?php endif; ?>
    </div>
    <?= illu_curve('bottom', 'cream') ?>
  </section>
<?php endif; ?>

<?php if ($flashes): ?>
  <div class="container u-pt-lg">
    <?php foreach ($flashes as $flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?>" role="status">
        <?= icon($flash['type'] === 'success' ? 'check' : 'sparkle') ?>
        <span><?= e($flash['message']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
