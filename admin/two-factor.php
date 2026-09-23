<?php
/**
 * Turning two-factor authentication on and off for your own account.
 *
 * There is no QR code on purpose -- see the note at the foot of
 * includes/totp.php. Every authenticator app accepts a setup key typed by
 * hand, which is reliable and verifiable; a hand-rolled QR that silently
 * fails to scan is not.
 */

require_once __DIR__ . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/includes/crud.php';

$pageTitle = 'Two-factor authentication';

$stmt = $pdo->prepare('SELECT * FROM admin_users WHERE id = ? LIMIT 1');
$stmt->execute([admin_id()]);
$me = $stmt->fetch();

$enabled = (int) ($me['totp_enabled'] ?? 0) === 1;
$error   = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    if (!verify_csrf()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $action = post_str('action', 20);

        // --- Turn it on ---------------------------------------------------
        if ($action === 'enable') {
            $secret = (string) ($_SESSION['totp_setup'] ?? '');
            $code   = post_str('code', 10);

            if ($secret === '') {
                $error = 'That setup session expired. Start again below.';
            } elseif (!totp_verify($secret, $code)) {
                $error = 'That code was not accepted. Make sure you typed the key correctly, '
                       . 'then enter the code showing right now.';
            } else {
                $pdo->prepare('UPDATE admin_users SET totp_secret = ?, totp_enabled = 1 WHERE id = ?')
                    ->execute([$secret, admin_id()]);

                unset($_SESSION['totp_setup']);
                audit($pdo, admin_id(), '2fa.enabled', $me['email']);

                flash('success', 'Two-factor authentication is on. You will need a code the next time you sign in.');
                redirect(base_url('admin/two-factor.php'));
            }
        }

        // --- Turn it off --------------------------------------------------
        if ($action === 'disable') {
            $password = (string) ($_POST['password'] ?? '');

            if (!password_verify($password, $me['password_hash'])) {
                $error = 'That is not your current password.';
            } else {
                $pdo->prepare("UPDATE admin_users SET totp_secret = '', totp_enabled = 0 WHERE id = ?")
                    ->execute([admin_id()]);

                audit($pdo, admin_id(), '2fa.disabled', $me['email']);
                flash('success', 'Two-factor authentication is off.');
                redirect(base_url('admin/two-factor.php'));
            }
        }
    }
}

// A fresh secret is generated per setup attempt and held in the session until
// a working code proves the app has it. It is only written to the database
// once that has happened.
if (!$enabled && empty($_SESSION['totp_setup'])) {
    $_SESSION['totp_setup'] = totp_secret();
}

$setupSecret = (string) ($_SESSION['totp_setup'] ?? '');
$siteName    = site_name($pdo);

require __DIR__ . '/includes/header.php';
?>

<?php if ($error): ?>
  <div class="alert alert-error" role="alert"><?= icon('sparkle') ?><span><?= e($error) ?></span></div>
<?php endif; ?>

<?php if ($enabled): ?>

  <div class="panel">
    <div class="panel-head"><h2>Two-factor is on</h2></div>

    <div class="alert alert-success">
      <?= icon('check') ?>
      <span>Signing in to this account needs your password <strong>and</strong> a code from
        your authenticator app.</span>
    </div>

    <p class="form-hint">
      If you change phones, turn two-factor off here first and set it up again on the new
      device. Otherwise you will be locked out, and only another administrator or direct
      database access can clear it.
    </p>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Turn it off</h2></div>

    <form method="post" action="<?= e(base_url('admin/two-factor.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="disable">

      <div class="form-grid">
        <div class="form-group">
          <label for="password">Confirm your password</label>
          <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
      </div>

      <div class="form-actions">
        <button class="btn btn-danger" type="submit">Turn two-factor off</button>
      </div>
    </form>
  </div>

<?php else: ?>

  <div class="panel">
    <div class="panel-head"><h2>Set up two-factor authentication</h2></div>

    <p>Two-factor means a stolen password is not enough on its own. You will need a code from
      an authenticator app each time you sign in.</p>

    <ol>
      <li>Install an authenticator app if you do not have one — Google Authenticator, Microsoft
        Authenticator, Authy and 1Password all work.</li>
      <li>In the app choose <strong>Add account &rarr; Enter a setup key</strong> (sometimes called
        &ldquo;Manual entry&rdquo;).</li>
      <li>Give it the account name and key below.</li>
      <li>Type the 6-digit code it shows into the box and save.</li>
    </ol>

    <div class="form-grid u-mt">
      <div class="form-group">
        <label for="acct">Account name</label>
        <input type="text" id="acct" value="<?= e($siteName . ': ' . $me['email']) ?>" readonly>
      </div>

      <div class="form-group">
        <label for="key">Setup key</label>
        <input type="text" id="key" value="<?= e($setupSecret) ?>" readonly>
        <p class="form-hint">Type it exactly. Spaces and capitalisation do not matter.</p>
      </div>

      <div class="form-group span-2">
        <label for="uri">Or use this link on a device with an authenticator installed</label>
        <input type="text" id="uri" value="<?= e(totp_uri($setupSecret, $me['email'], $siteName)) ?>" readonly>
      </div>
    </div>

    <form method="post" action="<?= e(base_url('admin/two-factor.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="enable">

      <div class="form-grid">
        <div class="form-group">
          <label for="code">Code from your app</label>
          <input type="text" id="code" name="code" required
                 inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                 autocomplete="one-time-code" placeholder="000000">
        </div>
      </div>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Turn two-factor on</button>
        <a class="btn btn-ghost" href="<?= e(base_url('admin/settings.php')) ?>">Cancel</a>
      </div>
    </form>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
