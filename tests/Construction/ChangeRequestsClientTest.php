<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Construction;

use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Resources\Construction\ChangeRequests\ChangeRequest;
use ControlAir\Intacct\Resources\Construction\ChangeRequests\ChangeRequestContractLineSource;
use ControlAir\Intacct\Resources\Construction\ChangeRequests\ChangeRequestLine;
use ControlAir\Intacct\Resources\Construction\ChangeRequests\ChangeRequestsClient;
use ControlAir\Intacct\Resources\Construction\ChangeRequests\ChangeRequestState;
use ControlAir\Intacct\Resources\Construction\ChangeRequests\ChangeRequestWorkflowType;
use ControlAir\Intacct\Resources\Construction\ChangeRequests\CreateChangeRequest;
use ControlAir\Intacct\Resources\Construction\ChangeRequests\CreateChangeRequestLine;
use ControlAir\Intacct\Resources\Construction\ChangeRequests\UpdateChangeRequest;
use ControlAir\Intacct\Resources\Construction\ChangeRequests\UpdateChangeRequestLine;
use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\ChangeOrderInternalReference;
use ControlAir\Intacct\Tests\Support\ApiTestCase;
use ControlAir\Intacct\Tests\Support\QueueHttpClient;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\Dimensions;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ChangeRequestsClient::class)]
#[CoversClass(ChangeRequest::class)]
#[CoversClass(ChangeRequestLine::class)]
#[CoversClass(CreateChangeRequest::class)]
#[CoversClass(CreateChangeRequestLine::class)]
#[CoversClass(UpdateChangeRequest::class)]
#[CoversClass(UpdateChangeRequestLine::class)]
final class ChangeRequestsClientTest extends ApiTestCase
{
    private const BASE = 'https://api.intacct.com/ia/api/v1/';

    public function test_it_reads_a_change_request_with_its_lines(): void
    {
        [$changeRequests, $http] = $this->changeRequests($this->json([
            'ia::result' => [
                'key' => '12',
                'id' => 'CR-0012',
                'description' => 'Lobby flooring upgrade',
                'changeRequestDate' => '2026-09-10',
                'costEffectiveDate' => '2026-09-10',
                'priceEffectiveDate' => '2026-09-11',
                'changeRequestState' => 'posted',
                'projectContractLineSource' => 'changeRequestLine',
                'totalCost' => '12500.00',
                'totalPrice' => '14375.00',
                'changeRequestType' => ['key' => '2', 'id' => 'Owner'],
                'changeRequestStatus' => ['key' => '1', 'id' => 'Approved', 'workflowType' => 'approvedChange'],
                'project' => ['key' => '10', 'id' => 'PROJ-001', 'name' => 'Headquarters'],
                'projectCustomer' => ['key' => '4', 'id' => 'CUST-4', 'name' => 'Owner LLC'],
                'projectContract' => ['key' => '3', 'id' => 'PC-003', 'name' => 'Owner contract'],
                'projectContractLine' => ['key' => null, 'id' => null, 'name' => null],
                'projectChangeOrder' => ['key' => '7', 'id' => 'PCO-0007'],
                'location' => ['key' => '1', 'id' => 'HQ', 'name' => 'Headquarters'],
                'schedule' => ['responseDueDate' => '2026-09-30'],
                'internalReference' => ['referenceNumber' => 'INT-12'],
                'externalReference' => ['referenceNumber' => null],
                'nsp::RFI' => 'RFI-9',
                'changeRequestLines' => [
                    [
                        'key' => '120',
                        'id' => '120',
                        'lineNo' => '1',
                        'changeRequest' => ['key' => '12', 'id' => 'CR-0012'],
                        'quantity' => '500.00',
                        'externalUOM' => 'sq ft',
                        'unitCost' => '25.00',
                        'cost' => '12500.00',
                        'priceMarkupPercent' => '15.00',
                        'priceMarkupAmount' => '1875.00',
                        'unitPrice' => '28.75',
                        'price' => '14375.00',
                        'linePrice' => '14375.00',
                        'numberOfProductionUnits' => '500',
                        'productionUnitDescription' => 'Square feet',
                        'workflowType' => 'approvedChange',
                        'memo' => 'Porcelain tile',
                        'glAccount' => ['key' => '88', 'id' => '5000', 'name' => 'Job costs'],
                        'projectContract' => ['key' => '3', 'id' => 'PC-003'],
                        'projectContractLine' => ['key' => '31', 'id' => 'PCL-031'],
                        'projectChangeOrder' => ['key' => '7', 'id' => 'PCO-0007'],
                        'projectEstimate' => ['key' => null, 'id' => null],
                        'dimensions' => [
                            'project' => ['key' => '10', 'id' => 'PROJ-001'],
                            'task' => ['key' => '40', 'id' => '09-600'],
                            'costType' => ['key' => '400', 'id' => 'MAT'],
                            'location' => ['key' => '1', 'id' => 'HQ'],
                            'nsp::phase' => ['key' => '9'],
                        ],
                        'nsp::LINE_NOTE' => 'Rush',
                        'href' => '/objects/construction/change-request-line/120',
                    ],
                    ['key' => '121', 'lineNo' => 2, 'workflowType' => 'pendingChange'],
                ],
                'href' => '/objects/construction/change-request/12',
            ],
        ]));

        $changeRequest = $changeRequests->get(new ObjectKey('12'));

        self::assertSame(self::BASE.'objects/construction/change-request/12', $this->url($http->requests[0]));
        self::assertSame('CR-0012', $changeRequest->id->value);
        self::assertSame(ChangeRequestState::Posted, $changeRequest->state);
        self::assertSame(ChangeRequestContractLineSource::ChangeRequestLine, $changeRequest->projectContractLineSource);
        self::assertSame(ChangeRequestWorkflowType::ApprovedChange, $changeRequest->workflowType);
        self::assertSame('Approved', $changeRequest->changeRequestStatus?->id?->value);
        self::assertSame('Owner', $changeRequest->changeRequestType?->id?->value);
        self::assertSame('14375.00', $changeRequest->totalPrice?->value);
        self::assertSame('Owner LLC', $changeRequest->projectCustomer?->name);
        self::assertNull($changeRequest->projectContractLine);
        self::assertSame('PCO-0007', $changeRequest->projectChangeOrder?->id?->value);
        self::assertSame('2026-09-30', $changeRequest->schedule->responseDueDate?->value);
        self::assertSame('INT-12', $changeRequest->internalReference->referenceNumber);
        self::assertNull($changeRequest->externalReference->referenceNumber);
        self::assertSame('RFI-9', $changeRequest->customFields->get('RFI'));
        self::assertCount(2, $changeRequest->lines);

        $line = $changeRequest->lines[0];
        self::assertSame('120', $line->key->value);
        self::assertSame(1, $line->lineNumber);
        self::assertSame('CR-0012', $line->changeRequest?->id?->value);
        self::assertSame('500.00', $line->quantity?->value);
        self::assertSame('sq ft', $line->externalUnitOfMeasure);
        self::assertSame('25.00', $line->unitCost?->value);
        self::assertSame('12500.00', $line->cost?->value);
        self::assertSame('15.00', $line->priceMarkupPercent?->value);
        self::assertSame('1875.00', $line->priceMarkupAmount?->value);
        self::assertSame('28.75', $line->unitPrice?->value);
        self::assertSame('14375.00', $line->price?->value);
        self::assertSame('14375.00', $line->linePrice?->value);
        self::assertSame('500', $line->numberOfProductionUnits?->value);
        self::assertSame('Square feet', $line->productionUnitDescription);
        self::assertSame(ChangeRequestWorkflowType::ApprovedChange, $line->workflowType);
        self::assertSame('5000', $line->glAccount?->id?->value);
        self::assertSame('PCL-031', $line->projectContractLine?->id?->value);
        self::assertSame('PCO-0007', $line->projectChangeOrder?->id?->value);
        self::assertNull($line->projectEstimate);
        self::assertSame('PROJ-001', $line->dimensions->project?->id?->value);
        self::assertSame('09-600', $line->dimensions->task?->id?->value);
        self::assertSame('MAT', $line->dimensions->costType?->id?->value);
        self::assertSame('9', $line->dimensions->userDefined('phase')?->key?->value);
        self::assertSame('Rush', $line->customFields->get('LINE_NOTE'));
        self::assertSame(2, $changeRequest->lines[1]->lineNumber);
        self::assertSame(ChangeRequestWorkflowType::PendingChange, $changeRequest->lines[1]->workflowType);
    }

    public function test_query_returns_headers_only(): void
    {
        [$changeRequests, $http] = $this->changeRequests($this->json([
            'ia::result' => [[
                'key' => '12',
                'id' => 'CR-0012',
                'changeRequestState' => 'draft',
                'changeRequestStatus.key' => '1',
                'changeRequestStatus.id' => 'Approved',
                'changeRequestStatus.workflowType' => 'approvedChange',
                'project.id' => 'PROJ-001',
                'totalCost' => '12500.00',
                'schedule.responseDueDate' => '2026-09-30',
                'externalReference.signedBy.key' => '55',
            ]],
            'ia::meta' => ['totalCount' => 1, 'start' => 1, 'pageSize' => 100, 'next' => null],
        ]));

        $page = $changeRequests->query(new ResourceQuery(size: 5));

        $body = $this->jsonBody($http->requests[0]);
        self::assertSame('construction/change-request', $body['object']);
        self::assertIsArray($body['fields']);
        foreach (['key', 'id', 'changeRequestState', 'changeRequestDate', 'changeRequestStatus.workflowType', 'changeRequestType.id', 'totalCost', 'totalPrice', 'project.id', 'projectContractLineSource', 'projectChangeOrder.id', 'schedule.responseDueDate', 'internalReference.referenceNumber', 'externalReference.signedBy.key', 'href'] as $field) {
            self::assertContains($field, $body['fields']);
        }
        self::assertNotContains('changeRequestLines', $body['fields']);

        $changeRequest = $page->items[0];
        self::assertSame(ChangeRequestState::Draft, $changeRequest->state);
        self::assertSame(ChangeRequestWorkflowType::ApprovedChange, $changeRequest->workflowType);
        self::assertSame('Approved', $changeRequest->changeRequestStatus?->id?->value);
        self::assertSame('PROJ-001', $changeRequest->project?->id?->value);
        self::assertSame('2026-09-30', $changeRequest->schedule->responseDueDate?->value);
        self::assertSame('55', $changeRequest->externalReference->signedBy?->key?->value);
        self::assertSame([], $changeRequest->lines);
    }

    public function test_it_creates_a_change_request_with_cost_code_lines(): void
    {
        [$changeRequests, $http] = $this->changeRequests($this->mutation('12', 'CR-0012'));

        $changeRequests->create(new CreateChangeRequest(
            project: ObjectReference::byId('PROJ-001'),
            changeRequestDate: new LocalDate('2026-09-10'),
            lines: [new CreateChangeRequestLine(
                project: ObjectReference::byId('PROJ-001'),
                task: ObjectReference::byId('09-600'),
                costType: ObjectReference::byId('MAT'),
                quantity: new Decimal('500'),
                externalUnitOfMeasure: 'sq ft',
                unitCost: new Decimal('25.00'),
                priceMarkupPercent: new Decimal('15'),
                workflowType: ChangeRequestWorkflowType::PendingChange,
                glAccount: ObjectReference::byId('5000'),
                projectContractLine: ObjectReference::byKey('31'),
                dimensions: new Dimensions(
                    task: ObjectReference::byId('IGNORED'),
                    custom: (new CustomFields)->with('phase', ObjectReference::byKey('9')),
                ),
                customFields: (new CustomFields)->with('LINE_NOTE', 'Rush'),
            )],
            id: new ObjectId('CR-0012'),
            description: 'Lobby flooring upgrade',
            state: ChangeRequestState::Draft,
            changeRequestType: ObjectReference::byId('Owner'),
            projectContract: ObjectReference::byId('PC-003'),
            projectContractLineSource: ChangeRequestContractLineSource::ChangeRequestLine,
            internalReference: new ChangeOrderInternalReference(referenceNumber: 'INT-12'),
            customFields: (new CustomFields)->with('RFI', 'RFI-9'),
        ), new IdempotencyKey('cr-0012'));

        self::assertSame('POST', $http->requests[0]->getMethod());
        self::assertSame(self::BASE.'objects/construction/change-request', $this->url($http->requests[0]));
        self::assertSame('cr-0012', $http->requests[0]->getHeaderLine('Idempotency-Key'));
        self::assertSame([
            'id' => 'CR-0012',
            'description' => 'Lobby flooring upgrade',
            'project' => ['id' => 'PROJ-001'],
            'changeRequestDate' => '2026-09-10',
            'changeRequestState' => 'draft',
            'changeRequestType' => ['id' => 'Owner'],
            'projectContract' => ['id' => 'PC-003'],
            'projectContractLineSource' => 'changeRequestLine',
            'internalReference' => ['referenceNumber' => 'INT-12'],
            'changeRequestLines' => [[
                'quantity' => '500',
                'externalUOM' => 'sq ft',
                'unitCost' => '25.00',
                'priceMarkupPercent' => '15',
                'workflowType' => 'pendingChange',
                'glAccount' => ['id' => '5000'],
                'projectContractLine' => ['key' => '31'],
                'dimensions' => [
                    'task' => ['id' => '09-600'],
                    'nsp::phase' => ['key' => '9'],
                    'project' => ['id' => 'PROJ-001'],
                    'costType' => ['id' => 'MAT'],
                ],
                'nsp::LINE_NOTE' => 'Rush',
            ]],
            'nsp::RFI' => 'RFI-9',
        ], $this->jsonBody($http->requests[0]));
    }

    public function test_update_combines_header_changes_and_line_operations(): void
    {
        [$changeRequests, $http] = $this->changeRequests($this->mutation('12'));

        $changeRequests->update(
            new ObjectKey('12'),
            UpdateChangeRequest::state(ChangeRequestState::Posted)
                ->withDescription(null)
                ->withProjectChangeOrder(ObjectReference::byKey('7'))
                ->withUpdatedLine(
                    new ObjectKey('120'),
                    UpdateChangeRequestLine::quantity(new Decimal('550'))
                        ->withPriceMarkupAmount(null)
                        ->withMemo(null)
                        ->withCustomField('LINE_NOTE', null),
                )
                ->withRemovedLine(new ObjectKey('121'))
                ->withAddedLine(new CreateChangeRequestLine(
                    project: ObjectReference::byId('PROJ-001'),
                    task: ObjectReference::byId('09-600'),
                    costType: ObjectReference::byId('LAB'),
                    cost: new Decimal('800'),
                    price: new Decimal('920'),
                ))
                ->withCustomField('RFI', 'RFI-10'),
        );

        self::assertSame('PATCH', $http->requests[0]->getMethod());
        self::assertSame(self::BASE.'objects/construction/change-request/12', $this->url($http->requests[0]));
        self::assertSame([
            'changeRequestState' => 'posted',
            'description' => null,
            'projectChangeOrder' => ['key' => '7'],
            'nsp::RFI' => 'RFI-10',
            'changeRequestLines' => [
                ['key' => '120', 'quantity' => '550', 'priceMarkupAmount' => null, 'memo' => null, 'nsp::LINE_NOTE' => null],
                ['ia::operation' => 'delete', 'key' => '121'],
                [
                    'cost' => '800',
                    'price' => '920',
                    'dimensions' => [
                        'project' => ['id' => 'PROJ-001'],
                        'task' => ['id' => '09-600'],
                        'costType' => ['id' => 'LAB'],
                    ],
                ],
            ],
        ], $this->jsonBody($http->requests[0]));
    }

    public function test_line_only_and_custom_field_only_updates(): void
    {
        self::assertSame(
            ['changeRequestLines' => [['ia::operation' => 'delete', 'key' => '121']]],
            UpdateChangeRequest::removeLine(new ObjectKey('121'))->toArray(),
        );
        self::assertSame(
            ['changeRequestLines' => [['key' => '120', 'unitPrice' => '30.00', 'dimensions' => ['costType' => ['id' => 'SUB']]]]],
            UpdateChangeRequest::updateLine(
                new ObjectKey('120'),
                UpdateChangeRequestLine::unitPrice(new Decimal('30.00'))
                    ->withDimensions(new Dimensions(costType: ObjectReference::byId('SUB'))),
            )->toArray(),
        );
        self::assertSame(
            ['changeRequestLines' => [['quantity' => '1', 'dimensions' => ['project' => ['key' => '10'], 'task' => ['key' => '40'], 'costType' => ['key' => '400']]]]],
            UpdateChangeRequest::addLine(new CreateChangeRequestLine(
                project: ObjectReference::byKey('10'),
                task: ObjectReference::byKey('40'),
                costType: ObjectReference::byKey('400'),
                quantity: new Decimal('1'),
            ))->toArray(),
        );
        self::assertSame(
            ['nsp::RFI' => null, 'changeRequestDate' => '2026-09-12'],
            UpdateChangeRequest::customField('RFI', null)->withChangeRequestDate(new LocalDate('2026-09-12'))->toArray(),
        );
    }

    public function test_delete_create_many_and_delete_many(): void
    {
        [$changeRequests, $http] = $this->changeRequests(
            new Response(204),
            $this->json([
                'ia::result' => [
                    ['key' => '12', 'id' => 'CR-0012', 'ia::status' => 201],
                    ['ia::error' => ['code' => 'invalidRequest', 'message' => 'Project is required']],
                ],
                'ia::meta' => ['totalCount' => 2, 'totalSuccess' => 1, 'totalError' => 1],
            ]),
            new Response(204),
        );

        $deleted = $changeRequests->delete(new ObjectKey('11'));
        $created = $changeRequests->createMany([
            new CreateChangeRequest(ObjectReference::byId('PROJ-001'), new LocalDate('2026-09-10')),
            new CreateChangeRequest(ObjectReference::byId('PROJ-404'), new LocalDate('2026-09-10')),
        ]);
        $batch = $changeRequests->deleteMany([new ObjectKey('12'), new ObjectKey('13')], atomic: true);

        self::assertSame('11', $deleted->reference->key?->value);
        self::assertSame(self::BASE.'objects/construction/change-request/11', $this->url($http->requests[0]));
        self::assertFalse($created->isSuccessful());
        self::assertSame('12', $created->items[0]->reference?->key?->value);
        self::assertSame(self::BASE.'objects/construction/change-request', $this->url($http->requests[1]));
        self::assertSame('', $http->requests[1]->getHeaderLine('X-IA-API-Param-Transaction'));
        self::assertSame([
            ['project' => ['id' => 'PROJ-001'], 'changeRequestDate' => '2026-09-10'],
            ['project' => ['id' => 'PROJ-404'], 'changeRequestDate' => '2026-09-10'],
        ], json_decode((string) $http->requests[1]->getBody(), true, flags: JSON_THROW_ON_ERROR));
        self::assertTrue($batch->isSuccessful());
        self::assertSame('DELETE', $http->requests[2]->getMethod());
        self::assertSame(self::BASE.'objects/construction/change-request/12,13', $this->url($http->requests[2]));
        self::assertSame('true', $http->requests[2]->getHeaderLine('X-IA-API-Param-Transaction'));
    }

    /** @return array{ChangeRequestsClient, QueueHttpClient} */
    private function changeRequests(Response ...$responses): array
    {
        [$transport, $http] = $this->transport(...$responses);

        return [new ChangeRequestsClient($transport, new QueryClient($transport)), $http];
    }
}
