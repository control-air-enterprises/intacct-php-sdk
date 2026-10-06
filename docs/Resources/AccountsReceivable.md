# Accounts Receivable

[Docs](../README.md) › [Resources](README.md)

Namespace: `ControlAir\Intacct\Resources\AccountsReceivable`

| Client | Object | Operations |
| --- | --- | --- |
| `accountsReceivable->customers` | `accounts-receivable/customer` | Get, query, create, update, delete |

Customer tax IDs are wrapped in `SensitiveString` and excluded from the default query field set. Customer status has its own enum because Sage adds `activeNonPosting`.

- Contacts are references to existing `company-config/contact` records in the `contacts` group: `primaryContact`, `billToContact`, and `shipToContact` are writable; `defaultContact`, the display contact Sage keeps on the record, is read-only.
- `retainagePercentage` and `creditLimit` are `Decimal` values; `totalDue` is read-only.
