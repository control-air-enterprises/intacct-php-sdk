<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Time\Timesheets;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\BatchResult;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

final readonly class TimesheetsClient
{
    /** @var ResourceGateway<Timesheet> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/time/timesheet',
            'time/timesheet',
            Timesheet::fromArray(...),
        );
    }

    public function get(ObjectKey $key): Timesheet
    {
        return $this->gateway->get($key);
    }

    /**
     * Query results contain timesheet headers only; read a timesheet to load its lines.
     *
     * @return Page<Timesheet>
     */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select([
            'key', 'id', 'state', 'employee.key', 'employee.id', 'beginDate', 'endDate',
            'postingDate', 'description', 'unitOfMeasure', 'hoursInDay', 'href',
        ]));
    }

    public function create(CreateTimesheet $timesheet, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->create($timesheet->toArray(), $idempotencyKey);
    }

    public function update(ObjectKey $key, UpdateTimesheet $timesheet, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->update($key, $timesheet->toArray(), $idempotencyKey);
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }

    /**
     * Creates up to 500 records in one request. With $atomic, Sage rolls back the whole batch
     * when any record fails; otherwise each record succeeds or fails on its own.
     *
     * @param  list<CreateTimesheet>  $records
     */
    public function createMany(array $records, bool $atomic = false, ?IdempotencyKey $idempotencyKey = null): BatchResult
    {
        return $this->gateway->createMany(
            array_map(static fn (CreateTimesheet $record): array => $record->toArray(), $records),
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
