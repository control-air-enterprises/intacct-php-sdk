<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Construction;

use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Resources\Construction\ProjectContracts\CreateProjectContract;
use ControlAir\Intacct\Resources\Construction\ProjectContracts\ProjectContract;
use ControlAir\Intacct\Resources\Construction\ProjectContracts\ProjectContractBilling;
use ControlAir\Intacct\Resources\Construction\ProjectContracts\ProjectContractSchedule;
use ControlAir\Intacct\Resources\Construction\ProjectContracts\ProjectContractsClient;
use ControlAir\Intacct\Resources\Construction\ProjectContracts\ProjectContractSummary;
use ControlAir\Intacct\Resources\Construction\ProjectContracts\UpdateProjectContract;
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

#[CoversClass(ProjectContractsClient::class)]
#[CoversClass(ProjectContract::class)]
#[CoversClass(CreateProjectContract::class)]
#[CoversClass(UpdateProjectContract::class)]
#[CoversClass(ProjectContractSummary::class)]
#[CoversClass(ProjectContractBilling::class)]
#[CoversClass(ProjectContractSchedule::class)]
final class ProjectContractsClientTest extends ApiTestCase
{
    private const URL = 'https://api.intacct.com/ia/api/v1/objects/construction/project-contract';

    public function test_it_maps_a_contract_with_its_groups_and_custom_fields(): void
    {
        [$contracts, $http] = $this->contracts($this->json([
            'ia::result' => [
                'key' => '3',
                'id' => 'PC-1001',
                'name' => 'Clinic HVAC retrofit',
                'description' => 'Prime contract',
                'contractDate' => '2026-04-01',
                'project' => ['key' => '1', 'id' => 'PRJ-1001', 'name' => 'Clinic retrofit', 'href' => '/objects/projects/project/1'],
                'customer' => ['key' => '4', 'id' => 'CUST-01', 'name' => 'Example Health', 'href' => '/objects/accounts-receivable/customer/4'],
                'location' => ['key' => '5', 'id' => 'HQ', 'name' => 'Headquarters'],
                'projectContractType' => ['key' => null, 'id' => null],
                'entity' => ['key' => null, 'id' => null, 'name' => null],
                'isBillable' => true,
                'excludeFromWIPReporting' => false,
                'status' => 'active',
                'scope' => 'Replace rooftop units',
                'inclusions' => null,
                'exclusions' => 'Roofing',
                'terms' => 'Net 30',
                'summary' => [
                    'originalPrice' => '250000.00',
                    'revisionPrice' => '0.00',
                    'approvedChangePrice' => '12500.50',
                    'pendingChangePrice' => '0.00',
                    'otherPrice' => '0.00',
                    'totalPrice' => '262500.50',
                    'forecastPrice' => null,
                ],
                'billing' => [
                    'billedPrice' => '50000.00',
                    'totalBilledNetRetainage' => '45000.00',
                    'percentBilled' => '19.05',
                    'percentBilledNetRetainage' => '17.14',
                    'totalRetainageHeld' => '5000.00',
                    'totalRetainageReleased' => '0.00',
                    'retainageBalance' => '5000.00',
                    'balanceToBill' => '212500.50',
                    'balanceToBillNetRetainage' => '217500.50',
                    'totalPaymentsReceived' => '45000.00',
                    'lastApplicationNumber' => '2',
                    'netTotalBilled' => '50000.00',
                    'netTotalPaymentsReceived' => '45000.00',
                    'currency' => ['billingCurrency' => null],
                ],
                'schedule' => [
                    'scheduledStartDate' => '2026-05-01',
                    'scheduledCompletionDate' => '2026-12-15',
                    'actualStartDate' => null,
                    'scheduleImpact' => null,
                ],
                'internalReference' => ['referenceNumber' => 'INT-77', 'issuedBy' => ['key' => null, 'id' => null, 'name' => null]],
                'externalReference' => ['referenceNumber' => 'OWNER-9', 'signedBy' => ['key' => null, 'id' => null]],
                'audit' => ['createdDateTime' => '2026-04-01T12:00:00Z'],
                'nsp::OWNER_PM' => 'Jordan',
                'href' => '/objects/construction/project-contract/3',
            ],
            'ia::meta' => ['totalCount' => 1, 'totalSuccess' => 1, 'totalError' => 0],
        ]));

        $contract = $contracts->get(new ObjectKey('3'));

        self::assertSame(self::URL.'/3', $this->url($http->requests[0]));
        self::assertSame('PC-1001', $contract->id->value);
        self::assertSame('Clinic HVAC retrofit', $contract->name);
        self::assertSame('2026-04-01', $contract->contractDate?->value);
        self::assertSame(RecordStatus::Active, $contract->status);
        self::assertSame('PRJ-1001', $contract->project?->id?->value);
        self::assertSame('Example Health', $contract->customer?->name);
        self::assertSame('HQ', $contract->location?->id?->value);
        self::assertNull($contract->projectContractType);
        self::assertNull($contract->entity);
        self::assertTrue($contract->billable);
        self::assertFalse($contract->excludeFromWipReporting);
        self::assertSame('Replace rooftop units', $contract->scope);
        self::assertNull($contract->inclusions);
        self::assertSame('Roofing', $contract->exclusions);
        self::assertSame('Net 30', $contract->terms);
        self::assertSame('250000.00', $contract->summary?->originalPrice?->value);
        self::assertSame('12500.50', $contract->summary->approvedChangePrice?->value);
        self::assertSame('262500.50', $contract->summary->totalPrice?->value);
        self::assertNull($contract->summary->forecastPrice);
        self::assertSame('50000.00', $contract->billing?->billedPrice?->value);
        self::assertSame('5000.00', $contract->billing->totalRetainageHeld?->value);
        self::assertSame('212500.50', $contract->billing->balanceToBill?->value);
        self::assertSame('2', $contract->billing->lastApplicationNumber);
        self::assertSame('2026-05-01', $contract->schedule?->scheduledStartDate?->value);
        self::assertSame('2026-12-15', $contract->schedule->scheduledCompletionDate?->value);
        self::assertNull($contract->schedule->actualStartDate);
        self::assertSame('INT-77', $contract->internalReferenceNumber);
        self::assertSame('OWNER-9', $contract->externalReferenceNumber);
        self::assertSame('Jordan', $contract->customFields->get('OWNER_PM'));
        self::assertCount(1, $contract->customFields->all());
    }

    public function test_query_selects_every_mapped_field_and_expands_dotted_rows(): void
    {
        [$contracts, $http] = $this->contracts($this->json([
            'ia::result' => [[
                'key' => '3',
                'id' => 'PC-1001',
                'name' => 'Clinic HVAC retrofit',
                'status' => 'inactive',
                'project.key' => '1',
                'project.id' => 'PRJ-1001',
                'project.name' => 'Clinic retrofit',
                'projectContractType.key' => null,
                'projectContractType.id' => null,
                'summary.originalPrice' => '250000.00',
                'summary.totalPrice' => '262500.50',
                'billing.balanceToBill' => '212500.50',
                'schedule.scheduledStartDate' => '2026-05-01',
                'internalReference.referenceNumber' => null,
                'nsp::OWNER_PM' => 'Jordan',
            ]],
            'ia::meta' => ['totalCount' => 1, 'start' => 1, 'pageSize' => 100, 'next' => null],
        ]));

        $page = $contracts->query((new ResourceQuery(size: 5))->withFields('nsp::OWNER_PM'));

        $body = $this->jsonBody($http->requests[0]);
        self::assertSame('construction/project-contract', $body['object']);
        self::assertSame(5, $body['size']);
        self::assertIsArray($body['fields']);
        foreach ([
            'key', 'id', 'name', 'contractDate', 'status', 'isBillable', 'project.id', 'customer.name',
            'projectContractType.id', 'location.key', 'summary.totalPrice', 'summary.forecastPrice',
            'billing.balanceToBill', 'billing.lastApplicationNumber', 'schedule.executedOnDate',
            'internalReference.referenceNumber', 'externalReference.referenceNumber', 'href',
        ] as $field) {
            self::assertContains($field, $body['fields']);
        }
        self::assertNotContains('projectContractType.name', $body['fields']);
        self::assertSame('nsp::OWNER_PM', end($body['fields']));

        $contract = $page->items[0];
        self::assertSame(RecordStatus::Inactive, $contract->status);
        self::assertSame('Clinic retrofit', $contract->project?->name);
        self::assertNull($contract->projectContractType);
        self::assertSame('262500.50', $contract->summary?->totalPrice?->value);
        self::assertSame('212500.50', $contract->billing?->balanceToBill?->value);
        self::assertSame('2026-05-01', $contract->schedule?->scheduledStartDate?->value);
        self::assertNull($contract->internalReferenceNumber);
        self::assertSame('Jordan', $contract->customFields->get('OWNER_PM'));
    }

    public function test_it_sends_create_payloads_with_groups_and_custom_fields(): void
    {
        [$contracts, $http] = $this->contracts($this->mutation('3', 'PC-1001'), $this->mutation('3', 'PC-1001'));

        $result = $contracts->create(new CreateProjectContract(
            id: new ObjectId('PC-1001'),
            name: 'Clinic HVAC retrofit',
            project: ObjectReference::byId('PRJ-1001'),
            customer: ObjectReference::byId('CUST-01'),
            contractDate: new LocalDate('2026-04-01'),
            description: 'Prime contract',
            projectContractType: ObjectReference::byId('LUMP'),
            location: ObjectReference::byKey('5'),
            billable: true,
            excludeFromWipReporting: false,
            scope: 'Replace rooftop units',
            terms: 'Net 30',
            status: RecordStatus::Active,
            schedule: new ProjectContractSchedule(
                scheduledStartDate: new LocalDate('2026-05-01'),
                scheduledCompletionDate: new LocalDate('2026-12-15'),
            ),
            internalReferenceNumber: 'INT-77',
            externalReferenceNumber: 'OWNER-9',
            customFields: (new CustomFields)->with('OWNER_PM', 'Jordan'),
        ), new IdempotencyKey('contract-pc-1001'));
        $contracts->create(new CreateProjectContract(new ObjectId('PC-1002'), 'Minimal'));

        self::assertSame('3', $result->reference->key?->value);
        self::assertSame('POST', $http->requests[0]->getMethod());
        self::assertSame(self::URL, $this->url($http->requests[0]));
        self::assertSame('contract-pc-1001', $http->requests[0]->getHeaderLine('Idempotency-Key'));
        self::assertSame([
            'id' => 'PC-1001',
            'name' => 'Clinic HVAC retrofit',
            'description' => 'Prime contract',
            'contractDate' => '2026-04-01',
            'project' => ['id' => 'PRJ-1001'],
            'customer' => ['id' => 'CUST-01'],
            'projectContractType' => ['id' => 'LUMP'],
            'location' => ['key' => '5'],
            'isBillable' => true,
            'excludeFromWIPReporting' => false,
            'scope' => 'Replace rooftop units',
            'terms' => 'Net 30',
            'status' => 'active',
            'schedule' => ['scheduledStartDate' => '2026-05-01', 'scheduledCompletionDate' => '2026-12-15'],
            'internalReference' => ['referenceNumber' => 'INT-77'],
            'externalReference' => ['referenceNumber' => 'OWNER-9'],
            'nsp::OWNER_PM' => 'Jordan',
        ], $this->jsonBody($http->requests[0]));
        self::assertSame(['id' => 'PC-1002', 'name' => 'Minimal'], $this->jsonBody($http->requests[1]));
    }

    public function test_updates_keep_omitted_fields_out_and_send_explicit_nulls(): void
    {
        [$contracts, $http] = $this->contracts($this->mutation('3'), $this->mutation('3'), $this->mutation('3'));

        $contracts->update(new ObjectKey('3'), UpdateProjectContract::status(RecordStatus::Inactive));
        $contracts->update(
            new ObjectKey('3'),
            UpdateProjectContract::description(null)
                ->withName('Clinic retrofit, phase 1')
                ->withContractDate(new LocalDate('2026-04-02'))
                ->withProjectContractType(null)
                ->withCustomer(ObjectReference::byKey('4'))
                ->withBillable(false)
                ->withExcludeFromWipReporting(true)
                ->withInclusions(null)
                ->withSchedule(new ProjectContractSchedule(actualStartDate: new LocalDate('2026-05-04')))
                ->withSchedule(new ProjectContractSchedule(scheduleImpact: 'Two weeks'))
                ->withExternalReferenceNumber(null)
                ->withCustomField('OWNER_PM', null),
            new IdempotencyKey('contract-3-rename'),
        );
        $contracts->update(new ObjectKey('3'), UpdateProjectContract::customFields(new CustomFields(['OWNER_PM' => 'Sam'])));

        self::assertSame('PATCH', $http->requests[0]->getMethod());
        self::assertSame(self::URL.'/3', $this->url($http->requests[0]));
        self::assertSame(['status' => 'inactive'], $this->jsonBody($http->requests[0]));
        self::assertSame('contract-3-rename', $http->requests[1]->getHeaderLine('Idempotency-Key'));
        self::assertSame([
            'description' => null,
            'name' => 'Clinic retrofit, phase 1',
            'contractDate' => '2026-04-02',
            'projectContractType' => null,
            'customer' => ['key' => '4'],
            'isBillable' => false,
            'excludeFromWIPReporting' => true,
            'inclusions' => null,
            'schedule' => ['actualStartDate' => '2026-05-04', 'scheduleImpact' => 'Two weeks'],
            'externalReference' => ['referenceNumber' => null],
            'nsp::OWNER_PM' => null,
        ], $this->jsonBody($http->requests[1]));
        self::assertSame(['nsp::OWNER_PM' => 'Sam'], $this->jsonBody($http->requests[2]));
    }

    public function test_delete_and_batch_writes(): void
    {
        [$contracts, $http] = $this->contracts(
            new Response(204),
            $this->json([
                'ia::result' => [
                    ['key' => '3', 'id' => 'PC-1001', 'ia::status' => 201],
                    ['key' => '4', 'id' => 'PC-1002', 'ia::status' => 201],
                ],
                'ia::meta' => ['totalCount' => 2, 'totalSuccess' => 2, 'totalError' => 0],
            ]),
            new Response(204),
        );

        $contracts->delete(new ObjectKey('3'));
        $created = $contracts->createMany([
            new CreateProjectContract(new ObjectId('PC-1001'), 'First'),
            new CreateProjectContract(new ObjectId('PC-1002'), 'Second', project: ObjectReference::byId('PRJ-1001')),
        ], atomic: true);
        $deleted = $contracts->deleteMany([new ObjectKey('3'), new ObjectKey('4')]);

        self::assertSame('DELETE', $http->requests[0]->getMethod());
        self::assertSame(self::URL.'/3', $this->url($http->requests[0]));
        self::assertTrue($created->isSuccessful());
        self::assertSame('4', $created->items[1]->reference?->key?->value);
        self::assertSame('true', $http->requests[1]->getHeaderLine('X-IA-API-Param-Transaction'));
        self::assertSame(
            [['id' => 'PC-1001', 'name' => 'First'], ['id' => 'PC-1002', 'name' => 'Second', 'project' => ['id' => 'PRJ-1001']]],
            json_decode((string) $http->requests[1]->getBody(), true, flags: JSON_THROW_ON_ERROR),
        );
        self::assertTrue($deleted->isSuccessful());
        self::assertSame(self::URL.'/3,4', $this->url($http->requests[2]));
    }

    public function test_it_rejects_a_blank_name(): void
    {
        $this->expectException(InvalidArgument::class);

        UpdateProjectContract::name(' ');
    }

    /** @return array{ProjectContractsClient, QueueHttpClient} */
    private function contracts(Response ...$responses): array
    {
        [$transport, $http] = $this->transport(...$responses);

        return [new ProjectContractsClient($transport, new QueryClient($transport)), $http];
    }
}
