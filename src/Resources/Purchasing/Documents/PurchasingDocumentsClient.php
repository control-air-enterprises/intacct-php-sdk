<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Purchasing\Documents;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\HttpMethod;
use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\BatchResult;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\ObjectKey;

/**
 * Purchasing documents of one transaction definition, such as "Purchase Order".
 */
final readonly class PurchasingDocumentsClient
{
    /** @var ResourceGateway<PurchasingDocument> */
    private ResourceGateway $gateway;

    public function __construct(
        private ApiTransport $transport,
        QueryClient $queries,
        public string $transactionDefinition,
    ) {
        Assert::notBlank($this->transactionDefinition, 'The transaction definition name');

        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/purchasing/document::'.rawurlencode($this->transactionDefinition),
            'purchasing/document::'.$this->transactionDefinition,
            PurchasingDocument::fromArray(...),
        );
    }

    public function get(ObjectKey $key): PurchasingDocument
    {
        return $this->gateway->get($key);
    }

    /**
     * Query results contain document headers only; read a document to load its lines.
     *
     * @return Page<PurchasingDocument>
     */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select([
            'key', 'id', 'documentNumber', 'state', 'txnDate', 'dueDate', 'vendor.key',
            'vendor.id', 'vendor.name', 'txnDefinition.key', 'txnDefinition.id',
            'sourceDocument.key', 'sourceDocument.id', 'referenceNumber', 'vendorDocumentNumber',
            'memo', 'notes', 'txnCurrency', 'subtotal', 'total', 'paymentStatus', 'href',
        ]));
    }

    public function create(CreatePurchasingDocument $document, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->create($document->toArray(), $idempotencyKey);
    }

    public function update(ObjectKey $key, UpdatePurchasingDocument $document, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->update($key, $document->toArray(), $idempotencyKey);
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }

    /**
     * Creates up to 500 records in one request. With $atomic, Sage rolls back the whole batch
     * when any record fails; otherwise each record succeeds or fails on its own.
     *
     * @param  list<CreatePurchasingDocument>  $records
     */
    public function createMany(array $records, bool $atomic = false, ?IdempotencyKey $idempotencyKey = null): BatchResult
    {
        return $this->gateway->createMany(
            array_map(static fn (CreatePurchasingDocument $record): array => $record->toArray(), $records),
            $atomic,
            $idempotencyKey,
        );
    }

    /** @param list<ObjectKey> $keys Up to 500 keys; results are matched to keys by position. */
    public function deleteMany(array $keys, bool $atomic = false): BatchResult
    {
        return $this->gateway->deleteMany($keys, $atomic);
    }

    /** Moves a draft or declined document into approval. */
    public function submit(ObjectKey $key): MutationResult
    {
        return $this->workflow('submit', ['key' => $key->value]);
    }

    /** @param list<ObjectKey> $lineKeys Approve only these lines; empty approves the whole document. */
    public function approve(ObjectKey $key, ?string $notes = null, array $lineKeys = []): MutationResult
    {
        return $this->workflow('approve', $this->approvalPayload($key, $notes, $lineKeys));
    }

    /** @param list<ObjectKey> $lineKeys Decline only these lines; empty declines the whole document. */
    public function decline(ObjectKey $key, ?string $notes = null, array $lineKeys = []): MutationResult
    {
        return $this->workflow('decline', $this->approvalPayload($key, $notes, $lineKeys));
    }

    /**
     * @param  list<ObjectKey>  $lineKeys
     * @return array<string, mixed>
     */
    private function approvalPayload(ObjectKey $key, ?string $notes, array $lineKeys): array
    {
        return array_filter([
            'key' => $key->value,
            'notes' => $notes,
            'lineKeys' => $lineKeys === []
                ? null
                : array_map(static fn (ObjectKey $line): string => $line->value, $lineKeys),
        ], static fn (mixed $value): bool => $value !== null);
    }

    /** @param array<string, mixed> $payload */
    private function workflow(string $action, array $payload): MutationResult
    {
        return MutationResult::fromPayload($this->transport->request(
            HttpMethod::Post,
            'workflows/purchasing/document/'.$action,
            json: $payload,
        ));
    }
}
