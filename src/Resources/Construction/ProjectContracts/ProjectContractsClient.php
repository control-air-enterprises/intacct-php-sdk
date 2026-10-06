<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectContracts;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\BatchResult;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

final readonly class ProjectContractsClient
{
    /** @var non-empty-list<string> */
    private const QUERY_FIELDS = [
        'key', 'id', 'name', 'description', 'contractDate', 'status', 'isBillable', 'excludeFromWIPReporting',
        'scope', 'inclusions', 'exclusions', 'terms',
        'project.key', 'project.id', 'project.name', 'customer.key', 'customer.id', 'customer.name',
        'projectContractType.key', 'projectContractType.id', 'location.key', 'location.id', 'location.name',
        'entity.key', 'entity.id', 'entity.name',
        'summary.originalPrice', 'summary.revisionPrice', 'summary.approvedChangePrice', 'summary.pendingChangePrice',
        'summary.otherPrice', 'summary.totalPrice', 'summary.forecastPrice',
        'billing.billedPrice', 'billing.totalBilledNetRetainage', 'billing.percentBilled',
        'billing.percentBilledNetRetainage', 'billing.totalRetainageHeld', 'billing.totalRetainageReleased',
        'billing.retainageBalance', 'billing.balanceToBill', 'billing.balanceToBillNetRetainage',
        'billing.totalPaymentsReceived', 'billing.netTotalBilled', 'billing.netTotalPaymentsReceived',
        'billing.lastApplicationNumber',
        'schedule.scheduledStartDate', 'schedule.actualStartDate', 'schedule.scheduledCompletionDate',
        'schedule.revisedCompletionDate', 'schedule.substantialCompletionDate', 'schedule.actualCompletionDate',
        'schedule.noticeToProceedDate', 'schedule.responseDueDate', 'schedule.executedOnDate', 'schedule.scheduleImpact',
        'internalReference.referenceNumber', 'externalReference.referenceNumber', 'href',
    ];

    /** @var ResourceGateway<ProjectContract> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/construction/project-contract',
            'construction/project-contract',
            ProjectContract::fromArray(...),
        );
    }

    public function get(ObjectKey $key): ProjectContract
    {
        return $this->gateway->get($key);
    }

    /** @return Page<ProjectContract> */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select(self::QUERY_FIELDS));
    }

    public function create(CreateProjectContract $contract, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->create($contract->toArray(), $idempotencyKey);
    }

    public function update(ObjectKey $key, UpdateProjectContract $contract, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->update($key, $contract->toArray(), $idempotencyKey);
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }

    /**
     * Creates up to 500 records in one request. With $atomic, Sage rolls back the whole batch
     * when any record fails; otherwise each record succeeds or fails on its own.
     *
     * @param  list<CreateProjectContract>  $records
     */
    public function createMany(array $records, bool $atomic = false, ?IdempotencyKey $idempotencyKey = null): BatchResult
    {
        return $this->gateway->createMany(
            array_map(static fn (CreateProjectContract $record): array => $record->toArray(), $records),
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
