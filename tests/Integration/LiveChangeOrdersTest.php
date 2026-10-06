<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Integration;

use ControlAir\Intacct\Core\Query\Query;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Resources\Construction\ChangeRequests\ChangeRequestLine;
use ControlAir\Intacct\Resources\Construction\ChangeRequests\ChangeRequestsClient;
use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\ProjectChangeOrdersClient;
use PHPUnit\Framework\Attributes\Group;

/**
 * Read-only checks that the change order clients' default query fields exist in the
 * company and that real records map. Nothing is created, changed, or deleted.
 */
#[Group('integration')]
final class LiveChangeOrdersTest extends LiveTestCase
{
    public function test_project_change_orders_query_and_read(): void
    {
        $client = $this->client();
        $changeOrders = new ProjectChangeOrdersClient($client->transport, $client->queries);

        $page = $changeOrders->query(new ResourceQuery(size: 5));

        self::assertGreaterThanOrEqual(count($page->items), $page->meta->totalCount);
        $this->report(sprintf('project-change-order: %d record(s)', $page->meta->totalCount));

        if ($page->items === []) {
            return;
        }

        $changeOrder = $changeOrders->get($page->items[0]->key);

        self::assertSame($page->items[0]->key->value, $changeOrder->key->value);
        self::assertSame($page->items[0]->id->value, $changeOrder->id->value);
        $this->report(sprintf(
            '  %s: state %s, total price %s',
            $changeOrder->id->value,
            $changeOrder->state->value ?? '-',
            $changeOrder->totalPrice->value ?? '-',
        ));
    }

    public function test_change_requests_query_and_read_with_lines(): void
    {
        $client = $this->client();
        $changeRequests = new ChangeRequestsClient($client->transport, $client->queries);

        $page = $changeRequests->query(new ResourceQuery(size: 5));

        self::assertGreaterThanOrEqual(count($page->items), $page->meta->totalCount);
        $this->report(sprintf('change-request: %d record(s)', $page->meta->totalCount));

        if ($page->items === []) {
            return;
        }

        self::assertSame([], $page->items[0]->lines);

        $changeRequest = $changeRequests->get($page->items[0]->key);

        self::assertSame($page->items[0]->key->value, $changeRequest->key->value);
        $this->report(sprintf(
            '  %s: state %s, %d line(s), total cost %s',
            $changeRequest->id->value,
            $changeRequest->state->value ?? '-',
            count($changeRequest->lines),
            $changeRequest->totalCost->value ?? '-',
        ));

        foreach ($changeRequest->lines as $line) {
            self::assertNotSame('', $line->key->value);
            $this->report(sprintf(
                '    line %s: cost type %s, quantity %s, price %s',
                $line->key->value,
                $line->dimensions->costType->id->value ?? '-',
                $line->quantity->value ?? '-',
                $line->price->value ?? '-',
            ));
        }
    }

    /**
     * Lines are only read through their change request, so this queries the owned object
     * directly to prove the field names the line mapper reads.
     */
    public function test_change_request_line_fields_exist(): void
    {
        $client = $this->client();

        $page = $client->queries->execute('construction/change-request-line', new Query(
            fields: [
                'key', 'lineNo', 'changeRequest.key', 'changeRequest.id', 'quantity', 'externalUOM', 'unitCost',
                'cost', 'priceMarkupPercent', 'priceMarkupAmount', 'unitPrice', 'price', 'linePrice',
                'numberOfProductionUnits', 'productionUnitDescription', 'workflowType', 'memo',
                'dimensions.project.id', 'dimensions.task.id', 'dimensions.costType.id', 'glAccount.id',
                'projectContract.id', 'projectContractLine.id', 'projectChangeOrder.id', 'projectEstimate.id', 'href',
            ],
            size: 5,
        ));

        self::assertGreaterThanOrEqual(count($page->items), $page->meta->totalCount);
        $this->report(sprintf('change-request-line: %d record(s)', $page->meta->totalCount));

        foreach ($page->items as $row) {
            self::assertNotSame('', ChangeRequestLine::fromArray($row)->key->value);
        }
    }
}
