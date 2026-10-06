<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Time;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Resources\Time\Timesheets\CreateTimesheet;
use ControlAir\Intacct\Resources\Time\Timesheets\CreateTimesheetLine;
use ControlAir\Intacct\Resources\Time\Timesheets\Timesheet;
use ControlAir\Intacct\Resources\Time\Timesheets\TimesheetExternalPayroll;
use ControlAir\Intacct\Resources\Time\Timesheets\TimesheetHours;
use ControlAir\Intacct\Resources\Time\Timesheets\TimesheetLine;
use ControlAir\Intacct\Resources\Time\Timesheets\TimesheetLineState;
use ControlAir\Intacct\Resources\Time\Timesheets\TimesheetsClient;
use ControlAir\Intacct\Resources\Time\Timesheets\TimesheetState;
use ControlAir\Intacct\Resources\Time\Timesheets\UpdateTimesheet;
use ControlAir\Intacct\Resources\Time\Timesheets\UpdateTimesheetLine;
use ControlAir\Intacct\Tests\Support\ApiTestCase;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\Dimensions;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TimesheetsClient::class)]
#[CoversClass(Timesheet::class)]
#[CoversClass(TimesheetLine::class)]
#[CoversClass(TimesheetExternalPayroll::class)]
#[CoversClass(TimesheetHours::class)]
#[CoversClass(CreateTimesheet::class)]
#[CoversClass(CreateTimesheetLine::class)]
#[CoversClass(UpdateTimesheet::class)]
#[CoversClass(UpdateTimesheetLine::class)]
final class TimesheetsClientTest extends ApiTestCase
{
    public function test_it_reads_a_timesheet_with_its_lines(): void
    {
        [$transport, $http] = $this->transport($this->json([
            'ia::result' => [
                'key' => '120',
                'id' => '120',
                'state' => 'partiallyApproved',
                'employee' => ['key' => '8', 'id' => 'EMP-8', 'href' => '/objects/company-config/employee/8'],
                'employeeContact' => ['key' => '30', 'id' => 'Ada Lovelace', 'firstName' => 'Ada', 'lastName' => 'Lovelace'],
                'beginDate' => '2026-09-07',
                'endDate' => '2026-09-13',
                'postingDate' => '2026-09-14',
                'description' => 'Week 37',
                'unitOfMeasure' => 'Hours',
                'hoursInDay' => 8,
                'calculationMethod' => 'hourly',
                'postActualLaborCost' => true,
                'nsp::CREW' => 'North',
                'lines' => [[
                    'key' => '901',
                    'id' => '901',
                    'lineNumber' => 1,
                    'entryDate' => '2026-09-08',
                    'quantity' => 7.5,
                    'timeType' => ['key' => '1', 'id' => 'Regular'],
                    'state' => 'approved',
                    'isBillable' => true,
                    'isBilled' => 'false',
                    'description' => 'Framing',
                    'notes' => null,
                    'dimensions' => [
                        'project' => ['key' => '10', 'id' => 'PROJ-001', 'name' => 'Headquarters Renovation'],
                        'task' => ['key' => '44', 'id' => 'FRAMING'],
                        'costType' => ['key' => '7', 'id' => 'LABOR'],
                        'customer' => ['key' => '14', 'id' => 'CUST-014'],
                        'employee' => ['key' => '8', 'id' => 'EMP-8'],
                        'location' => ['key' => '1', 'id' => 'HQ'],
                        'workOrder' => ['key' => null, 'id' => null],
                    ],
                    'laborClass' => ['key' => '2', 'id' => 'Journeyman'],
                    'laborShift' => ['key' => '1', 'id' => 'Day'],
                    'laborUnion' => ['key' => '5', 'id' => 'Local 12'],
                    'employeePosition' => ['key' => '3', 'id' => 'Carpenter'],
                    'externalPayroll' => [
                        'amount' => '412.50',
                        'billingRate' => 85,
                        'cashFringes' => null,
                        'costRate' => 55,
                        'employerTaxes' => 31.56,
                        'fringes' => 12.25,
                    ],
                    'hours' => [
                        'approved' => 7.5,
                        'approvedBillable' => 7.5,
                        'approvedNonBillable' => 0,
                        'billable' => 7.5,
                        'nonBillable' => 0,
                        'utilized' => 7.5,
                        'nonUtilized' => 0,
                    ],
                    'timesheet' => ['key' => '120', 'id' => '120'],
                    'nsp::EQUIPMENT' => 'Lift-3',
                    'href' => '/objects/time/timesheet-line/901',
                ], [
                    'key' => '902',
                    'lineNumber' => 2,
                    'entryDate' => '2026-09-09',
                    'quantity' => 8,
                    'state' => 'readyForApproval',
                    'externalPayroll' => ['amount' => null, 'costRate' => null],
                ]],
                'href' => '/objects/time/timesheet/120',
            ],
            'ia::meta' => ['totalCount' => 1, 'totalSuccess' => 1, 'totalError' => 0],
        ]));

        $timesheet = $this->timesheets($transport)->get(new ObjectKey('120'));

        self::assertSame('https://api.intacct.com/ia/api/v1/objects/time/timesheet/120', $this->url($http->requests[0]));
        self::assertSame(TimesheetState::PartiallyApproved, $timesheet->state);
        self::assertSame('EMP-8', $timesheet->employee?->id?->value);
        self::assertSame('2026-09-07', $timesheet->beginDate?->value);
        self::assertSame('2026-09-13', $timesheet->endDate?->value);
        self::assertSame('2026-09-14', $timesheet->postingDate?->value);
        self::assertSame('Hours', $timesheet->unitOfMeasure);
        self::assertSame('8', $timesheet->hoursInDay?->value);
        self::assertSame('North', $timesheet->customFields->get('CREW'));
        self::assertCount(2, $timesheet->lines);

        $line = $timesheet->lines[0];
        self::assertSame('901', $line->key->value);
        self::assertSame(1, $line->lineNumber);
        self::assertSame('2026-09-08', $line->entryDate?->value);
        self::assertSame('7.5', $line->quantity?->value);
        self::assertSame('Regular', $line->timeType?->id?->value);
        self::assertSame(TimesheetLineState::Approved, $line->state);
        self::assertTrue($line->billable);
        self::assertSame('Framing', $line->description);
        self::assertSame('PROJ-001', $line->dimensions->project?->id?->value);
        self::assertSame('FRAMING', $line->dimensions->task?->id?->value);
        self::assertSame('LABOR', $line->dimensions->costType?->id?->value);
        self::assertSame('CUST-014', $line->dimensions->customer?->id?->value);
        self::assertSame('Journeyman', $line->laborClass?->id?->value);
        self::assertSame('Day', $line->laborShift?->id?->value);
        self::assertSame('Local 12', $line->laborUnion?->id?->value);
        self::assertSame('Carpenter', $line->employeePosition?->id?->value);
        self::assertSame('412.50', $line->externalPayroll?->amount?->value);
        self::assertSame('85', $line->externalPayroll->billingRate?->value);
        self::assertSame('55', $line->externalPayroll->costRate?->value);
        self::assertSame('31.56', $line->externalPayroll->employerTaxes?->value);
        self::assertSame('12.25', $line->externalPayroll->fringes?->value);
        self::assertNull($line->externalPayroll->cashFringes);
        self::assertSame('7.5', $line->hours->approvedBillable?->value);
        self::assertSame('0', $line->hours->nonBillable?->value);
        self::assertNull($line->hours->approvedUtilized);
        self::assertSame('Lift-3', $line->customFields->get('EQUIPMENT'));
        self::assertSame(TimesheetLineState::ReadyForApproval, $timesheet->lines[1]->state);
        self::assertNull($timesheet->lines[1]->externalPayroll);
        self::assertNull($timesheet->lines[1]->hours->approved);
    }

    public function test_query_returns_headers_only_with_the_default_fields(): void
    {
        [$transport, $http] = $this->transport($this->json([
            'ia::result' => [[
                'key' => '120',
                'id' => '120',
                'state' => 'submitted',
                'employee.key' => '8',
                'employee.id' => 'EMP-8',
                'beginDate' => '2026-09-07',
                'nsp::CREW' => 'North',
            ]],
            'ia::meta' => ['totalCount' => 1, 'start' => 1, 'pageSize' => 100, 'next' => null],
        ]));

        $page = $this->timesheets($transport)->query((new ResourceQuery)->withFields('nsp::CREW'));

        $body = $this->jsonBody($http->requests[0]);
        self::assertSame('https://api.intacct.com/ia/api/v1/services/core/query', $this->url($http->requests[0]));
        self::assertSame('time/timesheet', $body['object']);
        self::assertSame([
            'key', 'id', 'state', 'employee.key', 'employee.id', 'beginDate', 'endDate',
            'postingDate', 'description', 'unitOfMeasure', 'hoursInDay', 'href', 'nsp::CREW',
        ], $body['fields']);
        self::assertSame(TimesheetState::Submitted, $page->items[0]->state);
        self::assertSame('EMP-8', $page->items[0]->employee?->id?->value);
        self::assertSame([], $page->items[0]->lines);
        self::assertSame('North', $page->items[0]->customFields->get('CREW'));
    }

    public function test_it_creates_a_timesheet_with_job_cost_lines(): void
    {
        [$transport, $http] = $this->transport($this->mutation('120', '120'));

        $result = $this->timesheets($transport)->create(new CreateTimesheet(
            employee: ObjectReference::byId('EMP-8'),
            beginDate: new LocalDate('2026-09-07'),
            lines: [new CreateTimesheetLine(
                entryDate: new LocalDate('2026-09-08'),
                quantity: new Decimal('7.5'),
                timeType: ObjectReference::byId('Regular'),
                dimensions: new Dimensions(
                    project: ObjectReference::byId('PROJ-001'),
                    task: ObjectReference::byId('FRAMING'),
                    costType: ObjectReference::byId('LABOR'),
                    custom: (new CustomFields)->with('phase', ObjectReference::byKey('10004')),
                ),
                billable: true,
                description: 'Framing',
                laborClass: ObjectReference::byId('Journeyman'),
                laborShift: ObjectReference::byId('Day'),
                laborUnion: ObjectReference::byId('Local 12'),
                employeePosition: ObjectReference::byId('Carpenter'),
                externalPayroll: new TimesheetExternalPayroll(
                    amount: new Decimal('412.50'),
                    costRate: new Decimal('55'),
                    fringes: new Decimal('12.25'),
                ),
                customFields: (new CustomFields)->with('EQUIPMENT', 'Lift-3'),
            ), new CreateTimesheetLine(
                entryDate: new LocalDate('2026-09-09'),
                quantity: new Decimal('8'),
            )],
            state: TimesheetState::Submitted,
            description: 'Week 37',
            customFields: (new CustomFields)->with('CREW', 'North'),
        ), new IdempotencyKey('timesheet-emp-8-2026-09-07'));

        self::assertSame('120', $result->reference->key?->value);
        self::assertSame('POST', $http->requests[0]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/time/timesheet', $this->url($http->requests[0]));
        self::assertSame('timesheet-emp-8-2026-09-07', $http->requests[0]->getHeaderLine('Idempotency-Key'));
        self::assertSame([
            'employee' => ['id' => 'EMP-8'],
            'beginDate' => '2026-09-07',
            'state' => 'submitted',
            'description' => 'Week 37',
            'lines' => [[
                'entryDate' => '2026-09-08',
                'quantity' => '7.5',
                'timeType' => ['id' => 'Regular'],
                'dimensions' => [
                    'project' => ['id' => 'PROJ-001'],
                    'task' => ['id' => 'FRAMING'],
                    'costType' => ['id' => 'LABOR'],
                    'nsp::phase' => ['key' => '10004'],
                ],
                'isBillable' => true,
                'description' => 'Framing',
                'laborClass' => ['id' => 'Journeyman'],
                'laborShift' => ['id' => 'Day'],
                'laborUnion' => ['id' => 'Local 12'],
                'employeePosition' => ['id' => 'Carpenter'],
                'externalPayroll' => ['amount' => '412.50', 'costRate' => '55', 'fringes' => '12.25'],
                'nsp::EQUIPMENT' => 'Lift-3',
            ], [
                'entryDate' => '2026-09-09',
                'quantity' => '8',
            ]],
            'nsp::CREW' => 'North',
        ], $this->jsonBody($http->requests[0]));
    }

    public function test_create_rejects_approval_states_empty_lines_and_empty_payroll(): void
    {
        $line = new CreateTimesheetLine(new LocalDate('2026-09-08'), new Decimal('8'));
        $attempts = [
            static fn () => new CreateTimesheet(ObjectReference::byId('EMP-8'), new LocalDate('2026-09-07'), [$line], TimesheetState::Approved),
            static fn () => new CreateTimesheet(ObjectReference::byId('EMP-8'), new LocalDate('2026-09-07'), []),
            static fn () => new TimesheetExternalPayroll,
        ];

        foreach ($attempts as $attempt) {
            try {
                $attempt();
                self::fail('Expected an invalid timesheet to be rejected.');
            } catch (InvalidArgument) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_update_combines_header_changes_and_line_operations(): void
    {
        [$transport, $http] = $this->transport($this->mutation('120'));

        $this->timesheets($transport)->update(
            new ObjectKey('120'),
            UpdateTimesheet::description('Week 37 (corrected)')
                ->withPostingDate(null)
                ->withUpdatedLine(
                    new ObjectKey('901'),
                    UpdateTimesheetLine::quantity(new Decimal('6'))
                        ->withDimensions(new Dimensions(project: ObjectReference::byId('PROJ-002'), costType: ObjectReference::byId('LABOR')))
                        ->withExternalPayroll(new TimesheetExternalPayroll(costRate: new Decimal('57.50')))
                        ->withNotes(null)
                        ->withLaborShift(null)
                        ->withCustomField('EQUIPMENT', null),
                )
                ->withRemovedLine(new ObjectKey('902'))
                ->withAddedLine(new CreateTimesheetLine(
                    entryDate: new LocalDate('2026-09-10'),
                    quantity: new Decimal('2'),
                    timeType: ObjectReference::byId('Overtime'),
                    dimensions: new Dimensions(project: ObjectReference::byId('PROJ-002')),
                ))
                ->withCustomField('CREW', 'South'),
        );

        self::assertSame('PATCH', $http->requests[0]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/time/timesheet/120', $this->url($http->requests[0]));
        self::assertSame([
            'description' => 'Week 37 (corrected)',
            'postingDate' => null,
            'nsp::CREW' => 'South',
            'lines' => [
                [
                    'key' => '901',
                    'quantity' => '6',
                    'dimensions' => ['project' => ['id' => 'PROJ-002'], 'costType' => ['id' => 'LABOR']],
                    'externalPayroll' => ['costRate' => '57.50'],
                    'notes' => null,
                    'laborShift' => null,
                    'nsp::EQUIPMENT' => null,
                ],
                ['ia::operation' => 'delete', 'key' => '902'],
                [
                    'entryDate' => '2026-09-10',
                    'quantity' => '2',
                    'timeType' => ['id' => 'Overtime'],
                    'dimensions' => ['project' => ['id' => 'PROJ-002']],
                ],
            ],
        ], $this->jsonBody($http->requests[0]));
    }

    public function test_line_operations_can_start_an_update_and_omitted_fields_are_not_sent(): void
    {
        self::assertSame(
            ['lines' => [['ia::operation' => 'delete', 'key' => '902']]],
            UpdateTimesheet::removeLine(new ObjectKey('902'))->toArray(),
        );
        self::assertSame(
            ['lines' => [['key' => '901', 'entryDate' => '2026-09-09', 'isBillable' => false]]],
            UpdateTimesheet::updateLine(
                new ObjectKey('901'),
                UpdateTimesheetLine::entryDate(new LocalDate('2026-09-09'))->withBillable(false),
            )->toArray(),
        );
        self::assertSame(
            ['lines' => [['entryDate' => '2026-09-11', 'quantity' => '4']]],
            UpdateTimesheet::addLine(new CreateTimesheetLine(new LocalDate('2026-09-11'), new Decimal('4')))->toArray(),
        );
        self::assertSame(['state' => 'submitted'], UpdateTimesheet::state(TimesheetState::Submitted)->toArray());
    }

    public function test_create_many_and_delete_many_use_batch_requests(): void
    {
        [$transport, $http] = $this->transport(
            $this->json([
                'ia::result' => [
                    ['key' => '120', 'id' => '120', 'ia::status' => 201],
                    ['key' => '121', 'id' => '121', 'ia::status' => 201],
                ],
                'ia::meta' => ['totalCount' => 2, 'totalSuccess' => 2, 'totalError' => 0],
            ]),
            new Response(204),
        );
        $timesheets = $this->timesheets($transport);
        $line = new CreateTimesheetLine(new LocalDate('2026-09-08'), new Decimal('8'));

        $created = $timesheets->createMany([
            new CreateTimesheet(ObjectReference::byId('EMP-8'), new LocalDate('2026-09-07'), [$line]),
            new CreateTimesheet(ObjectReference::byId('EMP-9'), new LocalDate('2026-09-07'), [$line]),
        ], atomic: true);
        $deleted = $timesheets->deleteMany([new ObjectKey('120'), new ObjectKey('121')]);

        self::assertTrue($created->isSuccessful());
        self::assertSame('121', $created->items[1]->reference?->key?->value);
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/time/timesheet', $this->url($http->requests[0]));
        self::assertSame('true', $http->requests[0]->getHeaderLine('X-IA-API-Param-Transaction'));
        $line = ['entryDate' => '2026-09-08', 'quantity' => '8'];
        self::assertSame(
            [
                ['employee' => ['id' => 'EMP-8'], 'beginDate' => '2026-09-07', 'lines' => [$line]],
                ['employee' => ['id' => 'EMP-9'], 'beginDate' => '2026-09-07', 'lines' => [$line]],
            ],
            json_decode((string) $http->requests[0]->getBody(), true, flags: JSON_THROW_ON_ERROR),
        );
        self::assertTrue($deleted->isSuccessful());
        self::assertSame('DELETE', $http->requests[1]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/time/timesheet/120,121', $this->url($http->requests[1]));
    }

    public function test_delete_removes_a_timesheet(): void
    {
        [$transport, $http] = $this->transport(new Response(204));

        $result = $this->timesheets($transport)->delete(new ObjectKey('120'));

        self::assertSame('120', $result->reference->key?->value);
        self::assertSame('DELETE', $http->requests[0]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/time/timesheet/120', $this->url($http->requests[0]));
    }

    private function timesheets(ApiTransport $transport): TimesheetsClient
    {
        return new TimesheetsClient($transport, new QueryClient($transport));
    }
}
