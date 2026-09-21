# Sage Intacct REST API SDK for PHP

A framework-agnostic, strongly typed PHP SDK for the Sage Intacct REST API. The package follows modern Laravel/PHP conventions without requiring Laravel at runtime.

The initial release includes OAuth 2.0, token lifecycle management, a typed query API, and the first project and company-configuration resources.

## Design goals

- PHP 8.2+ with strict types, readonly DTOs, enums, and value objects.
- PSR-18 HTTP, PSR-17 factories, and PSR-20 clocks instead of framework-specific services.
- Explicit grant objects so invalid combinations are difficult to represent.
- Token values redact themselves from debug output.
- Application-owned token persistence through a small `TokenStore` contract.
- No global configuration, facades, environment reads, or hidden I/O.

## Install

```bash
composer require control-air/intacct-php-sdk
```

The SDK accepts any PSR-18 client and PSR-17 request/stream factory. If the host application does not already provide them, Guzzle is one option:

```bash
composer require guzzlehttp/guzzle
```

## OAuth client

```php
use ControlAir\Intacct\Auth\OAuth\OAuthClient;
use ControlAir\Intacct\Configuration\OAuthApplication;
use ControlAir\Intacct\Support\SystemClock;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

$http = new Client();
$factory = new HttpFactory();

$oauth = new OAuthClient(
    application: new OAuthApplication(
        clientId: $_ENV['SAGE_INTACCT_CLIENT_ID'],
        clientSecret: $_ENV['SAGE_INTACCT_CLIENT_SECRET'],
    ),
    httpClient: $http,
    requestFactory: $factory,
    streamFactory: $factory,
    clock: new SystemClock(),
);
```

### Server-to-server authentication

The client-credentials grant is typed around Sage Intacct's two supported subjects. Use either a Web Services username or an existing Sage session ID.

```php
use ControlAir\Intacct\Auth\OAuth\ClientCredentialsGrant;

$tokens = $oauth->clientCredentials(
    ClientCredentialsGrant::forUsername(
        userId: 'api-user',
        companyId: 'my-company',
        entityId: 'Central', // optional
    ),
);

$bearerToken = $tokens->accessToken->reveal();
```

### Authorization-code flow

Generate and persist a cryptographically random `state` value before redirecting the browser. Validate the returned state in the callback before exchanging the code.

```php
use ControlAir\Intacct\Auth\OAuth\AuthorizationCodeGrant;
use ControlAir\Intacct\Auth\OAuth\AuthorizationRequest;

$state = bin2hex(random_bytes(32));

$authorizationUrl = $oauth->authorizationUrl(new AuthorizationRequest(
    redirectUri: 'https://example.com/intacct/callback',
    state: $state,
    scopes: ['offline_access'],
));

// In the callback, after constant-time state validation:
$tokens = $oauth->exchange(new AuthorizationCodeGrant(
    code: $authorizationCode,
    redirectUri: 'https://example.com/intacct/callback',
));
```

Public clients can use PKCE without a client secret:

```php
use ControlAir\Intacct\Auth\OAuth\PkcePair;

$pkce = PkcePair::generate();

$authorizationUrl = $oauth->authorizationUrl(new AuthorizationRequest(
    redirectUri: 'https://example.com/intacct/callback',
    state: $state,
    scopes: ['offline_access'],
    pkce: $pkce,
));

// Persist the PKCE verifier securely with the OAuth state, then reuse the
// same pair during the code exchange.
$tokens = $oauth->exchange(new AuthorizationCodeGrant(
    code: $authorizationCode,
    redirectUri: 'https://example.com/intacct/callback',
    pkce: $pkce,
));
```

## Token lifecycle

`TokenManager` reads tokens through `TokenStore`, refreshes them before expiry, preserves a prior refresh token when Sage does not rotate it, and saves a rotated token set atomically from the caller's perspective.

```php
use ControlAir\Intacct\Auth\Tokens\TokenKey;
use ControlAir\Intacct\Auth\Tokens\TokenManager;

$manager = new TokenManager(
    oauth: $oauth,
    store: $yourEncryptedTokenStore,
    clock: new SystemClock(),
    refreshLeewaySeconds: 60,
);

$accessToken = $manager->getValidAccessToken(
    new TokenKey('customer-42'),
);
```

Implement `ControlAir\Intacct\Auth\Contracts\TokenStore` with your application's persistence layer. In Laravel, bind that interface to an encrypted database or cache implementation in a service provider. `InMemoryTokenStore` is included for tests and short-lived processes only.

Production stores should:

- encrypt access and refresh tokens at rest;
- key tokens by the application's tenant/integration identity;
- use a lock or compare-and-swap strategy to prevent concurrent refreshes;
- replace the entire token set when refresh-token rotation occurs;
- avoid logging serialized grant or token objects.

## Other lifecycle operations

```php
$introspection = $oauth->introspect($tokens->accessToken);
$revoked = $oauth->revoke($tokens->refreshToken);
```

Introspection uses bearer authentication by default. To request Sage's additional Intacct-specific fields with client credentials, pass `IntrospectionAuthentication::ClientCredentials` as the second argument. Sage only permits that mode for tokens and applications authorized for client-authenticated introspection.

Token revocation can invalidate other tokens for the same Sage Intacct user/company combination. Treat it as an explicit disconnect operation, not ordinary cleanup.

## REST client

Create one `IntacctClient` with a token provider and any PSR-18/PSR-17 implementation. A managed provider obtains a valid token from `TokenManager` before every request and refreshes it when necessary.

```php
use ControlAir\Intacct\Auth\Tokens\ManagedAccessTokenProvider;
use ControlAir\Intacct\Auth\Tokens\TokenKey;
use ControlAir\Intacct\Configuration\ApiConfiguration;
use ControlAir\Intacct\IntacctClient;

$intacct = new IntacctClient(
    tokens: new ManagedAccessTokenProvider(
        manager: $manager,
        key: new TokenKey('customer-42'),
    ),
    httpClient: $http,
    requestFactory: $factory,
    streamFactory: $factory,
    configuration: new ApiConfiguration(entityId: 'Central'),
);
```

The client currently exposes:

- `$intacct->projects`, `$intacct->tasks`, and `$intacct->projectResources`;
- `$intacct->costTypes` for construction cost types;
- `$intacct->dimensions` for the company dimension catalog;
- `$intacct->employees`, `$intacct->classes`, `$intacct->departments`, and `$intacct->locations`;
- read-only `$intacct->entities` and `$intacct->users` clients;
- `$intacct->queries` for advanced typed queries.

### Read and query projects

```php
use ControlAir\Intacct\Core\Query\Filter;
use ControlAir\Intacct\Core\Query\OrderBy;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\ValueObjects\ObjectKey;

$project = $intacct->projects->get(new ObjectKey('123'));

$page = $intacct->projects->query(new ResourceQuery(
    filters: [Filter::equal('status', 'active')],
    orderBy: [new OrderBy('name')],
    size: 100,
));
```

Resource queries use Sage's `/services/core/query` endpoint rather than the basic list endpoint. Pagination starts at `1`, and the SDK enforces Sage's maximum page size of `4000`.

### Create, update, and delete projects

```php
use ControlAir\Intacct\Resources\Projects\CreateProject;
use ControlAir\Intacct\Resources\Projects\UpdateProject;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\RecordStatus;

$created = $intacct->projects->create(new CreateProject(
    id: new ObjectId('PROJ-001'),
    name: 'Headquarters Renovation',
    status: RecordStatus::Active,
    startDate: new LocalDate('2026-01-15'),
));

$intacct->projects->update(
    new ObjectKey('123'),
    UpdateProject::name('HQ Renovation')->withDescription(null),
);

$intacct->projects->delete(new ObjectKey('123'));
```

Update objects preserve the distinction between an omitted property and an explicitly supplied `null`, which is important for PATCH requests.

## Project structure

```text
src/
├── Auth/
│   ├── Contracts/ Token-provider and token-store extension points
│   ├── OAuth/     OAuth grants and protocol client
│   └── Tokens/    Token values, storage, and lifecycle orchestration
├── Core/          HTTP transport, queries, resource gateway, and responses
├── Resources/     Typed Sage REST resources grouped by Sage domain
├── ValueObjects/  Keys, IDs, references, dates, decimals, and safe secrets
├── Configuration/
├── Exceptions/
└── Support/
```

See [the architecture notes](docs/architecture.md) and [resource module guide](docs/modules.md).

## Development

```bash
composer check
composer format
```

Never commit Sage credentials or real tokens. Tests use synthetic values and queued PSR responses only.

### Live credential test

The default test suite never makes network calls. To verify the real Sage Intacct client-credentials flow, copy the example environment file and fill in a dedicated, least-privileged Web Services test user:

```bash
cp .env.example .env
composer test:integration
```

Required values:

```dotenv
SAGE_INTACCT_CLIENT_ID=your-app-client-id
SAGE_INTACCT_CLIENT_SECRET=your-app-client-secret
SAGE_INTACCT_USER_ID=your-web-services-user-id
SAGE_INTACCT_COMPANY_ID=your-company-id
SAGE_INTACCT_ENTITY_ID=optional-entity-id
```

The live test requests a token and introspects it. It does not call business-data endpoints, mutate Sage data, print credentials, or revoke the token. If any required value is absent, PHPUnit marks the integration test as skipped. The `.env` file is ignored by Git.
