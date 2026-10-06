<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Webhooks;

use ControlAir\Intacct\Configuration\OAuthApplication;
use ControlAir\Intacct\Exceptions\ConfigurationException;
use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Exceptions\WebhookVerificationException;
use ControlAir\Intacct\Resources\CompanyConfiguration\Classes\ClassDimension;
use ControlAir\Intacct\Support\Base64;
use ControlAir\Intacct\Tests\Support\FrozenClock;
use ControlAir\Intacct\ValueObjects\SensitiveString;
use ControlAir\Intacct\Webhooks\ClientContext;
use ControlAir\Intacct\Webhooks\Hs256Jwt;
use ControlAir\Intacct\Webhooks\SignatureClaims;
use ControlAir\Intacct\Webhooks\TriggerEvent;
use ControlAir\Intacct\Webhooks\VerificationFailure;
use ControlAir\Intacct\Webhooks\WebhookEvent;
use ControlAir\Intacct\Webhooks\WebhookPayload;
use ControlAir\Intacct\Webhooks\WebhookVerifier;
use DateTimeImmutable;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookVerifier::class)]
#[CoversClass(WebhookEvent::class)]
#[CoversClass(SignatureClaims::class)]
#[CoversClass(Hs256Jwt::class)]
#[CoversClass(WebhookPayload::class)]
#[CoversClass(WebhookVerificationException::class)]
#[CoversClass(VerificationFailure::class)]
#[CoversClass(Base64::class)]
final class WebhookVerifierTest extends TestCase
{
    private const SECRET = 'client-secret';

    /** 2026-01-16T21:28:00Z, in the epoch milliseconds Sage documents for `iat`. */
    private const ISSUED_AT_MS = 1_768_598_880_000;

    /** Verbatim X-ClientContext example from Sage's outbound webhook guide. */
    private const CONTEXT = 'eyJDT01QQU5ZIjoxOTkwNTYsIkVOVElUWSI6ZmFsc2UsIlVTRVIiOiI5IiwiT0JKRUNUTkFNRSI6IkNMQVNTIiwiUkVTVE9CSkVDVE5BTUUiOiJjb21wYW55LWNvbmZpZ1wvY2xhc3MiLCJET0NUWVBFIjoiIiwiSUQiOnt9LCJWSUQiOiI1MDAiLCJFVkVOVCI6ImFmdGVyX2NyZWF0ZSIsIkVWRU5UTkFNRSI6IkFmdGVyIGNyZWF0ZSBvciB1cGRhdGUgY2xhc3Mgbm90aWZpY2F0aW9uIiwiVElNRVNUQU1QIjoiMDFcLzE2XC8yMDI2IDIxOjI4OjAwIn0=';

    /** Verbatim X-IA-Sage-Signature example from the same guide; its jwtData is null. */
    private const SAMPLE_SIGNATURE = 'eyJjbGllbnRJZCI6ImQ0ZjJiNmIzMTgxNzRiOWE2MGE3LklOVEFDQ1QuYXBwLnNhZ2UuY29tIiwiZGJJZCI6NjE0MDQsImRiVVJJIjoiZGV2Nzc6OTA4MCIsImNvbm5JZCI6NzM5NDUsInVzZXJJZCI6IkFkbWluIiwidXNlcktleSI6MSwiY29tcGFueUlkIjo0NTIxMTk2OSwibG9jYXRpb25LZXkiOjAsIm1vbmdvU2hhcmQiOiJkZXZfcnMxIiwic2Vzc2lvbktleSI6IlN6a3dwVDl0bS11SVhobWl2a19fM29KSF80bGVHVXM1TUtVOTNGT0xpRjRab3I1UHZ0X3hkUHVJIiwidHJ4TGV2ZWwiOjAsImp3dERhdGEiOm51bGx9';

    private const BODY = '{"key":"500","id":"CL-500","name":"Field Services","status":"active","nsp::REGION":"West"}';

    public function test_it_verifies_a_signed_delivery_and_exposes_its_parts(): void
    {
        $event = $this->verifier()->verifyRaw(self::BODY, [
            'X-IA-Sage-Signature' => $this->token(),
            'X-ClientContext' => self::CONTEXT,
            'Idempotency-Key' => 'b61f5df7-cf8d-4e2c-99a1-bb38cddff413',
            'Content-Type' => 'application/json',
        ]);

        self::assertSame('b61f5df7-cf8d-4e2c-99a1-bb38cddff413', $event->idempotencyKey);
        self::assertSame('Field Services', $event->payload->json()['name']);
        self::assertSame('application/json', $event->payload->contentType);
        self::assertSame('Sage Intacct', $event->claims->issuer);
        self::assertSame('tenant-1', $event->claims->tenantId);
        self::assertSame('app-1', $event->claims->appId);
        self::assertSame('2026-01-16 21:28:00', $event->claims->issuedAt?->format('Y-m-d H:i:s'));
        self::assertSame('2026-01-16 22:28:00', $event->claims->expiresAt->format('Y-m-d H:i:s'));
        self::assertNotNull($event->context);
        self::assertSame('company-config/class', $event->context->restObjectName());
        self::assertSame(TriggerEvent::AfterCreate, $event->context->eventType());

        $class = $event->payload->map(ClassDimension::fromArray(...));

        self::assertSame('CL-500', $class->id->value);
        self::assertSame('West', $class->customFields->get('REGION'));
    }

    public function test_it_reads_the_jwt_from_a_base64_context_envelope(): void
    {
        $envelope = base64_encode(json_encode([
            'clientId' => 'client.INTACCT.app.sage.com',
            'sessionKey' => 'must-not-be-exposed',
            'jwtData' => $this->token(),
        ], JSON_THROW_ON_ERROR));

        $event = $this->verifier()->verifyRaw(self::BODY, ['x-ia-sage-signature' => [$envelope]]);

        self::assertSame('Field Services', $event->payload->json()['name']);
        self::assertStringNotContainsString('must-not-be-exposed', print_r($event, true));
    }

    public function test_sages_sample_envelope_without_a_jwt_is_rejected(): void
    {
        $this->assertFailure(VerificationFailure::MissingSignature, ['X-IA-Sage-Signature' => self::SAMPLE_SIGNATURE]);
    }

    public function test_it_verifies_a_psr7_request_and_leaves_the_body_readable(): void
    {
        $request = new ServerRequest('POST', 'https://example.test/webhooks/intacct', [
            'X-IA-Sage-Signature' => $this->token(),
            'X-ClientContext' => self::CONTEXT,
        ], self::BODY);
        $request->getBody()->getContents();

        $event = $this->verifier()->verify($request);

        self::assertSame(self::BODY, $event->payload->raw);
        self::assertSame(self::BODY, $request->getBody()->getContents());
    }

    public function test_it_accepts_expiry_in_seconds(): void
    {
        $token = $this->token(['iat' => intdiv(self::ISSUED_AT_MS, 1000), 'exp' => intdiv(self::ISSUED_AT_MS, 1000) + 3600]);

        $event = $this->verifier()->verifyRaw(self::BODY, ['X-IA-Sage-Signature' => $token]);

        self::assertSame('2026-01-16 22:28:00', $event->claims->expiresAt->format('Y-m-d H:i:s'));
        self::assertNull($event->context);
        self::assertNull($event->idempotencyKey);
    }

    public function test_it_tolerates_clock_skew_within_the_leeway(): void
    {
        $verifier = new WebhookVerifier(
            new SensitiveString(self::SECRET),
            $this->clock('2026-01-16 22:28:30'),
            leewaySeconds: 60,
        );

        $event = $verifier->verifyRaw(self::BODY, ['X-IA-Sage-Signature' => $this->token()]);

        self::assertTrue($event->payload->isJson());
    }

    public function test_it_builds_a_verifier_from_the_oauth_application(): void
    {
        $verifier = WebhookVerifier::forApplication(
            new OAuthApplication('client-id', self::SECRET),
            $this->clock('2026-01-16 21:30:00'),
        );

        self::assertTrue($verifier->verifyRaw(self::BODY, ['X-IA-Sage-Signature' => $this->token()])->payload->isJson());
    }

    public function test_an_application_without_a_secret_cannot_verify_webhooks(): void
    {
        $this->expectException(ConfigurationException::class);

        WebhookVerifier::forApplication(new OAuthApplication('client-id'));
    }

    public function test_a_negative_leeway_is_rejected(): void
    {
        $this->expectException(InvalidArgument::class);

        new WebhookVerifier(new SensitiveString(self::SECRET), leewaySeconds: -1);
    }

    /** @return iterable<string, array{VerificationFailure, string|null, string}> */
    public static function invalidDeliveries(): iterable
    {
        yield 'missing header' => [VerificationFailure::MissingSignature, null, self::BODY];
        yield 'blank header' => [VerificationFailure::MissingSignature, '  ', self::BODY];
        yield 'garbage header' => [VerificationFailure::MalformedSignature, 'not a jwt!', self::BODY];
        yield 'three garbage segments' => [VerificationFailure::MalformedSignature, 'a.b.c', self::BODY];
        yield 'tampered body' => [VerificationFailure::PayloadMismatch, 'valid', '{"key":"500","name":"Changed"}'];
    }

    #[DataProvider('invalidDeliveries')]
    public function test_it_rejects_invalid_deliveries(VerificationFailure $reason, ?string $signature, string $body): void
    {
        $headers = match ($signature) {
            null => [],
            'valid' => ['X-IA-Sage-Signature' => $this->token()],
            default => ['X-IA-Sage-Signature' => $signature],
        };

        $this->assertFailure($reason, $headers, $body);
    }

    public function test_it_rejects_a_token_signed_with_another_secret(): void
    {
        $this->assertFailure(VerificationFailure::InvalidSignature, ['X-IA-Sage-Signature' => $this->token(secret: 'other-secret')]);
    }

    public function test_it_rejects_a_token_with_a_corrupted_signature(): void
    {
        $this->assertFailure(VerificationFailure::InvalidSignature, ['X-IA-Sage-Signature' => $this->token().'x']);
    }

    /** @return iterable<string, array{string}> */
    public static function unsupportedAlgorithms(): iterable
    {
        yield 'none' => ['none'];
        yield 'HS512' => ['HS512'];
        yield 'RS256' => ['RS256'];
    }

    #[DataProvider('unsupportedAlgorithms')]
    public function test_it_only_accepts_hs256(string $algorithm): void
    {
        $this->assertFailure(VerificationFailure::UnsupportedAlgorithm, ['X-IA-Sage-Signature' => $this->token(algorithm: $algorithm)]);
    }

    public function test_it_rejects_an_unsupported_payload_hash_algorithm(): void
    {
        $this->assertFailure(VerificationFailure::UnsupportedAlgorithm, ['X-IA-Sage-Signature' => $this->token(['payload_signature_alg' => 'MD5'])]);
    }

    public function test_it_rejects_another_issuer(): void
    {
        $this->assertFailure(VerificationFailure::InvalidIssuer, ['X-IA-Sage-Signature' => $this->token(['iss' => 'Someone Else'])]);
    }

    public function test_it_rejects_an_expired_token(): void
    {
        $verifier = new WebhookVerifier(new SensitiveString(self::SECRET), $this->clock('2026-01-16 22:29:01'));

        $this->assertFailure(VerificationFailure::Expired, ['X-IA-Sage-Signature' => $this->token()], verifier: $verifier);
    }

    public function test_it_rejects_a_token_issued_in_the_future(): void
    {
        $verifier = new WebhookVerifier(new SensitiveString(self::SECRET), $this->clock('2026-01-16 21:26:00'));

        $this->assertFailure(VerificationFailure::IssuedInFuture, ['X-IA-Sage-Signature' => $this->token()], verifier: $verifier);
    }

    public function test_it_rejects_a_token_without_expiry(): void
    {
        $this->assertFailure(VerificationFailure::MalformedSignature, ['X-IA-Sage-Signature' => $this->token(['exp' => null])]);
    }

    public function test_it_rejects_a_token_without_a_payload_hash(): void
    {
        $this->assertFailure(VerificationFailure::MissingPayloadSignature, ['X-IA-Sage-Signature' => $this->token(['payload_signature' => null])]);
    }

    public function test_a_non_json_payload_is_kept_raw(): void
    {
        $body = '<class><id>CL-500</id></class>';
        $event = $this->verifier()->verifyRaw($body, ['X-IA-Sage-Signature' => $this->token(['payload_signature' => hash('sha256', $body)])]);

        self::assertFalse($event->payload->isJson());
        self::assertSame($body, $event->payload->raw);

        $this->expectException(MappingException::class);
        $event->payload->json();
    }

    /** @param array<string, string|list<string>> $headers */
    private function assertFailure(
        VerificationFailure $reason,
        array $headers,
        string $body = self::BODY,
        ?WebhookVerifier $verifier = null,
    ): void {
        try {
            ($verifier ?? $this->verifier())->verifyRaw($body, $headers);
            self::fail('The webhook was accepted.');
        } catch (WebhookVerificationException $exception) {
            self::assertSame($reason, $exception->reason);
            self::assertStringNotContainsString(self::SECRET, $exception->getMessage());
        }
    }

    private function verifier(): WebhookVerifier
    {
        return new WebhookVerifier(new SensitiveString(self::SECRET), $this->clock('2026-01-16 21:30:00'));
    }

    private function clock(string $now): FrozenClock
    {
        return new FrozenClock(new DateTimeImmutable($now, new \DateTimeZone('UTC')));
    }

    /** @param array<string, mixed> $claims null removes a claim */
    private function token(array $claims = [], string $secret = self::SECRET, string $algorithm = 'HS256'): string
    {
        $claims = array_filter($claims + [
            'iat' => self::ISSUED_AT_MS,
            'exp' => self::ISSUED_AT_MS + 3_600_000,
            'iss' => 'Sage Intacct',
            'tenant_id' => 'tenant-1',
            'app_id' => 'app-1',
            'payload_signature' => hash('sha256', self::BODY),
            'payload_signature_alg' => 'SHA-256',
        ], static fn (mixed $value): bool => $value !== null);

        $signingInput = Base64::encodeUrl(json_encode(['alg' => $algorithm, 'typ' => 'JWT'], JSON_THROW_ON_ERROR))
            .'.'.Base64::encodeUrl(json_encode($claims, JSON_THROW_ON_ERROR));

        return $signingInput.'.'.Base64::encodeUrl(hash_hmac('sha256', $signingInput, $secret, true));
    }
}
