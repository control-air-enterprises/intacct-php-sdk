<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Construction;

use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Resources\Construction\ProjectContractLines\CreateProjectContractLine;
use ControlAir\Intacct\Resources\Construction\ProjectContractLines\CreateProjectContractLineEntry;
use ControlAir\Intacct\Resources\Construction\ProjectContractLines\ProjectContractLine;
use ControlAir\Intacct\Resources\Construction\ProjectContractLines\ProjectContractLineBilling;
use ControlAir\Intacct\Resources\Construction\ProjectContractLines\ProjectContractLineBillingType;
use ControlAir\Intacct\Resources\Construction\ProjectContractLines\ProjectContractLineEntry;
use ControlAir\Intacct\Resources\Construction\ProjectContractLines\ProjectContractLineEntryWorkflowType;
use ControlAir\Intacct\Resources\Construction\ProjectContractLines\ProjectContractLineMaximumBilling;
use ControlAir\Intacct\Resources\Construction\ProjectContractLines\ProjectContractLinesClient;
use ControlAir\Intacct\Resources\Construction\ProjectContractLines\UpdateProjectContractLine;
use ControlAir\Intacct\Resources\Construction\ProjectContracts\ProjectContractSchedule;
use ControlAir\Intacct\Tests\Support\ApiTestCase;
use ControlAir\Intacct\Tests\Support\QueueHttpClient;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\Dimensions;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProjectContractLinesClient::class)]
#[CoversClass(ProjectContractLine::class)]
#[CoversClass(ProjectContractLineEntry::class)]
#[CoversClass(ProjectContractLineBilling::class)]
#[CoversClass(CreateProjectContractLine::class)]
#[CoversClass(CreateProjectContractLineEntry::class)]
#[CoversClass(UpdateProjectContractLine::class)]
final class ProjectContractLinesClientTest extends ApiTestCase
{
    private const URL = 'https://api.intacct.com/ia/api/v1/objects/construction/project-contract-line';

    public function test_it_maps_a_line_with_its_entries_dimensions_and_custom_fields(): void
    {
        [$lines, $http] = $this->lines($this->json([
            'ia::result' => [
                'key' => '22',
                'id' => '01',
                'name' => 'Mechanical',
                'description' => null,
                'projectContract' => ['key' => '3', 'id' => 'PC-1001', 'name' => 'Clinic HVAC retrofit', 'href' => '/objects/construction/project-contract/3'],
                'parent' => ['key' => null, 'id' => null, 'name' => null],
                'contractLineDate' => '2026-04-01',
                'glAccount' => ['key' => '99', 'id' => '4000', 'name' => 'Contract revenue'],
                'retainagePercentage' => '10.00',
                'isBillable' => true,
                'excludeFromGLBudget' => false,
                'billingSetup' => [
                    'billingType' => 'progressBill',
                    'maximumBilling' => 'specifiedAmount',
                    'maximumBillingAmount' => '120000.00',
                    'summarizeBill' => false,
                ],
                'summary' => ['originalPrice' => '100000.00', 'approvedChangePrice' => '2500.00', 'totalPrice' => '102500.00'],
                'billing' => [
                    'billedPrice' => '20000.00',
                    'billedNetRetainage' => '18000.00',
                    'percentBilled' => '19.51',
                    'retainageHeld' => '2000.00',
                    'retainageBalance' => '2000.00',
                    'paymentsReceived' => '18000.00',
                    'previouslyAppliedPrice' => null,
                    'externalReferenceNumber' => 'SOV-01',
                ],
                'schedule' => ['scheduledStartDate' => '2026-05-01', 'scheduleImpact' => null],
                'internalReference' => ['referenceNumber' => null],
                'externalReference' => ['referenceNumber' => 'OWNER-9-01'],
                'dimensions' => [
                    'location' => ['key' => '4', 'id' => 'HQ', 'name' => 'Headquarters'],
                    'project' => ['key' => '1', 'id' => 'PRJ-1001', 'name' => 'Clinic retrofit'],
                    'customer' => ['key' => '4', 'id' => 'CUST-01'],
                    'department' => ['key' => null, 'id' => null, 'name' => null],
                    'nsp::phase' => ['key' => '10002'],
                ],
                'status' => 'active',
                'projectContractLineEntries' => [[
                    'id' => '31',
                    'key' => '31',
                    'projectContractLine' => ['key' => '22', 'id' => '01'],
                    'workflowType' => 'original',
                    'dimensions' => ['item' => ['key' => '8', 'id' => 'REV', 'name' => 'Revenue']],
                    'quantity' => '1.00',
                    'externalUOM' => 'LS',
                    'unitPrice' => '100000.00',
                    'price' => '100000.00',
                    'priceMarkupPercent' => null,
                    'priceMarkupAmount' => '0.00',
                    'linePrice' => '100000.00',
                    'memo' => 'Original contract amount',
                    'priceEffectiveDate' => '2026-04-01',
                    'nsp::COST_CODE' => '23-00',
                    'href' => '/objects/construction/project-contract-line-entry/31',
                ]],
                'mappedTasks' => [],
                'changeRequestEntries' => [],
                'nsp::BID_PACKAGE' => 'BP-2',
                'href' => '/objects/construction/project-contract-line/22',
            ],
            'ia::meta' => ['totalCount' => 1, 'totalSuccess' => 1, 'totalError' => 0],
        ]));

        $line = $lines->get(new ObjectKey('22'));

        self::assertSame(self::URL.'/22', $this->url($http->requests[0]));
        self::assertSame('01', $line->id->value);
        self::assertSame('Mechanical', $line->name);
        self::assertSame('PC-1001', $line->projectContract->id?->value);
        self::assertNull($line->parent);
        self::assertSame('2026-04-01', $line->contractLineDate?->value);
        self::assertSame(RecordStatus::Active, $line->status);
        self::assertSame('4000', $line->glAccount?->id?->value);
        self::assertSame('10.00', $line->retainagePercentage?->value);
        self::assertTrue($line->billable);
        self::assertFalse($line->excludeFromGlBudget);
        self::assertSame(ProjectContractLineBillingType::ProgressBill, $line->billingType);
        self::assertSame(ProjectContractLineMaximumBilling::SpecifiedAmount, $line->maximumBilling);
        self::assertSame('120000.00', $line->maximumBillingAmount?->value);
        self::assertFalse($line->summarizeBill);
        self::assertSame('102500.00', $line->summary?->totalPrice?->value);
        self::assertNull($line->summary->revisionPrice);
        self::assertSame('20000.00', $line->billing?->billedPrice?->value);
        self::assertSame('2000.00', $line->billing->retainageHeld?->value);
        self::assertNull($line->billing->previouslyAppliedPrice);
        self::assertSame('SOV-01', $line->billing->externalReferenceNumber);
        self::assertSame('2026-05-01', $line->schedule?->scheduledStartDate?->value);
        self::assertNull($line->internalReferenceNumber);
        self::assertSame('OWNER-9-01', $line->externalReferenceNumber);
        self::assertSame('PRJ-1001', $line->dimensions->project?->id?->value);
        self::assertSame('HQ', $line->dimensions->location?->id?->value);
        self::assertNull($line->dimensions->department);
        self::assertSame('10002', $line->dimensions->userDefined('phase')?->key?->value);
        self::assertSame('BP-2', $line->customFields->get('BID_PACKAGE'));
        self::assertCount(1, $line->customFields->all());

        self::assertCount(1, $line->entries);
        $entry = $line->entries[0];
        self::assertSame('31', $entry->key->value);
        self::assertSame(ProjectContractLineEntryWorkflowType::Original, $entry->workflowType);
        self::assertSame('1.00', $entry->quantity?->value);
        self::assertSame('100000.00', $entry->unitPrice?->value);
        self::assertSame('100000.00', $entry->price?->value);
        self::assertNull($entry->priceMarkupPercent);
        self::assertSame('0.00', $entry->priceMarkupAmount?->value);
        self::assertSame('100000.00', $entry->linePrice?->value);
        self::assertSame('LS', $entry->externalUom);
        self::assertSame('Original contract amount', $entry->memo);
        self::assertSame('2026-04-01', $entry->priceEffectiveDate?->value);
        self::assertSame('REV', $entry->dimensions->item?->id?->value);
        self::assertSame('23-00', $entry->customFields->get('COST_CODE'));
        self::assertSame('/objects/construction/project-contract-line-entry/31', $entry->href);
    }

    public function test_query_selects_the_default_fields_and_returns_lines_without_entries(): void
    {
        [$lines, $http] = $this->lines($this->json([
            'ia::result' => [[
                'key' => '22',
                'id' => '01',
                'name' => 'Mechanical',
                'projectContract.key' => '3',
                'projectContract.id' => 'PC-1001',
                'projectContract.name' => 'Clinic HVAC retrofit',
                'parent.key' => '21',
                'parent.id' => '00',
                'parent.name' => 'Base bid',
                'billingSetup.billingType' => 'timeAndMaterial',
                'billingSetup.maximumBilling' => 'noMaximum',
                'billingSetup.maximumBillingAmount' => null,
                'summary.totalPrice' => '102500.00',
                'dimensions.project.key' => '1',
                'dimensions.project.id' => 'PRJ-1001',
                'dimensions.task.key' => null,
                'dimensions.task.id' => null,
                'status' => 'inactive',
            ]],
            'ia::meta' => ['totalCount' => 1, 'start' => 1, 'pageSize' => 100, 'next' => null],
        ]));

        $page = $lines->query(new ResourceQuery(size: 5));

        $body = $this->jsonBody($http->requests[0]);
        self::assertSame('construction/project-contract-line', $body['object']);
        self::assertIsArray($body['fields']);
        foreach ([
            'key', 'id', 'name', 'contractLineDate', 'retainagePercentage', 'projectContract.id', 'parent.key',
            'glAccount.id', 'billingSetup.maximumBillingAmount', 'summary.originalPrice', 'billing.retainageHeld',
            'schedule.scheduledStartDate', 'externalReference.referenceNumber', 'dimensions.project.id',
            'dimensions.costType.key', 'href',
        ] as $field) {
            self::assertContains($field, $body['fields']);
        }
        self::assertNotContains('projectContractLineEntries', $body['fields']);

        $line = $page->items[0];
        self::assertSame('Clinic HVAC retrofit', $line->projectContract->name);
        self::assertSame('00', $line->parent?->id?->value);
        self::assertSame(ProjectContractLineBillingType::TimeAndMaterial, $line->billingType);
        self::assertSame(ProjectContractLineMaximumBilling::NoMaximum, $line->maximumBilling);
        self::assertNull($line->maximumBillingAmount);
        self::assertSame('102500.00', $line->summary?->totalPrice?->value);
        self::assertSame('PRJ-1001', $line->dimensions->project?->id?->value);
        self::assertNull($line->dimensions->task);
        self::assertSame(RecordStatus::Inactive, $line->status);
        self::assertSame([], $line->entries);
    }

    public function test_a_line_without_its_contract_reference_is_rejected(): void
    {
        [$lines] = $this->lines($this->json(['ia::result' => ['key' => '22', 'id' => '01']]));

        $this->expectException(MappingException::class);

        $lines->get(new ObjectKey('22'));
    }

    public function test_it_creates_a_line_with_entries(): void
    {
        [$lines, $http] = $this->lines($this->mutation('22', '01'), $this->mutation('23', '02'));

        $result = $lines->create(new CreateProjectContractLine(
            projectContract: ObjectReference::byId('PC-1001'),
            id: new ObjectId('01'),
            name: 'Mechanical',
            parent: ObjectReference::byKey('21'),
            contractLineDate: new LocalDate('2026-04-01'),
            glAccount: ObjectReference::byId('4000'),
            retainagePercentage: new Decimal('10'),
            billable: true,
            billingType: ProjectContractLineBillingType::ProgressBill,
            maximumBilling: ProjectContractLineMaximumBilling::SpecifiedAmount,
            maximumBillingAmount: new Decimal('120000'),
            scope: 'HVAC',
            schedule: new ProjectContractSchedule(scheduledStartDate: new LocalDate('2026-05-01')),
            externalReferenceNumber: 'OWNER-9-01',
            dimensions: new Dimensions(
                project: ObjectReference::byId('PRJ-1001'),
                custom: (new CustomFields)->with('phase', ObjectReference::byKey('10002')),
            ),
            entries: [new CreateProjectContractLineEntry(
                workflowType: ProjectContractLineEntryWorkflowType::Original,
                quantity: new Decimal('1'),
                unitPrice: new Decimal('100000'),
                price: new Decimal('100000'),
                externalUom: 'LS',
                memo: 'Original contract amount',
                priceEffectiveDate: new LocalDate('2026-04-01'),
                dimensions: new Dimensions(item: ObjectReference::byId('REV')),
                customFields: (new CustomFields)->with('COST_CODE', '23-00'),
            )],
            customFields: (new CustomFields)->with('BID_PACKAGE', 'BP-2'),
        ), new IdempotencyKey('line-pc-1001-01'));
        $lines->create(new CreateProjectContractLine(ObjectReference::byKey('3'), new ObjectId('02'), 'Electrical'));

        self::assertSame('22', $result->reference->key?->value);
        self::assertSame('POST', $http->requests[0]->getMethod());
        self::assertSame(self::URL, $this->url($http->requests[0]));
        self::assertSame('line-pc-1001-01', $http->requests[0]->getHeaderLine('Idempotency-Key'));
        self::assertSame([
            'projectContract' => ['id' => 'PC-1001'],
            'id' => '01',
            'name' => 'Mechanical',
            'parent' => ['key' => '21'],
            'contractLineDate' => '2026-04-01',
            'glAccount' => ['id' => '4000'],
            'retainagePercentage' => '10',
            'isBillable' => true,
            'billingSetup' => ['billingType' => 'progressBill', 'maximumBilling' => 'specifiedAmount', 'maximumBillingAmount' => '120000'],
            'scope' => 'HVAC',
            'schedule' => ['scheduledStartDate' => '2026-05-01'],
            'externalReference' => ['referenceNumber' => 'OWNER-9-01'],
            'dimensions' => ['project' => ['id' => 'PRJ-1001'], 'nsp::phase' => ['key' => '10002']],
            'projectContractLineEntries' => [[
                'workflowType' => 'original',
                'quantity' => '1',
                'unitPrice' => '100000',
                'price' => '100000',
                'externalUOM' => 'LS',
                'memo' => 'Original contract amount',
                'priceEffectiveDate' => '2026-04-01',
                'dimensions' => ['item' => ['id' => 'REV']],
                'nsp::COST_CODE' => '23-00',
            ]],
            'nsp::BID_PACKAGE' => 'BP-2',
        ], $this->jsonBody($http->requests[0]));
        self::assertSame(
            ['projectContract' => ['key' => '3'], 'id' => '02', 'name' => 'Electrical'],
            $this->jsonBody($http->requests[1]),
        );
    }

    public function test_updates_merge_groups_carry_entry_operations_and_send_explicit_nulls(): void
    {
        [$lines, $http] = $this->lines($this->mutation('22'), $this->mutation('22'), $this->mutation('22'));

        $lines->update(new ObjectKey('22'), UpdateProjectContractLine::status(RecordStatus::Inactive));
        $lines->update(
            new ObjectKey('22'),
            UpdateProjectContractLine::description(null)
                ->withBillingType(ProjectContractLineBillingType::TimeAndMaterial)
                ->withMaximumBilling(ProjectContractLineMaximumBilling::TotalPrice)
                ->withSummarizeBill(true)
                ->withParent(null)
                ->withRetainagePercentage(new Decimal('5'))
                ->withExcludeFromGlBudget(true)
                ->withSchedule(new ProjectContractSchedule(revisedCompletionDate: new LocalDate('2027-01-15')))
                ->withInternalReferenceNumber('INT-1')
                ->withDimensions(new Dimensions(custom: (new CustomFields)->with('phase', null)))
                ->withRemovedEntry(new ObjectKey('31'))
                ->withAddedEntry(new CreateProjectContractLineEntry(
                    workflowType: ProjectContractLineEntryWorkflowType::Revision,
                    price: new Decimal('-500.00'),
                ))
                ->withCustomField('BID_PACKAGE', null),
            new IdempotencyKey('line-22-revise'),
        );
        $lines->update(new ObjectKey('22'), UpdateProjectContractLine::addEntry(new CreateProjectContractLineEntry(
            workflowType: ProjectContractLineEntryWorkflowType::Forecast,
            price: new Decimal('101000'),
        ))->withCustomFields(new CustomFields(['BID_PACKAGE' => 'BP-3'])));

        self::assertSame('PATCH', $http->requests[0]->getMethod());
        self::assertSame(self::URL.'/22', $this->url($http->requests[0]));
        self::assertSame(['status' => 'inactive'], $this->jsonBody($http->requests[0]));
        self::assertSame('line-22-revise', $http->requests[1]->getHeaderLine('Idempotency-Key'));
        self::assertSame([
            'description' => null,
            'billingSetup' => [
                'billingType' => 'timeAndMaterial',
                'maximumBilling' => 'totalPrice',
                'maximumBillingAmount' => null,
                'summarizeBill' => true,
            ],
            'parent' => null,
            'retainagePercentage' => '5',
            'excludeFromGLBudget' => true,
            'schedule' => ['revisedCompletionDate' => '2027-01-15'],
            'internalReference' => ['referenceNumber' => 'INT-1'],
            'dimensions' => ['nsp::phase' => null],
            'projectContractLineEntries' => [
                ['ia::operation' => 'delete', 'key' => '31'],
                ['workflowType' => 'revision', 'price' => '-500.00'],
            ],
            'nsp::BID_PACKAGE' => null,
        ], $this->jsonBody($http->requests[1]));
        self::assertSame([
            'projectContractLineEntries' => [['workflowType' => 'forecast', 'price' => '101000']],
            'nsp::BID_PACKAGE' => 'BP-3',
        ], $this->jsonBody($http->requests[2]));
        self::assertSame(
            ['projectContractLineEntries' => [['ia::operation' => 'delete', 'key' => '31']]],
            UpdateProjectContractLine::removeEntry(new ObjectKey('31'))->toArray(),
        );
    }

    public function test_a_specified_amount_maximum_requires_an_amount(): void
    {
        foreach ([
            static fn (): object => new CreateProjectContractLine(
                ObjectReference::byId('PC-1001'),
                new ObjectId('01'),
                'Mechanical',
                maximumBilling: ProjectContractLineMaximumBilling::SpecifiedAmount,
            ),
            static fn (): object => UpdateProjectContractLine::name('Mechanical')
                ->withMaximumBilling(ProjectContractLineMaximumBilling::SpecifiedAmount),
        ] as $build) {
            try {
                $build();
                self::fail('A specified-amount maximum without an amount was accepted.');
            } catch (InvalidArgument) {
                $this->addToAssertionCount(1);
            }
        }

        self::assertSame(
            [
                'name' => 'Mechanical',
                'billingSetup' => ['maximumBilling' => 'specifiedAmount', 'maximumBillingAmount' => '90000.00'],
            ],
            UpdateProjectContractLine::name('Mechanical')
                ->withMaximumBilling(ProjectContractLineMaximumBilling::SpecifiedAmount, new Decimal('90000.00'))
                ->toArray(),
        );
    }

    public function test_delete_and_batch_writes(): void
    {
        [$lines, $http] = $this->lines(
            new Response(204),
            $this->json([
                'ia::result' => [
                    ['key' => '22', 'id' => '01', 'ia::status' => 201],
                    ['key' => '23', 'id' => '02', 'ia::status' => 201],
                ],
                'ia::meta' => ['totalCount' => 2, 'totalSuccess' => 2, 'totalError' => 0],
            ]),
            new Response(204),
        );

        $lines->delete(new ObjectKey('22'));
        $created = $lines->createMany([
            new CreateProjectContractLine(ObjectReference::byKey('3'), new ObjectId('01'), 'Mechanical'),
            new CreateProjectContractLine(ObjectReference::byKey('3'), new ObjectId('02'), 'Electrical'),
        ], atomic: true);
        $deleted = $lines->deleteMany([new ObjectKey('22'), new ObjectKey('23')]);

        self::assertSame('DELETE', $http->requests[0]->getMethod());
        self::assertSame(self::URL.'/22', $this->url($http->requests[0]));
        self::assertTrue($created->isSuccessful());
        self::assertSame('23', $created->items[1]->reference?->key?->value);
        self::assertSame('true', $http->requests[1]->getHeaderLine('X-IA-API-Param-Transaction'));
        self::assertSame(
            [
                ['projectContract' => ['key' => '3'], 'id' => '01', 'name' => 'Mechanical'],
                ['projectContract' => ['key' => '3'], 'id' => '02', 'name' => 'Electrical'],
            ],
            json_decode((string) $http->requests[1]->getBody(), true, flags: JSON_THROW_ON_ERROR),
        );
        self::assertTrue($deleted->isSuccessful());
        self::assertSame(self::URL.'/22,23', $this->url($http->requests[2]));
    }

    /** @return array{ProjectContractLinesClient, QueueHttpClient} */
    private function lines(Response ...$responses): array
    {
        [$transport, $http] = $this->transport(...$responses);

        return [new ProjectContractLinesClient($transport, new QueryClient($transport)), $http];
    }
}
