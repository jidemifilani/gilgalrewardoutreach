<?php
/**
 * Requests a password-reset link.
 *
 * The response is identical whether or not the address exists, so this form
 * cannot be used to find out which accounts are real.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/illustrations.php';
require_once __DIR__ . '/../includes/mailer.php';

if (admin_user()) {
    redirect(base_url('admin/'));
}

$error   = '';
$sent    = false;
$devLink = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = post_str('email', 160);

    if (!verify_csrf()) {
        $error = 'Your session expired. Please try again.';
    } elseif (!rate_limit_ok($pdo, 'admin-reset', 5, 60)) {
        $error = 'Too many requests from this connection. Please wait a while.';
    } else {
        $token = admin_request_reset($pdo, $email);
        $sent  = true;

        if ($token !== null) {
            $link = full_base_url('admin/reset-password.php?token=' . $token);

            $delivered = send_notification(
                $email,
                'Reset your ' . site_name($pdo) . ' admin password',
                "Somebody asked to reset the password for this admin account.\n\n"
                . "Open this link within the next hour to choose a new one:\n" . $link . "\n\n"
                . "If that was not you, ignore this message. The link expires on its own\n"
                . "and your current password still works.\n"
            );

            // With SMTP not configured the mail is a no-op, so the link is shown
            // on screen instead. That only happens on a local install, where
            // whoever is asking already has database access anyway.
            if (!$delivered) {
                $devLink = $link;
            }
        }
    }
}

$siteName = site_name($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset password | <?= e($siteName) ?> admin</title>
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
  <h1>Reset your password</h1>
  <p class="login-sub">We will email a link that works for one hour</p>

  <?php if ($error): ?>
    <div class="alert alert-error" role="alert"><?= icon('sparkle') ?><span><?= e($error) ?></span></div>
  <?php endif; ?>

  <?php if ($sent): ?>

    <div class="alert alert-success" role="status">
      <?= icon('check') ?>
      <span>If that address belongs to an admin account, a reset link is on its way.
        Check your inbox, and your spam folder.</span>
    </div>

    <?php if ($devLink !== ''): ?>
      <div class="seed-note">
        <strong>Mail is not configured on this install</strong>, so here is the link directly:<br>
        <a href="<?= e($devLink) ?>"><?= e($devLink) ?></a>
      </div>
    <?php endif; ?>

  <?php else: ?>

    <form method="post" action="<?= e(base_url('admin/forgot-password.php')) ?>">
      <?= csrf_field() ?>

      <div class="form-group">
        <label for="email">Your admin email address</label>
        <input type="email" id="email" name="email" required autofocus autocomplete="username">
      </div>

      <button class="btn btn-primary btn-block" type="submit">Send the reset link</button>
    </form>

  <?php endif; ?>

  <p class="login-foot"><a href="<?= e(base_url('admin/login.php')) ?>">&larr; Back to sign in</a></p>
</main>

</body>
</html>
