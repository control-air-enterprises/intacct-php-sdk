# Purchasing

[Docs](../README.md) › [Resources](README.md)

Namespace: `ControlAir\Intacct\Resources\Purchasing`

| Client | Object | Operations |
| --- | --- | --- |
| `purchasing->transactionDefinitions` | `purchasing/txn-definition` | Get and query |
| `purchasing->documents('<name>')` | `purchasing/document::<name>` | Get, query, create, update, delete, submit, approve, decline |

Documents are addressed by transaction definition: the REST path URL-encodes the name (`document::Purchase%20Order`), while queries use the plain object name (`purchasing/document::Purchase Order`). Custom fields exist only on these derived objects.

- Lines are created inside the header and changed through the header PATCH: a line without a key is added, a line with a key is updated, and `ia::operation: delete` removes it.
- Sage allows only the `pending`, `draft`, or `submitted` state on create, and requires the vendor by ID.
- Conversion (PO → receipt → invoice) creates a document of the target type with `sourceDocument` on the header and `sourceDocument` plus `sourceDocumentLine` on each line.
- Transaction definitions are read-only in the typed surface because they control AP and GL posting.
