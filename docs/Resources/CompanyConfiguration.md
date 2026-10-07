# Company configuration and dimensions

[Docs](../README.md) › [Resources](README.md)

Namespace: `ControlAir\Intacct\Resources\CompanyConfiguration`

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

Sage's query service cannot select a user's owned lists, so `locations`, `departments`, and `roles` are empty on `users->query()` results and populated by `users->get()`. Locations have no `description` field in Sage's object model, so `Location`, `CreateLocation`, and `UpdateLocation` do not expose one.

Attachment files are sent as base64 in `files[].data`. `AttachmentFile` and `AttachedFile` redact file contents from debug output. Sage's schema describes the data as zipped while its examples use plain base64; the SDK sends plain base64 until this is verified against a live tenant.

Employee Social Security numbers are never part of the default query field set. When returned by a direct read, an SSN is wrapped in `SensitiveString`, whose debug representation is redacted.
