<?php
/**
 * Shared helpers for the whole site (public pages and admin).
 */

require_once __DIR__ . '/../config/config.php';

// ---------------------------------------------------------------------------
// Session
// ---------------------------------------------------------------------------

function start_session_if_needed(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

// ---------------------------------------------------------------------------
// URLs
//
// Every internal link on this site goes through base_url(). It strips a
// trailing ".php" so links render extension-less ("/about" not "/about.php"),
// and the .htaccess rewrite maps the clean path back to the real file. The
// original ".php" URLs keep working -- nothing is redirected away.
// ---------------------------------------------------------------------------

function base_url(string $path = ''): string
{
    $path  = ltrim($path, '/');
    $query = null;

    if (($qPos = strpos($path, '?')) !== false) {
        $query = substr($path, $qPos + 1);
        $path  = substr($path, 0, $qPos);
    }

    if (substr($path, -4) === '.php') {
        $path = substr($path, 0, -4);
    }
    // "index" (or "admin/index") collapses to the directory itself.
    $path = preg_replace('#(^|/)index$#', '$1', $path);

    $url = BASE_URL . '/' . $path;
    if ($query !== null && $query !== '') {
        $url .= '?' . $query;
    }
    return $url;
}

function absolute_url(string $rootRelativePath): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . $rootRelativePath;
}

function full_base_url(string $path = ''): string
{
    return absolute_url(base_url($path));
}

function asset_url(string $path): string
{
    return BASE_URL . '/assets/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/**
 * Where a picture actually comes from.
 *
 * If an admin has uploaded a real photo we serve that. Otherwise we fall back
 * to image.php, which draws a deterministic vector illustration from the seed,
 * so no page ever renders a broken or empty image box.
 */
function media_url(string $stored, string $seed = '', string $kind = 'scene'): string
{
    $stored = trim($stored);
    if ($stored !== '') {
        if (preg_match('#^(https?:)?//#', $stored)) {
            return $stored;
        }
        return BASE_URL . '/assets/uploads/' . ltrim($stored, '/');
    }
    return base_url('image.php?kind=' . urlencode($kind) . '&seed=' . urlencode($seed !== '' ? $seed : $kind));
}

// ---------------------------------------------------------------------------
// Output escaping
// ---------------------------------------------------------------------------

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Renders stored multi-line text as paragraphs, escaping as it goes. */
function paragraphs(?string $text): string
{
    $blocks = preg_split('/\n\s*\n/', trim((string) $text));
    $out    = '';
    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') {
            continue;
        }
        $out .= '<p>' . nl2br(e($block)) . '</p>';
    }
    return $out;
}

function excerpt(?string $text, int $limit = 160): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));
    if (mb_strlen($text) <= $limit) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $limit), " ,.;:-") . '...';
}

function slugify(string $text): string
{
    $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);
    $text = trim((string) $text, '-');
    $text = strtolower($text);
    return $text !== '' ? $text : 'item';
}

// ---------------------------------------------------------------------------
// Dates
// ---------------------------------------------------------------------------

function fmt_date(?string $date, string $format = 'j F Y'): string
{
    if (!$date || $date === '0000-00-00') {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '';
}

function fmt_time(?string $time): string
{
    if (!$time) {
        return '';
    }
    $ts = strtotime($time);
    return $ts ? date('g:ia', $ts) : '';
}

function is_upcoming(?string $date): bool
{
    return $date ? strtotime($date) >= strtotime('today') : false;
}

// ---------------------------------------------------------------------------
// Site settings (key/value, edited from Admin -> Settings)
// ---------------------------------------------------------------------------

function get_all_settings(PDO $pdo, bool $refresh = false): array
{
    static $cache = null;

    if ($cache === null || $refresh) {
        $cache = [];
        foreach ($pdo->query('SELECT setting_key, setting_value FROM site_settings') as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache;
}

function get_setting(PDO $pdo, string $key, string $default = ''): string
{
    $settings = get_all_settings($pdo);
    return isset($settings[$key]) && $settings[$key] !== '' ? $settings[$key] : $default;
}

function set_setting(PDO $pdo, string $key, string $value): void
{
    $pdo->prepare(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    )->execute([$key, $value]);

    // Refresh the request-lifetime cache. Without this, reading a setting back
    // in the same request that wrote it returns the value from before the write.
    get_all_settings($pdo, true);
}

function site_name(PDO $pdo): string
{
    return get_setting($pdo, 'site_name', SITE_NAME);
}

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------

function csrf_token(): string
{
    start_session_if_needed();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    start_session_if_needed();
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $sent);
}

// ---------------------------------------------------------------------------
// Flash messages
// ---------------------------------------------------------------------------

function flash(string $type, string $message): void
{
    start_session_if_needed();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    start_session_if_needed();
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

// ---------------------------------------------------------------------------
// Rate limiting + spam gate for public forms
// ---------------------------------------------------------------------------

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 60);
}

/** True when this IP is still under the allowance for this form. */
function rate_limit_ok(PDO $pdo, string $bucket, int $max = 5, int $minutes = 60): bool
{
    $ip = client_ip();

    $pdo->prepare('DELETE FROM rate_limit_hits WHERE created_at < (NOW() - INTERVAL 1 DAY)')->execute();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM rate_limit_hits
          WHERE bucket = ? AND ip = ? AND created_at > (NOW() - INTERVAL ? MINUTE)'
    );
    $stmt->execute([$bucket, $ip, $minutes]);

    if ((int) $stmt->fetchColumn() >= $max) {
        return false;
    }

    $pdo->prepare('INSERT INTO rate_limit_hits (bucket, ip) VALUES (?, ?)')->execute([$bucket, $ip]);
    return true;
}

/**
 * Honeypot + timing gate. Bots fill every field they see and submit instantly;
 * a real person leaves the hidden field alone and takes more than 3 seconds.
 */
function honeypot_field(): string
{
    return '<div class="hp-field" aria-hidden="true">'
        . '<label>Leave this field empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label>'
        . '<input type="hidden" name="form_started" value="' . time() . '">'
        . '</div>';
}

function looks_like_spam(): bool
{
    if (!empty($_POST['website'])) {
        return true;
    }
    $started = (int) ($_POST['form_started'] ?? 0);
    return $started > 0 && (time() - $started) < 3;
}

// ---------------------------------------------------------------------------
// Validation
// ---------------------------------------------------------------------------

function post_str(string $key, int $maxLen = 255): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    return mb_substr($value, 0, $maxLen);
}

function post_list(string $key): array
{
    $value = $_POST[$key] ?? [];
    if (!is_array($value)) {
        return [];
    }
    return array_values(array_filter(array_map(
        fn($v) => mb_substr(trim((string) $v), 0, 120),
        $value
    )));
}

function valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/** Nigerian phone numbers: 11 local digits, or +234 followed by 10. */
function valid_phone(string $phone): bool
{
    $digits = preg_replace('/\D+/', '', $phone);
    return strlen((string) $digits) >= 10 && strlen((string) $digits) <= 15;
}

// ---------------------------------------------------------------------------
// Audit log
// ---------------------------------------------------------------------------

function audit(PDO $pdo, ?int $adminId, string $action, string $detail = ''): void
{
    $pdo->prepare('INSERT INTO audit_log (admin_id, action, detail, ip) VALUES (?, ?, ?, ?)')
        ->execute([$adminId, $action, mb_substr($detail, 0, 400), client_ip()]);
}

// ---------------------------------------------------------------------------
// Navigation
// ---------------------------------------------------------------------------

/**
 * The main navigation.
 *
 * The site outgrew a flat row of links, so related pages are grouped under a
 * parent. A parent with children is still a real link in its own right -- the
 * dropdown is an extra, never the only way to reach a page.
 */
function nav_items(): array
{
    return [
        ['label' => 'Home', 'path' => 'index.php'],
        ['label' => 'About Us', 'path' => 'about.php', 'children' => [
            ['label' => 'Our story',    'path' => 'about.php'],
            ['label' => 'Stories',      'path' => 'stories.php'],
            ['label' => 'Questions',    'path' => 'faq.php'],
        ]],
        ['label' => 'Our Work', 'path' => 'outreaches.php', 'children' => [
            ['label' => 'Outreaches',   'path' => 'outreaches.php'],
            ['label' => 'Gallery',      'path' => 'gallery.php'],
            ['label' => 'Events',       'path' => 'events.php'],
        ]],
        ['label' => 'Get Involved', 'path' => 'volunteer.php', 'children' => [
            ['label' => 'Volunteer',    'path' => 'volunteer.php'],
            ['label' => 'Support us',   'path' => 'support.php'],
        ]],
        ['label' => 'Contact', 'path' => 'contact.php'],
    ];
}

/** Every page in the navigation, flattened -- used by the footer and sitemap. */
function nav_flat(): array
{
    $flat = [];
    foreach (nav_items() as $item) {
        $flat[$item['path']] = $item['label'];
        foreach ($item['children'] ?? [] as $child) {
            $flat[$child['path']] = $child['label'];
        }
    }
    return $flat;
}

/** True when this nav item, or any of its children, is the current page. */
function nav_is_active(array $item): bool
{
    if (is_current($item['path'])) {
        return true;
    }
    foreach ($item['children'] ?? [] as $child) {
        if (is_current($child['path'])) {
            return true;
        }
    }
    return false;
}

function current_script(): string
{
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
}

function is_current(string $path): bool
{
    return current_script() === basename($path);
}

// ---------------------------------------------------------------------------
// Shared queries
// ---------------------------------------------------------------------------

function active_states(PDO $pdo): array
{
    return $pdo->query(
        'SELECT * FROM states WHERE is_active = 1 ORDER BY sort_order, name'
    )->fetchAll();
}

function active_programmes(PDO $pdo): array
{
    return $pdo->query(
        'SELECT * FROM programmes WHERE is_active = 1 ORDER BY sort_order, id'
    )->fetchAll();
}

function upcoming_events(PDO $pdo, int $limit = 3): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT ' . (int) $limit
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Total volunteers on record = seeded chapter counts + real sign-ups. */
function volunteer_total(PDO $pdo): int
{
    $fromStates = (int) $pdo->query('SELECT COALESCE(SUM(volunteers), 0) FROM states')->fetchColumn();
    $signups    = (int) $pdo->query("SELECT COUNT(*) FROM volunteers WHERE status <> 'archived'")->fetchColumn();
    return $fromStates + $signups;
}

/** Builds "Osun, Oyo, Lagos, Abuja and 4 others" style running text. */
function state_sentence(array $states, int $showFirst = 6): string
{
    $names = array_column($states, 'name');
    $total = count($names);
    if ($total === 0) {
        return '';
    }
    if ($total <= $showFirst) {
        $last = array_pop($names);
        return $names ? implode(', ', $names) . ' and ' . $last : $last;
    }
    $shown     = array_slice($names, 0, $showFirst);
    $remaining = $total - $showFirst;
    return implode(', ', $shown) . ' and ' . $remaining . ' more';
}

// ---------------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------------

/**
 * Works out the slice for a paged listing.
 *
 * @return array{page:int, perPage:int, total:int, pages:int, offset:int}
 */
function paginate(int $total, int $perPage = 12, string $param = 'page'): array
{
    $perPage = max(1, $perPage);
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = max(1, min($pages, (int) ($_GET[$param] ?? 1)));

    return [
        'page'    => $page,
        'perPage' => $perPage,
        'total'   => $total,
        'pages'   => $pages,
        'offset'  => ($page - 1) * $perPage,
        'param'   => $param,
    ];
}

/** Renders the pager, preserving whatever other query parameters are set. */
function pager_html(array $p, string $basePath): string
{
    if ($p['pages'] < 2) {
        return '';
    }

    $query = $_GET;
    unset($query[$p['param']]);

    $link = function (int $page) use ($query, $basePath, $p): string {
        $query[$p['param']] = $page;
        return base_url($basePath . '?' . http_build_query($query));
    };

    $out = '<nav class="pager" aria-label="Pagination"><ul>';

    if ($p['page'] > 1) {
        $out .= '<li><a class="pager-step" href="' . e($link($p['page'] - 1)) . '" rel="prev">'
              . icon('arrow') . '<span>Previous</span></a></li>';
    }

    // First page, a window around the current page, and the last page.
    $window = [];
    for ($i = max(1, $p['page'] - 1); $i <= min($p['pages'], $p['page'] + 1); $i++) {
        $window[] = $i;
    }
    if (!in_array(1, $window, true))          { array_unshift($window, 1); }
    if (!in_array($p['pages'], $window, true)) { $window[] = $p['pages']; }

    $previous = 0;
    foreach ($window as $page) {
        if ($page - $previous > 1) {
            $out .= '<li><span class="pager-gap">&hellip;</span></li>';
        }
        $out .= $page === $p['page']
            ? '<li><span class="pager-num is-current" aria-current="page">' . $page . '</span></li>'
            : '<li><a class="pager-num" href="' . e($link($page)) . '">' . $page . '</a></li>';
        $previous = $page;
    }

    if ($p['page'] < $p['pages']) {
        $out .= '<li><a class="pager-step" href="' . e($link($p['page'] + 1)) . '" rel="next">'
              . '<span>Next</span>' . icon('arrow') . '</a></li>';
    }

    return $out . '</ul></nav>';
}

// ---------------------------------------------------------------------------
// Maintenance mode
// ---------------------------------------------------------------------------

/**
 * When Settings -> Maintenance mode is on, public pages show a holding notice
 * instead of content. The admin panel stays reachable so the site can be
 * switched back on, and a signed-in admin still sees the real site.
 */
function maintenance_guard(PDO $pdo): void
{
    if (get_setting($pdo, 'maintenance_mode', '0') !== '1') {
        return;
    }

    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if (str_contains($script, '/admin/')) {
        return;
    }

    start_session_if_needed();
    if (!empty($_SESSION['admin'])) {
        return;                                   // admins preview the live site
    }

    http_response_code(503);
    header('Retry-After: 3600');

    $siteName = site_name($pdo);
    $message  = get_setting($pdo, 'maintenance_message', 'We will be back shortly.');
    $phone    = get_setting($pdo, 'contact_phone', '');
    $email    = get_setting($pdo, 'contact_email', '');

    require __DIR__ . '/maintenance.php';
    exit;
}

// ---------------------------------------------------------------------------
// Newsletter
// ---------------------------------------------------------------------------

/**
 * @return array{0:bool,1:string} success flag and the message to show
 */
function newsletter_subscribe(PDO $pdo, string $email, string $name = '', string $source = 'footer'): array
{
    $email = mb_strtolower(trim($email));

    if (!valid_email($email)) {
        return [false, 'Please enter a valid email address.'];
    }

    $stmt = $pdo->prepare('SELECT id, is_active FROM newsletter_subscribers WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ((int) $existing['is_active'] === 1) {
            return [true, 'You are already on the list — thank you.'];
        }
        // Previously unsubscribed: turn them back on rather than refusing.
        $pdo->prepare('UPDATE newsletter_subscribers SET is_active = 1 WHERE id = ?')
            ->execute([$existing['id']]);
        return [true, 'Welcome back — you are subscribed again.'];
    }

    $pdo->prepare(
        'INSERT INTO newsletter_subscribers (email, name, source, token) VALUES (?, ?, ?, ?)'
    )->execute([$email, mb_substr($name, 0, 160), $source, bin2hex(random_bytes(16))]);

    return [true, 'Thank you — you are on the list. One short email a month, and one click to leave.'];
}

// ---------------------------------------------------------------------------
// Shared content queries added in v2
// ---------------------------------------------------------------------------

function active_faqs(PDO $pdo): array
{
    return $pdo->query(
        'SELECT * FROM faqs WHERE is_active = 1 ORDER BY category, sort_order, id'
    )->fetchAll();
}

function approved_stories(PDO $pdo, int $limit = 0): array
{
    $sql = 'SELECT * FROM stories WHERE is_approved = 1 ORDER BY sort_order, id';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }
    return $pdo->query($sql)->fetchAll();
}

function milestones(PDO $pdo): array
{
    return $pdo->query(
        'SELECT * FROM milestones WHERE is_active = 1 ORDER BY year, sort_order, id'
    )->fetchAll();
}
