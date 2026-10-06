<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectContractLines;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\BatchResult;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

/**
 * Contract lines are root objects with their own endpoint: create them with a reference to
 * their project contract. Query results omit the owned entries; get() a line to load them.
 */
final readonly class ProjectContractLinesClient
{
    /** @var non-empty-list<string> */
    private const QUERY_FIELDS = [
        'key', 'id', 'name', 'description', 'contractLineDate', 'status', 'retainagePercentage',
        'isBillable', 'excludeFromGLBudget', 'scope', 'inclusions', 'exclusions', 'terms',
        'projectContract.key', 'projectContract.id', 'projectContract.name', 'parent.key', 'parent.id', 'parent.name',
        'glAccount.key', 'glAccount.id', 'glAccount.name',
        'billingSetup.billingType', 'billingSetup.maximumBilling', 'billingSetup.maximumBillingAmount',
        'billingSetup.summarizeBill',
        'summary.originalPrice', 'summary.revisionPrice', 'summary.approvedChangePrice', 'summary.pendingChangePrice',
        'summary.otherPrice', 'summary.totalPrice', 'summary.forecastPrice',
        'billing.billedPrice', 'billing.billedNetRetainage', 'billing.percentBilled', 'billing.percentBilledNetRetainage',
        'billing.previouslyAppliedPrice', 'billing.retainageHeld', 'billing.retainageReleased', 'billing.retainageBalance',
        'billing.paymentsReceived', 'billing.externalReferenceNumber',
        'schedule.scheduledStartDate', 'schedule.actualStartDate', 'schedule.scheduledCompletionDate',
        'schedule.revisedCompletionDate', 'schedule.substantialCompletionDate', 'schedule.actualCompletionDate',
        'schedule.noticeToProceedDate', 'schedule.responseDueDate', 'schedule.executedOnDate', 'schedule.scheduleImpact',
        'internalReference.referenceNumber', 'externalReference.referenceNumber',
        'dimensions.location.key', 'dimensions.location.id', 'dimensions.department.key', 'dimensions.department.id',
        'dimensions.employee.key', 'dimensions.employee.id', 'dimensions.project.key', 'dimensions.project.id',
        'dimensions.customer.key', 'dimensions.customer.id', 'dimensions.vendor.key', 'dimensions.vendor.id',
        'dimensions.item.key', 'dimensions.item.id', 'dimensions.warehouse.key', 'dimensions.warehouse.id',
        'dimensions.class.key', 'dimensions.class.id', 'dimensions.task.key', 'dimensions.task.id',
        'dimensions.costType.key', 'dimensions.costType.id', 'href',
    ];

    /** @var ResourceGateway<ProjectContractLine> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/construction/project-contract-line',
            'construction/project-contract-line',
            ProjectContractLine::fromArray(...),
        );
    }

    public function get(ObjectKey $key): ProjectContractLine
    {
        return $this->gateway->get($key);
    }

    /** @return Page<ProjectContractLine> */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select(self::QUERY_FIELDS));
    }

    public function create(CreateProjectContractLine $line, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->create($line->toArray(), $idempotencyKey);
    }

    public function update(ObjectKey $key, UpdateProjectContractLine $line, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->update($key, $line->toArray(), $idempotencyKey);
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }

    /**
     * Creates up to 500 records in one request. With $atomic, Sage rolls back the whole batch
     * when any record fails; otherwise each record succeeds or fails on its own.
     *
     * @param  list<CreateProjectContractLine>  $records
     */
    public function createMany(array $records, bool $atomic = false, ?IdempotencyKey $idempotencyKey = null): BatchResult
    {
        return $this->gateway->createMany(
            array_map(static fn (CreateProjectContractLine $record): array => $record->toArray(), $records),
            $atomic,
            $idempotencyKey,
        );
    }

    /** @param list<ObjectKey> $keys Up to 500 keys; results are matched to keys by position. */
    public function deleteMany(array $keys, bool $atomic = false): BatchResult
    {
        return $this->gateway->deleteMany($keys, $atomic);
    }
}
