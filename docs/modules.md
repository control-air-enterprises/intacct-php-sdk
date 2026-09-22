# Resource modules

Resource clients mirror Sage Intacct REST domains while sharing the transport, query, pagination, and exception behavior in `Core`.

## Projects

| Client | Object | Operations |
| --- | --- | --- |
| `projects` | `projects/project` | Get, query, create, update, delete |
| `tasks` | `projects/task` | Get, query, create, update, delete |
| `projectResources` | `projects/project-resource` | Get, query, create, update, delete |
| `costTypes` | `construction/cost-type` | Get, query, create, update, delete |

Sage calls the project work-breakdown records **tasks**. The SDK uses the official REST object name instead of inventing a separate cost-code abstraction. Construction cost types remain a separate resource.

## Company configuration and dimensions

| Client | Object or service | Operations |
| --- | --- | --- |
| `dimensions` | `company-config/dimensions/list` | List enabled dimension definitions |
| `employees` | `company-config/employee` | Get, query, create, update, delete |
| `classes` | `company-config/class` | Get, query, create, update, delete |
| `departments` | `company-config/department` | Get, query, create, update, delete |
| `locations` | `company-config/location` | Get, query, create, update, delete |
| `entities` | `company-config/entity` | Get and query |
| `users` | `company-config/user` | Get and query |
| `contacts` | `company-config/contact` | Get, query, create, update, delete |
| `attachments` | `company-config/attachment` | Get, query, create, update, delete |
| `attachmentFolders` | `company-config/folder` | Get, query, create, update, delete |

Entities and users are deliberately read-only in the typed surface because modifying either can have broad accounting or security effects. Additional administrative write clients can be introduced separately with explicit request types and focused tests.

Attachment files are sent as base64 in `files[].data`. `AttachmentFile` and `AttachedFile` redact file contents from debug output. Sage's schema describes the data as zipped while its examples use plain base64; the SDK sends plain base64 until this is verified against a live tenant.

Employee Social Security numbers are never part of the default query field set. When returned by a direct read, an SSN is wrapped in `SensitiveString`, whose debug representation is redacted.

## Accounts Payable

| Client | Object | Operations |
| --- | --- | --- |
| `accountsPayable->vendors` | `accounts-payable/vendor` | Get, query, create, update, delete |
| `accountsPayable->terms` | `accounts-payable/term` | Get, query, create, update, delete |

Vendor tax IDs are wrapped in `SensitiveString` and excluded from the default query field set. Vendor status has its own enum because Sage adds `activeNonPosting`.

## Inventory Control

| Client | Object | Operations |
| --- | --- | --- |
| `inventory->items` | `inventory-control/item` | Get, query, create, update, delete |
| `inventory->warehouses` | `inventory-control/warehouse` | Get, query, create, update, delete |
| `inventory->productLines` | `inventory-control/product-line` | Get, query, create, update, delete |
| `inventory->unitOfMeasureGroups` | `inventory-control/unit-of-measure-group` | Get, query, create, update, delete |
| `inventory->unitsOfMeasure` | `inventory-control/unit-of-measure` | Get, query, create, update, delete |

Sage does not allow an item's type or cost method to change after creation, so `UpdateItem` does not offer them. Item-owned collections (warehouse details, vendors, kit components, cross-references) are not yet mapped.

## Purchasing

| Client | Object | Operations |
| --- | --- | --- |
| `purchasing->transactionDefinitions` | `purchasing/txn-definition` | Get and query |
| `purchasing->documents('<name>')` | `purchasing/document::<name>` | Get, query, create, update, delete, submit, approve, decline |

Documents are addressed by transaction definition: the REST path URL-encodes the name (`document::Purchase%20Order`), while queries use the plain object name (`purchasing/document::Purchase Order`). Custom fields exist only on these derived objects.

- Lines are created inside the header and changed through the header PATCH: a line without a key is added, a line with a key is updated, and `ia::operation: delete` removes it.
- Sage allows only the `pending`, `draft`, or `submitted` state on create, and requires the vendor by ID.
- Conversion (PO → receipt → invoice) creates a document of the target type with `sourceDocument` on the header and `sourceDocument` plus `sourceDocumentLine` on each line.
- Transaction definitions are read-only in the typed surface because they control AP and GL posting.

## General Ledger

| Client | Object | Operations |
| --- | --- | --- |
| `generalLedger->accounts` | `general-ledger/account` | Get and query |

GL accounts are read-only for the same reason as entities: chart-of-accounts changes have broad accounting effects.

## Shared services

| Client | Service | Purpose |
| --- | --- | --- |
| `composite` | `services/core/composite` | Run 2–10 dependent operations in one request (not atomic) |
| `model` | `services/core/model` | Describe objects, fields, relationships, and custom fields |
| `queries` | `services/core/query` | Raw queries with arbitrary field lists |

`ResourceGateway` also supports batch writes of up to 500 records (`createMany`, `updateMany`, `deleteMany`), optionally atomic through `X-IA-API-Param-Transaction`, and `Idempotency-Key` headers on create and update.

## Query results

Sage returns related fields in query rows as flat keys such as `"parent.id"`, while object reads nest them. `ResourceGateway::query()` expands dotted keys before mapping, so one mapper handles both shapes. Query field lists can therefore select related fields (`'vendor.id'`) and nested groups (`'purchasing.standardCost'`).

## Extending the SDK

Add a new resource beneath `src/Resources/<SageDomain>/<Resource>`, expose it through that domain's group class (for example `Resources/InventoryControl/InventoryControl.php`), and give it:

1. an immutable response DTO with a `fromArray()` mapper that reads nested objects (query rows are expanded for you);
2. separate immutable create and update DTOs;
3. a client backed by `ResourceGateway`;
4. a fixed default query field list containing every field required by its mapper;
5. transport-level fixture tests for read, query, and supported mutations.

Do not read environment variables or resolve framework services inside a resource module. Applications construct `IntacctClient` directly or through their own framework service provider.
