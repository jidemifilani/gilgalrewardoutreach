<?php
/**
 * Newsletter endpoints.
 *
 *   POST /subscribe          -- add an address (the footer form posts here)
 *   GET  /subscribe?leave=TOKEN -- one-click unsubscribe
 *
 * Both redirect back with a flash message rather than rendering a page of
 * their own, so the visitor never loses their place.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/illustrations.php';

start_session_if_needed();

$returnTo = (string) ($_POST['return_to'] ?? $_GET['return_to'] ?? '');

/** Only ever redirect back inside this site. */
$safeReturn = function (string $path): string {
    $path = trim($path);
    if ($path === '' || !str_starts_with($path, BASE_URL . '/') || str_contains($path, "\n")) {
        return base_url();
    }
    return $path;
};

// ---------------------------------------------------------------------------
// Unsubscribe
// ---------------------------------------------------------------------------
if (isset($_GET['leave'])) {
    $token = preg_replace('/[^a-f0-9]/i', '', (string) $_GET['leave']);

    if ($token !== '') {
        $stmt = $pdo->prepare('SELECT id, email FROM newsletter_subscribers WHERE token = ? LIMIT 1');
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        if ($row) {
            $pdo->prepare('UPDATE newsletter_subscribers SET is_active = 0 WHERE id = ?')
                ->execute([$row['id']]);
            flash('success', $row['email'] . ' has been removed from the list. Sorry to see you go.');
            redirect(base_url());
        }
    }

    flash('error', 'That unsubscribe link is not valid. Email us and we will remove you by hand.');
    redirect(base_url('contact.php'));
}

// ---------------------------------------------------------------------------
// Subscribe
// ---------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect(base_url());
}

if (!verify_csrf()) {
    flash('error', 'Your session expired. Please try subscribing again.');
    redirect($safeReturn($returnTo));
}

if (looks_like_spam()) {
    flash('success', 'Thank you — you are on the list.');
    redirect($safeReturn($returnTo));
}

if (!rate_limit_ok($pdo, 'newsletter', 5, 60)) {
    flash('error', 'That is several attempts from this connection in a short time. Please try again later.');
    redirect($safeReturn($returnTo));
}

[$ok, $message] = newsletter_subscribe(
    $pdo,
    post_str('email', 190),
    post_str('name', 160),
    post_str('source', 80) ?: 'footer'
);

flash($ok ? 'success' : 'error', $message);
redirect($safeReturn($returnTo));
