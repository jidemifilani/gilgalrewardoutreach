<?php
/** Choosing a new password from a reset link. */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/illustrations.php';

if (admin_user()) {
    redirect(base_url('admin/'));
}

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$user  = admin_reset_user($pdo, $token);
$error = '';

if ($user && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $new     = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (!verify_csrf()) {
        $error = 'Your session expired. Open the link again.';
    } elseif (strlen($new) < 10) {
        $error = 'Use at least 10 characters.';
    } elseif ($new !== $confirm) {
        $error = 'The two passwords do not match.';
    } else {
        admin_complete_reset($pdo, (int) $user['id'], $new);
        flash('success', 'Your password has been changed. Sign in with it now.');
        redirect(base_url('admin/login.php'));
    }
}

$siteName = site_name($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Choose a new password | <?= e($siteName) ?> admin</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= e(asset_url('img/logo.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(asset_url('css/admin.css')) ?>?v=2">
</head>
<body class="login-page">

<main class="login-card">
  <?= illu_logo('admin-logo') ?>
  <h1>Choose a new password</h1>

  <?php if (!$user): ?>

    <div class="alert alert-error" role="alert">
      <?= icon('sparkle') ?>
      <span>That link is not valid, or it has already been used, or the hour has run out.
        Request a fresh one.</span>
    </div>

    <p class="login-foot">
      <a href="<?= e(base_url('admin/forgot-password.php')) ?>">Request a new link</a>
    </p>

  <?php else: ?>

    <p class="login-sub">For <?= e($user['email']) ?></p>

    <?php if ($error): ?>
      <div class="alert alert-error" role="alert"><?= icon('sparkle') ?><span><?= e($error) ?></span></div>
    <?php endif; ?>

    <form method="post" action="<?= e(base_url('admin/reset-password.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">

      <div class="form-group">
        <label for="new_password">New password</label>
        <input type="password" id="new_password" name="new_password" required
               autofocus minlength="10" autocomplete="new-password">
        <p class="form-hint">At least 10 characters.</p>
      </div>

      <div class="form-group">
        <label for="confirm_password">Confirm it</label>
        <input type="password" id="confirm_password" name="confirm_password" required
               autocomplete="new-password">
      </div>

      <button class="btn btn-primary btn-block" type="submit">Save the new password</button>
    </form>

  <?php endif; ?>

  <p class="login-foot"><a href="<?= e(base_url('admin/login.php')) ?>">&larr; Back to sign in</a></p>
</main>

</body>
</html>
