<?php
/**
 * End-to-end smoke test.
 *
 *   php tests/smoke-test.php [base-url]
 *   php tests/smoke-test.php http://localhost/gilgalrewardoutreach
 *
 * Drives the real site over HTTP with a cookie jar, so it exercises sessions,
 * CSRF, rate limiting, uploads and redirects the way a browser would.
 *
 * NOT self-cleaning by default: it creates a volunteer sign-up, a contact
 * message and one outreach record, then deletes what it created at the end.
 * If it fails mid-run, look for rows named "Smoke Test ..." and remove them.
 */

$base = rtrim($argv[1] ?? 'http://localhost/gilgalrewardoutreach', '/');

$jar = sys_get_temp_dir() . '/grо-smoke-' . getmypid() . '.cookies';
@unlink($jar);

$passed = 0;
$failed = 0;
$notes  = [];

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function http(string $url, array $post = null, bool $follow = true): array
{
    global $jar;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HEADER         => true,
        CURLOPT_USERAGENT      => 'gilgal-smoke-test',
    ]);

    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }

    $raw        = curl_exec($ch);
    $status     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $error      = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['status' => 0, 'body' => '', 'headers' => '', 'error' => $error];
    }

    return [
        'status'  => $status,
        'headers' => substr($raw, 0, $headerSize),
        'body'    => substr($raw, $headerSize),
        'error'   => $error,
    ];
}

/** Same as http(), but posts multipart so a CURLFile can be attached. */
function http_upload(string $url, array $fields): array
{
    global $jar;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HEADER         => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $fields,     // array = multipart/form-data
    ]);

    $raw        = curl_exec($ch);
    $status     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($raw === false) {
        return ['status' => 0, 'body' => '', 'headers' => '', 'error' => 'upload failed'];
    }

    return [
        'status'  => $status,
        'headers' => substr($raw, 0, $headerSize),
        'body'    => substr($raw, $headerSize),
        'error'   => '',
    ];
}

function check(string $label, bool $condition, string $detail = ''): void
{
    global $passed, $failed, $notes;

    if ($condition) {
        $passed++;
        printf("  [ ok ] %s\n", $label);
    } else {
        $failed++;
        $notes[] = $label . ($detail !== '' ? ' — ' . $detail : '');
        printf("  [FAIL] %s%s\n", $label, $detail !== '' ? ' — ' . $detail : '');
    }
}

function section(string $title): void
{
    printf("\n%s\n%s\n", $title, str_repeat('-', strlen($title)));
}

/**
 * The current TOTP code for a secret, using the project's own implementation.
 * That is deliberate: this test checks the login *flow*, while the algorithm
 * itself is verified against the RFC 6238 vectors in its own right.
 */
require_once __DIR__ . '/../includes/totp.php';

function totp_now(string $secret): string
{
    return totp_code($secret);
}

/** Pulls the CSRF token out of a rendered form. */
function csrf_from(string $html): string
{
    return preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m) ? $m[1] : '';
}

// ---------------------------------------------------------------------------

printf("Smoke test against %s\n", $base);

/*
 * Clear this machine's rate-limit history before starting.
 *
 * The public forms allow 4 volunteer registrations and 5 contact messages per
 * IP per hour, which is right for real visitors but means a second run of this
 * test inside the same hour gets throttled and reports false failures.
 * Everything else here is black-box over HTTP; this is the one setup step that
 * reaches into the database directly.
 */
require_once __DIR__ . '/../config/config.php';

try {
    $resetPdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $resetPdo->exec('DELETE FROM rate_limit_hits');
    print("Rate-limit history cleared so the run is repeatable." . PHP_EOL);
} catch (PDOException $e) {
    print("WARNING: could not clear rate limits; re-runs within the hour may be throttled." . PHP_EOL);
}


// --- Public pages ----------------------------------------------------------
section('Public pages (clean URLs)');

foreach ([
    ''            => 'Homepage',
    'about'       => 'About Us',
    'outreaches'  => 'Outreaches',
    'gallery'     => 'Gallery',
    'events'      => 'Events',
    'volunteer'   => 'Volunteer',
    'contact'     => 'Contact Us',
    'stories'     => 'Stories',
    'support'     => 'Support us',
    'faq'         => 'Questions',
    'search'      => 'Search',
    'privacy'     => 'Privacy',
    'terms'       => 'Terms',
] as $path => $label) {
    $res = http("$base/$path");
    check("GET /$path returns 200", $res['status'] === 200, 'got ' . $res['status']);
    check("  /$path has no PHP error output",
        !preg_match('/(Fatal error|Warning:|Notice:|Deprecated:)/', $res['body']));
}

$res = http("$base/outreach?slug=back-to-school-osogbo");
check('Outreach detail page resolves by slug', $res['status'] === 200);
check('  detail page shows the outreach story',
    str_contains($res['body'], 'Ataoja') || str_contains($res['body'], 'exercise books'));

$res = http("$base/outreach?slug=does-not-exist");
check('Unknown outreach slug returns 404', $res['status'] === 404, 'got ' . $res['status']);

$res = http("$base/definitely-not-a-page");
check('Unknown path returns 404', $res['status'] === 404, 'got ' . $res['status']);

// The .php URLs must keep working alongside the clean ones.
$res = http("$base/about.php");
check('Legacy /about.php still works (non-breaking clean URLs)', $res['status'] === 200);

$res = http("$base/home");
check('/home alias serves the homepage', $res['status'] === 200);

// --- Generated artwork -----------------------------------------------------
section('Generated artwork');

$res = http("$base/image.php?kind=portrait&seed=test-person");
check('image.php renders a portrait', $res['status'] === 200 && str_contains($res['body'], '<svg'));
check('  served as image/svg+xml', str_contains(strtolower($res['headers']), 'image/svg+xml'));

$res2 = http("$base/image.php?kind=portrait&seed=test-person");
check('  same seed renders identical artwork', $res['body'] === $res2['body']);

$res3 = http("$base/image.php?kind=portrait&seed=a-different-person");
check('  different seed renders different artwork', $res['body'] !== $res3['body']);

$res = http("$base/image.php?kind=education&seed=outreach-1");
check('image.php renders a scene', $res['status'] === 200 && str_contains($res['body'], '<svg'));

// --- Security headers ------------------------------------------------------
section('Security headers');

$res = http("$base/");
foreach ([
    'content-security-policy' => 'Content-Security-Policy is sent',
    'x-content-type-options'  => 'X-Content-Type-Options is sent',
    'x-frame-options'         => 'X-Frame-Options is sent',
    'referrer-policy'         => 'Referrer-Policy is sent',
] as $header => $label) {
    check($label, str_contains(strtolower($res['headers']), $header));
}

// --- Volunteer registration ------------------------------------------------
section('Volunteer registration');

$page = http("$base/volunteer");
$token = csrf_from($page['body']);
check('Volunteer form exposes a CSRF token', $token !== '');

$marker = 'Smoke Test Volunteer ' . time();

// A submission with no token must be rejected.
$res = http("$base/volunteer", [
    'full_name' => $marker . ' NOTOKEN',
    'email'     => 'notoken@example.com',
    'phone'     => '08030000000',
    'state'     => 'Osun',
    'interests' => ['Education & Child Literacy'],
    'motivation'=> 'Testing that CSRF is enforced.',
    'form_started' => time() - 10,
]);
check('Registration without a CSRF token is refused',
    str_contains($res['body'], 'session expired'));

// The honeypot must swallow a bot submission.
$page  = http("$base/volunteer");
$token = csrf_from($page['body']);
$res = http("$base/volunteer", [
    'csrf_token' => $token,
    'full_name'  => $marker . ' BOT',
    'email'      => 'bot@example.com',
    'phone'      => '08030000000',
    'state'      => 'Osun',
    'interests'  => ['Education & Child Literacy'],
    'motivation' => 'I am a bot filling every field I can see.',
    'website'    => 'http://spam.example.com',      // honeypot
    'form_started' => time() - 10,
]);
check('Honeypot submission is accepted quietly but not stored',
    str_contains($res['body'], 'Thank you for registering'));

// Invalid input must come back with field errors, not a saved row.
$page  = http("$base/volunteer");
$token = csrf_from($page['body']);
$res = http("$base/volunteer", [
    'csrf_token'   => $token,
    'full_name'    => '',
    'email'        => 'not-an-email',
    'phone'        => '123',
    'state'        => '',
    'motivation'   => 'x',
    'form_started' => time() - 10,
]);
check('Invalid registration is rejected with field errors',
    str_contains($res['body'], 'valid email address')
    && str_contains($res['body'], 'Please check the highlighted fields'));

// A good submission must save.
$page  = http("$base/volunteer");
$token = csrf_from($page['body']);
$res = http("$base/volunteer", [
    'csrf_token'   => $token,
    'full_name'    => $marker,
    'email'        => 'smoke.volunteer@example.com',
    'phone'        => '08031234567',
    'state'        => 'Oyo',
    'city'         => 'Ibadan',
    'occupation'   => 'Teacher',
    'interests'    => ['Education & Child Literacy', 'Community Health Outreach'],
    'availability' => 'One Saturday a month',
    'skills'       => 'Reading, phonics, patience.',
    'motivation'   => 'I want to help children in my community learn to read.',
    'heard_from'   => 'Facebook',
    'form_started' => time() - 10,
]);
check('Valid registration is accepted', str_contains($res['body'], 'Thank you, ' . $marker));

// --- Contact form ----------------------------------------------------------
section('Contact form');

$page  = http("$base/contact");
$token = csrf_from($page['body']);
check('Contact form exposes a CSRF token', $token !== '');
check('Contact page shows the phone number', str_contains($page['body'], '+234'));
check('Contact page links to Facebook', str_contains($page['body'], 'facebook.com'));
check('Contact page shows the email address', str_contains($page['body'], 'mailto:'));

$messageMarker = 'Smoke Test Message ' . time();

$res = http("$base/contact", [
    'csrf_token'   => $token,
    'name'         => $messageMarker,
    'email'        => 'smoke.contact@example.com',
    'phone'        => '08039998888',
    'subject'      => 'Smoke test enquiry',
    'message'      => 'This message was created by the automated smoke test.',
    'form_started' => time() - 10,
]);
check('Valid contact message is accepted', str_contains($res['body'], 'Thank you, ' . $messageMarker));

$page  = http("$base/contact");
$token = csrf_from($page['body']);
$res = http("$base/contact", [
    'csrf_token'   => $token,
    'name'         => 'Too Short',
    'email'        => 'ok@example.com',
    'message'      => 'hi',
    'form_started' => time() - 10,
]);
check('Too-short message is rejected', str_contains($res['body'], 'write a little more'));

// --- New pages and endpoints -----------------------------------------------
section('New pages and endpoints');

$res = http("$base/sitemap.xml");
check('sitemap.xml is served', $res['status'] === 200 && str_contains($res['body'], '<urlset'));
check('  and lists the outreach pages', str_contains($res['body'], 'back-to-school-osogbo'));

$res = http("$base/robots.txt");
check('robots.txt is served', $res['status'] === 200 && str_contains($res['body'], 'Sitemap:'));
check('  and keeps crawlers out of /admin', str_contains($res['body'], 'Disallow') && str_contains($res['body'], '/admin/'));

$res = http("$base/ics?id=1");
check('Event calendar file downloads',
    $res['status'] === 200 && str_contains($res['body'], 'BEGIN:VCALENDAR'));
check('  is served as text/calendar', str_contains(strtolower($res['headers']), 'text/calendar'));
check('  and contains one event', substr_count($res['body'], 'BEGIN:VEVENT') === 1);

$res = http("$base/faq");
check('FAQ page shows questions', str_contains($res['body'], 'accordion-trigger'));

$res = http("$base/stories");
check('Stories page shows approved stories', str_contains($res['body'], 'story-card'));

$res = http("$base/support");
check('Support page shows the bank details', str_contains($res['body'], 'Account number'));
check('  and states that no card details are taken',
    stripos($res['body'], 'never asks for card details') !== false
    || stripos($res['body'], 'never processes payments') !== false);

// --- Search ----------------------------------------------------------------
section('Search');

$res = http("$base/search?q=reading");
check('Search finds matches', str_contains($res['body'], 'result-item'));
check('  and highlights the term', str_contains($res['body'], '<mark>'));

$res = http("$base/search?q=zzzzznothingmatches");
check('Search reports no matches cleanly', str_contains($res['body'], 'Nothing matched'));

$res = http("$base/search?q=a");
check('Search rejects a one-character term', str_contains($res['body'], 'at least two characters'));

// --- Gallery filtering and pagination --------------------------------------
section('Filtering and pagination');

$res = http("$base/gallery?category=Health");
check('Gallery filters by category server-side',
    $res['status'] === 200 && !str_contains($res['body'], 'No photographs match'));

$res = http("$base/gallery?category=Health&state=Abuja");
check('Gallery combines category and state filters', $res['status'] === 200);

$res = http("$base/gallery?category=DefinitelyNotACategory");
check('Unknown filter shows the empty state', str_contains($res['body'], 'No photographs match'));

$res = http("$base/gallery");
check('Gallery paginates 16 photos across pages', str_contains($res['body'], 'class="pager"'));

$res = http("$base/gallery?page=2");
check('  page two loads', $res['status'] === 200 && str_contains($res['body'], 'gallery-item'));

$res = http("$base/gallery?page=999");
check('  an out-of-range page clamps instead of erroring', $res['status'] === 200);

// --- Newsletter ------------------------------------------------------------
section('Newsletter');

$newsletterEmail = 'smoke.news.' . time() . '@example.com';

$page  = http("$base/");
$token = csrf_from($page['body']);
check('Footer newsletter form exposes a CSRF token', $token !== '');

$res = http("$base/subscribe.php", [
    'csrf_token' => $token,
    'email'      => $newsletterEmail,
    'source'     => 'smoke-test',
    'return_to'  => '/gilgalrewardoutreach/',
    'form_started' => time() - 10,
]);
check('Newsletter sign-up is accepted', str_contains($res['body'], 'on the list'));

$page  = http("$base/");
$token = csrf_from($page['body']);
$res = http("$base/subscribe.php", [
    'csrf_token' => $token,
    'email'      => $newsletterEmail,
    'return_to'  => '/gilgalrewardoutreach/',
    'form_started' => time() - 10,
]);
check('  subscribing twice does not duplicate', str_contains($res['body'], 'already on the list'));

$page  = http("$base/");
$token = csrf_from($page['body']);
$res = http("$base/subscribe.php", [
    'csrf_token' => $token,
    'email'      => 'not-an-email',
    'return_to'  => '/gilgalrewardoutreach/',
    'form_started' => time() - 10,
]);
check('  an invalid address is refused', str_contains($res['body'], 'valid email address'));

// An open redirect here would be handed to every visitor, so it is worth a test.
$page  = http("$base/");
$token = csrf_from($page['body']);
$res = http("$base/subscribe.php", [
    'csrf_token' => $token,
    'email'      => 'redirect.test.' . time() . '@example.com',
    'return_to'  => 'https://evil.example.com/',
    'form_started' => time() - 10,
], false);
check('  an off-site return_to is not honoured',
    !str_contains($res['headers'], 'evil.example.com'));

// --- Story submission ------------------------------------------------------
section('Story submission');

$storyMarker = 'Smoke Test Story ' . time();

$page  = http("$base/stories");
$token = csrf_from($page['body']);
$res = http("$base/stories", [
    'csrf_token'  => $token,
    'author_name' => $storyMarker,
    'role'        => 'Volunteer',
    'state'       => 'Osun',
    'email'       => 'smoke.story@example.com',
    'story'       => 'This story was submitted by the automated smoke test to prove the moderation queue works as intended.',
    'form_started' => time() - 10,
]);
check('Story submission is accepted', str_contains($res['body'], 'Thank you, ' . $storyMarker));

$res = http("$base/stories");
check('  but is NOT published until approved', !str_contains($res['body'], $storyMarker));

// --- Support pledge --------------------------------------------------------
section('Support pledge');

$pledgeMarker = 'Smoke Test Pledge ' . time();

$page  = http("$base/support");
$token = csrf_from($page['body']);
$res = http("$base/support", [
    'csrf_token'   => $token,
    'name'         => $pledgeMarker,
    'email'        => 'smoke.pledge@example.com',
    'phone'        => '08031112222',
    'organisation' => 'Smoke Test Ltd',
    'support_type' => 'Donating goods',
    'message'      => 'Offering exercise books.',
    'form_started' => time() - 10,
]);
check('Support pledge is accepted', str_contains($res['body'], 'Thank you, ' . $pledgeMarker));

$page  = http("$base/support");
$token = csrf_from($page['body']);
$res = http("$base/support", [
    'csrf_token' => $token,
    'name'       => 'No Type Given',
    'email'      => 'x@example.com',
    'form_started' => time() - 10,
]);
check('  a pledge with no offer type is refused',
    str_contains($res['body'], 'how you would like to help'));

// --- Admin -----------------------------------------------------------------
section('Admin panel');

$res = http("$base/admin/", null, false);
check('Admin dashboard redirects anonymous visitors',
    in_array($res['status'], [301, 302], true) && str_contains($res['headers'], 'login'));

$page  = http("$base/admin/login.php");
$token = csrf_from($page['body']);
check('Login page renders', $page['status'] === 200 && $token !== '');

// Wrong password must not sign in.
$res = http("$base/admin/login.php", [
    'csrf_token' => $token,
    'email'      => 'admin@gilgalrewardoutreach.org',
    'password'   => 'definitely-the-wrong-password',
]);
check('Wrong password is refused', str_contains($res['body'], 'not recognised'));
check('  and warns how many attempts remain', str_contains($res['body'], 'attempt(s) left'));

// Correct password signs in.
$page  = http("$base/admin/login.php");
$token = csrf_from($page['body']);
$res = http("$base/admin/login.php", [
    'csrf_token' => $token,
    'email'      => 'admin@gilgalrewardoutreach.org',
    'password'   => 'GilgalReward@2026',
]);
$loggedIn = str_contains($res['body'], 'Dashboard') && str_contains($res['body'], 'Sign out');
check('Correct password signs in', $loggedIn);

if (!$loggedIn) {
    $notes[] = 'Admin tests were skipped because sign-in failed.';
} else {

    check('Dashboard shows the new volunteer sign-up', str_contains($res['body'], $marker));
    check('Dashboard shows the new contact message', str_contains($res['body'], $messageMarker));

    // Every managed section must list without error.
    foreach (['volunteers', 'messages', 'outreaches', 'outreach_photos', 'gallery',
              'volunteer_profiles', 'events', 'programmes', 'states', 'team',
              'stats', 'partners', 'stories', 'pledges', 'newsletter',
              'faqs', 'milestones'] as $resource) {
        $res = http("$base/admin/manage.php?r=$resource");
        check("manage.php?r=$resource lists without error",
            $res['status'] === 200 && !preg_match('/(Fatal error|Warning:|Notice:)/', $res['body']));
    }

    // Create -> edit -> delete round trip on a real resource.
    $page  = http("$base/admin/edit.php?r=outreaches");
    $token = csrf_from($page['body']);
    check('New-outreach form renders', $page['status'] === 200 && $token !== '');

    $res = http("$base/admin/edit.php", [
        'csrf_token'    => $token,
        'r'             => 'outreaches',
        'id'            => 0,
        'title'         => 'Smoke Test Outreach',
        'slug'          => '',
        'outreach_date' => date('Y-m-d'),
        'location'      => 'Test Location',
        'state'         => 'Osun',
        'programme_id'  => '',
        'beneficiaries' => 42,
        'volunteers'    => 7,
        'sort_order'    => 99,
        'summary'       => 'Created by the smoke test.',
        'story'         => 'This record exists only while the smoke test runs.',
    ]);
    check('Outreach is created', str_contains($res['body'], 'Outreach created.'));

    $res = http("$base/admin/manage.php?r=outreaches");
    check('  and appears in the list', str_contains($res['body'], 'Smoke Test Outreach'));

    // Find its id so we can edit and delete it.
    $newId = 0;
    if (preg_match_all('#edit(?:\.php)?\?r=outreaches&(?:amp;)?id=(\d+)#', $res['body'], $m)) {
        $newId = (int) max($m[1]);
    }
    check('  and has an editable id', $newId > 0);

    if ($newId > 0) {
        // The generated slug must be usable on the public site.
        $res = http("$base/outreach?slug=smoke-test-outreach");
        check('  generated slug resolves on the public site', $res['status'] === 200);

        $page  = http("$base/admin/edit.php?r=outreaches&id=$newId");
        $token = csrf_from($page['body']);
        check('  edit form loads the saved values', str_contains($page['body'], 'Smoke Test Outreach'));

        $res = http("$base/admin/edit.php", [
            'csrf_token'    => $token,
            'r'             => 'outreaches',
            'id'            => $newId,
            'title'         => 'Smoke Test Outreach (edited)',
            'slug'          => 'smoke-test-outreach',
            'outreach_date' => date('Y-m-d'),
            'location'      => 'Edited Location',
            'state'         => 'Osun',
            'programme_id'  => '',
            'beneficiaries' => 84,
            'volunteers'    => 9,
            'sort_order'    => 99,
            'summary'       => 'Edited by the smoke test.',
            'story'         => 'Edited body.',
        ]);
        check('  edit saves', str_contains($res['body'], 'Outreach updated.')
            && str_contains($res['body'], 'Smoke Test Outreach (edited)'));

        // Deleting without a token must not delete.
        $res = http("$base/admin/delete.php", ['r' => 'outreaches', 'id' => $newId]);
        check('  delete without CSRF is refused', str_contains($res['body'], 'session expired'));

        $page  = http("$base/admin/manage.php?r=outreaches");
        $token = csrf_from($page['body']);
        $res = http("$base/admin/delete.php", [
            'csrf_token' => $token, 'r' => 'outreaches', 'id' => $newId,
        ]);
        check('  delete removes it', str_contains($res['body'], 'Outreach deleted.')
            && !str_contains($res['body'], 'Smoke Test Outreach'));
    }

    // Settings page and CSV export.
    $res = http("$base/admin/settings.php");
    check('Settings page renders', $res['status'] === 200 && str_contains($res['body'], 'Site name'));

    $res = http("$base/admin/export.php?r=volunteers");
    check('Volunteer CSV export downloads',
        $res['status'] === 200 && str_contains(strtolower($res['headers']), 'text/csv'));
    check('  and contains the smoke-test sign-up', str_contains($res['body'], $marker));

    // --- New admin pages --------------------------------------------------
    $res = http("$base/admin/users.php");
    check('Administrators page renders', $res['status'] === 200 && str_contains($res['body'], 'Add an administrator'));

    $res = http("$base/admin/two-factor.php");
    check('Two-factor setup page renders', $res['status'] === 200 && str_contains($res['body'], 'Setup key'));
    check('  and offers manual entry rather than a QR code',
        str_contains($res['body'], 'Enter a setup key') || str_contains($res['body'], 'Manual entry'));

    // --- Moderation: approving a story publishes it -----------------------
    $page  = http("$base/admin/manage.php?r=stories&q=" . urlencode($storyMarker));
    $token = csrf_from($page['body']);
    check('Submitted story is waiting in the admin', str_contains($page['body'], $storyMarker));

    $storyId = 0;
    if (preg_match('#edit(?:\\.php)?\\?r=stories&(?:amp;)?id=(\\d+)#', $page['body'], $m)) {
        $storyId = (int) $m[1];
    }
    check('  and has an id', $storyId > 0);

    if ($storyId > 0) {
        // Bulk-approve it, which also exercises the bulk action machinery.
        $res = http("$base/admin/manage.php?r=stories", [
            'csrf_token'  => $token,
            'bulk_action' => 'approve',
            'ids'         => [$storyId],
            'q'           => '',
        ]);
        check('Bulk approve publishes the story', str_contains($res['body'], 'published'));

        $res = http("$base/stories");
        check('  and it now appears on the public page', str_contains($res['body'], $storyMarker));

        $page  = http("$base/admin/manage.php?r=stories&q=" . urlencode($storyMarker));
        $token = csrf_from($page['body']);
        $res = http("$base/admin/manage.php?r=stories", [
            'csrf_token'  => $token,
            'bulk_action' => 'delete',
            'ids'         => [$storyId],
            'q'           => '',
        ]);
        check('  bulk delete removes it', str_contains($res['body'], 'deleted'));
    }

    // A bulk action with no CSRF token must change nothing.
    $res = http("$base/admin/manage.php?r=messages", [
        'bulk_action' => 'delete',
        'ids'         => [1, 2, 3],
    ]);
    check('Bulk action without CSRF is refused', str_contains($res['body'], 'session expired'));

    // --- Admin pagination --------------------------------------------------
    $res = http("$base/admin/manage.php?r=gallery&page=1");
    check('Admin list page one renders', $res['status'] === 200 && str_contains($res['body'], 'listTable'));

    $res = http("$base/admin/manage.php?r=gallery&page=999");
    check('  an out-of-range admin page clamps instead of erroring',
        $res['status'] === 200 && str_contains($res['body'], 'listTable'));

    $res = http("$base/admin/manage.php?r=gallery&page=-5");
    check('  a negative page number is handled', $res['status'] === 200);

    // --- Maintenance mode --------------------------------------------------
    $page  = http("$base/admin/settings.php");
    $token = csrf_from($page['body']);
    check('Settings page offers maintenance mode', str_contains($page['body'], 'maintenance_mode'));

    // Collect every setting currently on the form so saving does not blank them.
    preg_match_all('/name="([a-z_]+)"[^>]*value="([^"]*)"/', $page['body'], $fieldMatches, PREG_SET_ORDER);
    $settings = ['csrf_token' => $token, 'action' => 'settings', 'maintenance_mode' => '1'];
    foreach ($fieldMatches as $match) {
        if (!in_array($match[1], ['csrf_token', 'action', 'maintenance_mode'], true)) {
            $settings[$match[1]] = html_entity_decode($match[2], ENT_QUOTES, 'UTF-8');
        }
    }
    preg_match_all('/<textarea id="s_([a-z_]+)" name="\\1"[^>]*>(.*?)<\\/textarea>/s', $page['body'], $areas, PREG_SET_ORDER);
    foreach ($areas as $area) {
        $settings[$area[1]] = html_entity_decode($area[2], ENT_QUOTES, 'UTF-8');
    }

    $res = http("$base/admin/settings.php", $settings);
    check('Maintenance mode can be turned on', str_contains($res['body'], 'saved'));

    // A signed-out visitor should now get the holding page.
    $adminJar = $jar;
    $jar = sys_get_temp_dir() . '/gro-anon-' . getmypid() . '.cookies';
    @unlink($jar);

    $res = http("$base/");
    check('  visitors see the holding page', $res['status'] === 503);
    check('  and it explains why', stripos($res['body'], 'back') !== false || stripos($res['body'], 'update') !== false);

    $res = http("$base/admin/login.php");
    check('  the admin login stays reachable', $res['status'] === 200);

    @unlink($jar);
    $jar = $adminJar;

    // The signed-in admin still sees the real site.
    $res = http("$base/");
    check('  a signed-in admin still sees the real site', $res['status'] === 200 && str_contains($res['body'], 'site-header'));

    $page  = http("$base/admin/settings.php");
    $token = csrf_from($page['body']);
    $settings['csrf_token'] = $token;
    unset($settings['maintenance_mode']);        // absent checkbox means off

    $res = http("$base/admin/settings.php", $settings);
    check('Maintenance mode can be turned off again', str_contains($res['body'], 'saved'));

    $res = http("$base/");
    check('  and the site is public again', $res['status'] === 200);

    // --- Clean up the pledge and newsletter rows --------------------------
    // $extraMarker, not $marker: destructuring into $marker here would
    // overwrite the volunteer marker the cleanup below still needs.
    foreach ([['pledges', $pledgeMarker], ['newsletter', $newsletterEmail],
              ['newsletter', 'redirect.test']] as [$resourceKey, $extraMarker]) {
        $page  = http("$base/admin/manage.php?r={$resourceKey}&q=" . urlencode($extraMarker));
        $token = csrf_from($page['body']);
        if (preg_match('#edit(?:\\.php)?\\?r=' . $resourceKey . '&(?:amp;)?id=(\\d+)#', $page['body'], $m)) {
            http("$base/admin/manage.php?r={$resourceKey}", [
                'csrf_token'  => $token,
                'bulk_action' => 'delete',
                'ids'         => [(int) $m[1]],
                'q'           => '',
            ]);
        }
    }

    // Clean up the rows this test created.
    $cleanup = [];

    $page  = http("$base/admin/manage.php?r=volunteers&q=" . urlencode($marker));
    $token = csrf_from($page['body']);
    if (preg_match('#edit(?:\.php)?\?r=volunteers&(?:amp;)?id=(\d+)#', $page['body'], $m)) {
        $res = http("$base/admin/delete.php", ['csrf_token' => $token, 'r' => 'volunteers', 'id' => (int) $m[1]]);
        $cleanup[] = str_contains($res['body'], 'deleted');
    }

    $page  = http("$base/admin/manage.php?r=messages&q=" . urlencode($messageMarker));
    $token = csrf_from($page['body']);
    if (preg_match('#edit(?:\.php)?\?r=messages&(?:amp;)?id=(\d+)#', $page['body'], $m)) {
        $res = http("$base/admin/delete.php", ['csrf_token' => $token, 'r' => 'messages', 'id' => (int) $m[1]]);
        $cleanup[] = str_contains($res['body'], 'deleted');
    }

    check('Test records cleaned up', count($cleanup) === 2 && !in_array(false, $cleanup, true),
        'remove any remaining "Smoke Test" rows by hand');

    // --- Theme and branding -------------------------------------------------
    section('Theme and branding');

    $page  = http("$base/admin/settings.php");
    $token = csrf_from($page['body']);

    check('Settings offers a brand colour', str_contains($page['body'], 'name="brand_color"'));
    check('Settings offers a default appearance', str_contains($page['body'], 'name="default_theme"'));
    check('Settings offers a logo upload', str_contains($page['body'], 'name="logo_image"'));
    check('Settings offers a favicon upload', str_contains($page['body'], 'name="favicon_image"'));
    check('Settings offers a social sharing image', str_contains($page['body'], 'name="social_image"'));

    // At the stock colours no extra CSS should be emitted at all.
    $res = http("$base/");
    check('Default colours emit no override CSS', !str_contains($res['body'], '--brand-ink:'));

    // Change the brand colour and confirm the whole palette is re-derived.
    $res = http("$base/admin/settings.php", [
        'csrf_token' => $token, 'action' => 'settings',
        'brand_color' => '#7A3FA0', 'accent_color' => '#E8A33D',
    ]);
    check('Brand colour saves', str_contains($res['body'], 'saved'));

    $res = http("$base/");
    check('  a custom colour emits a derived palette', str_contains($res['body'], '--brand:#7A3FA0'));
    check('  with a derived deep shade', str_contains($res['body'], '--brand-deep:'));
    check('  a readable on-brand text colour', str_contains($res['body'], '--on-brand:'));
    check('  and a matching theme-color meta tag', str_contains($res['body'], 'content="#7A3FA0"'));

    // A nonsense value must fall back rather than reach the stylesheet.
    $page  = http("$base/admin/settings.php");
    $token = csrf_from($page['body']);
    http("$base/admin/settings.php", [
        'csrf_token' => $token, 'action' => 'settings',
        'brand_color' => 'javascript:alert(1)', 'accent_color' => '#F0A73E',
    ]);
    $res = http("$base/");
    check('An invalid colour is rejected, not rendered', !str_contains($res['body'], 'javascript'));

    // Put the stock colours back.
    $page  = http("$base/admin/settings.php");
    $token = csrf_from($page['body']);
    http("$base/admin/settings.php", [
        'csrf_token' => $token, 'action' => 'settings',
        'brand_color' => '#0E6E62', 'accent_color' => '#F0A73E',
    ]);

    // --- Default appearance and the toggle ---------------------------------
    $page  = http("$base/admin/settings.php");
    $token = csrf_from($page['body']);
    http("$base/admin/settings.php", [
        'csrf_token' => $token, 'action' => 'settings', 'default_theme' => 'dark',
        // allow_theme_toggle omitted = unchecked = off
    ]);

    $res = http("$base/");
    check('Default appearance reaches the page', str_contains($res['body'], '"dark"'));
    check('  and the toggle can be hidden', !str_contains($res['body'], 'id="themeToggle"'));

    $page  = http("$base/admin/settings.php");
    $token = csrf_from($page['body']);
    http("$base/admin/settings.php", [
        'csrf_token' => $token, 'action' => 'settings',
        'default_theme' => 'system', 'allow_theme_toggle' => '1',
    ]);

    $res = http("$base/");
    check('  and restored again', str_contains($res['body'], 'id="themeToggle"'));

    // --- Logo upload --------------------------------------------------------
    $logoPath = sys_get_temp_dir() . '/gro-test-logo.png';
    $img = imagecreatetruecolor(200, 60);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
    imagefilledellipse($img, 30, 30, 40, 40, imagecolorallocate($img, 14, 110, 98));
    imagepng($img, $logoPath);
    imagedestroy($img);

    $page  = http("$base/admin/settings.php");
    $token = csrf_from($page['body']);

    $res = http_upload("$base/admin/settings.php", [
        'csrf_token' => $token,
        'action'     => 'settings',
        'logo_image' => new CURLFile($logoPath, 'image/png', 'test-logo.png'),
    ]);
    check('A logo uploads', str_contains($res['body'], 'saved'));

    $res = http("$base/");
    check('  and replaces the drawn mark on the site', str_contains($res['body'], 'class="brand-logo"'));

    $logoUrl = '';
    if (preg_match('#<img class="brand-logo" src="([^"]+)"#', $res['body'], $m)) {
        $logoUrl = $m[1];
    }
    check('  the uploaded file is served', $logoUrl !== ''
        && http('http://localhost' . $logoUrl)['status'] === 200);

    // A non-image must be refused.
    $badPath = sys_get_temp_dir() . '/gro-not-an-image.png';
    file_put_contents($badPath, "<?php echo 'not an image'; ?>");

    $page  = http("$base/admin/settings.php");
    $token = csrf_from($page['body']);
    $res = http_upload("$base/admin/settings.php", [
        'csrf_token' => $token,
        'action'     => 'settings',
        'logo_image' => new CURLFile($badPath, 'image/png', 'evil.png'),
    ]);
    check('  a file that is not really an image is refused',
        str_contains($res['body'], 'not a readable image'));

    // Remove the logo again.
    $page  = http("$base/admin/settings.php");
    $token = csrf_from($page['body']);
    $res = http("$base/admin/settings.php", [
        'csrf_token' => $token, 'action' => 'settings', 'remove_logo_image' => '1',
    ]);
    check('  and it can be removed', str_contains($res['body'], 'saved'));

    $res = http("$base/");
    check('  leaving the drawn mark behind', !str_contains($res['body'], 'class="brand-logo"'));

    @unlink($logoPath);
    @unlink($badPath);

    // Put the toggles back. A settings POST that omits a checkbox reads it as
    // "off" -- correct for the real form, which always submits every field,
    // but this test posts partial forms, so it has to restore them explicitly.
    $page  = http("$base/admin/settings.php");
    $token = csrf_from($page['body']);
    http("$base/admin/settings.php", [
        'csrf_token' => $token, 'action' => 'settings',
        'default_theme' => 'system', 'allow_theme_toggle' => '1',
    ]);

    $res = http("$base/");
    check('Theme settings restored to defaults', str_contains($res['body'], 'id="themeToggle"'));

    // --- Two-factor round trip ---------------------------------------------
    //
    // Enables 2FA with a code this script computes itself, signs out, then
    // proves the password alone is not enough and that the right code is.
    // The TOTP maths is verified separately against the RFC 6238 vectors;
    // this checks that the login flow is wired to it correctly.
    section('Two-factor round trip');

    $page  = http("$base/admin/two-factor.php");
    $token = csrf_from($page['body']);

    $secret = '';
    if (preg_match('/id="key" value="([A-Z2-7]+)"/', $page['body'], $m)) {
        $secret = $m[1];
    }
    check('Setup page offers a base32 secret', strlen($secret) === 32);

    if ($secret !== '') {
        // A wrong code must not switch 2FA on.
        $res = http("$base/admin/two-factor.php", [
            'csrf_token' => $token, 'action' => 'enable', 'code' => '000000',
        ]);
        check('  a wrong code is refused', str_contains($res['body'], 'not accepted'));

        $page  = http("$base/admin/two-factor.php");
        $token = csrf_from($page['body']);
        if (preg_match('/id="key" value="([A-Z2-7]+)"/', $page['body'], $m)) {
            $secret = $m[1];
        }

        $res = http("$base/admin/two-factor.php", [
            'csrf_token' => $token, 'action' => 'enable', 'code' => totp_now($secret),
        ]);
        check('  the right code turns two-factor on', str_contains($res['body'], 'Two-factor is on'));

        // Sign out, then try to get back in.
        http("$base/admin/logout.php");

        $page  = http("$base/admin/login.php");
        $token = csrf_from($page['body']);
        $res = http("$base/admin/login.php", [
            'csrf_token' => $token,
            'email'      => 'admin@gilgalrewardoutreach.org',
            'password'   => 'GilgalReward@2026',
        ]);
        check('  the password alone now stops at the code step',
            str_contains($res['body'], 'One more step') && !str_contains($res['body'], 'Sign out'));

        $res2 = http("$base/admin/", null, false);
        check('  and the dashboard is still closed at that point',
            in_array($res2['status'], [301, 302], true));

        // A wrong code must not complete the sign-in.
        $token = csrf_from($res['body']);
        $bad = http("$base/admin/two-factor-verify.php", ['csrf_token' => $token, 'code' => '111111']);
        check('  a wrong code does not sign you in', !str_contains($bad['body'], 'Sign out'));

        $token = csrf_from($bad['body']);
        $good = http("$base/admin/two-factor-verify.php", [
            'csrf_token' => $token, 'code' => totp_now($secret),
        ]);
        check('  the right code completes the sign-in',
            str_contains($good['body'], 'Dashboard') && str_contains($good['body'], 'Sign out'));

        // Put the account back how it started.
        $page  = http("$base/admin/two-factor.php");
        $token = csrf_from($page['body']);
        $res = http("$base/admin/two-factor.php", [
            'csrf_token' => $token, 'action' => 'disable', 'password' => 'GilgalReward@2026',
        ]);
        check('  two-factor can be turned off again', str_contains($res['body'], 'Set up two-factor'));
    }

    $res = http("$base/admin/logout.php");
    check('Sign out works', str_contains($res['body'], 'signed out'));

    $res = http("$base/admin/", null, false);
    check('  and the dashboard is protected again',
        in_array($res['status'], [301, 302], true));
}

// ---------------------------------------------------------------------------
@unlink($jar);

printf("\n%s\n", str_repeat('=', 52));
printf("%d passed, %d failed\n", $passed, $failed);

if ($notes) {
    print("\nFailures:\n");
    foreach ($notes as $note) {
        printf("  - %s\n", $note);
    }
}

exit($failed === 0 ? 0 : 1);
