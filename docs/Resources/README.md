# Resources

[Docs](../README.md) › Resources

Namespace: `ControlAir\Intacct\Resources`

Resource clients mirror Sage Intacct REST domains while sharing the transport, query, pagination, and exception behavior in `Core`.

| Domain | Namespace | Guide |
| --- | --- | --- |
| Projects | `Resources\Projects` | [Projects.md](Projects.md) |
| Construction | `Resources\Construction` | [Construction.md](Construction.md) |
| Company configuration | `Resources\CompanyConfiguration` | [CompanyConfiguration.md](CompanyConfiguration.md) |
| Accounts Payable | `Resources\AccountsPayable` | [AccountsPayable.md](AccountsPayable.md) |
| Inventory Control | `Resources\InventoryControl` | [InventoryControl.md](InventoryControl.md) |
| Purchasing | `Resources\Purchasing` | [Purchasing.md](Purchasing.md) |
| General Ledger | `Resources\GeneralLedger` | [GeneralLedger.md](GeneralLedger.md) |

Every writable client also supports idempotency keys and batch writes; see [Core](../Core/README.md#batch-writes-and-idempotency).

## Custom fields

Custom fields are top-level `nsp::` keys. Response DTOs expose them through `customFields`, create DTOs take a `CustomFields` set, and update DTOs offer `customField()`/`withCustomField()`. Select custom fields in queries with `ResourceQuery::withFields('nsp::NAME')`. User-defined dimensions are `nsp::` references inside a line's `dimensions` object and are exposed through `Dimensions::$custom`. A reflection test in `tests/CustomFields` fails if a new resource DTO omits custom-field support.

## Extending the SDK

Add a new resource beneath `src/Resources/<SageDomain>/<Resource>`, expose it through that domain's group class (for example `Resources/InventoryControl/InventoryControl.php`), and give it:

1. an immutable response DTO with a `fromArray()` mapper that reads nested objects (query rows are expanded for you);
2. separate immutable create and update DTOs;
3. a client backed by `ResourceGateway`;
4. a fixed default query field list containing every field required by its mapper;
5. transport-level fixture tests for read, query, and supported mutations;
6. custom-field support: `customFields` on the response DTO, a `CustomFields` argument on the create DTO, and `withCustomField()` on the update DTO.

Do not read environment variables or resolve framework services inside a resource module. Applications construct `IntacctClient` directly or through their own framework service provider.
