<?php
/**
 * Hand-authored vector illustration library.
 *
 * Everything visual on this site that isn't an uploaded photograph is drawn
 * here as SVG: the hero scene, section illustrations, programme icons, UI
 * icons, the curved section dividers, and the deterministic portrait/scene
 * generator that image.php serves when no real photo has been uploaded yet.
 *
 * Style rules kept consistent across every illustration:
 *   - flat geometric shapes, no gradients except one soft sun glow
 *   - rounded line caps, 0 or very few strokes
 *   - figures built from the same primitives (blob head, capsule limbs)
 *   - the palette below and nothing outside it
 */

require_once __DIR__ . '/functions.php';

// ---------------------------------------------------------------------------
// Palette
// ---------------------------------------------------------------------------

const ILLU = [
    'ink'        => '#13241F',
    'brand'      => '#0E6E62',
    'brandMid'   => '#2FA090',
    'brandPale'  => '#BFE6DD',
    'brandWash'  => '#E4F4F0',
    'amber'      => '#F0A73E',
    'amberPale'  => '#FBE0B4',
    'coral'      => '#E8705F',
    'coralPale'  => '#F8CFC8',
    'sky'        => '#4E93C8',
    'skyPale'    => '#CBE2F2',
    'cream'      => '#FFF7EE',
    'white'      => '#FFFFFF',
];

/** Warm skin tones, used for every figure across the site. */
const ILLU_SKIN = ['#6B4226', '#8D5524', '#A9703F', '#C68642', '#5A3620', '#B77B4A'];

/** Clothing colours figures are dressed from. */
const ILLU_CLOTH = ['#0E6E62', '#E8705F', '#F0A73E', '#4E93C8', '#2FA090', '#C05C7E', '#5B5BD6'];

const ILLU_HAIRCOLOR = ['#1B1210', '#2B1A14', '#241612'];

// ---------------------------------------------------------------------------
// Deterministic pseudo-randomness
//
// The same seed always produces the same picture, so a volunteer's portrait
// or an outreach photo never changes between page loads.
// ---------------------------------------------------------------------------

function illu_seed(string $seed): int
{
    return (int) (hexdec(substr(md5($seed), 0, 8)) & 0x7FFFFFFF);
}

/** Pulls stable value #$index out of $list for this seed. */
function illu_pick(array $list, string $seed, int $index = 0): mixed
{
    $n = illu_seed($seed . '|' . $index);
    return $list[$n % count($list)];
}

function illu_int(string $seed, int $index, int $min, int $max): int
{
    $n = illu_seed($seed . '#' . $index);
    return $min + ($n % max(1, ($max - $min + 1)));
}

// ---------------------------------------------------------------------------
// Figure primitives -- shared by every scene and portrait
// ---------------------------------------------------------------------------

/**
 * A standing/sitting figure built from capsules and a blob head.
 *
 * @param array $o x, y (top of head), scale, skin, cloth, hair, pose
 *                 pose: 'stand' | 'reach' | 'sit' | 'carry' | 'wave'
 */
function illu_figure(array $o): string
{
    $x     = $o['x'] ?? 0;
    $y     = $o['y'] ?? 0;
    $s     = $o['scale'] ?? 1;
    $skin  = $o['skin']  ?? ILLU_SKIN[1];
    $cloth = $o['cloth'] ?? ILLU['brand'];
    $hair  = $o['hair']  ?? ILLU_HAIRCOLOR[0];
    $pose  = $o['pose']  ?? 'stand';
    $hairStyle = $o['hairStyle'] ?? 0;

    $ink = ILLU['ink'];
    $g   = '<g transform="translate(' . $x . ',' . $y . ') scale(' . $s . ')">';

    // Legs / lower body vary by pose.
    if ($pose === 'sit') {
        $g .= '<path d="M -20 96 q 4 34 -14 44 l 46 0 q 10 -24 6 -44 z" fill="' . $ink . '" opacity=".85"/>';
        $g .= '<path d="M 6 96 q 10 30 34 34 l 6 -14 q -20 -8 -22 -28 z" fill="' . $ink . '" opacity=".85"/>';
    } else {
        $g .= '<rect x="-20" y="88" width="16" height="56" rx="8" fill="' . $ink . '" opacity=".85"/>';
        $g .= '<rect x="4"  y="88" width="16" height="56" rx="8" fill="' . $ink . '" opacity=".85"/>';
        $g .= '<path d="M -24 140 h 22 a 6 6 0 0 1 0 12 h -22 z" fill="' . ILLU['cream'] . '"/>';
        $g .= '<path d="M 24 140 h -22 a 6 6 0 0 0 0 12 h 22 z" fill="' . ILLU['cream'] . '"/>';
    }

    // Torso
    $g .= '<path d="M 0 22 c 22 0 34 16 34 40 l 0 34 q -34 10 -68 0 l 0 -34 c 0 -24 12 -40 34 -40 z" fill="' . $cloth . '"/>';

    // Arms
    switch ($pose) {
        case 'reach':
            $g .= '<path d="M 30 46 q 30 -12 44 -34" stroke="' . $skin . '" stroke-width="13" stroke-linecap="round" fill="none"/>';
            $g .= '<path d="M -30 46 q -14 18 -10 38" stroke="' . $skin . '" stroke-width="13" stroke-linecap="round" fill="none"/>';
            break;
        case 'carry':
            $g .= '<path d="M 30 46 q 16 16 2 30" stroke="' . $skin . '" stroke-width="13" stroke-linecap="round" fill="none"/>';
            $g .= '<path d="M -30 46 q -16 16 -2 30" stroke="' . $skin . '" stroke-width="13" stroke-linecap="round" fill="none"/>';
            break;
        case 'wave':
            $g .= '<path d="M 30 46 q 24 -6 26 -36" stroke="' . $skin . '" stroke-width="13" stroke-linecap="round" fill="none"/>';
            $g .= '<path d="M -30 46 q -18 14 -16 36" stroke="' . $skin . '" stroke-width="13" stroke-linecap="round" fill="none"/>';
            break;
        case 'sit':
            $g .= '<path d="M 30 46 q 12 22 -6 34" stroke="' . $skin . '" stroke-width="13" stroke-linecap="round" fill="none"/>';
            $g .= '<path d="M -30 46 q -12 22 6 34" stroke="' . $skin . '" stroke-width="13" stroke-linecap="round" fill="none"/>';
            break;
        default:
            $g .= '<path d="M 32 48 q 12 20 6 40" stroke="' . $skin . '" stroke-width="13" stroke-linecap="round" fill="none"/>';
            $g .= '<path d="M -32 48 q -12 20 -6 40" stroke="' . $skin . '" stroke-width="13" stroke-linecap="round" fill="none"/>';
    }

    // Neck + head
    $g .= '<rect x="-7" y="6" width="14" height="22" rx="7" fill="' . $skin . '"/>';
    $g .= '<ellipse cx="0" cy="-8" rx="25" ry="27" fill="' . $skin . '"/>';

    // Hair -- six variants so a crowd never looks cloned.
    switch ($hairStyle % 6) {
        case 0: // close cut
            $g .= '<path d="M -25 -12 a 25 27 0 0 1 50 0 q -6 -14 -25 -14 t -25 14 z" fill="' . $hair . '"/>';
            break;
        case 1: // afro
            $g .= '<circle cx="0" cy="-20" r="27" fill="' . $hair . '"/>'
                . '<ellipse cx="0" cy="-8" rx="25" ry="27" fill="' . $skin . '"/>'
                . '<path d="M -25 -10 a 25 27 0 0 1 50 0 q 0 -26 -25 -26 t -25 26 z" fill="' . $hair . '"/>';
            break;
        case 2: // headwrap (gele)
            $g .= '<path d="M -27 -14 q 2 -30 27 -30 t 27 30 q -14 -10 -27 -10 t -27 10 z" fill="' . ILLU['amber'] . '"/>'
                . '<path d="M 14 -38 q 22 -14 26 4 q -16 0 -24 8 z" fill="' . ILLU['amber'] . '"/>';
            break;
        case 3: // braids
            $g .= '<path d="M -25 -12 a 25 27 0 0 1 50 0 q -6 -16 -25 -16 t -25 16 z" fill="' . $hair . '"/>'
                . '<rect x="-30" y="-14" width="8" height="34" rx="4" fill="' . $hair . '"/>'
                . '<rect x="22"  y="-14" width="8" height="34" rx="4" fill="' . $hair . '"/>';
            break;
        case 4: // bun
            $g .= '<path d="M -25 -12 a 25 27 0 0 1 50 0 q -6 -15 -25 -15 t -25 15 z" fill="' . $hair . '"/>'
                . '<circle cx="0" cy="-38" r="11" fill="' . $hair . '"/>';
            break;
        default: // cap
            $g .= '<path d="M -26 -14 q 4 -26 26 -26 t 26 26 z" fill="' . ILLU['coral'] . '"/>'
                . '<rect x="-30" y="-16" width="60" height="7" rx="3.5" fill="' . ILLU['coral'] . '"/>';
    }

    // Face: two dots and a smile. Deliberately minimal.
    $g .= '<circle cx="-8" cy="-8" r="2.4" fill="' . $ink . '"/>';
    $g .= '<circle cx="9"  cy="-8" r="2.4" fill="' . $ink . '"/>';
    $g .= '<path d="M -7 2 q 7 7 15 0" stroke="' . $ink . '" stroke-width="2.4" stroke-linecap="round" fill="none"/>';

    if (!empty($o['glasses'])) {
        $g .= '<g stroke="' . $ink . '" stroke-width="2" fill="none" opacity=".8">'
            . '<circle cx="-8" cy="-8" r="7"/><circle cx="9" cy="-8" r="7"/>'
            . '<path d="M -1 -8 h 3 M -15 -9 l -6 -2 M 16 -9 l 6 -2"/></g>';
    }

    return $g . '</g>';
}

/** A simple open/closed book prop. */
function illu_book(float $x, float $y, float $s = 1, string $cover = null, bool $open = true): string
{
    $cover = $cover ?? ILLU['coral'];
    $g = '<g transform="translate(' . $x . ',' . $y . ') scale(' . $s . ')">';
    if ($open) {
        $g .= '<path d="M -34 -4 q 17 -12 34 -4 l 0 26 q -17 -8 -34 4 z" fill="' . ILLU['white'] . '" stroke="' . $cover . '" stroke-width="3" stroke-linejoin="round"/>';
        $g .= '<path d="M 34 -4 q -17 -12 -34 -4 l 0 26 q 17 -8 34 4 z" fill="' . ILLU['white'] . '" stroke="' . $cover . '" stroke-width="3" stroke-linejoin="round"/>';
        $g .= '<path d="M -26 2 h 16 M -26 9 h 13 M 10 2 h 16 M 13 9 h 13" stroke="' . $cover . '" stroke-width="2" stroke-linecap="round" opacity=".55"/>';
    } else {
        $g .= '<rect x="-20" y="-14" width="40" height="28" rx="4" fill="' . $cover . '"/>';
        $g .= '<rect x="-20" y="-14" width="9"  height="28" rx="4" fill="' . ILLU['ink'] . '" opacity=".25"/>';
        $g .= '<path d="M -2 -6 h 14 M -2 1 h 11" stroke="' . ILLU['white'] . '" stroke-width="2.4" stroke-linecap="round" opacity=".8"/>';
    }
    return $g . '</g>';
}

/** Leafy plant used along the ground line of most scenes. */
function illu_plant(float $x, float $y, float $s = 1, string $color = null): string
{
    $color = $color ?? ILLU['brandMid'];
    return '<g transform="translate(' . $x . ',' . $y . ') scale(' . $s . ')">'
        . '<path d="M 0 0 v -46" stroke="' . $color . '" stroke-width="4" stroke-linecap="round"/>'
        . '<path d="M 0 -18 q -22 -6 -26 -26 q 22 -2 26 18 z" fill="' . $color . '"/>'
        . '<path d="M 0 -30 q 20 -6 24 -26 q -20 -2 -24 18 z" fill="' . $color . '" opacity=".75"/>'
        . '<path d="M 0 -42 q -14 -8 -14 -24 q 14 4 14 20 z" fill="' . $color . '" opacity=".55"/>'
        . '</g>';
}

// ---------------------------------------------------------------------------
// Organic shape helpers -- the blob masks and curved accents
// ---------------------------------------------------------------------------

/** One of six hand-drawn organic blob outlines, normalised to a 0..200 box. */
function illu_blob_path(int $variant = 0): string
{
    $paths = [
        'M 100 4 C 148 4 196 38 198 88 C 200 142 160 196 104 198 C 48 200 6 160 4 106 C 2 50 48 4 100 4 Z',
        'M 104 2 C 160 0 198 46 196 104 C 194 156 148 198 96 196 C 40 194 2 150 6 96 C 10 42 52 4 104 2 Z',
        'M 98 6 C 152 0 194 42 196 96 C 198 152 154 194 100 196 C 44 198 4 154 6 98 C 8 46 48 10 98 6 Z',
        'M 100 0 C 154 6 200 44 194 100 C 188 154 150 200 96 194 C 44 188 0 148 6 94 C 12 42 50 -4 100 0 Z',
        'M 96 4 C 150 8 192 40 196 94 C 200 150 156 192 102 196 C 46 200 8 156 4 102 C 0 48 44 0 96 4 Z',
        'M 102 2 C 158 4 194 48 196 102 C 198 158 152 196 98 194 C 42 192 4 148 4 96 C 4 44 48 0 102 2 Z',
    ];
    return $paths[$variant % count($paths)];
}

/**
 * Wraps an image in an organic blob mask.
 * Used for every photograph slot on the site -- volunteers, outreaches, gallery.
 */
function illu_blob_image(string $src, string $alt, int $variant = 0, string $class = ''): string
{
    $id = 'blob' . substr(md5($src . $variant), 0, 8);
    return '<div class="blob-frame ' . e($class) . '">'
        . '<svg viewBox="0 0 200 200" aria-hidden="true" class="blob-frame-svg">'
        . '<defs><clipPath id="' . $id . '" clipPathUnits="userSpaceOnUse">'
        . '<path d="' . illu_blob_path($variant) . '"/>'
        . '</clipPath></defs>'
        . '<image href="' . e($src) . '" x="0" y="0" width="200" height="200" '
        . 'preserveAspectRatio="xMidYMid slice" clip-path="url(#' . $id . ')"/>'
        . '</svg>'
        . '<span class="sr-only">' . e($alt) . '</span>'
        . '</div>';
}

/**
 * The curved colour accent that separates sections.
 *
 * $tone names the surface the curve is pretending to be -- "cream" or
 * "paper" -- rather than taking a colour. The fill is currentColor and the
 * actual value comes from a CSS class, because a hardcoded hex here turns
 * into a bright band across the page the moment dark mode is on.
 */
function illu_curve(string $position = 'bottom', string $tone = 'cream', string $class = ''): string
{
    $tone = in_array($tone, ['cream', 'paper', 'wash'], true) ? $tone : 'cream';

    $d = $position === 'top'
        ? 'M 0 80 C 260 0 740 0 1000 60 C 1180 100 1340 70 1440 34 L 1440 0 L 0 0 Z'
        : 'M 0 26 C 180 90 420 96 720 54 C 980 18 1240 20 1440 72 L 1440 120 L 0 120 Z';

    return '<svg class="curve curve-' . e($position) . ' curve-tone-' . e($tone) . ' ' . e($class) . '" '
        . 'viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true" focusable="false">'
        . '<path d="' . $d . '" fill="currentColor"/></svg>';
}

/** Scattered dot grid used as a decorative accent behind cards. */
function illu_dots(int $cols = 6, int $rows = 5, string $color = null): string
{
    $color = $color ?? ILLU['amber'];
    $out = '<svg class="dot-grid" viewBox="0 0 ' . ($cols * 16) . ' ' . ($rows * 16) . '" aria-hidden="true">';
    for ($r = 0; $r < $rows; $r++) {
        for ($c = 0; $c < $cols; $c++) {
            $out .= '<circle cx="' . ($c * 16 + 4) . '" cy="' . ($r * 16 + 4) . '" r="2.6" fill="' . $color . '"/>';
        }
    }
    return $out . '</svg>';
}

// ---------------------------------------------------------------------------
// Full scene illustrations
// ---------------------------------------------------------------------------

/** Homepage hero: a volunteer handing a book to a child, with a reading child. */
function illu_hero(): string
{
    $I = ILLU;

    $svg  = '<svg class="illu illu-hero" viewBox="0 0 720 580" role="img" '
          . 'aria-label="Illustration of a volunteer handing a book to a child while another child reads">';

    // soft sun glow
    $svg .= '<defs><radialGradient id="sunGlow" cx="50%" cy="50%" r="50%">'
          . '<stop offset="0%" stop-color="' . $I['amber'] . '" stop-opacity=".55"/>'
          . '<stop offset="100%" stop-color="' . $I['amber'] . '" stop-opacity="0"/>'
          . '</radialGradient>';

    // organic backdrop + sun
    $backdrop = 'M 372 22 C 548 14 690 150 700 316 C 710 470 590 556 402 566 '
              . 'C 214 576 56 512 38 356 C 20 200 196 30 372 22 Z';

    $svg .= '<clipPath id="heroBlob"><path d="' . $backdrop . '"/></clipPath></defs>';
    $svg .= '<path d="' . $backdrop . '" fill="' . $I['brandWash'] . '"/>';
    $svg .= '<circle cx="556" cy="150" r="118" fill="url(#sunGlow)"/>';
    $svg .= '<circle cx="556" cy="150" r="52" fill="' . $I['amber'] . '"/>';

    // Ground and accent sweeps are clipped to the backdrop, so their ends
    // follow the organic edge instead of stopping on a straight line.
    $svg .= '<g clip-path="url(#heroBlob)">';
    $svg .= '<path d="M 60 392 C 200 300 330 470 470 372 C 570 302 640 330 692 386" stroke="'
          . $I['brandPale'] . '" stroke-width="26" stroke-linecap="round" fill="none"/>';
    $svg .= '<path d="M 44 436 C 210 352 340 512 492 408 C 586 344 652 372 700 424" stroke="'
          . $I['coralPale'] . '" stroke-width="14" stroke-linecap="round" fill="none" opacity=".9"/>';
    $svg .= '<path d="M -20 494 C 220 452 470 452 740 494 L 740 600 L -20 600 Z" fill="'
          . $I['brandPale'] . '" opacity=".7"/>';
    $svg .= '</g>';

    // sitting child, reading
    $svg .= illu_figure([
        'x' => 176, 'y' => 348, 'scale' => .82,
        'skin' => ILLU_SKIN[3], 'cloth' => $I['sky'], 'pose' => 'sit', 'hairStyle' => 3,
    ]);
    $svg .= illu_book(176, 404, 1.15, $I['coral']);

    // volunteer, offering a book
    $svg .= illu_figure([
        'x' => 372, 'y' => 252, 'scale' => 1.18,
        'skin' => ILLU_SKIN[1], 'cloth' => $I['brand'], 'pose' => 'carry', 'hairStyle' => 2,
    ]);
    $svg .= illu_book(372, 356, 1.25, $I['amber'], false);

    // child reaching for it
    $svg .= illu_figure([
        'x' => 516, 'y' => 320, 'scale' => .92,
        'skin' => ILLU_SKIN[0], 'cloth' => $I['coral'], 'pose' => 'reach', 'hairStyle' => 1,
    ]);

    // plants along the ground line
    $svg .= illu_plant(92, 500, 1.25);
    $svg .= illu_plant(640, 504, 1.05, $I['brandMid']);
    $svg .= illu_plant(600, 516, .75, $I['brand']);

    // floating pencil
    $svg .= '<g transform="translate(160,140) rotate(-18)">'
          . '<rect x="-6" y="-42" width="12" height="70" rx="3" fill="' . $I['sky'] . '"/>'
          . '<path d="M -6 28 h 12 l -6 14 z" fill="' . $I['amberPale'] . '"/>'
          . '<rect x="-6" y="-48" width="12" height="8" rx="3" fill="' . $I['coral'] . '"/>'
          . '</g>';

    // stars and dots
    $svg .= '<path d="M 268 92 l 6 14 15 2 -11 10 3 15 -13 -8 -13 8 3 -15 -11 -10 15 -2 z" fill="'
          . $I['amber'] . '" opacity=".9"/>';
    $svg .= '<path d="M 640 328 l 5 11 12 2 -9 8 2 12 -10 -6 -10 6 2 -12 -9 -8 12 -2 z" fill="'
          . $I['coral'] . '" opacity=".8"/>';
    $svg .= '<circle cx="120" cy="300" r="7" fill="' . $I['coral'] . '" opacity=".7"/>';
    $svg .= '<circle cx="612" cy="452" r="9" fill="' . $I['brandMid'] . '" opacity=".6"/>';
    $svg .= '<circle cx="330" cy="70" r="6" fill="' . $I['brandMid'] . '" opacity=".5"/>';

    return $svg . '</svg>';
}

/** About page: a circle of community members around a shared centre. */
function illu_community(): string
{
    $I = ILLU;
    $ring = '';
    // eight figures arranged on an ellipse, facing in
    $people = [
        [160, 300, .62, 0], [250, 214, .6, 1], [372, 186, .66, 2], [492, 214, .6, 3],
        [576, 300, .62, 4], [492, 388, .6, 5], [372, 416, .66, 0], [250, 388, .6, 2],
    ];
    foreach ($people as $i => $p) {
        $ring .= illu_figure([
            'x' => $p[0], 'y' => $p[1], 'scale' => $p[2],
            'skin'  => ILLU_SKIN[$i % count(ILLU_SKIN)],
            'cloth' => ILLU_CLOTH[$i % count(ILLU_CLOTH)],
            'hairStyle' => $p[3],
            'pose'  => $i % 3 === 0 ? 'wave' : 'stand',
        ]);
    }

    return '<svg class="illu illu-community" viewBox="0 0 720 560" role="img" '
        . 'aria-label="Illustration of community members standing in a circle around a shared heart">'
        . '<ellipse cx="368" cy="330" rx="316" ry="214" fill="' . $I['brandWash'] . '"/>'
        . '<ellipse cx="368" cy="336" rx="196" ry="116" fill="' . $I['brandPale'] . '" opacity=".75"/>'
        . '<path d="M 368 268 c -30 -40 -96 -14 -92 34 c 4 44 62 74 92 98 c 30 -24 88 -54 92 -98 c 4 -48 -62 -74 -92 -34 z" fill="' . $I['coral'] . '"/>'
        . '<ellipse cx="336" cy="302" rx="15" ry="10" fill="' . $I['white'] . '" opacity=".28" transform="rotate(-28 336 302)"/>'
        . $ring
        . illu_plant(92, 470, .8)
        . illu_plant(648, 470, .8, $I['brandMid'])
        . '<circle cx="120" cy="150" r="9" fill="' . $I['amber'] . '" opacity=".7"/>'
        . '<circle cx="620" cy="134" r="12" fill="' . $I['sky'] . '" opacity=".5"/>'
        . '<circle cx="660" cy="410" r="7" fill="' . $I['coral'] . '" opacity=".6"/>'
        . '</svg>';
}

/**
 * Volunteer page: an abstract map of Nigeria with a pin for each chapter state.
 * The outline is stylised, not survey-accurate -- it reads as a map, not a chart.
 */
function illu_nigeria_map(array $states = []): string
{
    $I = ILLU;

    // Approximate plot positions for the states we work in, on a 0..560 x 0..440 box.
    // Spaced so the text labels (drawn to the right of each pin) do not
    // collide with a neighbouring pin.
    $coords = [
        'Lagos'  => [118, 332], 'Ogun'  => [142, 292], 'Oyo'   => [150, 232],
        'Osun'   => [220, 270], 'Kwara' => [206, 186], 'Abuja' => [302, 196],
        'Benue'  => [362, 272], 'Rivers'=> [292, 368],
    ];

    $pins = '';
    $labels = '';
    foreach ($states as $i => $state) {
        $name = $state['name'] ?? '';
        if (!isset($coords[$name])) {
            continue;
        }
        [$x, $y] = $coords[$name];
        $color = [$I['coral'], $I['amber'], $I['sky'], $I['brand']][$i % 4];

        $pins .= '<g class="map-pin" style="--pin-delay:' . ($i * 120) . 'ms">'
            . '<circle cx="' . $x . '" cy="' . $y . '" r="18" fill="' . $color . '" opacity=".22"/>'
            . '<path d="M ' . $x . ' ' . ($y - 26) . ' a 11 11 0 0 1 11 11 c 0 8 -11 21 -11 21 s -11 -13 -11 -21 a 11 11 0 0 1 11 -11 z" fill="' . $color . '"/>'
            . '<circle cx="' . $x . '" cy="' . ($y - 15) . '" r="4.2" fill="' . $I['white'] . '"/>'
            . '</g>';

        $labels .= '<text x="' . ($x + 16) . '" y="' . ($y + 5) . '" class="map-label">' . e($name) . '</text>';
    }

    return '<svg class="illu illu-map" viewBox="0 0 560 440" role="img" '
        . 'aria-label="Stylised map of Nigeria marking the states where our volunteers serve">'
        . '<path d="M 96 168 C 120 108 176 74 244 72 C 318 70 372 92 424 82 C 470 74 498 104 492 150 '
        . 'C 486 196 456 214 452 254 C 448 300 418 336 372 356 C 322 378 268 404 216 388 '
        . 'C 162 372 128 330 114 280 C 102 236 82 210 96 168 Z" fill="' . $I['brandPale'] . '"/>'
        . '<path d="M 96 168 C 120 108 176 74 244 72 C 318 70 372 92 424 82 C 470 74 498 104 492 150 '
        . 'C 486 196 456 214 452 254 C 448 300 418 336 372 356 C 322 378 268 404 216 388 '
        . 'C 162 372 128 330 114 280 C 102 236 82 210 96 168 Z" fill="none" stroke="' . $I['brandMid'] . '" '
        . 'stroke-width="3" stroke-dasharray="7 9" opacity=".7"/>'
        . $pins . $labels
        . '<circle cx="492" cy="374" r="10" fill="' . $I['amber'] . '" opacity=".6"/>'
        . '<circle cx="62"  cy="112" r="7"  fill="' . $I['coral'] . '" opacity=".6"/>'
        . '</svg>';
}

/** Contact page: an envelope with a message flying out of it. */
function illu_contact(): string
{
    $I = ILLU;
    return '<svg class="illu illu-contact" viewBox="0 0 560 460" role="img" '
        . 'aria-label="Illustration of a message being sent">'
        . '<path d="M 282 28 C 418 22 528 128 522 260 C 516 380 420 438 286 440 C 156 442 44 372 40 254 C 36 132 146 34 282 28 Z" fill="' . $I['brandWash'] . '"/>'
        . '<path d="M 96 322 C 210 286 356 286 470 326" stroke="' . $I['brandPale'] . '" stroke-width="22" stroke-linecap="round" fill="none"/>'
        // envelope
        . '<g transform="translate(280,262)">'
        . '<rect x="-132" y="-78" width="264" height="168" rx="16" fill="' . $I['brand'] . '"/>'
        . '<path d="M -132 -66 L 0 26 L 132 -66" fill="none" stroke="' . $I['brandWash'] . '" stroke-width="8" stroke-linejoin="round"/>'
        . '<path d="M -132 90 L -18 4 M 132 90 L 18 4" stroke="' . $I['brandWash'] . '" stroke-width="6" opacity=".55"/>'
        . '</g>'
        // letter flying out
        . '<g transform="translate(300,120) rotate(-10)">'
        . '<rect x="-84" y="-58" width="168" height="112" rx="10" fill="' . $I['white'] . '" stroke="' . $I['brandPale'] . '" stroke-width="3"/>'
        . '<path d="M -58 -26 h 116 M -58 -4 h 96 M -58 18 h 74" stroke="' . $I['brandMid'] . '" stroke-width="7" stroke-linecap="round" opacity=".8"/>'
        . '</g>'
        . '<path d="M 96 168 q 40 -34 84 -10" stroke="' . $I['amber'] . '" stroke-width="7" stroke-linecap="round" fill="none" opacity=".8"/>'
        . '<path d="M 402 148 q 44 -28 82 2" stroke="' . $I['coral'] . '" stroke-width="7" stroke-linecap="round" fill="none" opacity=".8"/>'
        . '<circle cx="456" cy="222" r="9" fill="' . $I['amber'] . '" opacity=".7"/>'
        . '<circle cx="104" cy="252" r="7" fill="' . $I['coral'] . '" opacity=".7"/>'
        . '</svg>';
}

/** Volunteer call-to-action: a figure raising a hand to join. */
function illu_join(): string
{
    $I = ILLU;
    return '<svg class="illu illu-join" viewBox="0 0 520 440" role="img" '
        . 'aria-label="Illustration of volunteers raising their hands to join">'
        . '<circle cx="260" cy="226" r="196" fill="' . $I['brandWash'] . '"/>'
        . '<path d="M 72 300 C 180 250 340 250 448 302" stroke="' . $I['amberPale'] . '" stroke-width="20" stroke-linecap="round" fill="none"/>'
        . illu_figure(['x' => 150, 'y' => 156, 'scale' => .86, 'skin' => ILLU_SKIN[0], 'cloth' => $I['coral'], 'pose' => 'wave', 'hairStyle' => 2])
        . illu_figure(['x' => 260, 'y' => 128, 'scale' => 1,   'skin' => ILLU_SKIN[3], 'cloth' => $I['brand'], 'pose' => 'reach', 'hairStyle' => 1])
        . illu_figure(['x' => 372, 'y' => 156, 'scale' => .86, 'skin' => ILLU_SKIN[1], 'cloth' => $I['sky'],   'pose' => 'wave', 'hairStyle' => 4])
        . '<path d="M 258 18 l 7 17 18 2 -13 12 4 18 -16 -10 -16 10 4 -18 -13 -12 18 -2 z" fill="' . $I['amber'] . '"/>'
        . '<circle cx="96"  cy="120" r="10" fill="' . $I['coral'] . '" opacity=".6"/>'
        . '<circle cx="430" cy="106" r="8"  fill="' . $I['brandMid'] . '" opacity=".6"/>'
        . '</svg>';
}

// ---------------------------------------------------------------------------
// Programme icons -- larger illustrated glyphs, one per cause
// ---------------------------------------------------------------------------

function illu_programme_icon(string $name, string $class = ''): string
{
    $I = ILLU;
    $body = '';

    switch ($name) {
        case 'education':
            $body = '<circle cx="48" cy="48" r="44" fill="' . $I['brandWash'] . '"/>'
                . '<path d="M 48 24 l 30 14 -30 14 -30 -14 z" fill="' . $I['brand'] . '"/>'
                . '<path d="M 26 46 v 16 q 22 14 44 0 v -16 l -22 10 z" fill="' . $I['brandMid'] . '"/>'
                . '<path d="M 78 38 v 22" stroke="' . $I['amber'] . '" stroke-width="4" stroke-linecap="round"/>'
                . '<circle cx="78" cy="64" r="5" fill="' . $I['amber'] . '"/>';
            break;
        case 'health':
            $body = '<circle cx="48" cy="48" r="44" fill="' . $I['coralPale'] . '"/>'
                . '<path d="M 48 34 c -12 -16 -38 -6 -36 14 c 2 18 24 30 36 40 c 12 -10 34 -22 36 -40 c 2 -20 -24 -30 -36 -14 z" fill="' . $I['coral'] . '"/>'
                . '<path d="M 22 62 h 12 l 5 -10 6 20 6 -14 4 6 h 17" fill="none" stroke="' . $I['white'] . '" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>';
            break;
        case 'skills':
            $body = '<circle cx="48" cy="48" r="44" fill="' . $I['skyPale'] . '"/>'
                . '<rect x="22" y="30" width="52" height="34" rx="5" fill="' . $I['sky'] . '"/>'
                . '<rect x="28" y="36" width="40" height="22" rx="3" fill="' . $I['white'] . '" opacity=".85"/>'
                . '<rect x="16" y="64" width="64" height="7" rx="3.5" fill="' . $I['brand'] . '"/>'
                . '<path d="M 38 46 l -6 5 6 5 M 58 46 l 6 5 -6 5" stroke="' . $I['sky'] . '" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round" fill="none"/>';
            break;
        case 'relief':
            $body = '<circle cx="48" cy="48" r="44" fill="' . $I['amberPale'] . '"/>'
                . '<path d="M 20 44 h 56 l -6 30 q -22 8 -44 0 z" fill="' . $I['amber'] . '"/>'
                . '<path d="M 20 44 q 28 -12 56 0" fill="none" stroke="' . $I['brand'] . '" stroke-width="4"/>'
                . '<circle cx="38" cy="34" r="8" fill="' . $I['coral'] . '"/>'
                . '<circle cx="56" cy="32" r="10" fill="' . $I['brandMid'] . '"/>'
                . '<path d="M 56 22 q 8 -6 10 2 q -8 2 -10 -2 z" fill="' . $I['brand'] . '"/>';
            break;
        case 'people':
            $body = '<circle cx="48" cy="48" r="44" fill="' . $I['brandWash'] . '"/>'
                . '<circle cx="36" cy="38" r="11" fill="' . $I['brand'] . '"/>'
                . '<circle cx="60" cy="41" r="9"  fill="' . $I['brandMid'] . '"/>'
                . '<path d="M 18 72 a 18 18 0 0 1 36 0 z" fill="' . $I['brand'] . '"/>'
                . '<path d="M 48 72 a 15 15 0 0 1 30 0 z" fill="' . $I['brandMid'] . '"/>'
                . '<path d="M 70 26 l 3 7 8 1 -6 5 2 8 -7 -4 -7 4 2 -8 -6 -5 8 -1 z" fill="' . $I['amber'] . '"/>';
            break;

        case 'heart':
        default:
            $body = '<circle cx="48" cy="48" r="44" fill="' . $I['brandWash'] . '"/>'
                . '<path d="M 48 32 c -13 -17 -38 -6 -36 15 c 2 19 25 31 36 42 c 11 -11 34 -23 36 -42 c 2 -21 -23 -32 -36 -15 z" fill="' . $I['brand'] . '"/>';
    }

    return '<svg class="prog-icon ' . e($class) . '" viewBox="0 0 96 96" aria-hidden="true" focusable="false">'
        . $body . '</svg>';
}

// ---------------------------------------------------------------------------
// UI icon set -- single-colour line icons, inherit currentColor
// ---------------------------------------------------------------------------

function icon(string $name, string $class = ''): string
{
    $paths = [
        'phone'     => '<path d="M6.6 3h3l1.5 3.8-2 1.4a12 12 0 0 0 5.7 5.7l1.4-2L20 13.4v3a2 2 0 0 1-2.2 2A15.6 15.6 0 0 1 4.6 5.2 2 2 0 0 1 6.6 3z"/>',
        'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3.6 6.5 12 13l8.4-6.5"/>',
        'pin'       => '<path d="M12 21s7-6.4 7-11a7 7 0 1 0-14 0c0 4.6 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.2 2"/>',
        'calendar'  => '<rect x="3.5" y="5" width="17" height="16" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'users'     => '<circle cx="9" cy="8" r="3.4"/><path d="M2.8 20a6.2 6.2 0 0 1 12.4 0"/><path d="M16.5 5.2a3.4 3.4 0 0 1 0 6.6M17.6 14.4A6.2 6.2 0 0 1 21.2 20"/>',
        'arrow'     => '<path d="M5 12h13M13 6.5 18.6 12 13 17.5"/>',
        'arrow-up'  => '<path d="M12 19V5M6.5 10.5 12 5l5.5 5.5"/>',
        'check'     => '<path d="M5 12.6 9.6 17 19 6.6"/>',
        'heart'     => '<path d="M12 20s-7.5-4.6-7.5-9.7A4.3 4.3 0 0 1 12 7.4a4.3 4.3 0 0 1 7.5 2.9C19.5 15.4 12 20 12 20z"/>',
        'facebook'  => '<path d="M14.6 8.4h2.2V5.2h-2.5c-2.3 0-3.7 1.5-3.7 3.9v1.7H8.2v3.1h2.4V21h3.3v-7.1h2.4l.4-3.1h-2.8V9.6c0-.8.3-1.2.7-1.2z"/>',
        'instagram' => '<rect x="3.6" y="3.6" width="16.8" height="16.8" rx="5"/><circle cx="12" cy="12" r="3.8"/><circle cx="17" cy="7" r="1.1" fill="currentColor" stroke="none"/>',
        'twitter'   => '<path d="M4 4.2 10.6 13 4.4 19.8h1.9l5.2-5.7 4.3 5.7h4.2L13 10.5l5.8-6.3h-1.9l-4.8 5.2-3.9-5.2z" fill="currentColor" stroke="none"/>',
        'whatsapp'  => '<path d="M12 3.6A8.4 8.4 0 0 0 4.7 16.3L3.8 20l3.8-.9A8.4 8.4 0 1 0 12 3.6z"/><path d="M9.2 8.3c.3 0 .5.2.6.4l.6 1.4c.1.3 0 .5-.2.7l-.5.5a6.2 6.2 0 0 0 2.9 2.9l.5-.6c.2-.2.5-.3.7-.2l1.4.6c.2.1.4.3.4.6a2 2 0 0 1-2 1.7 7.4 7.4 0 0 1-6.2-6.2 2 2 0 0 1 1.8-1.8z" fill="currentColor" stroke="none"/>',
        'menu'      => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close'     => '<path d="M6 6l12 12M18 6 6 18"/>',
        'search'    => '<circle cx="11" cy="11" r="6.4"/><path d="M15.8 15.8 20 20"/>',
        'quote'     => '<path d="M9.4 6.6C6.8 8 5.4 10.2 5.4 13v4.4h5.2V12H8.2c0-1.6.8-2.8 2.4-3.6zM19 6.6c-2.6 1.4-4 3.6-4 6.4v4.4h5.2V12h-2.4c0-1.6.8-2.8 2.4-3.6z" fill="currentColor" stroke="none"/>',
        'sparkle'   => '<path d="M12 3.5 13.9 9l5.6 2-5.6 2-1.9 5.5L10.1 13l-5.6-2 5.6-2z"/>',
        'chevron'   => '<path d="M6 9.5 12 15.5l6-6"/>',
        'sun'       => '<circle cx="12" cy="12" r="4.2"/><path d="M12 2.6v2.2M12 19.2v2.2M4.2 12H2M22 12h-2.2M6.1 6.1 4.6 4.6M19.4 19.4l-1.5-1.5M17.9 6.1l1.5-1.5M4.6 19.4l1.5-1.5"/>',
        'moon'      => '<path d="M20.5 14.3A8.4 8.4 0 0 1 9.7 3.5a8.4 8.4 0 1 0 10.8 10.8z"/>',
        'download'  => '<path d="M12 4v10M7.6 10.4 12 14.8l4.4-4.4M4.5 19.5h15"/>',
        'external'  => '<path d="M14 4.5h5.5V10M19 5l-8 8M18 14v4.5a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 4 18.5v-11A1.5 1.5 0 0 1 5.5 6H10"/>',
    ];

    $body = $paths[$name] ?? $paths['sparkle'];

    return '<svg class="icon icon-' . e($name) . ' ' . e($class) . '" viewBox="0 0 24 24" '
        . 'fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" '
        . 'stroke-linejoin="round" aria-hidden="true" focusable="false">' . $body . '</svg>';
}

// ---------------------------------------------------------------------------
// Generated stand-in artwork (served by image.php)
// ---------------------------------------------------------------------------

/**
 * A portrait, drawn from a seed. Used for volunteer faces until real
 * photographs are uploaded through the admin panel.
 */
function illu_portrait_svg(string $seed, int $size = 480): string
{
    $skin  = illu_pick(ILLU_SKIN, $seed, 1);
    $cloth = illu_pick(ILLU_CLOTH, $seed, 2);
    $hair  = illu_pick(ILLU_HAIRCOLOR, $seed, 3);
    $bg    = illu_pick([ILLU['brandWash'], ILLU['amberPale'], ILLU['coralPale'], ILLU['skyPale'], ILLU['brandPale']], $seed, 4);
    $style = illu_int($seed, 5, 0, 5);
    $ring  = illu_pick([ILLU['brandMid'], ILLU['amber'], ILLU['coral'], ILLU['sky']], $seed, 6);

    // Scaled and offset so the frame crops to head-and-shoulders, the way a
    // real portrait would, rather than showing a small whole-body figure.
    $figure = illu_figure([
        'x' => 240, 'y' => 268, 'scale' => 3.0,
        'skin' => $skin, 'cloth' => $cloth, 'hair' => $hair,
        'hairStyle' => $style, 'pose' => 'stand',
        'glasses' => illu_int($seed, 7, 0, 2) === 0,
    ]);

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 480 480" width="' . $size . '" height="' . $size . '">'
        . '<rect width="480" height="480" fill="' . $bg . '"/>'
        . '<circle cx="240" cy="300" r="180" fill="' . $ring . '" opacity=".22"/>'
        . '<path d="M 60 430 C 150 386 330 386 420 430 L 420 480 L 60 480 Z" fill="' . $ring . '" opacity=".3"/>'
        . '<circle cx="86"  cy="88"  r="18" fill="' . ILLU['white'] . '" opacity=".45"/>'
        . '<circle cx="404" cy="132" r="11" fill="' . ILLU['white'] . '" opacity=".4"/>'
        . '<g clip-path="inset(0 0 0 0)">' . $figure . '</g>'
        . '</svg>';
}

/**
 * An outreach/gallery scene, drawn from a seed. $kind steers the props so an
 * education photo slot doesn't render a food parcel.
 */
function illu_scene_svg(string $seed, string $kind = 'outreach', int $w = 800, int $h = 560): string
{
    $I    = ILLU;
    $kind = strtolower($kind);

    $skyColor = illu_pick([$I['brandWash'], $I['skyPale'], $I['amberPale'], $I['coralPale']], $seed, 1);
    $ground   = illu_pick([$I['brandPale'], $I['brandMid'], $I['amber']], $seed, 2);
    $accent   = illu_pick([$I['coral'], $I['amber'], $I['sky'], $I['brandMid']], $seed, 3);

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 560" width="' . $w . '" height="' . $h . '">';
    $svg .= '<rect width="800" height="560" fill="' . $skyColor . '"/>';

    // sun / sky furniture
    $svg .= '<circle cx="' . illu_int($seed, 4, 120, 680) . '" cy="' . illu_int($seed, 5, 70, 140) . '" r="'
        . illu_int($seed, 6, 34, 56) . '" fill="' . $I['amber'] . '" opacity=".75"/>';

    // rolling curved accent
    $svg .= '<path d="M -20 360 C 180 284 420 430 620 344 C 700 310 770 320 820 352 L 820 560 L -20 560 Z" fill="'
        . $ground . '" opacity=".55"/>';
    $svg .= '<path d="M -20 420 C 200 350 430 480 640 402 C 720 372 780 380 820 406 L 820 560 L -20 560 Z" fill="'
        . $ground . '" opacity=".85"/>';

    // A building or canopy behind the group, varied by kind.
    if (in_array($kind, ['education', 'outreach', 'volunteers'], true)) {
        $svg .= '<g transform="translate(' . illu_int($seed, 7, 90, 180) . ',196)">'
            . '<rect x="0" y="40" width="210" height="150" rx="8" fill="' . $I['cream'] . '"/>'
            . '<path d="M -16 44 L 105 -6 L 226 44 Z" fill="' . $accent . '"/>'
            . '<rect x="28"  y="80" width="48" height="44" rx="5" fill="' . $I['skyPale'] . '"/>'
            . '<rect x="134" y="80" width="48" height="44" rx="5" fill="' . $I['skyPale'] . '"/>'
            . '<rect x="84"  y="136" width="42" height="54" rx="6" fill="' . $I['brand'] . '"/>'
            . '</g>';
    } else {
        // canopy / tent for health, skills and relief scenes
        $signage = match ($kind) {
            // A medical cross belongs on a health camp only.
            'health' => '<path d="M 96 138 h 28 M 110 124 v 28" stroke="' . $I['coral']
                        . '" stroke-width="9" stroke-linecap="round"/>',
            // Relief gets stacked parcels on the trestle table.
            'relief' => '<rect x="60" y="130" width="42" height="30" rx="5" fill="' . $I['amber'] . '"/>'
                        . '<rect x="112" y="136" width="46" height="24" rx="5" fill="' . $I['brandMid'] . '"/>'
                        . '<path d="M 60 145 h 42 M 112 148 h 46" stroke="' . $I['cream'] . '" stroke-width="4"/>',
            // Skills gets a workbench tool rack.
            'skills' => '<path d="M 74 128 v 34 M 92 128 v 34 M 110 128 v 34" stroke="' . $I['sky']
                        . '" stroke-width="7" stroke-linecap="round"/>'
                        . '<rect x="124" y="132" width="34" height="26" rx="4" fill="' . $I['sky'] . '"/>',
            default  => '<path d="M 78 142 h 64" stroke="' . $I['brandMid']
                        . '" stroke-width="8" stroke-linecap="round"/>',
        };

        $svg .= '<g transform="translate(' . illu_int($seed, 7, 96, 170) . ',210)">'
            . '<path d="M 0 40 L 110 -10 L 220 40 L 196 40 L 110 8 L 24 40 Z" fill="' . $accent . '"/>'
            . '<rect x="6"   y="36" width="208" height="16" rx="8" fill="' . $accent . '"/>'
            . '<rect x="14"  y="52" width="10" height="132" rx="5" fill="' . $I['ink'] . '" opacity=".55"/>'
            . '<rect x="196" y="52" width="10" height="132" rx="5" fill="' . $I['ink'] . '" opacity=".55"/>'
            . '<rect x="44"  y="120" width="132" height="64" rx="8" fill="' . $I['cream'] . '"/>'
            . $signage
            . '</g>';
    }

    // The group of figures. Count and poses vary with the seed.
    $count  = illu_int($seed, 8, 3, 5);
    $startX = 330;
    for ($i = 0; $i < $count; $i++) {
        $scale = ($i % 2 === 0) ? 1.0 : 0.76;
        $svg .= illu_figure([
            'x'         => $startX + $i * 96,
            'y'         => 300 + ($i % 2 === 0 ? 0 : 34),
            'scale'     => $scale,
            'skin'      => illu_pick(ILLU_SKIN, $seed, 20 + $i),
            'cloth'     => illu_pick(ILLU_CLOTH, $seed, 30 + $i),
            'hair'      => illu_pick(ILLU_HAIRCOLOR, $seed, 40 + $i),
            'hairStyle' => illu_int($seed, 50 + $i, 0, 5),
            'pose'      => illu_pick(['stand', 'wave', 'carry', 'reach'], $seed, 60 + $i),
        ]);
    }

    // Kind-specific foreground prop.
    switch ($kind) {
        case 'education':
            $svg .= illu_book(210, 430, 1.5, $I['coral']);
            $svg .= illu_book(660, 452, 1.2, $I['sky'], false);
            break;
        case 'health':
            $svg .= '<g transform="translate(210,430)">'
                . '<rect x="-38" y="-26" width="76" height="52" rx="8" fill="' . $I['white'] . '"/>'
                . '<path d="M -14 0 h 28 M 0 -14 v 28" stroke="' . $I['coral'] . '" stroke-width="10" stroke-linecap="round"/>'
                . '</g>';
            break;
        case 'skills':
            $svg .= '<g transform="translate(206,432)">'
                . '<rect x="-44" y="-30" width="88" height="56" rx="7" fill="' . $I['sky'] . '"/>'
                . '<rect x="-36" y="-22" width="72" height="40" rx="4" fill="' . $I['white'] . '"/>'
                . '<rect x="-54" y="26"  width="108" height="9" rx="4.5" fill="' . $I['brand'] . '"/>'
                . '</g>';
            break;
        case 'relief':
            $svg .= '<g transform="translate(206,436)">'
                . '<path d="M -44 -14 h 88 l -9 46 q -35 12 -70 0 z" fill="' . $I['amber'] . '"/>'
                . '<path d="M -44 -14 q 44 -18 88 0" fill="none" stroke="' . $I['brand'] . '" stroke-width="6"/>'
                . '<circle cx="-16" cy="-28" r="13" fill="' . $I['coral'] . '"/>'
                . '<circle cx="12"  cy="-30" r="15" fill="' . $I['brandMid'] . '"/>'
                . '</g>';
            break;
        default:
            $svg .= illu_book(214, 434, 1.4, $accent);
    }

    $svg .= illu_plant(94, 500, 1.3);
    $svg .= illu_plant(742, 494, 1.1, $I['brandMid']);
    $svg .= illu_plant(690, 512, .8, $I['brand']);

    return $svg . '</svg>';
}

/**
 * The brand mark: a stone circle (Gilgal) opening toward a rising sun, with a
 * sheltering hand beneath it. Drawn rather than imported so it scales cleanly
 * in the header, the footer and the favicon.
 */
function illu_logo(string $class = 'brand-mark', ?string $brand = null, ?string $accent = null): string
{
    // Unlike the scene illustrations, the mark follows the brand colours set
    // in Admin -> Settings -- it is branding, not artwork.
    $brand  = $brand  ?: ILLU['brand'];
    $accent = $accent ?: ILLU['amber'];

    $pale = function_exists('shade') ? shade($brand, 82, .55) : ILLU['brandPale'];

    return '<svg class="' . e($class) . '" viewBox="0 0 64 64" role="img" aria-label="Site mark">'
        . '<path d="M 32 3 C 48 3 61 15 61 32 C 61 49 49 61 32 61 C 15 61 3 49 3 32 C 3 15 16 3 32 3 Z" fill="' . e($brand) . '"/>'
        . '<circle cx="32" cy="26" r="10" fill="' . e($accent) . '"/>'
        . '<path d="M 32 12 v -5 M 46 26 h 5 M 13 26 h 5 M 42 16 l 3.5 -3.5 M 22 16 l -3.5 -3.5" '
        . 'stroke="' . e($accent) . '" stroke-width="3" stroke-linecap="round"/>'
        . '<path d="M 14 41 C 22 34 42 34 50 41 C 46 52 38 56 32 56 C 26 56 18 52 14 41 Z" fill="' . e($pale) . '"/>'
        . '<path d="M 20 42 C 25 38 39 38 44 42" stroke="' . e($brand) . '" stroke-width="2.6" stroke-linecap="round" fill="none" opacity=".55"/>'
        . '</svg>';
}
