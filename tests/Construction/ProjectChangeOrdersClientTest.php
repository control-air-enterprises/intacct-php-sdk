<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Construction;

use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\ChangeOrderExternalReference;
use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\ChangeOrderInternalReference;
use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\ChangeOrderSchedule;
use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\CreateProjectChangeOrder;
use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\ProjectChangeOrder;
use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\ProjectChangeOrdersClient;
use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\ProjectChangeOrderState;
use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\UpdateProjectChangeOrder;
use ControlAir\Intacct\Tests\Support\ApiTestCase;
use ControlAir\Intacct\Tests\Support\QueueHttpClient;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProjectChangeOrdersClient::class)]
#[CoversClass(ProjectChangeOrder::class)]
#[CoversClass(CreateProjectChangeOrder::class)]
#[CoversClass(UpdateProjectChangeOrder::class)]
#[CoversClass(ChangeOrderSchedule::class)]
#[CoversClass(ChangeOrderInternalReference::class)]
#[CoversClass(ChangeOrderExternalReference::class)]
final class ProjectChangeOrdersClientTest extends ApiTestCase
{
    private const BASE = 'https://api.intacct.com/ia/api/v1/';

    public function test_it_reads_a_change_order_with_its_groups_and_custom_fields(): void
    {
        [$changeOrders, $http] = $this->changeOrders($this->json([
            'ia::result' => [
                'key' => '7',
                'id' => 'PCO-0007',
                'description' => 'Added lobby finishes',
                'projectChangeOrderDate' => '2026-09-14',
                'priceEffectiveDate' => '2026-09-15',
                'scope' => 'Upgrade lobby flooring',
                'inclusions' => 'Materials and labor',
                'exclusions' => 'Furniture',
                'terms' => 'Net 30',
                'state' => 'posted',
                'status' => 'active',
                'totalCost' => '12500.00',
                'totalPrice' => '14375.00',
                'project' => ['key' => '10', 'id' => 'PROJ-001', 'name' => 'Headquarters', 'href' => '/objects/projects/project/10'],
                'projectContract' => ['key' => '3', 'id' => 'PC-003', 'name' => 'Owner contract'],
                'projectContractLine' => ['key' => '31', 'id' => 'PCL-031', 'name' => 'Finishes'],
                'customer' => ['key' => '4', 'id' => 'CUST-4', 'name' => 'Owner LLC'],
                'changeRequestStatus' => ['key' => '1', 'id' => 'Approved'],
                'item' => ['key' => null, 'id' => null, 'name' => null],
                'location' => ['key' => '1', 'id' => 'HQ', 'name' => 'Headquarters'],
                'entity' => ['key' => null, 'id' => null, 'name' => null],
                'sendToContact' => ['key' => '55', 'id' => 'Jane Owner'],
                'attachment' => ['key' => null, 'id' => null],
                'schedule' => [
                    'scheduledStartDate' => '2026-10-01',
                    'scheduledCompletionDate' => '2026-11-30',
                    'revisedCompletionDate' => null,
                    'scheduleImpact' => '5 days',
                ],
                'internalReference' => [
                    'referenceNumber' => 'INT-77',
                    'source' => 'Field',
                    'initiatedBy' => ['key' => '20', 'id' => 'EMP-20', 'name' => 'Ada Builder'],
                    'approvedBy' => ['key' => null, 'id' => null, 'name' => null],
                    'approvedOnDate' => null,
                ],
                'externalReference' => [
                    'referenceNumber' => 'OWN-12',
                    'signedBy' => ['key' => '55', 'id' => 'Jane Owner'],
                    'signedOnDate' => '2026-09-20',
                ],
                'audit' => ['createdDateTime' => '2026-09-14T15:00:00Z'],
                'nsp::OWNER_REF' => 'A-1',
                'href' => '/objects/construction/project-change-order/7',
            ],
        ]));

        $changeOrder = $changeOrders->get(new ObjectKey('7'));

        self::assertSame('GET', $http->requests[0]->getMethod());
        self::assertSame(self::BASE.'objects/construction/project-change-order/7', $this->url($http->requests[0]));
        self::assertSame('PCO-0007', $changeOrder->id->value);
        self::assertSame(ProjectChangeOrderState::Posted, $changeOrder->state);
        self::assertSame(RecordStatus::Active, $changeOrder->status);
        self::assertSame('2026-09-14', $changeOrder->projectChangeOrderDate?->value);
        self::assertSame('14375.00', $changeOrder->totalPrice?->value);
        self::assertSame('12500.00', $changeOrder->totalCost?->value);
        self::assertSame('PROJ-001', $changeOrder->project?->id?->value);
        self::assertSame('PCL-031', $changeOrder->projectContractLine?->id?->value);
        self::assertSame('Owner LLC', $changeOrder->customer?->name);
        self::assertSame('Approved', $changeOrder->changeRequestStatus?->id?->value);
        self::assertNull($changeOrder->item);
        self::assertNull($changeOrder->entity);
        self::assertSame('Jane Owner', $changeOrder->sendToContact?->id?->value);
        self::assertSame('Net 30', $changeOrder->terms);
        self::assertSame('2026-10-01', $changeOrder->schedule->scheduledStartDate?->value);
        self::assertNull($changeOrder->schedule->revisedCompletionDate);
        self::assertSame('5 days', $changeOrder->schedule->scheduleImpact);
        self::assertSame('Ada Builder', $changeOrder->internalReference->initiatedBy?->name);
        self::assertNull($changeOrder->internalReference->approvedBy);
        self::assertSame('2026-09-20', $changeOrder->externalReference->signedOnDate?->value);
        self::assertSame('A-1', $changeOrder->customFields->get('OWNER_REF'));
        self::assertCount(1, $changeOrder->customFields->all());
    }

    public function test_query_selects_the_mapped_fields_and_expands_dotted_rows(): void
    {
        [$changeOrders, $http] = $this->changeOrders($this->json([
            'ia::result' => [[
                'key' => '7',
                'id' => 'PCO-0007',
                'state' => 'draft',
                'totalPrice' => '14375.00',
                'project.key' => '10',
                'project.id' => 'PROJ-001',
                'schedule.scheduledStartDate' => '2026-10-01',
                'internalReference.issuedBy.key' => '21',
                'internalReference.issuedBy.id' => 'EMP-21',
                'externalReference.referenceNumber' => 'OWN-12',
                'nsp::OWNER_REF' => 'A-1',
            ]],
            'ia::meta' => ['totalCount' => 1, 'start' => 1, 'pageSize' => 100, 'next' => null],
        ]));

        $page = $changeOrders->query((new ResourceQuery(size: 5))->withFields('nsp::OWNER_REF'));

        $body = $this->jsonBody($http->requests[0]);
        self::assertSame(self::BASE.'services/core/query', $this->url($http->requests[0]));
        self::assertSame('construction/project-change-order', $body['object']);
        self::assertSame(5, $body['size']);
        self::assertIsArray($body['fields']);
        foreach (['key', 'id', 'state', 'totalCost', 'totalPrice', 'project.id', 'projectContract.key', 'changeRequestStatus.id', 'schedule.scheduleImpact', 'internalReference.verbalApprovalBy.name', 'externalReference.signedBy.id', 'href'] as $field) {
            self::assertContains($field, $body['fields']);
        }
        // Sage rejects `status` in a query.
        self::assertNotContains('status', $body['fields']);
        self::assertSame('nsp::OWNER_REF', end($body['fields']));

        $changeOrder = $page->items[0];
        self::assertSame(ProjectChangeOrderState::Draft, $changeOrder->state);
        self::assertNull($changeOrder->status);
        self::assertSame('10', $changeOrder->project?->key?->value);
        self::assertSame('2026-10-01', $changeOrder->schedule->scheduledStartDate?->value);
        self::assertSame('EMP-21', $changeOrder->internalReference->issuedBy?->id?->value);
        self::assertSame('OWN-12', $changeOrder->externalReference->referenceNumber);
        self::assertSame('A-1', $changeOrder->customFields->get('OWNER_REF'));
    }

    public function test_it_creates_a_change_order_with_set_group_fields_only(): void
    {
        [$changeOrders, $http] = $this->changeOrders($this->mutation('7', 'PCO-0007'));

        $result = $changeOrders->create(new CreateProjectChangeOrder(
            project: ObjectReference::byId('PROJ-001'),
            projectChangeOrderDate: new LocalDate('2026-09-14'),
            id: new ObjectId('PCO-0007'),
            description: 'Added lobby finishes',
            state: ProjectChangeOrderState::Draft,
            status: RecordStatus::Active,
            projectContract: ObjectReference::byId('PC-003'),
            changeRequestStatus: ObjectReference::byId('Approved'),
            sendToContact: ObjectReference::byKey('55'),
            scope: 'Upgrade lobby flooring',
            schedule: new ChangeOrderSchedule(scheduledStartDate: new LocalDate('2026-10-01'), scheduleImpact: '5 days'),
            internalReference: new ChangeOrderInternalReference(initiatedBy: ObjectReference::byId('EMP-20')),
            externalReference: new ChangeOrderExternalReference,
            customFields: (new CustomFields)->with('OWNER_REF', 'A-1'),
        ), new IdempotencyKey('pco-0007'));

        self::assertSame('7', $result->reference->key?->value);
        self::assertSame('POST', $http->requests[0]->getMethod());
        self::assertSame(self::BASE.'objects/construction/project-change-order', $this->url($http->requests[0]));
        self::assertSame('pco-0007', $http->requests[0]->getHeaderLine('Idempotency-Key'));
        self::assertSame([
            'id' => 'PCO-0007',
            'description' => 'Added lobby finishes',
            'project' => ['id' => 'PROJ-001'],
            'projectChangeOrderDate' => '2026-09-14',
            'state' => 'draft',
            'status' => 'active',
            'projectContract' => ['id' => 'PC-003'],
            'changeRequestStatus' => ['id' => 'Approved'],
            'sendToContact' => ['key' => '55'],
            'scope' => 'Upgrade lobby flooring',
            'schedule' => ['scheduledStartDate' => '2026-10-01', 'scheduleImpact' => '5 days'],
            'internalReference' => ['initiatedBy' => ['id' => 'EMP-20']],
            'nsp::OWNER_REF' => 'A-1',
        ], $this->jsonBody($http->requests[0]));
    }

    public function test_update_sends_only_named_fields_and_explicit_nulls(): void
    {
        [$changeOrders, $http] = $this->changeOrders($this->mutation('7'), $this->mutation('7'));

        $changeOrders->update(
            new ObjectKey('7'),
            UpdateProjectChangeOrder::state(ProjectChangeOrderState::Posted)
                ->withDescription(null)
                ->withPriceEffectiveDate(new LocalDate('2026-09-16'))
                ->withItem(null)
                ->withSchedule(new ChangeOrderSchedule(revisedCompletionDate: new LocalDate('2026-12-05')))
                ->withCustomField('OWNER_REF', null),
            new IdempotencyKey('pco-0007-post'),
        );
        $changeOrders->update(new ObjectKey('7'), UpdateProjectChangeOrder::customField('OWNER_REF', 'A-2'));

        self::assertSame('PATCH', $http->requests[0]->getMethod());
        self::assertSame(self::BASE.'objects/construction/project-change-order/7', $this->url($http->requests[0]));
        self::assertSame('pco-0007-post', $http->requests[0]->getHeaderLine('Idempotency-Key'));
        self::assertSame([
            'state' => 'posted',
            'description' => null,
            'priceEffectiveDate' => '2026-09-16',
            'item' => null,
            'schedule' => [
                'scheduledStartDate' => null,
                'actualStartDate' => null,
                'scheduledCompletionDate' => null,
                'revisedCompletionDate' => '2026-12-05',
                'substantialCompletionDate' => null,
                'actualCompletionDate' => null,
                'noticeToProceedDate' => null,
                'responseDueDate' => null,
                'executedOnDate' => null,
                'scheduleImpact' => null,
            ],
            'nsp::OWNER_REF' => null,
        ], $this->jsonBody($http->requests[0]));
        self::assertSame(['nsp::OWNER_REF' => 'A-2'], $this->jsonBody($http->requests[1]));
    }

    public function test_update_writes_reference_groups_and_references(): void
    {
        $update = UpdateProjectChangeOrder::description('Revised')
            ->withProject(ObjectReference::byId('PROJ-002'))
            ->withProjectContractLine(ObjectReference::byKey('31'))
            ->withExternalReference(new ChangeOrderExternalReference(
                referenceNumber: 'OWN-13',
                approvedBy: ObjectReference::byKey('55'),
                approvedOnDate: new LocalDate('2026-09-21'),
            ));

        self::assertSame([
            'description' => 'Revised',
            'project' => ['id' => 'PROJ-002'],
            'projectContractLine' => ['key' => '31'],
            'externalReference' => [
                'referenceNumber' => 'OWN-13',
                'approvedBy' => ['key' => '55'],
                'approvedOnDate' => '2026-09-21',
                'signedBy' => null,
                'signedOnDate' => null,
                'verbalApprovalBy' => null,
            ],
        ], $update->toArray());
    }

    public function test_delete_create_many_and_delete_many(): void
    {
        [$changeOrders, $http] = $this->changeOrders(
            new Response(204),
            $this->json([
                'ia::result' => [
                    ['key' => '7', 'id' => 'PCO-0007', 'ia::status' => 201],
                    ['key' => '8', 'id' => 'PCO-0008', 'ia::status' => 201],
                ],
                'ia::meta' => ['totalCount' => 2, 'totalSuccess' => 2, 'totalError' => 0],
            ]),
            new Response(204),
        );

        $deleted = $changeOrders->delete(new ObjectKey('6'));
        $created = $changeOrders->createMany([
            new CreateProjectChangeOrder(ObjectReference::byId('PROJ-001'), new LocalDate('2026-09-14')),
            new CreateProjectChangeOrder(ObjectReference::byId('PROJ-001'), new LocalDate('2026-09-15'), new ObjectId('PCO-0008')),
        ], atomic: true);
        $batch = $changeOrders->deleteMany([new ObjectKey('7'), new ObjectKey('8')]);

        self::assertSame('6', $deleted->reference->key?->value);
        self::assertSame('DELETE', $http->requests[0]->getMethod());
        self::assertSame(self::BASE.'objects/construction/project-change-order/6', $this->url($http->requests[0]));
        self::assertTrue($created->isSuccessful());
        self::assertSame('8', $created->items[1]->reference?->key?->value);
        self::assertSame('true', $http->requests[1]->getHeaderLine('X-IA-API-Param-Transaction'));
        self::assertSame([
            ['project' => ['id' => 'PROJ-001'], 'projectChangeOrderDate' => '2026-09-14'],
            ['id' => 'PCO-0008', 'project' => ['id' => 'PROJ-001'], 'projectChangeOrderDate' => '2026-09-15'],
        ], json_decode((string) $http->requests[1]->getBody(), true, flags: JSON_THROW_ON_ERROR));
        self::assertTrue($batch->isSuccessful());
        self::assertSame(self::BASE.'objects/construction/project-change-order/7,8', $this->url($http->requests[2]));
    }

    /** @return array{ProjectChangeOrdersClient, QueueHttpClient} */
    private function changeOrders(Response ...$responses): array
    {
        [$transport, $http] = $this->transport(...$responses);

        return [new ProjectChangeOrdersClient($transport, new QueryClient($transport)), $http];
    }
}
