<?php
/** Admin sign-in. */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/illustrations.php';

if (admin_user()) {
    redirect(base_url('admin/'));
}

$error = '';
$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email    = post_str('email', 160);
    $password = (string) ($_POST['password'] ?? '');

    if (!verify_csrf()) {
        $error = 'Your session expired. Please sign in again.';
    } elseif (!rate_limit_ok($pdo, 'admin-login', 12, 15)) {
        $error = 'Too many sign-in attempts from this connection. Please wait a few minutes.';
    } else {
        [$ok, $message] = admin_login($pdo, $email, $password);

        if ($ok) {
            $target = $_SESSION['admin_redirect'] ?? null;
            unset($_SESSION['admin_redirect']);
            flash('success', 'Welcome back.');
            redirect($target ?: base_url('admin/'));
        }

        // The password was right but the account has two-factor turned on.
        if ($message === '__2FA__') {
            redirect(base_url('admin/two-factor-verify.php'));
        }

        $error = $message;
    }
}

$siteName = site_name($pdo);

// Only offer the seeded credentials while that account has never signed in.
$showSeedHint = (int) $pdo->query(
    "SELECT COUNT(*) FROM admin_users WHERE email = 'admin@gilgalrewardoutreach.org' AND last_login_at IS NULL"
)->fetchColumn() === 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in | <?= e($siteName) ?> admin</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= e(asset_url('img/logo.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(asset_url('css/admin.css')) ?>?v=1">
</head>
<body class="login-page">

<main class="login-card">
  <?= illu_logo('admin-logo') ?>
  <h1><?= e($siteName) ?></h1>
  <p class="login-sub">Sign in to manage the site</p>

  <?php foreach (take_flashes() as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>" role="status">
      <?= icon($flash['type'] === 'success' ? 'check' : 'sparkle') ?>
      <span><?= e($flash['message']) ?></span>
    </div>
  <?php endforeach; ?>

  <?php if ($error): ?>
    <div class="alert alert-error" role="alert">
      <?= icon('sparkle') ?><span><?= e($error) ?></span>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= e(base_url('admin/login.php')) ?>">
    <?= csrf_field() ?>

    <div class="form-group">
      <label for="email">Email address</label>
      <input type="email" id="email" name="email" required autocomplete="username"
             autofocus value="<?= e($email) ?>">
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>

    <button class="btn btn-primary btn-block" type="submit">Sign in</button>
  </form>

  <?php if ($showSeedHint): ?>
    <p class="seed-note">
      First sign-in: <code>admin@gilgalrewardoutreach.org</code> / <code>GilgalReward@2026</code>.
      Change this password from Settings straight after. This hint disappears once the account has been used.
    </p>
  <?php endif; ?>

  <p class="login-foot">
    <a href="<?= e(base_url('admin/forgot-password.php')) ?>">Forgotten your password?</a>
  </p>
  <p class="login-foot"><a href="<?= e(base_url()) ?>">&larr; Back to the website</a></p>
</main>

</body>
</html>
