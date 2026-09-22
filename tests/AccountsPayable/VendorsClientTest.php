<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\AccountsPayable;

use ControlAir\Intacct\Resources\AccountsPayable\Vendors\CreateVendor;
use ControlAir\Intacct\Resources\AccountsPayable\Vendors\UpdateVendor;
use ControlAir\Intacct\Resources\AccountsPayable\Vendors\Vendor;
use ControlAir\Intacct\Resources\AccountsPayable\Vendors\VendorsClient;
use ControlAir\Intacct\Resources\AccountsPayable\Vendors\VendorStatus;
use ControlAir\Intacct\Tests\Support\ApiTestCase;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\SensitiveString;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(VendorsClient::class)]
#[CoversClass(Vendor::class)]
final class VendorsClientTest extends ApiTestCase
{
    public function test_it_maps_a_vendor_and_redacts_the_tax_id(): void
    {
        [$client, $http] = $this->client($this->json([
            'ia::result' => [
                'key' => '85',
                'id' => 'VEND-001',
                'name' => 'Acme Supply',
                'status' => 'activeNonPosting',
                'vendorType' => ['key' => '2', 'id' => 'Supplier'],
                'term' => ['key' => '4', 'id' => 'Net 30'],
                'taxId' => '12-3456789',
                'creditLimit' => 5000.5,
                'totalDue' => '120.00',
                'isOnHold' => false,
                'href' => '/objects/accounts-payable/vendor/85',
            ],
        ]));

        $vendor = $client->accountsPayable->vendors->get(new ObjectKey('85'));

        self::assertSame('VEND-001', $vendor->id->value);
        self::assertSame(VendorStatus::ActiveNonPosting, $vendor->status);
        self::assertSame('Net 30', $vendor->term?->id?->value);
        self::assertSame('5000.5', $vendor->creditLimit?->value);
        self::assertSame('12-3456789', $vendor->taxId?->reveal());
        self::assertSame(['value' => '[redacted]'], $vendor->taxId->__debugInfo());
        self::assertFalse($vendor->onHold);
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/accounts-payable/vendor/85', $this->url($http->requests[0]));
    }

    public function test_query_rows_with_dotted_keys_populate_references(): void
    {
        [$client, $http] = $this->client($this->json([
            'ia::result' => [[
                'key' => '85',
                'id' => 'VEND-001',
                'name' => 'Acme Supply',
                'vendorType.key' => '2',
                'vendorType.id' => 'Supplier',
                'term.key' => null,
                'term.id' => null,
            ]],
            'ia::meta' => ['totalCount' => 1, 'start' => 1, 'pageSize' => 100, 'next' => null],
        ]));

        $page = $client->accountsPayable->vendors->query();

        self::assertSame('Supplier', $page->items[0]->vendorType?->id?->value);
        self::assertNull($page->items[0]->term);
        $body = $this->jsonBody($http->requests[0]);
        self::assertSame('accounts-payable/vendor', $body['object']);
        self::assertIsArray($body['fields']);
        self::assertNotContains('taxId', $body['fields']);
    }

    public function test_it_sends_create_update_and_delete_payloads(): void
    {
        [$client, $http] = $this->client($this->mutation('85'), $this->mutation('85'), $this->mutation('85'));
        $vendors = $client->accountsPayable->vendors;

        $vendors->create(new CreateVendor(
            id: new ObjectId('VEND-001'),
            name: 'Acme Supply',
            status: VendorStatus::Active,
            term: ObjectReference::byId('Net 30'),
            taxId: new SensitiveString('12-3456789'),
            creditLimit: new Decimal('5000.00'),
        ));
        $vendors->update(new ObjectKey('85'), UpdateVendor::name('Acme')->withOnHold(true)->withNotes(null));
        $vendors->delete(new ObjectKey('85'));

        self::assertSame([
            'id' => 'VEND-001',
            'name' => 'Acme Supply',
            'status' => 'active',
            'term' => ['id' => 'Net 30'],
            'taxId' => '12-3456789',
            'creditLimit' => '5000.00',
        ], $this->jsonBody($http->requests[0]));
        self::assertSame('PATCH', $http->requests[1]->getMethod());
        self::assertSame(['name' => 'Acme', 'isOnHold' => true, 'notes' => null], $this->jsonBody($http->requests[1]));
        self::assertSame('DELETE', $http->requests[2]->getMethod());
    }
}
