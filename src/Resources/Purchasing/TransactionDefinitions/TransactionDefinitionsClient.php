<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Purchasing\TransactionDefinitions;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

/**
 * Read-only: transaction definitions control how purchasing documents post to AP and the GL.
 */
final readonly class TransactionDefinitionsClient
{
    /** @var ResourceGateway<TransactionDefinition> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/purchasing/txn-definition',
            'purchasing/txn-definition',
            TransactionDefinition::fromArray(...),
        );
    }

    public function get(ObjectKey $key): TransactionDefinition
    {
        return $this->gateway->get($key);
    }

    /** @return Page<TransactionDefinition> */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select([
            'key', 'id', 'description', 'docClass', 'workflowCategory', 'inventoryUpdateType',
            'txnPostingMethod', 'status', 'href',
        ]));
    }
}
