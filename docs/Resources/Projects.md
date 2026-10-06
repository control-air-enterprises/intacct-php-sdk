# Projects

[Docs](../README.md) › [Resources](README.md)

Namespace: `ControlAir\Intacct\Resources\Projects`

| Client | Object | Operations |
| --- | --- | --- |
| `projects` | `projects/project` | Get, query, create, update, delete |
| `tasks` | `projects/task` | Get, query, create, update, delete |
| `projectResources` | `projects/project-resource` | Get, query, create, update, delete |

Sage calls the project work-breakdown records **tasks**. The SDK uses the official REST object name instead of inventing a separate cost-code abstraction. Construction cost types remain a separate resource; see [Construction](Construction.md).
