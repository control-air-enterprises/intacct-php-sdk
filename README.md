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
- `$intacct->employees`, `$intacct->classes`, `$intacct->departments`, `$intacct->locations`, and `$intacct->contacts`;
- `$intacct->attachments` and `$intacct->attachmentFolders`;
- read-only `$intacct->entities` and `$intacct->users` clients;
- `$intacct->accountsPayable->vendors` and `$intacct->accountsPayable->terms`;
- `$intacct->inventory->items`, `->warehouses`, `->productLines`, `->unitOfMeasureGroups`, and `->unitsOfMeasure`;
- `$intacct->purchasing->transactionDefinitions` (read-only) and `$intacct->purchasing->documents('<transaction definition>')`;
- read-only `$intacct->generalLedger->accounts`;
- `$intacct->queries` for advanced typed queries, `$intacct->composite` for composite requests, and `$intacct->model` for object introspection.

Newer Sage domains are grouped (`$intacct->inventory->items`) so each domain can grow without crowding the top-level client.

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

### Purchase orders

Purchasing documents are addressed by their transaction definition, such as `Purchase Order` or `PO Receiver`. Lines require an item, warehouse, and location; other dimensions are optional.

```php
use ControlAir\Intacct\Resources\Purchasing\Documents\CreatePurchasingDocument;
use ControlAir\Intacct\Resources\Purchasing\Documents\CreatePurchasingDocumentLine;
use ControlAir\Intacct\Resources\Purchasing\Documents\PurchasingDocumentState;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\Dimensions;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectReference;

$orders = $intacct->purchasing->documents('Purchase Order');

$created = $orders->create(new CreatePurchasingDocument(
    transactionDate: new LocalDate('2026-09-01'),
    vendor: ObjectReference::byId('VEND-001'),
    state: PurchasingDocumentState::Draft,
    lines: [new CreatePurchasingDocumentLine(
        item: ObjectReference::byId('HAMMER16'),
        warehouse: ObjectReference::byId('WH-01'),
        location: ObjectReference::byId('HQ'),
        unit: 'Each',
        unitQuantity: new Decimal('20'),
        unitPrice: new Decimal('9.25'),
        dimensions: new Dimensions(project: ObjectReference::byId('PROJ-001')),
    )],
));

$orders->submit($created->reference->key);
```

To receive or invoice an order, create a document of the target type whose header and lines point at the source:

```php
$order = $orders->get($created->reference->key);
$line = $order->lines[0];

$intacct->purchasing->documents('PO Receiver')->create(new CreatePurchasingDocument(
    transactionDate: new LocalDate('2026-09-10'),
    vendor: ObjectReference::byId('VEND-001'),
    sourceDocument: ObjectReference::byKey($order->key->value),
    lines: [new CreatePurchasingDocumentLine(
        item: ObjectReference::byId('HAMMER16'),
        warehouse: ObjectReference::byId('WH-01'),
        location: ObjectReference::byId('HQ'),
        unit: 'Each',
        unitQuantity: new Decimal('20'),
        unitPrice: new Decimal('9.25'),
        sourceDocument: ObjectReference::byKey($order->key->value),
        sourceDocumentLine: ObjectReference::byKey($line->key->value),
    )],
));
```

`UpdatePurchasingDocument` combines header changes with `withAddedLine()`, `withUpdatedLine()`, and `withRemovedLine()` in one PATCH. Query results contain document headers only; read a document to load its lines.

### Iterate every page

`Paginator` fetches pages lazily as you iterate:

```php
use ControlAir\Intacct\Core\Query\Paginator;

$items = Paginator::over(
    $intacct->inventory->items->query(...),
    new ResourceQuery(filters: [Filter::equal('status', 'active')], size: 500),
);

foreach ($items as $item) {
    // ...
}
```

It stops when Sage reports no next page, on an empty page, or when the next offset does not advance. Pass `maxPages` to cap the number of requests.

### Retries

Wrap your PSR-18 client in `RetryingHttpClient` to retry rate limits and transient failures:

```php
use ControlAir\Intacct\Core\Http\RetryingHttpClient;
use ControlAir\Intacct\Core\Http\RetryPolicy;

$http = new RetryingHttpClient(
    client: new \GuzzleHttp\Client(['timeout' => 30]),
    policy: new RetryPolicy(maxRetries: 3, baseDelayMilliseconds: 500, maxDelayMilliseconds: 30_000),
);
```

- HTTP 429 is retried for every method, honoring `Retry-After` and Sage's `X-IA-*-Retry-After` headers.
- HTTP 5xx and network errors are retried for GET, HEAD, OPTIONS, and DELETE, and for a POST or PATCH carrying an `Idempotency-Key`. Other writes are never resent, so a request that timed out after Sage processed it cannot create a duplicate.

### Composite requests

A composite request runs 2 to 10 operations in order and can feed one result into a later operation. Execution stops at the first failure, and earlier operations are **not** rolled back.

```php
use ControlAir\Intacct\Core\Composite\CompositeOperation;
use ControlAir\Intacct\Core\Composite\CompositeReference;
use ControlAir\Intacct\Core\Composite\CompositeRequest;

$result = $intacct->composite->execute(CompositeRequest::of(
    CompositeOperation::post('/objects/accounts-payable/vendor', ['id' => 'V100', 'name' => 'Acme'], resultReference: 'vendor'),
    CompositeOperation::get('/objects/accounts-payable/vendor/'.CompositeReference::to('vendor', 1, 'key')),
));

if (! $result->isSuccessful()) {
    $failure = $result->firstFailure();
}
```

### Object model

`$intacct->model` describes an object's fields, groups, and relationships as configured in the tenant, including custom fields:

```php
$model = $intacct->model->describe('accounts-payable/vendor');

foreach ($model?->customFields() ?? [] as $field) {
    echo $field->name, ' ', $field->type, PHP_EOL;
}
```

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

The live test requests a token and uses it to read and map the company dimension catalog. This verifies both authentication and a real REST API request without mutating Sage data, printing credentials, introspecting, or revoking the token. `LiveResourcesTest` then runs read-only queries against vendors, items, purchasing transaction definitions and documents, and the model service to confirm query object names and response shapes. It never creates, changes, or deletes records. If any required value is absent, PHPUnit marks the integration tests as skipped. The `.env` file is ignored by Git.
