<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\AccountsReceivable;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Resources\AccountsReceivable\Customers\CreateCustomer;
use ControlAir\Intacct\Resources\AccountsReceivable\Customers\Customer;
use ControlAir\Intacct\Resources\AccountsReceivable\Customers\CustomersClient;
use ControlAir\Intacct\Resources\AccountsReceivable\Customers\CustomerStatus;
use ControlAir\Intacct\Resources\AccountsReceivable\Customers\UpdateCustomer;
use ControlAir\Intacct\Tests\Support\ApiTestCase;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\SensitiveString;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CustomersClient::class)]
#[CoversClass(Customer::class)]
#[CoversClass(CreateCustomer::class)]
#[CoversClass(UpdateCustomer::class)]
final class CustomersClientTest extends ApiTestCase
{
    public function test_it_maps_a_customer_with_contacts_and_redacts_the_tax_id(): void
    {
        [$transport, $http] = $this->transport($this->json([
            'ia::result' => [
                'key' => '14',
                'id' => 'CUST-014',
                'name' => 'Northwind Builders',
                'status' => 'activeNonPosting',
                'customerType' => ['key' => '3', 'id' => 'General Contractor', 'href' => '/objects/accounts-receivable/customer-type/3'],
                'parent' => ['key' => null, 'id' => null, 'name' => null],
                'term' => ['key' => '5', 'id' => 'Net 30'],
                'salesRepresentative' => ['key' => '8', 'id' => 'EMP-8', 'name' => 'Ada Lovelace'],
                'accountGroup' => ['key' => '2', 'id' => 'Commercial'],
                'defaultRevenueGLAccount' => ['key' => '40', 'id' => '4000', 'name' => 'Contract Revenue'],
                'contacts' => [
                    'default' => ['key' => '71', 'id' => 'Northwind Builders(CCUST-014)', 'printAs' => 'Northwind Builders'],
                    'primary' => ['key' => '72', 'id' => 'Grace Hopper'],
                    'billTo' => ['key' => '73', 'id' => 'Accounts Payable Desk'],
                    'shipTo' => ['key' => null, 'id' => null],
                ],
                'currency' => 'USD',
                'taxId' => '98-7654321',
                'creditLimit' => 250000,
                'retainagePercentage' => 10,
                'totalDue' => '18250.00',
                'isOnHold' => false,
                'notes' => null,
                'nsp::PROJECT_MANAGER' => 'grace',
                'href' => '/objects/accounts-receivable/customer/14',
            ],
            'ia::meta' => ['totalCount' => 1, 'totalSuccess' => 1, 'totalError' => 0],
        ]));

        $customer = $this->customers($transport)->get(new ObjectKey('14'));

        self::assertSame('https://api.intacct.com/ia/api/v1/objects/accounts-receivable/customer/14', $this->url($http->requests[0]));
        self::assertSame('CUST-014', $customer->id->value);
        self::assertSame(CustomerStatus::ActiveNonPosting, $customer->status);
        self::assertSame('General Contractor', $customer->customerType?->id?->value);
        self::assertNull($customer->parent);
        self::assertSame('Ada Lovelace', $customer->salesRepresentative?->name);
        self::assertSame('4000', $customer->defaultRevenueGLAccount?->id?->value);
        self::assertSame('71', $customer->defaultContact?->key?->value);
        self::assertSame('Grace Hopper', $customer->primaryContact?->id?->value);
        self::assertSame('73', $customer->billToContact?->key?->value);
        self::assertNull($customer->shipToContact);
        self::assertSame('250000', $customer->creditLimit?->value);
        self::assertSame('10', $customer->retainagePercentage?->value);
        self::assertSame('18250.00', $customer->totalDue?->value);
        self::assertSame('98-7654321', $customer->taxId?->reveal());
        self::assertSame(['value' => '[redacted]'], $customer->taxId->__debugInfo());
        self::assertFalse($customer->onHold);
        self::assertNull($customer->notes);
        self::assertSame('grace', $customer->customFields->get('PROJECT_MANAGER'));
    }

    public function test_an_unknown_status_maps_to_null(): void
    {
        $customer = Customer::fromArray(['key' => '14', 'id' => 'CUST-014', 'name' => 'Northwind', 'status' => 'archived']);

        self::assertNull($customer->status);
    }

    public function test_query_selects_the_default_fields_and_expands_dotted_contacts(): void
    {
        [$transport, $http] = $this->transport($this->json([
            'ia::result' => [[
                'key' => '14',
                'id' => 'CUST-014',
                'name' => 'Northwind Builders',
                'status' => 'active',
                'term.key' => '5',
                'term.id' => 'Net 30',
                'contacts.primary.key' => '72',
                'contacts.primary.id' => 'Grace Hopper',
                'contacts.shipTo.key' => null,
                'contacts.shipTo.id' => null,
                'nsp::REGION' => 'West',
            ]],
            'ia::meta' => ['totalCount' => 1, 'start' => 1, 'pageSize' => 100, 'next' => null],
        ]));

        $page = $this->customers($transport)->query((new ResourceQuery)->withFields('nsp::REGION'));

        $body = $this->jsonBody($http->requests[0]);
        self::assertSame('https://api.intacct.com/ia/api/v1/services/core/query', $this->url($http->requests[0]));
        self::assertSame('accounts-receivable/customer', $body['object']);
        self::assertSame([
            'key', 'id', 'name', 'status', 'customerType.key', 'customerType.id',
            'parent.key', 'parent.id', 'term.key', 'term.id', 'salesRepresentative.key',
            'salesRepresentative.id', 'accountGroup.key', 'accountGroup.id',
            'defaultRevenueGLAccount.key', 'defaultRevenueGLAccount.id', 'contacts.default.key',
            'contacts.default.id', 'contacts.primary.key', 'contacts.primary.id',
            'contacts.billTo.key', 'contacts.billTo.id', 'contacts.shipTo.key', 'contacts.shipTo.id',
            'currency', 'creditLimit', 'retainagePercentage', 'totalDue', 'isOnHold', 'notes', 'href',
            'nsp::REGION',
        ], $body['fields']);
        self::assertNotContains('taxId', $body['fields']);
        $customer = $page->items[0];
        self::assertSame('Net 30', $customer->term?->id?->value);
        self::assertSame('72', $customer->primaryContact?->key?->value);
        self::assertNull($customer->shipToContact);
        self::assertNull($customer->taxId);
        self::assertSame('West', $customer->customFields->get('REGION'));
    }

    public function test_it_sends_create_update_and_delete_payloads(): void
    {
        [$transport, $http] = $this->transport($this->mutation('14', 'CUST-014'), $this->mutation('14'), new Response(204));
        $customers = $this->customers($transport);
        $key = new IdempotencyKey('customer-cust-014');

        $result = $customers->create(new CreateCustomer(
            id: new ObjectId('CUST-014'),
            name: 'Northwind Builders',
            status: CustomerStatus::Active,
            customerType: ObjectReference::byId('General Contractor'),
            term: ObjectReference::byId('Net 30'),
            primaryContact: ObjectReference::byId('Grace Hopper'),
            billToContact: ObjectReference::byKey('73'),
            taxId: new SensitiveString('98-7654321'),
            creditLimit: new Decimal('250000.00'),
            retainagePercentage: new Decimal('10'),
            customFields: (new CustomFields)->with('PROJECT_MANAGER', 'grace'),
        ), $key);
        $customers->update(
            new ObjectKey('14'),
            UpdateCustomer::name('Northwind Builders LLC')
                ->withOnHold(true)
                ->withNotes(null)
                ->withShipToContact(ObjectReference::byId('Site Office'))
                ->withPrimaryContact(null)
                ->withCustomField('PROJECT_MANAGER', null),
            $key,
        );
        $customers->delete(new ObjectKey('14'));

        self::assertSame('14', $result->reference->key?->value);
        self::assertSame('POST', $http->requests[0]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/accounts-receivable/customer', $this->url($http->requests[0]));
        self::assertSame('customer-cust-014', $http->requests[0]->getHeaderLine('Idempotency-Key'));
        self::assertSame([
            'id' => 'CUST-014',
            'name' => 'Northwind Builders',
            'status' => 'active',
            'customerType' => ['id' => 'General Contractor'],
            'term' => ['id' => 'Net 30'],
            'contacts' => ['primary' => ['id' => 'Grace Hopper'], 'billTo' => ['key' => '73']],
            'taxId' => '98-7654321',
            'creditLimit' => '250000.00',
            'retainagePercentage' => '10',
            'nsp::PROJECT_MANAGER' => 'grace',
        ], $this->jsonBody($http->requests[0]));
        self::assertSame('PATCH', $http->requests[1]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/accounts-receivable/customer/14', $this->url($http->requests[1]));
        self::assertSame('customer-cust-014', $http->requests[1]->getHeaderLine('Idempotency-Key'));
        self::assertSame([
            'name' => 'Northwind Builders LLC',
            'isOnHold' => true,
            'notes' => null,
            'contacts' => ['shipTo' => ['id' => 'Site Office'], 'primary' => null],
            'nsp::PROJECT_MANAGER' => null,
        ], $this->jsonBody($http->requests[1]));
        self::assertSame('DELETE', $http->requests[2]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/accounts-receivable/customer/14', $this->url($http->requests[2]));
    }

    public function test_an_omitted_field_is_not_sent_while_an_explicit_null_clears_it(): void
    {
        self::assertSame(['status' => 'inactive'], UpdateCustomer::status(CustomerStatus::Inactive)->toArray());
        self::assertSame(
            ['status' => 'inactive', 'term' => null, 'creditLimit' => null, 'taxId' => null],
            UpdateCustomer::status(CustomerStatus::Inactive)->withTerm(null)->withCreditLimit(null)->withTaxId(null)->toArray(),
        );
    }

    public function test_create_many_and_delete_many_use_batch_requests(): void
    {
        [$transport, $http] = $this->transport(
            $this->json([
                'ia::result' => [
                    ['key' => '14', 'id' => 'CUST-014', 'ia::status' => 201],
                    ['key' => '15', 'id' => 'CUST-015', 'ia::status' => 201],
                ],
                'ia::meta' => ['totalCount' => 2, 'totalSuccess' => 2, 'totalError' => 0],
            ]),
            new Response(204),
        );
        $customers = $this->customers($transport);

        $created = $customers->createMany([
            new CreateCustomer(new ObjectId('CUST-014'), 'Northwind Builders'),
            new CreateCustomer(new ObjectId('CUST-015'), 'Contoso Developments', status: CustomerStatus::Inactive),
        ], atomic: true);
        $deleted = $customers->deleteMany([new ObjectKey('14'), new ObjectKey('15')]);

        self::assertTrue($created->isSuccessful());
        self::assertSame('15', $created->items[1]->reference?->key?->value);
        self::assertSame('true', $http->requests[0]->getHeaderLine('X-IA-API-Param-Transaction'));
        self::assertSame(
            [['id' => 'CUST-014', 'name' => 'Northwind Builders'], ['id' => 'CUST-015', 'name' => 'Contoso Developments', 'status' => 'inactive']],
            json_decode((string) $http->requests[0]->getBody(), true, flags: JSON_THROW_ON_ERROR),
        );
        self::assertTrue($deleted->isSuccessful());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/accounts-receivable/customer/14,15', $this->url($http->requests[1]));
    }

    private function customers(ApiTransport $transport): CustomersClient
    {
        return new CustomersClient($transport, new QueryClient($transport));
    }
}
