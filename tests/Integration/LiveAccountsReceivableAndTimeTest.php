<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Integration;

use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Resources\AccountsReceivable\Customers\CustomersClient;
use ControlAir\Intacct\Resources\Time\Timesheets\TimesheetsClient;
use ControlAir\Intacct\Resources\Time\TimeTypes\TimeTypesClient;
use PHPUnit\Framework\Attributes\Group;

/**
 * Read-only checks that the default query field lists of the customer, timesheet and time
 * type clients are accepted by Sage and that real records map. Nothing is created, changed,
 * or deleted.
 */
#[Group('integration')]
final class LiveAccountsReceivableAndTimeTest extends LiveTestCase
{
    public function test_customers_query_and_read(): void
    {
        $client = $this->client();
        $customers = new CustomersClient($client->transport, $client->queries);

        $page = $customers->query(new ResourceQuery(size: 5));
        $this->report(sprintf('accounts-receivable/customer: %d record(s)', $page->meta->totalCount));

        self::assertGreaterThanOrEqual(count($page->items), $page->meta->totalCount);

        if ($page->items === []) {
            self::markTestSkipped('The company has no customers.');
        }

        $customer = $customers->get($page->items[0]->key);

        self::assertSame($page->items[0]->key->value, $customer->key->value);
        self::assertSame($page->items[0]->id->value, $customer->id->value);
        self::assertSame($page->items[0]->primaryContact?->id?->value, $customer->primaryContact?->id?->value);
    }

    public function test_timesheets_query_and_read_with_lines(): void
    {
        $client = $this->client();
        $timesheets = new TimesheetsClient($client->transport, $client->queries);

        $page = $timesheets->query(new ResourceQuery(size: 5));
        $this->report(sprintf('time/timesheet: %d record(s)', $page->meta->totalCount));

        self::assertGreaterThanOrEqual(count($page->items), $page->meta->totalCount);

        if ($page->items === []) {
            self::markTestSkipped('The company has no timesheets.');
        }

        self::assertSame([], $page->items[0]->lines);

        $timesheet = $timesheets->get($page->items[0]->key);
        $this->report(sprintf('  timesheet %s: %d line(s)', $timesheet->key->value, count($timesheet->lines)));

        self::assertSame($page->items[0]->key->value, $timesheet->key->value);
        self::assertSame($page->items[0]->state, $timesheet->state);

        foreach ($timesheet->lines as $line) {
            self::assertNotSame('', $line->key->value);
        }
    }

    public function test_time_types_query_and_read(): void
    {
        $client = $this->client();
        $timeTypes = new TimeTypesClient($client->transport, $client->queries);

        $page = $timeTypes->query(new ResourceQuery(size: 5));
        $this->report(sprintf('time/time-type: %d record(s)', $page->meta->totalCount));

        self::assertGreaterThanOrEqual(count($page->items), $page->meta->totalCount);

        if ($page->items === []) {
            self::markTestSkipped('The company has no time types.');
        }

        $timeType = $timeTypes->get($page->items[0]->key);

        self::assertSame($page->items[0]->key->value, $timeType->key->value);
        self::assertSame($page->items[0]->glAccount?->key?->value, $timeType->glAccount?->key?->value);
    }
}
