# Construction

[Docs](../README.md) › [Resources](README.md)

Namespace: `ControlAir\Intacct\Resources\Construction`

Construction clients are grouped under `$intacct->construction`. Cost types are also available as `$intacct->costTypes`, where they were first introduced.

## Cost types

| Client | Object | Operations |
| --- | --- | --- |
| `construction->costTypes` | `construction/cost-type` | Get, query, create, update, delete |

Cost types are kept separate from project [tasks](Projects.md), matching Sage's REST object names.

## Labor

| Client | Object | Operations |
| --- | --- | --- |
| `construction->laborUnions` | `construction/labor-union` | Get, query, create, update, delete |
| `construction->laborClasses` | `construction/labor-class` | Get, query, create, update, delete |
| `construction->laborShifts` | `construction/labor-shift` | Get, query, create, update, delete |
| `construction->employeePositions` | `construction/employee-position` | Get, query, create, update, delete |

Labor unions, labor classes, labor shifts, and employee positions share one shape: ID, name, description, status, and the owning entity. The ID is set on create and cannot be changed. [Timesheet](Time.md) lines reference them.

Reading these objects needs Construction labor permissions; a Web Services user without them receives HTTP 403 `unauthorizedUser`.

## Project contracts

| Client | Object | Operations |
| --- | --- | --- |
| `construction->projectContracts` | `construction/project-contract` | Get, query, create, update, delete |
| `construction->projectContractLines` | `construction/project-contract-line` | Get, query, create, update, delete |

A project contract is the owner (prime) contract of a job. Sage's `contracts/` objects belong to the separate revenue-recognition Contracts module.

- A contract's `summary` prices (original, revision, approved and pending change, other, total, forecast) and `billing` totals are read-only `Decimal` values that Sage maintains; `schedule` dates and the internal and external reference numbers are writable. Updating the schedule sends only the dates you set.
- Contract lines are root objects with their own endpoint, not owned by the contract: create them with a `projectContract` reference and, optionally, a `parent` line. Lines carry their own dimensions, including user-defined ones, and a billing setup (progress bill or time and material; a `specifiedAmount` maximum requires an amount).
- A line's price entries (`construction/project-contract-line-entry`) are owned by the line: `get()` returns them in `entries`, query results do not. Add entries on create or with `withAddedEntry()`, and remove them with `withRemovedEntry()` in the same PATCH; to change an entry, remove it and add a replacement.
- The `projectContractType` reference has no `name` field, so only its key and ID are selected.

## Change orders

| Client | Object | Operations |
| --- | --- | --- |
| `construction->projectChangeOrders` | `construction/project-change-order` | Get, query, create, update, delete |
| `construction->changeRequests` | `construction/change-request` | Get, query, create, update, delete |

A change request proposes cost and price changes by cost code (project, task, and cost type on each line); a project change order is the owner change order against a project contract that collects them.

- Change request lines (`construction/change-request-line`) are created inside the header and changed through the header PATCH (`changeRequestLines`): a line without a key is added, a line with a key is updated, and `ia::operation: delete` removes it. The line object itself only allows GET.
- Queries return change request headers only; `get()` loads the lines. `ChangeRequest::$workflowType` is the workflow type of its change request status, for example `approvedChange`.
- Sage cannot query a project change order's `status` (error `CORE-1134`), so query results leave it null; read the change order to load it.
- `schedule`, `internalReference` (employees), and `externalReference` (contacts) map to `ChangeOrderSchedule`, `ChangeOrderInternalReference`, and `ChangeOrderExternalReference` on both objects. Create payloads send only the fields that are set; the group setters on updates replace the whole group, so a null clears its field.
- Totals (`totalCost`, `totalPrice`, `linePrice`), the customer, location, and entity are read-only.
- The SDK requires the project and date on both headers, and the project, task, and cost type on each change request line.

Line writes through the header follow the object model; no change request had been written against a live tenant when this client was added.
