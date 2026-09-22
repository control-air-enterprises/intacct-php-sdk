<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Purchasing;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Resources\Purchasing\Documents\CreatePurchasingDocument;
use ControlAir\Intacct\Resources\Purchasing\Documents\CreatePurchasingDocumentLine;
use ControlAir\Intacct\Resources\Purchasing\Documents\PaymentStatus;
use ControlAir\Intacct\Resources\Purchasing\Documents\PurchasingDocument;
use ControlAir\Intacct\Resources\Purchasing\Documents\PurchasingDocumentsClient;
use ControlAir\Intacct\Resources\Purchasing\Documents\PurchasingDocumentState;
use ControlAir\Intacct\Resources\Purchasing\Documents\UpdatePurchasingDocument;
use ControlAir\Intacct\Resources\Purchasing\Documents\UpdatePurchasingDocumentLine;
use ControlAir\Intacct\Resources\Purchasing\TransactionDefinitions\DocumentClass;
use ControlAir\Intacct\Resources\Purchasing\TransactionDefinitions\PostingMethod;
use ControlAir\Intacct\Resources\Purchasing\TransactionDefinitions\TransactionDefinitionsClient;
use ControlAir\Intacct\Tests\Support\ApiTestCase;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\Dimensions;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PurchasingDocumentsClient::class)]
#[CoversClass(PurchasingDocument::class)]
#[CoversClass(CreatePurchasingDocument::class)]
#[CoversClass(UpdatePurchasingDocument::class)]
#[CoversClass(TransactionDefinitionsClient::class)]
final class PurchasingDocumentsClientTest extends ApiTestCase
{
    public function test_it_reads_a_document_with_lines_from_an_encoded_definition_path(): void
    {
        [$client, $http] = $this->client($this->json([
            'ia::result' => [
                'key' => '10',
                'id' => 'Purchase Order-PO-0010',
                'documentNumber' => 'PO-0010',
                'state' => 'approved',
                'txnDate' => '2026-09-01',
                'vendor' => ['key' => '85', 'id' => 'VEND-001', 'name' => 'Acme Supply'],
                'txnDefinition' => ['key' => '4', 'id' => 'Purchase Order'],
                'txnCurrency' => 'USD',
                'total' => '185.00',
                'paymentStatus' => 'open',
                'lines' => [[
                    'key' => '46',
                    'lineNumber' => 1,
                    'unit' => 'Each',
                    'unitQuantity' => '20',
                    'unitPrice' => '9.25',
                    'quantityRemaining' => '20',
                    'dimensions' => [
                        'item' => ['key' => '12', 'id' => 'HAMMER16', 'name' => 'Hammer'],
                        'warehouse' => ['key' => '5', 'id' => 'WH-01'],
                        'location' => ['key' => '1', 'id' => 'HQ'],
                        'project' => ['key' => '10', 'id' => 'PROJ-001'],
                    ],
                ]],
            ],
        ]));

        $document = $client->purchasing->documents('Purchase Order')->get(new ObjectKey('10'));

        self::assertSame('https://api.intacct.com/ia/api/v1/objects/purchasing/document::Purchase%20Order/10', $this->url($http->requests[0]));
        self::assertSame(PurchasingDocumentState::Approved, $document->state);
        self::assertSame(PaymentStatus::Open, $document->paymentStatus);
        self::assertSame('VEND-001', $document->vendor?->id?->value);
        self::assertCount(1, $document->lines);
        self::assertSame(1, $document->lines[0]->lineNumber);
        self::assertSame('HAMMER16', $document->lines[0]->item?->id?->value);
        self::assertSame('PROJ-001', $document->lines[0]->dimensions->project?->id?->value);
        self::assertSame('20', $document->lines[0]->quantityRemaining?->value);
    }

    public function test_query_uses_the_unencoded_definition_object_name(): void
    {
        [$client, $http] = $this->client($this->json([
            'ia::result' => [[
                'key' => '10',
                'id' => 'Purchase Order-PO-0010',
                'state' => 'pending',
                'vendor.id' => 'VEND-001',
            ]],
            'ia::meta' => ['totalCount' => 1],
        ]));

        $page = $client->purchasing->documents('Purchase Order')->query();

        self::assertSame('https://api.intacct.com/ia/api/v1/services/core/query', $this->url($http->requests[0]));
        self::assertSame('purchasing/document::Purchase Order', $this->jsonBody($http->requests[0])['object']);
        self::assertSame('VEND-001', $page->items[0]->vendor?->id?->value);
        self::assertSame([], $page->items[0]->lines);
    }

    public function test_it_creates_a_document_with_required_line_dimensions(): void
    {
        [$client, $http] = $this->client($this->mutation('10', 'Purchase Order-PO-0010'));

        $result = $client->purchasing->documents('Purchase Order')->create(new CreatePurchasingDocument(
            transactionDate: new LocalDate('2026-09-01'),
            vendor: ObjectReference::byId('VEND-001'),
            lines: [new CreatePurchasingDocumentLine(
                item: ObjectReference::byId('HAMMER16'),
                warehouse: ObjectReference::byId('WH-01'),
                location: ObjectReference::byId('HQ'),
                unit: 'Each',
                unitQuantity: new Decimal('20'),
                unitPrice: new Decimal('9.25'),
                dimensions: new Dimensions(project: ObjectReference::byId('PROJ-001')),
            )],
            state: PurchasingDocumentState::Draft,
            referenceNumber: 'REQ-77',
        ));

        self::assertSame('10', $result->reference->key?->value);
        self::assertSame('POST', $http->requests[0]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/purchasing/document::Purchase%20Order', $this->url($http->requests[0]));
        self::assertSame([
            'txnDate' => '2026-09-01',
            'vendor' => ['id' => 'VEND-001'],
            'state' => 'draft',
            'referenceNumber' => 'REQ-77',
            'lines' => [[
                'unit' => 'Each',
                'unitQuantity' => '20',
                'unitPrice' => '9.25',
                'dimensions' => [
                    'project' => ['id' => 'PROJ-001'],
                    'item' => ['id' => 'HAMMER16'],
                    'warehouse' => ['id' => 'WH-01'],
                    'location' => ['id' => 'HQ'],
                ],
            ]],
        ], $this->jsonBody($http->requests[0]));
    }

    public function test_a_converted_line_references_its_source_line(): void
    {
        $line = new CreatePurchasingDocumentLine(
            item: ObjectReference::byId('HAMMER16'),
            warehouse: ObjectReference::byId('WH-01'),
            location: ObjectReference::byId('HQ'),
            unit: 'Each',
            unitQuantity: new Decimal('5'),
            unitPrice: new Decimal('9.25'),
            sourceDocument: ObjectReference::byKey('10'),
            sourceDocumentLine: ObjectReference::byKey('46'),
        );

        self::assertSame(['key' => '10'], $line->toArray()['sourceDocument']);
        self::assertSame(['key' => '46'], $line->toArray()['sourceDocumentLine']);
    }

    public function test_create_rejects_invalid_states_vendor_keys_and_empty_lines(): void
    {
        $line = new CreatePurchasingDocumentLine(
            item: ObjectReference::byId('HAMMER16'),
            warehouse: ObjectReference::byId('WH-01'),
            location: ObjectReference::byId('HQ'),
            unit: 'Each',
            unitQuantity: new Decimal('1'),
            unitPrice: new Decimal('1'),
        );
        $attempts = [
            static fn () => new CreatePurchasingDocument(new LocalDate('2026-09-01'), ObjectReference::byId('V'), [$line], PurchasingDocumentState::Approved),
            static fn () => new CreatePurchasingDocument(new LocalDate('2026-09-01'), ObjectReference::byKey('85'), [$line]),
            static fn () => new CreatePurchasingDocument(new LocalDate('2026-09-01'), ObjectReference::byId('V'), []),
        ];

        foreach ($attempts as $attempt) {
            try {
                $attempt();
                self::fail('Expected an invalid purchasing document to be rejected.');
            } catch (InvalidArgument) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_update_combines_header_changes_and_line_operations(): void
    {
        [$client, $http] = $this->client($this->mutation('10'));

        $client->purchasing->documents('Purchase Order')->update(
            new ObjectKey('10'),
            UpdatePurchasingDocument::memo('Rush order')
                ->withUpdatedLine(new ObjectKey('46'), UpdatePurchasingDocumentLine::unitQuantity(new Decimal('25')))
                ->withRemovedLine(new ObjectKey('44'))
                ->withAddedLine(new CreatePurchasingDocumentLine(
                    item: ObjectReference::byId('NAILS'),
                    warehouse: ObjectReference::byId('WH-01'),
                    location: ObjectReference::byId('HQ'),
                    unit: 'Box',
                    unitQuantity: new Decimal('2'),
                    unitPrice: new Decimal('4.50'),
                )),
        );

        self::assertSame('PATCH', $http->requests[0]->getMethod());
        self::assertSame([
            'memo' => 'Rush order',
            'lines' => [
                ['key' => '46', 'unitQuantity' => '25'],
                ['ia::operation' => 'delete', 'key' => '44'],
                [
                    'unit' => 'Box',
                    'unitQuantity' => '2',
                    'unitPrice' => '4.50',
                    'dimensions' => [
                        'item' => ['id' => 'NAILS'],
                        'warehouse' => ['id' => 'WH-01'],
                        'location' => ['id' => 'HQ'],
                    ],
                ],
            ],
        ], $this->jsonBody($http->requests[0]));
    }

    public function test_workflow_actions_post_to_the_workflow_endpoints(): void
    {
        $workflow = fn (string $state): Response => $this->json([
            'ia::result' => ['key' => '10', 'id' => 'Purchase Order-PO-0010', 'state' => $state],
            'ia::meta' => ['totalCount' => 1, 'totalSuccess' => 1, 'totalError' => 0],
        ]);
        [$client, $http] = $this->client($workflow('submitted'), $workflow('approved'), $workflow('declined'));
        $documents = $client->purchasing->documents('Purchase Order');

        $documents->submit(new ObjectKey('10'));
        $documents->approve(new ObjectKey('10'), 'Looks good', [new ObjectKey('46')]);
        $documents->decline(new ObjectKey('10'));

        self::assertSame('https://api.intacct.com/ia/api/v1/workflows/purchasing/document/submit', $this->url($http->requests[0]));
        self::assertSame(['key' => '10'], $this->jsonBody($http->requests[0]));
        self::assertSame('https://api.intacct.com/ia/api/v1/workflows/purchasing/document/approve', $this->url($http->requests[1]));
        self::assertSame(['key' => '10', 'notes' => 'Looks good', 'lineKeys' => ['46']], $this->jsonBody($http->requests[1]));
        self::assertSame('https://api.intacct.com/ia/api/v1/workflows/purchasing/document/decline', $this->url($http->requests[2]));
        self::assertSame(['key' => '10'], $this->jsonBody($http->requests[2]));
    }

    public function test_transaction_definitions_are_read_only_and_typed(): void
    {
        [$client, $http] = $this->client($this->json([
            'ia::result' => [
                'key' => '4',
                'id' => 'Purchase Order',
                'docClass' => 'order',
                'workflowCategory' => 'order',
                'inventoryUpdateType' => 'quantity',
                'txnPostingMethod' => 'noPosting',
                'status' => 'active',
            ],
        ]));

        $definition = $client->purchasing->transactionDefinitions->get(new ObjectKey('4'));

        self::assertSame(DocumentClass::Order, $definition->documentClass);
        self::assertSame(PostingMethod::None, $definition->postingMethod);
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/purchasing/txn-definition/4', $this->url($http->requests[0]));
    }
}
