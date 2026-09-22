<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsPayable\Vendors;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
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

    public function create(CreateVendor $vendor): MutationResult
    {
        return $this->gateway->create($vendor->toArray());
    }

    public function update(ObjectKey $key, UpdateVendor $vendor): MutationResult
    {
        return $this->gateway->update($key, $vendor->toArray());
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }
}
