<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsPayable\Vendors;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\BatchResult;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

final readonly class VendorsClient
{
    /** @var ResourceGateway<Vendor> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/accounts-payable/vendor',
            'accounts-payable/vendor',
            Vendor::fromArray(...),
        );
    }

    public function get(ObjectKey $key): Vendor
    {
        return $this->gateway->get($key);
    }

    /**
     * The vendor tax ID is excluded from query results; read a single vendor to retrieve it.
     *
     * @return Page<Vendor>
     */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select([
            'key', 'id', 'name', 'status', 'vendorType.key', 'vendorType.id',
            'parent.key', 'parent.id', 'term.key', 'term.id', 'currency',
            'vendorAccountNumber', 'creditLimit', 'totalDue', 'isOnHold', 'notes', 'href',
        ]));
    }

    public function create(CreateVendor $vendor, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->create($vendor->toArray(), $idempotencyKey);
    }

    public function update(ObjectKey $key, UpdateVendor $vendor, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->update($key, $vendor->toArray(), $idempotencyKey);
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }

    /**
     * Creates up to 500 records in one request. With $atomic, Sage rolls back the whole batch
     * when any record fails; otherwise each record succeeds or fails on its own.
     *
     * @param  list<CreateVendor>  $records
     */
    public function createMany(array $records, bool $atomic = false, ?IdempotencyKey $idempotencyKey = null): BatchResult
    {
        return $this->gateway->createMany(
            array_map(static fn (CreateVendor $record): array => $record->toArray(), $records),
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
