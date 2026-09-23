<?php
/**
 * Curved photo band.
 *
 * Lays a set of photographs along an organic wave that flows across the page,
 * each one cropped to a circle or a soft blob. Used as a showcase strip
 * between sections.
 *
 * Positions are computed here in PHP and emitted as a <style nonce> block
 * rather than inline style="" attributes: the site's Content-Security-Policy
 * has no 'unsafe-inline' for styles, and a nonce covers a <style> element but
 * never a style attribute. See includes/security_headers.php.
 *
 * The generated rules live inside a min-width media query, so below that
 * breakpoint no positioning applies at all and the band falls back to the
 * plain centred grid defined in style.css. That is deliberate -- a wave needs
 * width to read as a wave, and at phone size it would just be a jumble.
 */

require_once __DIR__ . '/functions.php';

const WAVE_BREAKPOINT = 780;   // px; below this the band becomes a simple grid

/**
 * @param array $photos  [['src' => ..., 'alt' => ..., 'caption' => ...], ...]
 * @param array $options amplitude, cycles, phase, sizes, inset, height, shape
 */
function photo_wave(array $photos, array $options = []): string
{
    $photos = array_values(array_filter($photos, fn($p) => !empty($p['src'])));
    if (!$photos) {
        return '';
    }

    $amplitude = (float) ($options['amplitude'] ?? 21);    // % of band height
    $cycles    = (float) ($options['cycles']    ?? 1.15);  // waves across the band
    $phase     = (float) ($options['phase']     ?? 0);
    $inset     = (float) ($options['inset']     ?? 7);     // % kept clear each side
    $shape     = $options['shape'] ?? 'mixed';
    $sizes     = $options['sizes'] ?? [1, .68, .88, .6, .94, .72, .82, .64];

    $count = count($photos);
    $id    = 'wave' . substr(md5(serialize(array_column($photos, 'src')) . $amplitude . $phase), 0, 8);
    $span  = 100 - ($inset * 2);

    // Where each photo sits on the curve.
    $positions = [];
    for ($i = 0; $i < $count; $i++) {
        $t = $count > 1 ? $i / ($count - 1) : 0.5;
        $positions[] = [
            'left'  => $inset + ($t * $span),
            'top'   => 50 + ($amplitude * sin((2 * M_PI * $cycles * $t) + $phase)),
            'scale' => $sizes[$i % count($sizes)],
        ];
    }

    // The guide line the photos ride, sampled from the same function so the
    // curve and the photographs can never drift apart.
    $samples = [];
    for ($s = 0; $s <= 120; $s++) {
        $t = $s / 120;
        $samples[] = round($inset + ($t * $span), 3) * 10       // x in a 0..1000 viewBox
            . ' ' . round(50 + ($amplitude * sin((2 * M_PI * $cycles * $t) + $phase)), 3) * 10;
    }
    $linePath = 'M ' . implode(' L ', $samples);

    // --- Generated positioning -------------------------------------------
    $rules = '';
    foreach ($positions as $i => $position) {
        $rules .= sprintf(
            '#%1$s .pw-item:nth-child(%2$d){left:%3$.3f%%;top:%4$.3f%%;width:calc(var(--pw-base) * %5$.3f);}',
            $id,
            $i + 1,
            $position['left'],
            $position['top'],
            $position['scale']
        );
    }

    $style = '<style nonce="' . e(csp_nonce()) . '">@media (min-width:' . WAVE_BREAKPOINT . 'px){'
           . $rules . '}</style>';

    // --- Markup ------------------------------------------------------------
    $out  = $style;
    $out .= '<div class="photo-wave-wrap">';

    $out .= '<svg class="pw-line" viewBox="0 0 1000 1000" preserveAspectRatio="none" aria-hidden="true" focusable="false">'
          . '<path d="' . $linePath . '" fill="none" stroke="currentColor" stroke-width="2" '
          . 'stroke-linecap="round" stroke-dasharray="3 14" vector-effect="non-scaling-stroke"/>'
          . '</svg>';

    $out .= '<ul class="photo-wave" id="' . e($id) . '">';

    foreach ($photos as $i => $photo) {
        // Alternate circles and blobs so the row reads as organic, not as beads.
        $shapeClass = match ($shape) {
            'circle' => 'pw-circle',
            'blob'   => 'pw-blob-' . (($i % 4) + 1),
            default  => ($i % 3 === 0) ? 'pw-circle' : 'pw-blob-' . (($i % 4) + 1),
        };

        $caption = trim((string) ($photo['caption'] ?? ''));

        $out .= '<li class="pw-item ' . $shapeClass . '" data-ring="' . ($i % 4) . '">';
        $out .= '<img src="' . e($photo['src']) . '" alt="' . e($photo['alt'] ?? '') . '" '
              . 'loading="lazy" decoding="async" width="320" height="320">';

        if ($caption !== '') {
            $out .= '<span class="pw-caption">' . e($caption) . '</span>';
        }

        $out .= '</li>';
    }

    $out .= '</ul></div>';

    return $out;
}

/**
 * Builds a photo set for the band out of any of the site's content tables.
 *
 * $kind picks the table and the fallback artwork style, so a band of
 * volunteers gets generated portraits and a band of outreaches gets scenes.
 */
function wave_photos(PDO $pdo, string $kind, int $limit = 9): array
{
    $photos = [];

    switch ($kind) {

        case 'volunteers':
            $stmt = $pdo->prepare(
                'SELECT * FROM volunteer_profiles WHERE is_active = 1 ORDER BY sort_order, id LIMIT ' . (int) $limit
            );
            $stmt->execute();
            foreach ($stmt as $row) {
                $photos[] = [
                    'src'     => media_url($row['photo'], 'volunteer-' . $row['id'] . '-' . $row['full_name'], 'portrait'),
                    'alt'     => $row['full_name'] . ', ' . $row['role'] . ', ' . $row['state'],
                    'caption' => $row['full_name'] . ' · ' . $row['state'],
                ];
            }
            break;

        case 'outreaches':
            $stmt = $pdo->prepare(
                'SELECT o.*, p.icon AS programme_icon FROM outreaches o
                   LEFT JOIN programmes p ON p.id = o.programme_id
                  ORDER BY o.outreach_date DESC LIMIT ' . (int) $limit
            );
            $stmt->execute();
            foreach ($stmt as $row) {
                $photos[] = [
                    'src'     => media_url($row['cover_image'], 'outreach-' . $row['id'], $row['programme_icon'] ?? 'outreach'),
                    'alt'     => $row['title'],
                    'caption' => $row['state'] . ' · ' . fmt_date($row['outreach_date'], 'M Y'),
                ];
            }
            break;

        case 'team':
            $stmt = $pdo->prepare(
                'SELECT * FROM team_members WHERE is_active = 1 ORDER BY sort_order, id LIMIT ' . (int) $limit
            );
            $stmt->execute();
            foreach ($stmt as $row) {
                $photos[] = [
                    'src'     => media_url($row['photo'], 'team-' . $row['id'] . '-' . $row['name'], 'portrait'),
                    'alt'     => $row['name'] . ', ' . $row['role'],
                    'caption' => $row['name'],
                ];
            }
            break;

        case 'gallery':
        default:
            $stmt = $pdo->prepare(
                'SELECT * FROM gallery_items ORDER BY sort_order, id LIMIT ' . (int) $limit
            );
            $stmt->execute();
            foreach ($stmt as $row) {
                $photos[] = [
                    'src'     => media_url($row['image'], 'gallery-' . $row['id'] . '-' . $row['title'], strtolower($row['category'])),
                    'alt'     => $row['title'] . '. ' . $row['caption'],
                    'caption' => $row['title'],
                ];
            }
            break;
    }

    return $photos;
}
