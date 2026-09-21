# Architecture

## Dependency direction

The SDK is organized so higher-level workflows depend on small contracts and immutable values:

```text
Application / Laravel adapter
            │
            ▼
       IntacctClient ────► Resources
            │                 │
            ▼                 ▼
      ApiTransport ◄──── ResourceGateway / QueryClient
            │
            ▼
      Token provider ───► TokenManager ───► TokenStore
            │
            ▼
       OAuthClient ─────► PSR-18 / PSR-17 / PSR-20
            │
            ▼
 Grant objects + token value objects + package exceptions
```

No class in the core reads environment variables, resolves a service container, calls a Laravel facade, or assumes a storage mechanism. A Laravel integration belongs in an optional adapter namespace or companion package and should only bind these contracts.

## Package boundaries

- `Auth` handles authentication and token lifecycle only.
- `Core` implements protocol concerns shared by every REST resource: transport, query serialization, response envelopes, and the resource gateway.
- `Resources` maps Sage REST objects into typed PHP clients and DTOs. Its subdirectories follow Sage's API domains.
- `ValueObjects` contains immutable primitives shared by resource modules.
- `Configuration`, `Exceptions`, and `Support` remain framework neutral.

Dependencies point inward: resource clients may depend on `Core` and `ValueObjects`; `Core` never depends on a resource module. The top-level `IntacctClient` is the composition root.

## Authentication boundary

`OAuthClient` owns the wire-level differences between Sage Intacct grants:

| Flow | Request encoding | Typed input |
| --- | --- | --- |
| Authorization code | Form URL encoded | `AuthorizationCodeGrant` |
| Authorization code with PKCE | Form URL encoded | `AuthorizationCodeGrant` + `PkcePair` |
| Refresh token | Form URL encoded | `RefreshTokenGrant` |
| Client credentials | JSON | `ClientCredentialsGrant` |
| Revoke | Form URL encoded | `AccessToken` or `RefreshToken` |
| Introspect | Form URL encoded | `AccessToken` |

Raw associative arrays are limited to the private HTTP mapping boundary. Public methods accept and return concrete types.

## Token persistence

The package deliberately does not serialize tokens. Serialization is a security and application-lifecycle decision. A host application may use an encrypted relational record, a secrets service, or a cache backed by durable storage.

`TokenManager` handles expiry and rotation but does not implement distributed locking. The storage adapter should lock on `TokenKey` before a refresh in horizontally scaled applications. A future adapter can add a lock contract without coupling the OAuth client to a specific cache.

## REST API layer

The REST resource layer follows the same boundary rules:

1. `ApiTransport` adds bearer authentication, Sage headers, JSON encoding, and error mapping.
2. Create/update DTOs validate write payloads and preserve omitted-versus-null PATCH semantics.
3. Resource DTOs and response envelopes map `ia::result` and `ia::meta` into stable types.
4. Resource clients group one Sage domain, such as Accounts Payable or General Ledger.
5. Laravel support only configures and binds the same public clients.

The raw `QueryClient` intentionally returns rows as arrays because callers choose arbitrary field sets. All resource clients use fixed field selections and map those rows into concrete DTOs. When Sage adds fields, update the corresponding DTO and its mapper with fixture-backed tests.
