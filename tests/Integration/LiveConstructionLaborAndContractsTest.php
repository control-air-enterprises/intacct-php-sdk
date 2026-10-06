<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Integration;

use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Exceptions\ApiException;
use ControlAir\Intacct\Resources\Construction\EmployeePositions\EmployeePositionsClient;
use ControlAir\Intacct\Resources\Construction\LaborClasses\LaborClassesClient;
use ControlAir\Intacct\Resources\Construction\LaborShifts\LaborShiftsClient;
use ControlAir\Intacct\Resources\Construction\LaborUnions\LaborUnionsClient;
use ControlAir\Intacct\Resources\Construction\ProjectContractLines\ProjectContractLinesClient;
use ControlAir\Intacct\Resources\Construction\ProjectContracts\ProjectContractsClient;
use PHPUnit\Framework\Attributes\Group;

/**
 * Read-only checks that the default query field lists are accepted by Sage and that real
 * records map. Nothing is created, changed, or deleted. A test is skipped when the company
 * has no record or the user lacks permission for the object.
 */
#[Group('integration')]
final class LiveConstructionLaborAndContractsTest extends LiveTestCase
{
    public function test_labor_unions_query_and_get(): void
    {
        $client = $this->client();

        $this->assertLaborClientReads('construction/labor-union', new LaborUnionsClient($client->transport, $client->queries));
    }

    public function test_labor_classes_query_and_get(): void
    {
        $client = $this->client();

        $this->assertLaborClientReads('construction/labor-class', new LaborClassesClient($client->transport, $client->queries));
    }

    public function test_labor_shifts_query_and_get(): void
    {
        $client = $this->client();

        $this->assertLaborClientReads('construction/labor-shift', new LaborShiftsClient($client->transport, $client->queries));
    }

    public function test_employee_positions_query_and_get(): void
    {
        $client = $this->client();

        $this->assertLaborClientReads('construction/employee-position', new EmployeePositionsClient($client->transport, $client->queries));
    }

    public function test_project_contracts_query_and_get(): void
    {
        $client = $this->client();
        $contracts = new ProjectContractsClient($client->transport, $client->queries);

        $page = $this->permitted('construction/project-contract', static fn () => $contracts->query(new ResourceQuery(size: 5)));
        $this->report(sprintf('construction/project-contract: %d record(s)', $page->meta->totalCount));

        if ($page->items === []) {
            self::markTestSkipped('The company has no project contracts.');
        }

        $first = $page->items[0];
        $contract = $contracts->get($first->key);

        self::assertSame($first->key->value, $contract->key->value);
        self::assertSame($first->id->value, $contract->id->value);
        self::assertSame($first->summary?->totalPrice?->value, $contract->summary?->totalPrice?->value);
        self::assertSame($first->project?->key?->value, $contract->project?->key?->value);
        self::assertNotNull($contract->status);
    }

    public function test_project_contract_lines_query_and_get(): void
    {
        $client = $this->client();
        $lines = new ProjectContractLinesClient($client->transport, $client->queries);

        $page = $this->permitted('construction/project-contract-line', static fn () => $lines->query(new ResourceQuery(size: 5)));
        $this->report(sprintf('construction/project-contract-line: %d record(s)', $page->meta->totalCount));

        if ($page->items === []) {
            self::markTestSkipped('The company has no project contract lines.');
        }

        $first = $page->items[0];
        $line = $lines->get($first->key);

        self::assertSame([], $first->entries);
        self::assertSame($first->key->value, $line->key->value);
        self::assertSame($first->projectContract->key?->value, $line->projectContract->key?->value);
        self::assertSame($first->summary?->totalPrice?->value, $line->summary?->totalPrice?->value);
        self::assertSame($first->dimensions->project?->key?->value, $line->dimensions->project?->key?->value);
        self::assertNotNull($line->billingType);

        foreach ($line->entries as $entry) {
            self::assertNotNull($entry->workflowType);
        }

        $this->report(sprintf('  line %s has %d entr(ies)', $line->key->value, count($line->entries)));
    }

    private function assertLaborClientReads(
        string $object,
        LaborUnionsClient|LaborClassesClient|LaborShiftsClient|EmployeePositionsClient $records,
    ): void {
        $page = $this->permitted($object, static fn () => $records->query(new ResourceQuery(size: 5)));
        $this->report(sprintf('%s: %d record(s)', $object, $page->meta->totalCount));

        if ($page->items === []) {
            self::markTestSkipped(sprintf('The company has no %s records.', $object));
        }

        $first = $page->items[0];
        $record = $records->get($first->key);

        self::assertSame($first->key->value, $record->key->value);
        self::assertSame($first->id->value, $record->id->value);
        self::assertSame($first->name, $record->name);
        self::assertNotNull($record->status);
    }

    /**
     * Runs a read, skipping the test when the Sage user is not permitted to read the object.
     *
     * @template T
     *
     * @param  \Closure(): T  $read
     * @return T
     */
    private function permitted(string $object, \Closure $read): mixed
    {
        try {
            return $read();
        } catch (ApiException $exception) {
            if ($exception->statusCode === 403) {
                self::markTestSkipped(sprintf('The Sage user lacks permission to read %s.', $object));
            }

            throw $exception;
        }
    }
}
