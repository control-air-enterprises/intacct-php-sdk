<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\AccountsPayable;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Resources\AccountsPayable\Terms\CreateTerm;
use ControlAir\Intacct\Resources\AccountsPayable\Terms\Term;
use ControlAir\Intacct\Resources\AccountsPayable\Terms\TermAmountUnit;
use ControlAir\Intacct\Resources\AccountsPayable\Terms\TermDateBasis;
use ControlAir\Intacct\Resources\AccountsPayable\Terms\TermDiscount;
use ControlAir\Intacct\Resources\AccountsPayable\Terms\TermDiscountCalculation;
use ControlAir\Intacct\Resources\AccountsPayable\Terms\TermDue;
use ControlAir\Intacct\Resources\AccountsPayable\Terms\TermPenalty;
use ControlAir\Intacct\Resources\AccountsPayable\Terms\TermPenaltyCycle;
use ControlAir\Intacct\Resources\AccountsPayable\Terms\TermsClient;
use ControlAir\Intacct\Resources\AccountsPayable\Terms\UpdateTerm;
use ControlAir\Intacct\Tests\Support\ApiTestCase;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\RecordStatus;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TermsClient::class)]
#[CoversClass(Term::class)]
#[CoversClass(TermDue::class)]
#[CoversClass(TermDiscount::class)]
#[CoversClass(TermPenalty::class)]
#[CoversClass(CreateTerm::class)]
#[CoversClass(UpdateTerm::class)]
final class TermsClientTest extends ApiTestCase
{
    public function test_it_maps_a_term_with_its_nested_groups(): void
    {
        [$client, $http] = $this->client($this->json([
            'ia::result' => [
                'id' => 'N500',
                'description' => '60 Days term',
                'status' => 'active',
                'key' => '27',
                'audit' => ['createdBy' => '1', 'modifiedBy' => '71'],
                'due' => ['days' => 2, 'from' => 'of5thMonthFromBillDate'],
                'discount' => [
                    'days' => 1,
                    'from' => 'of3rdMonthFromBillDate',
                    'amount' => 10,
                    'unit' => 'percentage',
                    'graceDays' => 15,
                    'calculateOn' => 'billTotal',
                ],
                'penalty' => ['cycle' => 'quarterly', 'amount' => 10.5, 'unit' => 'percentage', 'graceDays' => 16],
                'href' => '/objects/accounts-payable/term/27',
            ],
            'ia::meta' => ['totalCount' => 1, 'totalSuccess' => 1, 'totalError' => 0],
        ]));

        $term = $client->accountsPayable->terms->get(new ObjectKey('27'));

        self::assertSame('27', $term->key->value);
        self::assertSame('N500', $term->id->value);
        self::assertSame('60 Days term', $term->description);
        self::assertSame(RecordStatus::Active, $term->status);
        self::assertSame(2, $term->due?->days);
        self::assertSame(TermDateBasis::Of5thMonthFromBillDate, $term->due->from);
        self::assertSame(1, $term->discount?->days);
        self::assertSame(TermDateBasis::Of3rdMonthFromBillDate, $term->discount->from);
        self::assertSame('10', $term->discount->amount?->value);
        self::assertSame(TermAmountUnit::Percentage, $term->discount->unit);
        self::assertSame(15, $term->discount->graceDays);
        self::assertSame(TermDiscountCalculation::BillTotal, $term->discount->calculateOn);
        self::assertSame(TermPenaltyCycle::Quarterly, $term->penalty?->cycle);
        self::assertSame('10.5', $term->penalty->amount?->value);
        self::assertSame(TermAmountUnit::Percentage, $term->penalty->unit);
        self::assertSame(16, $term->penalty->graceDays);
        self::assertSame('/objects/accounts-payable/term/27', $term->href);
        self::assertSame('GET', $http->requests[0]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/accounts-payable/term/27', $this->url($http->requests[0]));
    }

    public function test_null_group_values_and_unknown_enums_map_to_null(): void
    {
        [$client] = $this->client($this->json([
            'ia::result' => [
                'key' => '28',
                'id' => 'NET30',
                'status' => 'somethingNew',
                'due' => ['days' => 30, 'from' => null],
                'discount' => ['days' => null, 'from' => null, 'unit' => null, 'calculateOn' => null],
                'penalty' => ['cycle' => 'noPenalty', 'unit' => 'fortnightly'],
            ],
        ]));

        $term = $client->accountsPayable->terms->get(new ObjectKey('28'));

        self::assertNull($term->status);
        self::assertNull($term->description);
        self::assertSame(30, $term->due?->days);
        self::assertNull($term->due->from);
        self::assertNull($term->discount);
        self::assertSame(TermPenaltyCycle::NoPenalty, $term->penalty?->cycle);
        self::assertNull($term->penalty->unit);
    }

    public function test_query_rows_with_dotted_keys_populate_nested_groups(): void
    {
        [$client, $http] = $this->client($this->json([
            'ia::result' => [[
                'key' => '27',
                'id' => 'N500',
                'description' => '60 Days term',
                'status' => 'inactive',
                'due.days' => '2',
                'due.from' => 'fromBillDate',
                'discount.days' => null,
                'discount.from' => null,
                'discount.amount' => null,
                'discount.unit' => null,
                'discount.graceDays' => null,
                'discount.calculateOn' => null,
                'penalty.cycle' => 'monthly',
                'penalty.amount' => '1.5',
                'penalty.unit' => 'percentage',
                'penalty.graceDays' => 5,
                'href' => '/objects/accounts-payable/term/27',
            ]],
            'ia::meta' => ['totalCount' => 1, 'start' => 1, 'pageSize' => 100, 'next' => null],
        ]));

        $page = $client->accountsPayable->terms->query();
        $term = $page->items[0];

        self::assertSame(RecordStatus::Inactive, $term->status);
        self::assertSame(2, $term->due?->days);
        self::assertSame(TermDateBasis::FromBillDate, $term->due->from);
        self::assertNull($term->discount);
        self::assertSame(TermPenaltyCycle::Monthly, $term->penalty?->cycle);
        self::assertSame('1.5', $term->penalty->amount?->value);
        self::assertSame(5, $term->penalty->graceDays);

        self::assertSame('https://api.intacct.com/ia/api/v1/services/core/query', $this->url($http->requests[0]));
        $body = $this->jsonBody($http->requests[0]);
        self::assertSame('accounts-payable/term', $body['object']);
        self::assertIsArray($body['fields']);
        self::assertContains('due.from', $body['fields']);
        self::assertContains('discount.calculateOn', $body['fields']);
        self::assertContains('penalty.graceDays', $body['fields']);
    }

    public function test_it_sends_create_update_and_delete_payloads(): void
    {
        [$client, $http] = $this->client(
            $this->mutation('27', 'N500'),
            $this->mutation('27', 'N500'),
            $this->mutation('27', 'N500'),
            $this->mutation('27', 'N500'),
        );
        $terms = $client->accountsPayable->terms;

        $created = $terms->create(new CreateTerm(
            id: new ObjectId('N500'),
            description: '60 Days term',
            status: RecordStatus::Active,
            due: new TermDue(days: 2, from: TermDateBasis::Of5thMonthFromBillDate),
            discount: new TermDiscount(
                days: 1,
                from: TermDateBasis::Of3rdMonthFromBillDate,
                amount: Decimal::fromInt(10),
                unit: TermAmountUnit::Amount,
                graceDays: 15,
                calculateOn: TermDiscountCalculation::BillTotal,
            ),
            penalty: new TermPenalty(
                cycle: TermPenaltyCycle::Quarterly,
                amount: Decimal::fromInt(101),
                unit: TermAmountUnit::Amount,
                graceDays: 16,
            ),
        ));
        $terms->update(new ObjectKey('27'), UpdateTerm::penalty(new TermPenalty(amount: new Decimal('10.5'))));
        $terms->update(
            new ObjectKey('27'),
            UpdateTerm::description('Net 60')
                ->withStatus(RecordStatus::Inactive)
                ->withDue(new TermDue(days: 60))
                ->withDiscount(new TermDiscount(unit: TermAmountUnit::Percentage)),
        );
        $terms->delete(new ObjectKey('27'));

        self::assertSame('27', $created->reference->key?->value);
        self::assertSame('POST', $http->requests[0]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/accounts-payable/term', $this->url($http->requests[0]));
        self::assertSame([
            'id' => 'N500',
            'description' => '60 Days term',
            'status' => 'active',
            'due' => ['days' => 2, 'from' => 'of5thMonthFromBillDate'],
            'discount' => [
                'days' => 1,
                'from' => 'of3rdMonthFromBillDate',
                'amount' => '10',
                'unit' => 'amount',
                'graceDays' => 15,
                'calculateOn' => 'billTotal',
            ],
            'penalty' => ['cycle' => 'quarterly', 'amount' => '101', 'unit' => 'amount', 'graceDays' => 16],
        ], $this->jsonBody($http->requests[0]));

        self::assertSame('PATCH', $http->requests[1]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/accounts-payable/term/27', $this->url($http->requests[1]));
        self::assertSame(['penalty' => ['amount' => '10.5']], $this->jsonBody($http->requests[1]));

        self::assertSame([
            'description' => 'Net 60',
            'status' => 'inactive',
            'due' => ['days' => 60],
            'discount' => ['unit' => 'percentage'],
        ], $this->jsonBody($http->requests[2]));

        self::assertSame('DELETE', $http->requests[3]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/accounts-payable/term/27', $this->url($http->requests[3]));
    }

    public function test_create_omits_unset_optional_fields(): void
    {
        $term = new CreateTerm(new ObjectId('NET30'), 'Net 30');

        self::assertSame(['id' => 'NET30', 'description' => 'Net 30'], $term->toArray());
    }

    public function test_it_rejects_a_blank_description(): void
    {
        $this->expectException(InvalidArgument::class);

        new CreateTerm(new ObjectId('NET30'), '  ');
    }

    public function test_it_rejects_an_empty_group(): void
    {
        $this->expectException(InvalidArgument::class);

        new TermPenalty;
    }

    public function test_it_rejects_negative_days(): void
    {
        $this->expectException(InvalidArgument::class);

        new TermDue(days: -1);
    }
}
