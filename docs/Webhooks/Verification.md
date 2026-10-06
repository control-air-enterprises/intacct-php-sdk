# Verification

[Docs](../README.md) › [Webhooks](README.md) › Verification

Namespace: `ControlAir\Intacct\Webhooks`

`WebhookVerifier` proves that a request came from Sage Intacct and that its body was not changed, then returns a typed `WebhookEvent`. Every delivery must pass through it before your application trusts any of its contents.

## Create a verifier

The verifier needs the client secret of the OAuth application whose client ID the trigger was configured with.

```php
use ControlAir\Intacct\Webhooks\WebhookVerifier;
use ControlAir\Intacct\ValueObjects\SensitiveString;

// From the same OAuthApplication used for API calls:
$verifier = WebhookVerifier::forApplication($application);

// Or directly from the secret:
$verifier = new WebhookVerifier(new SensitiveString($_ENV['SAGE_INTACCT_CLIENT_SECRET']));
```

| Argument | Default | Purpose |
| --- | --- | --- |
| `clientSecret` | — | The HS256 key. Wrapped in `SensitiveString` so it is redacted from debug output. |
| `clock` | `SystemClock` | Any PSR-20 clock; pass a frozen clock in tests. |
| `leewaySeconds` | `60` | Clock-skew allowance when checking `exp` and `iat`. |

`forApplication()` throws `ConfigurationException` when the application has no client secret.

## Verify a request

From any PSR-7 server request:

```php
$event = $verifier->verify($request);
```

The body stream is rewound before and after reading, so your code can still read it.

From a raw body and headers, for frameworks that do not use PSR-7:

```php
// Laravel
$event = $verifier->verifyRaw($request->getContent(), $request->headers->all());

// Plain PHP
$event = $verifier->verifyRaw(file_get_contents('php://input'), getallheaders());
```

Header names are matched case-insensitively, and each value may be a string or a list of strings.

> **Use the raw body.** The signature covers the exact bytes Sage sent. A body that was parsed and re-encoded (for example `json_encode($request->all())`) has different whitespace and escaping and fails with `payload_mismatch`.

## What is checked

Verification follows Sage's two-step check and stops at the first failure:

1. **Find the JWT.** `X-IA-Sage-Signature` may hold the JWT itself, or a base64 JSON envelope with the JWT in `jwtData`. Sage's documentation shows both forms. Nothing else in the envelope is read or kept.
2. **Check the signature.** The JWT must be HS256 and signed with your client secret. The algorithm is fixed by the SDK, not read from the token, so `alg: none` and algorithm-substitution attacks are rejected. The comparison is constant-time.
3. **Check the claims.** `iss` must be `Sage Intacct`, `exp` must not have passed, and `iat`, if present, must not be in the future, both within the leeway. Sage documents `iat` in epoch milliseconds; the SDK accepts seconds or milliseconds.
4. **Check the body.** The SHA-256 hex hash of the raw body must equal the `payload_signature` claim. `payload_signature_alg`, when present, must be SHA-256.

## Failures

Every failure throws `WebhookVerificationException`, which implements the package-wide `IntacctException`. Its `reason` is a `VerificationFailure` enum that is safe to log and never contains request data.

| `reason` | Meaning |
| --- | --- |
| `missing_signature` | No signature header, or the envelope's `jwtData` is empty |
| `malformed_signature` | The header or JWT cannot be decoded, or `exp` is missing |
| `unsupported_algorithm` | The JWT is not HS256, or the body hash is not SHA-256 |
| `invalid_signature` | The JWT was not signed with your client secret |
| `invalid_issuer` | `iss` is not `Sage Intacct` |
| `expired` | `exp` has passed |
| `issued_in_future` | `iat` is ahead of your clock |
| `missing_payload_signature` | The JWT has no `payload_signature` claim |
| `payload_mismatch` | The body differs from the one Sage signed |

Respond with `401` to a failed verification. Sage only retries 408, 429, and 5xx responses, so a forged request is not retried.

## The verified event

```php
$event->payload;        // WebhookPayload
$event->context;        // ?ClientContext, null when the header is absent or undecodable
$event->claims;         // SignatureClaims
$event->idempotencyKey; // ?string, the Idempotency-Key header
```

### Payload

The body's shape is whatever the trigger's document template produces.

```php
$event->payload->raw;          // the body exactly as received
$event->payload->contentType;  // the Content-Type header, if any
$event->payload->isJson();     // true when the body is a JSON object
$event->payload->json();       // array<string, mixed>; throws MappingException otherwise

// Map it with any resource DTO when the template uses REST field names:
$vendor = $event->payload->map(Vendor::fromArray(...));
```

### Client context

`ClientContext` reads the base64 `X-ClientContext` header. Keys are matched case-insensitively because Sage uses `OBJECTNAME` in webhooks and `object` in queued events.

| Method | Example | Source key |
| --- | --- | --- |
| `companyId()` | `199056` | `COMPANY` |
| `entityId()` | `Central`, or null at the top level | `ENTITY` |
| `userKey()` | `9` | `USER` |
| `objectName()` | `CLASS` | `OBJECTNAME` or `object` |
| `restObjectName()` | `company-config/class` | `RESTOBJECTNAME` |
| `documentType()` | `Purchase Order` | `DOCTYPE` |
| `recordKey()` / `recordId()` | `12345` / `B1234` | `KEY` / `ID` |
| `event()` | `after_create` | `EVENT` |
| `eventType()` | `TriggerEvent::AfterCreate` | `EVENT`, normalized |
| `eventName()` | `After create or update class notification` | `EVENTNAME` |
| `timestamp()` | `01/16/2026 21:28:00` | `TIMESTAMP` |

`get('VID')` reads any other key, and `raw` holds the decoded array. `timestamp()` is returned as sent because Sage does not document its time zone. `eventType()` accepts `after_create` and `after.create` and returns null for events the SDK does not know, so check `event()` for those.

The context is not signed. Use it for routing only.

### Signature claims

`SignatureClaims` exposes `issuer`, `issuedAt`, `expiresAt` (as `DateTimeImmutable`), `tenantId`, `appId`, `payloadSignature`, and the full `raw` claim set.

## Testing your handler

Sign test deliveries with the same secret you give the verifier, and pass a fixed PSR-20 clock so expiry checks are deterministic:

```php
$base64url = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

$body = '{"key":"500","id":"CL-500","name":"Field Services"}';
$issuedAtMs = 1_768_598_880_000;
$claims = [
    'iss' => 'Sage Intacct',
    'iat' => $issuedAtMs,
    'exp' => $issuedAtMs + 3_600_000,
    'payload_signature' => hash('sha256', $body),
];

$input = $base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])).'.'.$base64url(json_encode($claims));
$jwt = $input.'.'.$base64url(hash_hmac('sha256', $input, 'test-secret', true));

$verifier = new WebhookVerifier(new SensitiveString('test-secret'), $clockAtIssueTime);
$event = $verifier->verifyRaw($body, ['X-IA-Sage-Signature' => $jwt]);
```

The SDK's own suite, `tests/Webhooks/WebhookVerifierTest.php`, covers every failure reason and can serve as a reference.

## Known gaps in Sage's documentation

- Sage's sample `X-IA-Sage-Signature` value is an envelope whose `jwtData` is `null`, so it cannot be verified as published. The verifier accepts both the bare-JWT and envelope forms. Capture one real delivery from a sandbox company and add it as a test fixture to confirm which form your tenant sends.
- Whether `exp` is in seconds or milliseconds is not stated; both are accepted.
