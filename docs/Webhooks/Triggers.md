# Trigger setup

[Docs](../README.md) › [Webhooks](README.md) › Trigger setup

Platform Triggers are created in the Sage Intacct UI, not through the API. This page is the checklist for configuring them so the SDK can receive their events, and the things to confirm before relying on them.

## Before you start

- [ ] **Use a sandbox company.** Create and test every trigger there before production.
- [ ] **Use one OAuth application.** The trigger's client ID must belong to the application whose credentials the SDK uses. Queued events are stored per application, so `$intacct->eventQueue` only sees events for the client ID its token was issued to, and webhook signatures are made with that application's client secret.
- [ ] **Grant read permissions.** The Web Services user needs permission to read every object you trigger on, because your handler fetches the current record after an event. Without it, Sage returns HTTP 403 `unauthorizedUser`. For example, the Construction labor objects need Construction labor permissions.
- [ ] **Choose a delivery type.** For a pull-based sync, prefer **Event queue**: it needs no public endpoint, absorbs bursts, and keeps events until you acknowledge them. Use **HTTP post** with webhook delivery only when you need near-real-time pushes; see [Verification](Verification.md).

## Trigger form settings

Create each trigger at **Platform Services › Objects › *object* › Triggers › New trigger**.

| Setting | Value | Why |
| --- | --- | --- |
| This trigger is deployed | Checked | An undeployed trigger never fires. This is the most common reason for an empty queue. |
| Trigger activation | After create, After update, After delete | The *before* events fire before the record is saved. |
| On field change | Any update | A specific field fires only when that field changes. |
| Type | Event queue (or HTTP post with **Use webhook delivery**) | See [Before you start](#before-you-start). |
| Client ID | Your OAuth application's client ID | See [Before you start](#before-you-start). |
| Document template | A small JSON template (below) | Its output is the event payload. |
| Trigger condition formula | `true`, or a condition | A formula that evaluates to false suppresses the event. |

Some objects are labelled differently in the UI when construction terminology is enabled. For example, `projects/project` is listed as **Job**. To confirm which REST object a trigger fires for, run the [verification test](#verify-each-trigger) and read the object name it prints.

## Document templates

The event context does not reliably identify the record: Sage's context can arrive with empty record key and ID fields. The payload is the only dependable way to know which record changed, so:

- [ ] **Send valid JSON.** Wrap every merge field in quotes. `{ "job_id": 101-21-0027 }` is not JSON; `{ "job_id": "101-21-0027" }` is.
- [ ] **Include the record key** (the record number) and the parent key you group by, such as the job's key for tasks, cost types, contracts and change orders.
- [ ] **Keep it small.** Send keys, not the whole record. Fetch current values through the API when you process the event, so a delayed or replayed event never applies stale data.
- [ ] **Use REST field names** (`key`, `id`, `name`) when you want `$event->payload->map(Dto::fromArray(...))` to work.

Copy merge-field tokens from the template editor's **Available merge fields** panel instead of typing them. A template for a task, for example:

```text
{ "key": "<task record number token>", "projectKey": "<job record number token>" }
```

## Triggers for a construction job-cost sync

These objects cover a typical job-cost integration. Create one trigger per object you sync.

| Data | REST object | SDK client |
| --- | --- | --- |
| Jobs | `projects/project` | `$intacct->projects` |
| Cost codes | `projects/task` | `$intacct->tasks` |
| Categories | `construction/cost-type` | `$intacct->construction->costTypes` |
| Owner contracts | `construction/project-contract`, `construction/project-contract-line` | `$intacct->construction->projectContracts`, `->projectContractLines` |
| Change orders | `construction/project-change-order`, `construction/change-request` | `$intacct->construction->projectChangeOrders`, `->changeRequests` |
| Labor time | `time/timesheet` | `$intacct->time->timesheets` |
| Labor master data | `construction/labor-union`, `labor-class`, `labor-shift`, `employee-position` | `$intacct->construction->laborUnions`, … |
| Customers | `accounts-receivable/customer` | `$intacct->accountsReceivable->customers` |
| Vendors | `accounts-payable/vendor` | `$intacct->accountsPayable->vendors` |
| Employees | `company-config/employee` | `$intacct->employees` |

A job can have hundreds of cost codes, so creating or importing one job can queue hundreds of events at once. Process events in batches: drain the queue, group events by job, fetch each changed job's records with one query (up to 4,000 rows a page), then acknowledge. See [Event queue](EventQueue.md#drain-the-queue).

## Verify each trigger

`composer test:webhooks` runs `tests/Integration/Webhooks/LiveJobTriggerTest.php` against the company in `.env`:

| Step | Checks | Runs when |
| --- | --- | --- |
| 1 | The queue answers for your application | Always (read-only) |
| 2 | Editing a job queues a new event | `SAGE_INTACCT_TRIGGER_TEST_JOB_KEY` is set to a sandbox job's key |
| 3 | Lists each queued event's object, event, key and payload, without removing anything | Always (read-only) |
| 4 | Acknowledging a batch removes it | `SAGE_INTACCT_TRIGGER_TEST_ACKNOWLEDGE=true` |

After creating a trigger, change a record of that object in the sandbox and run step 3. Check that the object name and event are right and that the payload reads as **JSON object**.

## Confirm before relying on triggers

These behaviours are not settled by Sage's documentation. Check each one in your sandbox:

- [ ] **Cost changes may not fire triggers.** Job-to-date cost, commitments and similar totals are likely derived from transactions (bills, purchase orders, timesheets, journal entries), not stored on the job or cost-code record. If so, posting a transaction does not update those records and no trigger fires. Post a bill or timesheet against a job, then run step 3. If nothing is queued, sync cost totals on a schedule instead.
- [ ] **Acknowledgement request body.** Sage's example omits it; the SDK sends `{"ackId": "..."}`. Run step 4 once to confirm Sage accepts it.
- [ ] **Webhook signature format.** For HTTP post triggers, Sage documents the signature header both as a JWT and as an envelope containing one, and its sample cannot be verified. Capture one real delivery and add it as a test fixture; see [Verification](Verification.md#known-gaps-in-sages-documentation).
- [ ] **Line writes.** Writes to timesheet lines and change request lines go through their header. Try one in the sandbox before production use.

## Keep a reconciliation sync

Triggers can be undeployed, edited, or blocked by a condition, and some changes never fire them. Run a scheduled query for records modified since the last successful sync, with a stored checkpoint, alongside the queue worker. It catches anything the triggers missed. Without it, missed events go unnoticed.
