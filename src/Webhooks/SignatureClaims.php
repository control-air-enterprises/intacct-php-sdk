<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Webhooks;

use ControlAir\Intacct\Exceptions\WebhookVerificationException;
use ControlAir\Intacct\Support\ArrayReader;
use DateTimeImmutable;

/** The verified claims of a webhook's signature JWT. */
final readonly class SignatureClaims
{
    public const ISSUER = 'Sage Intacct';

    /**
     * Sage documents `iat` in epoch milliseconds; anything above this is treated as
     * milliseconds, anything below as seconds (the cut-over is the year 5138).
     */
    private const MILLISECONDS_THRESHOLD = 100_000_000_000;

    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $issuer,
        public DateTimeImmutable $expiresAt,
        public string $payloadSignature,
        public ?DateTimeImmutable $issuedAt = null,
        public ?string $tenantId = null,
        public ?string $appId = null,
        public array $raw = [],
    ) {}

    /** @param array<string, mixed> $claims */
    public static function fromArray(array $claims): self
    {
        $issuer = ArrayReader::string($claims, 'iss');

        if ($issuer !== self::ISSUER) {
            throw new WebhookVerificationException(VerificationFailure::InvalidIssuer);
        }

        $expiresAt = self::timestamp($claims['exp'] ?? null)
            ?? throw new WebhookVerificationException(
                VerificationFailure::MalformedSignature,
                'The webhook signature has no valid "exp" claim.',
            );

        $payloadSignature = ArrayReader::string($claims, 'payload_signature')
            ?? throw new WebhookVerificationException(VerificationFailure::MissingPayloadSignature);

        $algorithm = ArrayReader::string($claims, 'payload_signature_alg');

        if ($algorithm !== null && strtoupper(str_replace('-', '', $algorithm)) !== 'SHA256') {
            throw new WebhookVerificationException(
                VerificationFailure::UnsupportedAlgorithm,
                sprintf('The webhook payload hash uses an unsupported algorithm "%s".', $algorithm),
            );
        }

        return new self(
            issuer: $issuer,
            expiresAt: $expiresAt,
            payloadSignature: strtolower($payloadSignature),
            issuedAt: self::timestamp($claims['iat'] ?? null),
            tenantId: ArrayReader::string($claims, 'tenant_id'),
            appId: ArrayReader::string($claims, 'app_id'),
            raw: $claims,
        );
    }

    private static function timestamp(mixed $value): ?DateTimeImmutable
    {
        if (is_string($value) && preg_match('/^\d+(\.\d+)?$/', $value) === 1) {
            $value = (float) $value;
        }

        if (! is_int($value) && ! is_float($value)) {
            return null;
        }

        $seconds = $value > self::MILLISECONDS_THRESHOLD ? $value / 1000 : $value;
        $date = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $seconds));

        return $date === false ? null : $date;
    }
}
