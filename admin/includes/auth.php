<?php
/**
 * Admin authentication: login, lockout and the session guard.
 *
 * Failed attempts are counted per account and the account locks for
 * LOGIN_LOCKOUT_MINUTES after LOGIN_MAX_ATTEMPTS failures, so a stolen email
 * address cannot be brute-forced from the login form.
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/totp.php';

start_session_if_needed();

function admin_user(): ?array
{
    return $_SESSION['admin'] ?? null;
}

function admin_id(): ?int
{
    $user = admin_user();
    return $user ? (int) $user['id'] : null;
}

/**
 * Call at the top of every admin page except login.
 *
 * Also enforces the idle timeout: an unattended admin session on a shared
 * machine is the likeliest way this panel gets misused, so a session with no
 * activity for the configured period is ended rather than merely hidden.
 */
function require_admin(): void
{
    global $pdo;

    if (!admin_user()) {
        $_SESSION['admin_redirect'] = $_SERVER['REQUEST_URI'] ?? null;
        redirect(base_url('admin/login.php'));
    }

    $minutes = max(5, (int) get_setting($pdo, 'session_timeout_min', '45'));
    $last    = (int) ($_SESSION['admin_seen'] ?? time());

    if (time() - $last > $minutes * 60) {
        $id = admin_id();
        unset($_SESSION['admin'], $_SESSION['admin_seen']);
        session_regenerate_id(true);

        if ($id) {
            audit($pdo, $id, 'logout.idle', $minutes . ' minutes idle');
        }

        flash('info', 'You were signed out after ' . $minutes . ' minutes of inactivity.');
        redirect(base_url('admin/login.php'));
    }

    $_SESSION['admin_seen'] = time();
}

/**
 * @return array{0: bool, 1: string} success flag and a message to show on failure
 */
function admin_login(PDO $pdo, string $email, string $password): array
{
    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Same message whichever half is wrong, so the form never reveals
    // which email addresses exist.
    $generic = 'That email and password combination was not recognised.';

    if (!$user) {
        // Burn comparable time on a miss so timing does not leak account existence.
        password_verify($password, '$2y$10$usesomesillystringforsalt0000000000000000000000000000000');
        return [false, $generic];
    }

    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        $minutes = max(1, (int) ceil((strtotime($user['locked_until']) - time()) / 60));
        return [false, "This account is locked for another {$minutes} minute(s) after too many failed attempts."];
    }

    if (!password_verify($password, $user['password_hash'])) {
        $attempts = (int) $user['failed_attempts'] + 1;

        if ($attempts >= LOGIN_MAX_ATTEMPTS) {
            $pdo->prepare(
                'UPDATE admin_users SET failed_attempts = 0,
                        locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?'
            )->execute([LOGIN_LOCKOUT_MINUTES, $user['id']]);

            audit($pdo, (int) $user['id'], 'login.locked', 'Account locked after ' . $attempts . ' failed attempts');
            return [false, 'Too many failed attempts. This account is locked for '
                . LOGIN_LOCKOUT_MINUTES . ' minutes.'];
        }

        $pdo->prepare('UPDATE admin_users SET failed_attempts = ? WHERE id = ?')
            ->execute([$attempts, $user['id']]);

        $left = LOGIN_MAX_ATTEMPTS - $attempts;
        return [false, $generic . ' ' . $left . ' attempt(s) left before the account locks.'];
    }

    if ((int) ($user['is_active'] ?? 1) !== 1) {
        return [false, 'That account has been deactivated. Ask another administrator to re-enable it.'];
    }

    // Password was right. Reset the counters either way.
    $pdo->prepare(
        'UPDATE admin_users SET failed_attempts = 0, locked_until = NULL WHERE id = ?'
    )->execute([$user['id']]);

    // With 2FA on, the password is only the first step. Nothing is written to
    // $_SESSION['admin'] until the code checks out, so a stolen password alone
    // never produces a signed-in session.
    if ((int) ($user['totp_enabled'] ?? 0) === 1) {
        session_regenerate_id(true);
        $_SESSION['admin_pending'] = [
            'id'    => (int) $user['id'],
            'since' => time(),
        ];
        audit($pdo, (int) $user['id'], 'login.2fa_required', $user['email']);
        return [false, '__2FA__'];
    }

    admin_establish_session($pdo, $user);
    return [true, ''];
}

/** Writes the signed-in session. The single place that grants admin access. */
function admin_establish_session(PDO $pdo, array $user): void
{
    $pdo->prepare(
        'UPDATE admin_users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?'
    )->execute([client_ip(), $user['id']]);

    session_regenerate_id(true);
    unset($_SESSION['admin_pending']);

    $_SESSION['admin'] = [
        'id'    => (int) $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
    ];
    $_SESSION['admin_seen'] = time();

    audit($pdo, (int) $user['id'], 'login.success', $user['email']);
}

/** The account that has passed the password step but not yet the code step. */
function admin_pending_user(PDO $pdo): ?array
{
    $pending = $_SESSION['admin_pending'] ?? null;

    // The half-finished state is deliberately short-lived.
    if (!$pending || (time() - (int) $pending['since']) > 600) {
        unset($_SESSION['admin_pending']);
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE id = ? LIMIT 1');
    $stmt->execute([$pending['id']]);

    return $stmt->fetch() ?: null;
}

function admin_logout(PDO $pdo): void
{
    if ($id = admin_id()) {
        audit($pdo, $id, 'logout', '');
    }
    unset($_SESSION['admin'], $_SESSION['admin_pending'], $_SESSION['admin_seen']);
    session_regenerate_id(true);
}

// ---------------------------------------------------------------------------
// Password reset
// ---------------------------------------------------------------------------

/**
 * Issues a reset token. Always reports success to the caller, whether or not
 * the address exists, so the form cannot be used to discover accounts.
 */
function admin_request_reset(PDO $pdo, string $email): ?string
{
    $stmt = $pdo->prepare('SELECT id FROM admin_users WHERE email = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        return null;
    }

    $token = bin2hex(random_bytes(32));

    $pdo->prepare(
        'UPDATE admin_users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 60 MINUTE)
          WHERE id = ?'
    )->execute([hash('sha256', $token), $user['id']]);

    audit($pdo, (int) $user['id'], 'password.reset_requested', $email);

    return $token;
}

/** Looks up a live reset token. Tokens are stored hashed, never in the clear. */
function admin_reset_user(PDO $pdo, string $token): ?array
{
    $token = preg_replace('/[^a-f0-9]/i', '', $token);
    if ($token === '' || strlen((string) $token) !== 64) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM admin_users
          WHERE reset_token = ? AND reset_expires IS NOT NULL AND reset_expires > NOW()
          LIMIT 1'
    );
    $stmt->execute([hash('sha256', $token)]);

    return $stmt->fetch() ?: null;
}

function admin_complete_reset(PDO $pdo, int $userId, string $password): void
{
    $pdo->prepare(
        "UPDATE admin_users
            SET password_hash = ?, reset_token = '', reset_expires = NULL,
                failed_attempts = 0, locked_until = NULL
          WHERE id = ?"
    )->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);

    audit($pdo, $userId, 'password.reset_completed', '');
}
