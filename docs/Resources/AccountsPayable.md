# Accounts Payable

[Docs](../README.md) › [Resources](README.md)

Namespace: `ControlAir\Intacct\Resources\AccountsPayable`

| Client | Object | Operations |
| --- | --- | --- |
| `accountsPayable->vendors` | `accounts-payable/vendor` | Get, query, create, update, delete |
| `accountsPayable->terms` | `accounts-payable/term` | Get, query, create, update, delete |

Vendor tax IDs are wrapped in `SensitiveString` and excluded from the default query field set. Vendor status has its own enum because Sage adds `activeNonPosting`.
