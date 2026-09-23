<?php
/**
 * Administrator accounts.
 *
 * Kept out of the generic CRUD engine on purpose: passwords, two-factor and
 * "do not lock yourself out" rules need handling that a declarative field list
 * cannot express safely.
 */

require_once __DIR__ . '/includes/auth.php';
require_admin();
require_once __DIR__ . '/includes/crud.php';

$pageTitle = 'Administrators';
$errors    = [];
$old       = [];

/** How many accounts can still sign in. Used to refuse the last one being removed. */
$activeCount = static fn(PDO $pdo): int =>
    (int) $pdo->query('SELECT COUNT(*) FROM admin_users WHERE is_active = 1')->fetchColumn();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    if (!verify_csrf()) {
        $errors['form'] = 'Your session expired. Nothing was changed.';
    } else {
        $action = post_str('action', 20);
        $id     = (int) ($_POST['id'] ?? 0);

        // --- Add an administrator -----------------------------------------
        if ($action === 'create') {
            $old = [
                'name'  => post_str('name', 120),
                'email' => mb_strtolower(post_str('email', 160)),
            ];
            $password = (string) ($_POST['password'] ?? '');

            if ($old['name'] === '')             $errors['name']     = 'Please give them a name.';
            if (!valid_email($old['email']))     $errors['email']    = 'Please enter a valid email address.';
            if (strlen($password) < 10)          $errors['password'] = 'Use at least 10 characters.';

            if (!$errors) {
                $exists = $pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE email = ?');
                $exists->execute([$old['email']]);

                if ((int) $exists->fetchColumn() > 0) {
                    $errors['email'] = 'There is already an account with that address.';
                } else {
                    $pdo->prepare(
                        'INSERT INTO admin_users (name, email, password_hash) VALUES (?, ?, ?)'
                    )->execute([$old['name'], $old['email'], password_hash($password, PASSWORD_DEFAULT)]);

                    audit($pdo, admin_id(), 'admin.create', $old['email']);
                    flash('success', $old['name'] . ' can now sign in.');
                    redirect(base_url('admin/users.php'));
                }
            }
        }

        // --- Deactivate / reactivate --------------------------------------
        if ($action === 'toggle' && $id > 0) {
            if ($id === admin_id()) {
                flash('error', 'You cannot deactivate the account you are signed in with.');
            } else {
                $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE id = ?');
                $stmt->execute([$id]);
                $target = $stmt->fetch();

                if (!$target) {
                    flash('error', 'That account no longer exists.');
                } elseif ((int) $target['is_active'] === 1 && $activeCount($pdo) <= 1) {
                    flash('error', 'That is the only account that can sign in. Add another first.');
                } else {
                    $next = (int) $target['is_active'] === 1 ? 0 : 1;
                    $pdo->prepare('UPDATE admin_users SET is_active = ? WHERE id = ?')->execute([$next, $id]);

                    audit($pdo, admin_id(), $next ? 'admin.activate' : 'admin.deactivate', $target['email']);
                    flash('success', $target['name'] . ($next ? ' can sign in again.' : ' can no longer sign in.'));
                }
            }
            redirect(base_url('admin/users.php'));
        }

        // --- Clear someone's two-factor -----------------------------------
        if ($action === 'clear2fa' && $id > 0) {
            $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE id = ?');
            $stmt->execute([$id]);
            $target = $stmt->fetch();

            if ($target) {
                $pdo->prepare("UPDATE admin_users SET totp_secret = '', totp_enabled = 0 WHERE id = ?")
                    ->execute([$id]);
                audit($pdo, admin_id(), '2fa.cleared_by_admin', $target['email']);
                flash('success', 'Two-factor cleared for ' . $target['name'] . '. They should set it up again.');
            }
            redirect(base_url('admin/users.php'));
        }

        // --- Delete --------------------------------------------------------
        if ($action === 'delete' && $id > 0) {
            if ($id === admin_id()) {
                flash('error', 'You cannot delete the account you are signed in with.');
            } elseif ($activeCount($pdo) <= 1) {
                flash('error', 'That is the only account that can sign in. Add another first.');
            } else {
                $stmt = $pdo->prepare('SELECT email FROM admin_users WHERE id = ?');
                $stmt->execute([$id]);
                $email = (string) $stmt->fetchColumn();

                $pdo->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$id]);
                audit($pdo, admin_id(), 'admin.delete', $email);
                flash('success', 'That administrator has been removed.');
            }
            redirect(base_url('admin/users.php'));
        }
    }
}

$admins = $pdo->query('SELECT * FROM admin_users ORDER BY is_active DESC, name')->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<?php if (!empty($errors['form'])): ?>
  <div class="alert alert-error" role="alert"><?= icon('sparkle') ?><span><?= e($errors['form']) ?></span></div>
<?php endif; ?>

<div class="panel">
  <div class="panel-head">
    <h2>Administrators <span class="form-hint">(<?= count($admins) ?>)</span></h2>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Name</th><th>Email</th><th>Two-factor</th><th>Active</th>
          <th>Last signed in</th><th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($admins as $admin): ?>
          <tr>
            <td>
              <strong><?= e($admin['name']) ?></strong>
              <?php if ((int) $admin['id'] === admin_id()): ?>
                <span class="pill pill-active">You</span>
              <?php endif; ?>
            </td>
            <td><?= e($admin['email']) ?></td>
            <td>
              <?= (int) $admin['totp_enabled'] === 1
                    ? '<span class="pill pill-yes">On</span>'
                    : '<span class="pill pill-no">Off</span>' ?>
            </td>
            <td>
              <?= (int) $admin['is_active'] === 1
                    ? '<span class="pill pill-yes">Yes</span>'
                    : '<span class="pill pill-no">No</span>' ?>
            </td>
            <td>
              <?php if ($admin['last_login_at']): ?>
                <?= e(fmt_date($admin['last_login_at'], 'j M Y')) ?>
                <br><span class="form-hint"><?= e($admin['last_login_ip'] ?: '') ?></span>
              <?php else: ?>
                <span class="form-hint">Never</span>
              <?php endif; ?>
            </td>
            <td class="actions">
              <?php if ((int) $admin['totp_enabled'] === 1): ?>
                <form method="post" action="<?= e(base_url('admin/users.php')) ?>"
                      data-confirm="Clear two-factor for <?= e($admin['name']) ?>? They will be able to sign in with just their password until they set it up again.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="clear2fa">
                  <input type="hidden" name="id" value="<?= (int) $admin['id'] ?>">
                  <button class="btn btn-ghost btn-sm" type="submit">Clear 2FA</button>
                </form>
              <?php endif; ?>

              <?php if ((int) $admin['id'] !== admin_id()): ?>
                <form method="post" action="<?= e(base_url('admin/users.php')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $admin['id'] ?>">
                  <button class="btn btn-ghost btn-sm" type="submit">
                    <?= (int) $admin['is_active'] === 1 ? 'Deactivate' : 'Reactivate' ?>
                  </button>
                </form>

                <form method="post" action="<?= e(base_url('admin/users.php')) ?>"
                      data-confirm="Delete <?= e($admin['name']) ?> permanently? This cannot be undone.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $admin['id'] ?>">
                  <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h2>Add an administrator</h2></div>

  <form method="post" action="<?= e(base_url('admin/users.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">

    <div class="form-grid">
      <div class="form-group <?= isset($errors['name']) ? 'has-error' : '' ?>">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" required value="<?= e($old['name'] ?? '') ?>">
        <?php if (isset($errors['name'])): ?><span class="field-error"><?= e($errors['name']) ?></span><?php endif; ?>
      </div>

      <div class="form-group <?= isset($errors['email']) ? 'has-error' : '' ?>">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" required value="<?= e($old['email'] ?? '') ?>">
        <?php if (isset($errors['email'])): ?><span class="field-error"><?= e($errors['email']) ?></span><?php endif; ?>
      </div>

      <div class="form-group <?= isset($errors['password']) ? 'has-error' : '' ?>">
        <label for="password">Temporary password</label>
        <input type="password" id="password" name="password" required minlength="10"
               autocomplete="new-password">
        <p class="form-hint">At least 10 characters. Ask them to change it once they are in.</p>
        <?php if (isset($errors['password'])): ?><span class="field-error"><?= e($errors['password']) ?></span><?php endif; ?>
      </div>
    </div>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Add administrator</button>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
