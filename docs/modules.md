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

Entities and users are deliberately read-only in the typed surface because modifying either can have broad accounting or security effects. Additional administrative write clients can be introduced separately with explicit request types and focused tests.

Employee Social Security numbers are never part of the default query field set. When returned by a direct read, an SSN is wrapped in `SensitiveString`, whose debug representation is redacted.

## Extending the SDK

Add a new resource beneath `src/Resources/<SageDomain>/<Resource>` and give it:

1. an immutable response DTO with a `fromArray()` mapper;
2. separate immutable create and update DTOs;
3. a client backed by `ResourceGateway`;
4. a fixed default query field list containing every field required by its mapper;
5. transport-level fixture tests for read, query, and supported mutations.

Do not read environment variables or resolve framework services inside a resource module. Applications construct `IntacctClient` directly or through their own framework service provider.
