<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Webhooks;

use ControlAir\Intacct\Exceptions\WebhookVerificationException;
use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\Support\Base64;

/**
 * Minimal HS256 JWT verification, so the SDK needs no JWT dependency.
 *
 * Only HS256 is accepted: the algorithm is pinned rather than read from the token,
 * which rules out `alg: none` and algorithm-confusion attacks.
 *
 * @internal
 */
final class Hs256Jwt
{
    /**
     * Verifies the token's signature and returns its claims. Claims are not validated here.
     *
     * @return array<string, mixed>
     */
    public static function verify(string $token, #[\SensitiveParameter] string $secret): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new WebhookVerificationException(VerificationFailure::MalformedSignature);
        }

        [$encodedHeader, $encodedClaims, $encodedSignature] = $parts;

        $header = self::object($encodedHeader);

        if (($header['alg'] ?? null) !== 'HS256') {
            throw new WebhookVerificationException(VerificationFailure::UnsupportedAlgorithm);
        }

        $signature = Base64::decode($encodedSignature);
        $expected = hash_hmac('sha256', $encodedHeader.'.'.$encodedClaims, $secret, true);

        if ($signature === null || ! hash_equals($expected, $signature)) {
            throw new WebhookVerificationException(VerificationFailure::InvalidSignature);
        }

        return self::object($encodedClaims);
    }

    public static function looksLikeToken(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]*$/', $value) === 1;
    }

    /** @return array<string, mixed> */
    private static function object(string $segment): array
    {
        $json = Base64::decode($segment);
        $value = $json === null ? null : json_decode($json, true);

        return ArrayReader::object($value)
            ?? throw new WebhookVerificationException(VerificationFailure::MalformedSignature);
    }

    private function __construct() {}
}
