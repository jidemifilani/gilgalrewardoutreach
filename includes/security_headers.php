<?php
/**
 * Security response headers, sent on every request via db.php.
 *
 * The CSP uses a per-request nonce so inline <style>/<script> blocks still work
 * without opening the page up to 'unsafe-inline'. Templates read the nonce
 * through csp_nonce() and put it on any inline tag they emit.
 */

function csp_nonce(): string
{
    static $nonce = null;
    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(16));
    }
    return $nonce;
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    $nonce = csp_nonce();

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    // fonts.googleapis.com / fonts.gstatic.com are allowed because the type
    // pairing is loaded from Google Fonts; everything else is same-origin.
    header(
        "Content-Security-Policy: "
        . "default-src 'self'; "
        . "script-src 'self' 'nonce-$nonce'; "
        . "style-src 'self' 'nonce-$nonce' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com data:; "
        . "img-src 'self' data:; "
        . "form-action 'self'; "
        . "base-uri 'self'; "
        . "frame-ancestors 'self'"
    );
}
