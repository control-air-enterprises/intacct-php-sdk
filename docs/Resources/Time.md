# Time

[Docs](../README.md) › [Resources](README.md)

Namespace: `ControlAir\Intacct\Resources\Time`

| Client | Object | Operations |
| --- | --- | --- |
| `time->timesheets` | `time/timesheet` | Get, query, create, update, delete |
| `time->timeTypes` | `time/time-type` | Get, query, create, update, delete |

Timesheets carry labor hours and cost by job and cost code, replacing Sage 300 CRE job cost labor transactions. Timesheet lines (`time/timesheet-line`) are owned by their timesheet. Queries return headers only; reading a timesheet loads its lines.

- Lines are created inside the header and changed through the header PATCH: a line without a key is added, a line with a key is updated, and `ia::operation: delete` removes it.
- Sage allows only the `draft`, `saved`, or `submitted` state on create and derives the end date from the begin date. Line states are read-only and have their own enum (`readyForApproval` exists only on lines).
- Each line charges its quantity to `Dimensions` (project, task, cost type, customer, and user-defined dimensions) and can reference a labor class, shift, union, and employee position from [Construction](Construction.md#labor).
- `externalPayroll` holds labor cost and billing figures from an outside payroll system as `Decimal` values; a PATCH sends only the values that are set. The `hours` group is read-only.
- A time type has no separate name: its immutable ID, such as `Overtime`, is the name. Its GL and offset GL accounts drive labor cost posting.
