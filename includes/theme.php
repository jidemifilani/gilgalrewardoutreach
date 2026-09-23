<?php
/**
 * Theme: brand colours and branding images, both editable from Admin → Settings.
 *
 * One brand colour and one accent colour are stored. Every other shade the
 * stylesheet needs -- the deep variant, the pale panel, the text colour that
 * sits on that panel, the dark-mode equivalents -- is derived here rather than
 * asked for, so an admin cannot pick a combination that makes text disappear.
 *
 * The result is emitted as a nonced <style> block after style.css. It cannot
 * be inline style attributes: the Content-Security-Policy has no
 * 'unsafe-inline' for styles, and a nonce covers a <style> element only.
 */

require_once __DIR__ . '/functions.php';

const THEME_DEFAULT_BRAND  = '#0E6E62';
const THEME_DEFAULT_ACCENT = '#F0A73E';

// ---------------------------------------------------------------------------
// Colour maths
// ---------------------------------------------------------------------------

/** Accepts #abc, #aabbcc, or aabbcc. Falls back when the value is unusable. */
function hex_norm(?string $hex, string $fallback): string
{
    $hex = strtoupper(trim((string) $hex));
    $hex = ltrim($hex, '#');

    if (preg_match('/^[0-9A-F]{3}$/', $hex)) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    return preg_match('/^[0-9A-F]{6}$/', $hex) ? '#' . $hex : $fallback;
}

/** @return array{0:float,1:float,2:float} r, g, b in 0..1 */
function hex_to_rgb(string $hex): array
{
    $hex = ltrim(hex_norm($hex, '#000000'), '#');
    return [
        hexdec(substr($hex, 0, 2)) / 255,
        hexdec(substr($hex, 2, 2)) / 255,
        hexdec(substr($hex, 4, 2)) / 255,
    ];
}

/** @return array{0:float,1:float,2:float} hue 0..360, saturation and lightness 0..100 */
function hex_to_hsl(string $hex): array
{
    [$r, $g, $b] = hex_to_rgb($hex);

    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $l   = ($max + $min) / 2;

    if ($max === $min) {
        return [0.0, 0.0, $l * 100];
    }

    $d = $max - $min;
    $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

    $h = match (true) {
        $max === $r => (($g - $b) / $d) + ($g < $b ? 6 : 0),
        $max === $g => (($b - $r) / $d) + 2,
        default     => (($r - $g) / $d) + 4,
    };

    return [$h * 60, $s * 100, $l * 100];
}

function hsl_to_hex(float $h, float $s, float $l): string
{
    $h = fmod(fmod($h, 360) + 360, 360) / 360;
    $s = max(0, min(100, $s)) / 100;
    $l = max(0, min(100, $l)) / 100;

    if ($s == 0) {
        $v = (int) round($l * 255);
        return sprintf('#%02X%02X%02X', $v, $v, $v);
    }

    $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - ($l * $s);
    $p = (2 * $l) - $q;

    $channel = static function (float $t) use ($p, $q): int {
        if ($t < 0) { $t += 1; }
        if ($t > 1) { $t -= 1; }
        if ($t < 1 / 6) { return (int) round(($p + (($q - $p) * 6 * $t)) * 255); }
        if ($t < 1 / 2) { return (int) round($q * 255); }
        if ($t < 2 / 3) { return (int) round(($p + (($q - $p) * ((2 / 3) - $t) * 6)) * 255); }
        return (int) round($p * 255);
    };

    return sprintf(
        '#%02X%02X%02X',
        $channel($h + 1 / 3),
        $channel($h),
        $channel($h - 1 / 3)
    );
}

/**
 * The same hue at a given lightness.
 *
 * $satScale pulls saturation down for very pale tints, which otherwise come
 * out looking like highlighter pen.
 */
function shade(string $hex, float $lightness, float $satScale = 1.0): string
{
    [$h, $s, ] = hex_to_hsl($hex);
    return hsl_to_hex($h, $s * $satScale, $lightness);
}

function rgba_of(string $hex, float $alpha): string
{
    [$r, $g, $b] = hex_to_rgb($hex);
    return sprintf('rgba(%d, %d, %d, %.2f)', round($r * 255), round($g * 255), round($b * 255), $alpha);
}

/**
 * White or near-black, whichever is readable on this colour.
 * Uses relative luminance (WCAG), not a naive brightness average.
 */
function readable_on(string $hex): string
{
    [$r, $g, $b] = hex_to_rgb($hex);

    $linear = static fn(float $c): float =>
        $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

    $luminance = (0.2126 * $linear($r)) + (0.7152 * $linear($g)) + (0.0722 * $linear($b));

    // Contrast against white vs against a near-black; pick the better one.
    $againstWhite = 1.05 / ($luminance + 0.05);
    $againstDark  = ($luminance + 0.05) / 0.06;

    return $againstDark >= $againstWhite ? '#0B1A16' : '#FFFFFF';
}

// ---------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------

function theme_brand(PDO $pdo): string
{
    return hex_norm(get_setting($pdo, 'brand_color', THEME_DEFAULT_BRAND), THEME_DEFAULT_BRAND);
}

function theme_accent(PDO $pdo): string
{
    return hex_norm(get_setting($pdo, 'accent_color', THEME_DEFAULT_ACCENT), THEME_DEFAULT_ACCENT);
}

/** 'system', 'light' or 'dark'. */
function theme_default(PDO $pdo): string
{
    $value = get_setting($pdo, 'default_theme', 'system');
    return in_array($value, ['system', 'light', 'dark'], true) ? $value : 'system';
}

function theme_toggle_allowed(PDO $pdo): bool
{
    return get_setting($pdo, 'allow_theme_toggle', '1') === '1';
}

/**
 * A branding image, or '' when none has been uploaded.
 * $key is one of: logo_image, favicon_image, social_image.
 */
function theme_image(PDO $pdo, string $key): string
{
    $file = trim(get_setting($pdo, $key, ''));
    if ($file === '') {
        return '';
    }

    $path = dirname(__DIR__) . '/assets/uploads/' . basename($file);
    return is_file($path) ? BASE_URL . '/assets/uploads/' . basename($file) : '';
}

// ---------------------------------------------------------------------------
// Emitted CSS
// ---------------------------------------------------------------------------

/**
 * The derived palette, as a <style> block.
 *
 * Returns '' when both colours are the defaults, so the common case ships no
 * extra CSS at all and style.css stays the single source of truth.
 */
function theme_style_block(PDO $pdo): string
{
    $brand  = theme_brand($pdo);
    $accent = theme_accent($pdo);

    if ($brand === THEME_DEFAULT_BRAND && $accent === THEME_DEFAULT_ACCENT) {
        return '';
    }

    // --- Light -------------------------------------------------------------
    $light = [
        '--brand'       => $brand,
        '--brand-deep'  => shade($brand, 17),
        '--brand-mid'   => shade($brand, 41),
        '--brand-pale'  => shade($brand, 82, .55),
        '--brand-wash'  => shade($brand, 92, .45),
        '--brand-ink'   => shade($brand, 17),
        '--on-brand'    => readable_on($brand),
        '--amber'       => $accent,
        '--amber-pale'  => shade($accent, 85, .75),
        '--amber-ink'   => shade($accent, 26),
        '--mark-bg'     => shade($accent, 85, .75),
        '--footer-bg'   => shade($brand, 12),
        '--glow-brand'  => rgba_of(shade($brand, 40), .38),
        '--glow-accent' => rgba_of($accent, .24),
    ];

    // --- Dark --------------------------------------------------------------
    // Lightness is pinned to values that work on a dark ground rather than
    // nudged from the light values, so an already-dark brand colour does not
    // come out invisible.
    $dark = [
        '--brand'       => shade($brand, 60),
        '--brand-deep'  => shade($brand, 18),
        '--brand-mid'   => shade($brand, 41),
        '--brand-pale'  => shade($brand, 22),
        '--brand-wash'  => shade($brand, 15),
        '--brand-ink'   => shade($brand, 76, .8),
        '--on-brand'    => readable_on(shade($brand, 60)),
        '--amber'       => shade($accent, 62),
        '--amber-pale'  => shade($accent, 17),
        '--amber-ink'   => shade($accent, 82, .8),
        '--mark-bg'     => rgba_of(shade($accent, 62), .26),
        '--footer-bg'   => shade($brand, 10),
        '--glow-brand'  => rgba_of(shade($brand, 45), .40),
        '--glow-accent' => rgba_of(shade($accent, 60), .22),
    ];

    $render = static function (array $vars): string {
        $out = '';
        foreach ($vars as $name => $value) {
            $out .= $name . ':' . $value . ';';
        }
        return $out;
    };

    return '<style nonce="' . e(csp_nonce()) . '">'
        . ':root{' . $render($light) . '}'
        . '@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){' . $render($dark) . '}}'
        . ':root[data-theme="dark"]{' . $render($dark) . '}'
        . '</style>';
}
