<?php
/**
 * The second step of signing in when two-factor is enabled.
 *
 * Reached only after the password has already been accepted. The session is
 * not granted admin access until a valid code is entered here.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/illustrations.php';

if (admin_user()) {
    redirect(base_url('admin/'));
}

$user = admin_pending_user($pdo);

if (!$user) {
    flash('error', 'That sign-in attempt timed out. Please enter your password again.');
    redirect(base_url('admin/login.php'));
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $code = post_str('code', 10);

    if (!verify_csrf()) {
        $error = 'Your session expired. Please sign in again.';
    } elseif (!rate_limit_ok($pdo, 'admin-2fa', 10, 15)) {
        $error = 'Too many attempts. Please wait a few minutes and try again.';
    } elseif (totp_verify($user['totp_secret'], $code)) {
        $target = $_SESSION['admin_redirect'] ?? null;
        unset($_SESSION['admin_redirect']);

        admin_establish_session($pdo, $user);

        flash('success', 'Welcome back.');
        redirect($target ?: base_url('admin/'));
    } else {
        $error = 'That code was not accepted. Check your authenticator app and try the current code.';
        audit($pdo, (int) $user['id'], 'login.2fa_failed', $user['email']);
    }
}

$siteName = site_name($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Two-factor code | <?= e($siteName) ?> admin</title>
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
  <h1>One more step</h1>
  <p class="login-sub">Enter the 6-digit code from your authenticator app</p>

  <?php if ($error): ?>
    <div class="alert alert-error" role="alert">
      <?= icon('sparkle') ?><span><?= e($error) ?></span>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= e(base_url('admin/two-factor-verify.php')) ?>">
    <?= csrf_field() ?>

    <div class="form-group">
      <label for="code">Authentication code</label>
      <input type="text" id="code" name="code" required autofocus
             inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
             autocomplete="one-time-code" placeholder="000000">
      <p class="form-hint">Signing in as <?= e($user['email']) ?>.</p>
    </div>

    <button class="btn btn-primary btn-block" type="submit">Verify and sign in</button>
  </form>

  <p class="login-foot">
    <a href="<?= e(base_url('admin/logout.php')) ?>">Cancel and start again</a>
  </p>

  <p class="seed-note">
    Lost your authenticator? Two-factor can only be turned off from inside a signed-in
    session, so another administrator will need to disable it for you, or it can be
    cleared directly in the database
    (<code>UPDATE admin_users SET totp_enabled = 0 WHERE email = ...</code>).
  </p>
</main>

</body>
</html>
