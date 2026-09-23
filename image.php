<?php
/**
 * Stand-in artwork generator.
 *
 * Serves a deterministic vector illustration for any picture slot that has no
 * uploaded photograph yet, so gallery / outreach / volunteer pages always look
 * complete. Once an admin uploads a real photo, media_url() stops pointing
 * here for that record.
 *
 *   image.php?kind=portrait&seed=Yewande+Adeniyi
 *   image.php?kind=education&seed=outreach-3
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/illustrations.php';

$kind = preg_replace('/[^a-z]/', '', strtolower((string) ($_GET['kind'] ?? 'scene')));
$seed = mb_substr(trim((string) ($_GET['seed'] ?? 'default')), 0, 120);

if ($seed === '') {
    $seed = $kind !== '' ? $kind : 'default';
}

$svg = $kind === 'portrait'
    ? illu_portrait_svg($seed)
    : illu_scene_svg($seed, $kind);

// These images never change for a given seed, so they cache hard.
$etag = '"' . md5($kind . '|' . $seed . '|v1') . '"';

if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: ' . $etag);
header('Content-Length: ' . strlen($svg));

echo $svg;
