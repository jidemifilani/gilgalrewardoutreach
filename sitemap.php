<?php
/** XML sitemap, served at /sitemap.xml via the .htaccess rewrite. */

require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/xml; charset=utf-8');

/** @var array<int, array{loc:string, lastmod:?string, priority:string, freq:string}> */
$urls = [];

$add = function (string $path, ?string $lastmod = null, string $priority = '0.6', string $freq = 'monthly') use (&$urls) {
    $urls[] = [
        'loc'      => full_base_url($path),
        'lastmod'  => $lastmod ? date('Y-m-d', strtotime($lastmod)) : null,
        'priority' => $priority,
        'freq'     => $freq,
    ];
};

// Static pages, weighted by how often they actually change.
$add('index.php',      null, '1.0', 'weekly');
$add('about.php',      null, '0.8', 'monthly');
$add('outreaches.php', null, '0.9', 'weekly');
$add('gallery.php',    null, '0.8', 'weekly');
$add('events.php',     null, '0.9', 'weekly');
$add('volunteer.php',  null, '0.9', 'monthly');
$add('stories.php',    null, '0.7', 'weekly');
$add('support.php',    null, '0.8', 'monthly');
$add('faq.php',        null, '0.6', 'monthly');
$add('contact.php',    null, '0.7', 'yearly');
$add('privacy.php',    null, '0.3', 'yearly');
$add('terms.php',      null, '0.3', 'yearly');

// Every outreach has its own page.
foreach ($pdo->query('SELECT slug, outreach_date, created_at FROM outreaches ORDER BY outreach_date DESC') as $row) {
    $add('outreach.php?slug=' . urlencode($row['slug']), $row['created_at'] ?: $row['outreach_date'], '0.7', 'yearly');
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $url): ?>
  <url>
    <loc><?= e($url['loc']) ?></loc>
<?php if ($url['lastmod']): ?>
    <lastmod><?= e($url['lastmod']) ?></lastmod>
<?php endif; ?>
    <changefreq><?= e($url['freq']) ?></changefreq>
    <priority><?= e($url['priority']) ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
