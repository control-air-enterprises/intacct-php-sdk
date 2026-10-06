<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsReceivable\Customers;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\BatchResult;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

final readonly class CustomersClient
{
    /** @var ResourceGateway<Customer> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/accounts-receivable/customer',
            'accounts-receivable/customer',
            Customer::fromArray(...),
        );
    }

    public function get(ObjectKey $key): Customer
    {
        return $this->gateway->get($key);
    }

    /**
     * The customer tax ID is excluded from query results; read a single customer to retrieve it.
     *
     * @return Page<Customer>
     */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select([
            'key', 'id', 'name', 'status', 'customerType.key', 'customerType.id',
            'parent.key', 'parent.id', 'term.key', 'term.id', 'salesRepresentative.key',
            'salesRepresentative.id', 'accountGroup.key', 'accountGroup.id',
            'defaultRevenueGLAccount.key', 'defaultRevenueGLAccount.id', 'contacts.default.key',
            'contacts.default.id', 'contacts.primary.key', 'contacts.primary.id',
            'contacts.billTo.key', 'contacts.billTo.id', 'contacts.shipTo.key', 'contacts.shipTo.id',
            'currency', 'creditLimit', 'retainagePercentage', 'totalDue', 'isOnHold', 'notes', 'href',
        ]));
    }

    public function create(CreateCustomer $customer, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->create($customer->toArray(), $idempotencyKey);
    }

    public function update(ObjectKey $key, UpdateCustomer $customer, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->update($key, $customer->toArray(), $idempotencyKey);
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }

    /**
     * Creates up to 500 records in one request. With $atomic, Sage rolls back the whole batch
     * when any record fails; otherwise each record succeeds or fails on its own.
     *
     * @param  list<CreateCustomer>  $records
     */
    public function createMany(array $records, bool $atomic = false, ?IdempotencyKey $idempotencyKey = null): BatchResult
    {
        return $this->gateway->createMany(
            array_map(static fn (CreateCustomer $record): array => $record->toArray(), $records),
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
