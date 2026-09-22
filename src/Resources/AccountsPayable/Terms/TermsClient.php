<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsPayable\Terms;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\BatchResult;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

final readonly class TermsClient
{
    /** @var ResourceGateway<Term> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/accounts-payable/term',
            'accounts-payable/term',
            Term::fromArray(...),
        );
    }

    public function get(ObjectKey $key): Term
    {
        return $this->gateway->get($key);
    }

    /** @return Page<Term> */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select([
            'key', 'id', 'description', 'status', 'due.days', 'due.from',
            'discount.days', 'discount.from', 'discount.amount', 'discount.unit',
            'discount.graceDays', 'discount.calculateOn', 'penalty.cycle',
            'penalty.amount', 'penalty.unit', 'penalty.graceDays', 'href',
        ]));
    }

    public function create(CreateTerm $term, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->create($term->toArray(), $idempotencyKey);
    }

    public function update(ObjectKey $key, UpdateTerm $term, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->update($key, $term->toArray(), $idempotencyKey);
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }

    /**
     * Creates up to 500 records in one request. With $atomic, Sage rolls back the whole batch
     * when any record fails; otherwise each record succeeds or fails on its own.
     *
     * @param  list<CreateTerm>  $records
     */
    public function createMany(array $records, bool $atomic = false, ?IdempotencyKey $idempotencyKey = null): BatchResult
    {
        return $this->gateway->createMany(
            array_map(static fn (CreateTerm $record): array => $record->toArray(), $records),
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
