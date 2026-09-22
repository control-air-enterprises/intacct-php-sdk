<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsPayable\Terms;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
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

    public function create(CreateTerm $term): MutationResult
    {
        return $this->gateway->create($term->toArray());
    }

    public function update(ObjectKey $key, UpdateTerm $term): MutationResult
    {
        return $this->gateway->update($key, $term->toArray());
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }
}
