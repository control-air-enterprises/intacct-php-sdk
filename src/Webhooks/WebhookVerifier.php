<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Webhooks;

use ControlAir\Intacct\Configuration\OAuthApplication;
use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Exceptions\WebhookVerificationException;
use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\Support\Base64;
use ControlAir\Intacct\Support\SystemClock;
use ControlAir\Intacct\ValueObjects\SensitiveString;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Verifies that a webhook request came from Sage Intacct and that its body was not
 * changed in transit, following Sage's two-step check:
 *
 * 1. the `X-IA-Sage-Signature` JWT is HS256-signed with the application's client
 *    secret, was issued by "Sage Intacct", and has not expired;
 * 2. the SHA-256 hash of the raw body equals the JWT's `payload_signature` claim.
 *
 * Sage describes the signature header both as a JWT and as base64 context information
 * that includes the JWT; both forms are accepted. Context fields other than the JWT,
 * such as the session key Sage includes, are never read or retained.
 */
final readonly class WebhookVerifier
{
    public const SIGNATURE_HEADER = 'X-IA-Sage-Signature';

    public const CONTEXT_HEADER = 'X-ClientContext';

    public const IDEMPOTENCY_HEADER = 'Idempotency-Key';

    /** Envelope fields that may carry the JWT, in order of preference. */
    private const TOKEN_FIELDS = ['jwtData', 'jwt', 'token'];

    public function __construct(
        private SensitiveString $clientSecret,
        private ClockInterface $clock = new SystemClock,
        private int $leewaySeconds = 60,
    ) {
        if ($this->leewaySeconds < 0) {
            throw new InvalidArgument('The webhook clock leeway cannot be negative.');
        }
    }

    /** Uses the client secret of the OAuth application that the triggers were configured with. */
    public static function forApplication(
        OAuthApplication $application,
        ClockInterface $clock = new SystemClock,
        int $leewaySeconds = 60,
    ): self {
        return new self(new SensitiveString($application->requireClientSecret()), $clock, $leewaySeconds);
    }

    /**
     * Verifies a PSR-7 server request. The body stream is rewound before and after reading
     * when it is seekable, so the application can still read it afterwards.
     */
    public function verify(ServerRequestInterface $request): WebhookEvent
    {
        $stream = $request->getBody();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $body = $stream->getContents();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return $this->verifyRaw($body, $request->getHeaders());
    }

    /**
     * Verifies a raw body and its headers. Pass the body exactly as received: a body that
     * was decoded and re-encoded will not match the signed hash.
     *
     * @param  array<array-key, string|array<string>>  $headers  header names are case-insensitive
     */
    public function verifyRaw(string $body, array $headers): WebhookEvent
    {
        $signature = self::header($headers, self::SIGNATURE_HEADER)
            ?? throw new WebhookVerificationException(VerificationFailure::MissingSignature);

        $claims = SignatureClaims::fromArray(
            Hs256Jwt::verify($this->token($signature), $this->clientSecret->reveal()),
        );

        $this->assertCurrent($claims);

        if (! hash_equals($claims->payloadSignature, hash('sha256', $body))) {
            throw new WebhookVerificationException(VerificationFailure::PayloadMismatch);
        }

        return new WebhookEvent(
            payload: new WebhookPayload($body, self::header($headers, 'Content-Type')),
            claims: $claims,
            context: ClientContext::tryFromEncoded(self::header($headers, self::CONTEXT_HEADER)),
            idempotencyKey: self::header($headers, self::IDEMPOTENCY_HEADER),
        );
    }

    private function token(string $signature): string
    {
        $signature = trim($signature);

        if (stripos($signature, 'Bearer ') === 0) {
            $signature = trim(substr($signature, 7));
        }

        if (Hs256Jwt::looksLikeToken($signature)) {
            return $signature;
        }

        $json = Base64::decode($signature);
        $envelope = $json === null ? null : ArrayReader::object(json_decode($json, true));

        if ($envelope === null) {
            throw new WebhookVerificationException(VerificationFailure::MalformedSignature);
        }

        foreach (self::TOKEN_FIELDS as $field) {
            $token = $envelope[$field] ?? null;

            if (is_string($token) && Hs256Jwt::looksLikeToken(trim($token))) {
                return trim($token);
            }
        }

        throw new WebhookVerificationException(
            VerificationFailure::MissingSignature,
            'The webhook signature header does not contain a JWT.',
        );
    }

    private function assertCurrent(SignatureClaims $claims): void
    {
        $now = $this->clock->now()->getTimestamp();

        if ($claims->expiresAt->getTimestamp() + $this->leewaySeconds < $now) {
            throw new WebhookVerificationException(VerificationFailure::Expired);
        }

        if ($claims->issuedAt !== null && $claims->issuedAt->getTimestamp() - $this->leewaySeconds > $now) {
            throw new WebhookVerificationException(VerificationFailure::IssuedInFuture);
        }
    }

    /** @param array<array-key, string|array<string>> $headers */
    private static function header(array $headers, string $name): ?string
    {
        foreach ($headers as $header => $value) {
            if (strcasecmp((string) $header, $name) !== 0) {
                continue;
            }

            $value = is_array($value) ? (string) reset($value) : $value;
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return null;
    }
}
