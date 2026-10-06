# Documentation

The documentation mirrors the source tree: each folder is named after a namespace under `src/`, and each page states the namespace it documents. A namespace with sub-namespaces is a folder with a `README.md` overview; a leaf namespace is a single page in its parent's folder.

```text
docs/
├── README.md                     This index
├── architecture.md               Dependency direction and package boundaries
├── Core/
│   └── README.md                 ControlAir\Intacct\Core
├── Resources/
│   ├── README.md                 ControlAir\Intacct\Resources: overview, custom fields, extending
│   ├── AccountsPayable.md        Resources\AccountsPayable
│   ├── AccountsReceivable.md     Resources\AccountsReceivable
│   ├── CompanyConfiguration.md   Resources\CompanyConfiguration
│   ├── Construction.md           Resources\Construction: cost types, labor, contracts, change orders
│   ├── GeneralLedger.md          Resources\GeneralLedger
│   ├── InventoryControl.md       Resources\InventoryControl
│   ├── Projects.md               Resources\Projects
│   ├── Purchasing.md             Resources\Purchasing
│   └── Time.md                   Resources\Time: timesheets and time types
└── Webhooks/
    ├── README.md                 ControlAir\Intacct\Webhooks: how Sage delivers events, setup, quick start
    ├── Verification.md           Webhooks: WebhookVerifier and the verified event
    ├── EventQueue.md             Webhooks\EventQueue
    └── Idempotency.md            Webhooks\Contracts
```

| Namespace | Guide |
| --- | --- |
| `Auth` | [OAuth client](../README.md#oauth-client) and [token lifecycle](../README.md#token-lifecycle) in the package README |
| `Core` | [Core](Core/README.md) |
| `Resources` | [Resources](Resources/README.md) |
| `Webhooks` | [Webhooks](Webhooks/README.md) |
| Cross-cutting | [Architecture](architecture.md) |

When you add a namespace, add its page in the matching folder and list it here.
