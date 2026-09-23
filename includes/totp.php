<?php
/**
 * Time-based one-time passwords (RFC 6238) for admin two-factor auth.
 *
 * Written out rather than pulled from a library so the project stays
 * dependency-free. Compatible with Google Authenticator, Authy, 1Password,
 * Microsoft Authenticator and anything else that speaks otpauth://.
 */

/** A fresh base32 secret. 160 bits, the length RFC 4226 recommends. */
function totp_secret(int $length = 32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret   = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[random_int(0, 31)];
    }
    return $secret;
}

function base32_decode(string $secret): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret   = strtoupper(str_replace('=', '', $secret));

    $bits = '';
    for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
        $index = strpos($alphabet, $secret[$i]);
        if ($index === false) {
            continue;                       // ignore spaces and stray characters
        }
        $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
    }

    $binary = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $binary .= chr(bindec($byte));
        }
    }
    return $binary;
}

/** The 6-digit code for a given moment. */
function totp_code(string $secret, ?int $timestamp = null, int $period = 30, int $digits = 6): string
{
    $timestamp = $timestamp ?? time();
    $counter   = (int) floor($timestamp / $period);

    // 8-byte big-endian counter
    $binaryCounter = pack('N*', 0, $counter);
    $hash          = hash_hmac('sha1', $binaryCounter, base32_decode($secret), true);

    // Dynamic truncation, RFC 4226 section 5.3
    $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
    $value  = ((ord($hash[$offset])     & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            |  (ord($hash[$offset + 3]) & 0xFF);

    return str_pad((string) ($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}

/**
 * Checks a submitted code, allowing one step either side so a phone whose
 * clock is slightly off still works.
 */
function totp_verify(string $secret, string $code, int $window = 1, int $period = 30): bool
{
    $code = preg_replace('/\D+/', '', $code);
    if ($code === '' || strlen((string) $code) !== 6) {
        return false;
    }

    $now = time();
    for ($offset = -$window; $offset <= $window; $offset++) {
        if (hash_equals(totp_code($secret, $now + ($offset * $period), $period), (string) $code)) {
            return true;
        }
    }
    return false;
}

/** The otpauth:// URI an authenticator app scans. */
function totp_uri(string $secret, string $account, string $issuer): string
{
    return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
        . '?secret=' . $secret
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=6&period=30';
}

/*
 * Deliberately no QR code.
 *
 * A hand-rolled QR encoder is a few hundred lines of bit packing, Reed-Solomon
 * and masking that cannot be verified without a decoder to check it against --
 * and a QR that silently fails to scan is worse than no QR at all. Every
 * authenticator app supports entering a setup key by hand ("Enter a setup key"
 * / "Manual entry"), so admin/two-factor.php shows the secret and the
 * otpauth:// URI instead. Both are reliable and both are testable.
 */
