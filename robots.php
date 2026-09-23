<?php
/** robots.txt, served through PHP so the sitemap URL follows BASE_URL. */

require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=utf-8');

$disallowed = ['/admin/', '/includes/', '/config/', '/database/', '/tests/', '/assets/uploads/'];

echo "User-agent: *\n";
foreach ($disallowed as $path) {
    echo 'Disallow: ' . rtrim(BASE_URL, '/') . $path . "\n";
}

// Search result pages are thin and infinitely variable; keep them out of the index.
echo 'Disallow: ' . rtrim(BASE_URL, '/') . "/search\n";

if (get_setting($pdo, 'maintenance_mode', '0') === '1') {
    echo "\n# Maintenance mode is on.\nDisallow: /\n";
}

echo "\nSitemap: " . absolute_url(rtrim(BASE_URL, '/') . '/sitemap.xml') . "\n";
