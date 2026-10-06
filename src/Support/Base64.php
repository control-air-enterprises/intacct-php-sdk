<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Support;

/**
 * Strict base64 decoding that accepts both the standard and the URL-safe
 * alphabet, with or without padding.
 *
 * @internal
 */
final class Base64
{
    public static function decode(string $value): ?string
    {
        $value = strtr(trim($value), '-_', '+/');
        $remainder = strlen($value) % 4;

        if ($remainder === 1) {
            return null;
        }

        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($value, true);

        return $decoded === false ? null : $decoded;
    }

    public static function encodeUrl(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function __construct() {}
}
