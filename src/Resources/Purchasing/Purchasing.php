<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Purchasing;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Resources\Purchasing\Documents\PurchasingDocumentsClient;
use ControlAir\Intacct\Resources\Purchasing\TransactionDefinitions\TransactionDefinitionsClient;

final readonly class Purchasing
{
    public TransactionDefinitionsClient $transactionDefinitions;

    public function __construct(
        private ApiTransport $transport,
        private QueryClient $queries,
    ) {
        $this->transactionDefinitions = new TransactionDefinitionsClient($transport, $queries);
    }

    /**
     * @param  string  $transactionDefinition  The transaction definition ID, such as "Purchase Order".
     */
    public function documents(string $transactionDefinition): PurchasingDocumentsClient
    {
        return new PurchasingDocumentsClient($this->transport, $this->queries, $transactionDefinition);
    }
}
