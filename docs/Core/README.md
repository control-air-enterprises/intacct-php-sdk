# Core

[Docs](../README.md) › Core

Namespace: `ControlAir\Intacct\Core`

`Core` holds the protocol concerns every resource shares: the HTTP transport, query serialization, response envelopes, the resource gateway, and the shared Sage services.

## Shared services

| Client | Service | Purpose |
| --- | --- | --- |
| `composite` | `services/core/composite` | Run 2–10 dependent operations in one request (not atomic) |
| `model` | `services/core/model` | Describe objects, fields, relationships, and custom fields |
| `queries` | `services/core/query` | Raw queries with arbitrary field lists |

## Batch writes and idempotency

Every writable client also exposes:

- an optional `IdempotencyKey` on `create()` and `update()`, sent as the `Idempotency-Key` header;
- `createMany()` and `deleteMany()` for batches of up to 500 records, optionally atomic through `X-IA-API-Param-Transaction`.

`ResourceGateway::updateMany()` exists for keyed batch updates but is not yet exposed through the typed clients.

## Query results

Sage returns related fields in query rows as flat keys such as `"parent.id"`, while object reads nest them. `ResourceGateway::query()` expands dotted keys before mapping, so one mapper handles both shapes. Query field lists can therefore select related fields (`'vendor.id'`) and nested groups (`'purchasing.standardCost'`).
