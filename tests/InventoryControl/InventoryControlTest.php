<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\InventoryControl;

use ControlAir\Intacct\Core\Query\Filter;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Resources\InventoryControl\Items\CostMethod;
use ControlAir\Intacct\Resources\InventoryControl\Items\CreateItem;
use ControlAir\Intacct\Resources\InventoryControl\Items\Item;
use ControlAir\Intacct\Resources\InventoryControl\Items\ItemsClient;
use ControlAir\Intacct\Resources\InventoryControl\Items\ItemType;
use ControlAir\Intacct\Resources\InventoryControl\Items\UpdateItem;
use ControlAir\Intacct\Resources\InventoryControl\ProductLines\CreateProductLine;
use ControlAir\Intacct\Resources\InventoryControl\UnitsOfMeasure\CreateUnitOfMeasure;
use ControlAir\Intacct\Resources\InventoryControl\UnitsOfMeasure\UnitOfMeasureGroup;
use ControlAir\Intacct\Resources\InventoryControl\UnitsOfMeasure\UpdateUnitOfMeasureGroup;
use ControlAir\Intacct\Resources\InventoryControl\Warehouses\CreateWarehouse;
use ControlAir\Intacct\Resources\InventoryControl\Warehouses\UpdateWarehouse;
use ControlAir\Intacct\Resources\InventoryControl\Warehouses\WarehousesClient;
use ControlAir\Intacct\Tests\Support\ApiTestCase;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ItemsClient::class)]
#[CoversClass(Item::class)]
#[CoversClass(WarehousesClient::class)]
#[CoversClass(UnitOfMeasureGroup::class)]
final class InventoryControlTest extends ApiTestCase
{
    public function test_it_maps_an_item_with_nested_purchasing_and_sales_groups(): void
    {
        [$client, $http] = $this->client($this->json([
            'ia::result' => [
                'key' => '12',
                'id' => 'HAMMER16',
                'name' => '16oz framing claw hammer',
                'status' => 'active',
                'itemType' => 'inventory',
                'costMethod' => 'FIFO',
                'productLine' => ['key' => '3', 'id' => 'TOOLS'],
                'unitOfMeasureGroup' => ['key' => '1', 'id' => 'Count'],
                'purchasing' => ['standardCost' => '9.25'],
                'sales' => ['basePrice' => '19.99', 'isTaxable' => true],
                'quantityOnHand' => '42',
                'quantityOnOrder' => '10',
            ],
        ]));

        $item = $client->inventory->items->get(new ObjectKey('12'));

        self::assertSame(ItemType::Inventory, $item->itemType);
        self::assertSame(CostMethod::Fifo, $item->costMethod);
        self::assertSame('TOOLS', $item->productLine?->id?->value);
        self::assertSame('9.25', $item->standardCost?->value);
        self::assertSame('19.99', $item->basePrice?->value);
        self::assertTrue($item->taxable);
        self::assertSame('42', $item->quantityOnHand?->value);
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/inventory-control/item/12', $this->url($http->requests[0]));
    }

    public function test_item_queries_expand_nested_groups_from_dotted_keys(): void
    {
        [$client, $http] = $this->client($this->json([
            'ia::result' => [[
                'key' => '12',
                'id' => 'HAMMER16',
                'name' => 'Hammer',
                'productLine.id' => 'TOOLS',
                'purchasing.standardCost' => '9.25',
                'sales.basePrice' => '19.99',
            ]],
            'ia::meta' => ['totalCount' => 1],
        ]));

        $page = $client->inventory->items->query(new ResourceQuery(
            filters: [Filter::equal('itemType', 'inventory')],
        ));

        self::assertSame('TOOLS', $page->items[0]->productLine?->id?->value);
        self::assertSame('9.25', $page->items[0]->standardCost?->value);
        self::assertSame('19.99', $page->items[0]->basePrice?->value);
        self::assertSame('inventory-control/item', $this->jsonBody($http->requests[0])['object']);
    }

    public function test_item_payloads_nest_purchasing_and_sales_fields(): void
    {
        [$client, $http] = $this->client($this->mutation('12'), $this->mutation('12'));

        $client->inventory->items->create(new CreateItem(
            id: new ObjectId('HAMMER16'),
            name: 'Hammer',
            itemType: ItemType::Inventory,
            costMethod: CostMethod::Average,
            productLine: ObjectReference::byId('TOOLS'),
            standardCost: new Decimal('9.25'),
            basePrice: new Decimal('19.99'),
        ));
        $client->inventory->items->update(
            new ObjectKey('12'),
            UpdateItem::status(RecordStatus::Inactive)->withBasePrice(new Decimal('21.00'))->withTaxable(false),
        );

        self::assertSame([
            'id' => 'HAMMER16',
            'name' => 'Hammer',
            'itemType' => 'inventory',
            'costMethod' => 'average',
            'productLine' => ['id' => 'TOOLS'],
            'purchasing' => ['standardCost' => '9.25'],
            'sales' => ['basePrice' => '19.99'],
        ], $this->jsonBody($http->requests[0]));
        self::assertSame([
            'status' => 'inactive',
            'sales' => ['basePrice' => '21.00', 'isTaxable' => false],
        ], $this->jsonBody($http->requests[1]));
    }

    public function test_warehouse_payloads_and_mapping(): void
    {
        [$client, $http] = $this->client(
            $this->mutation('5'),
            $this->mutation('5'),
            $this->json(['ia::result' => [
                'key' => '5',
                'id' => 'WH-01',
                'name' => 'Main',
                'location' => ['key' => '1', 'id' => 'HQ'],
                'enableNegativeInv' => false,
            ]]),
        );
        $warehouses = $client->inventory->warehouses;

        $warehouses->create(new CreateWarehouse(
            id: new ObjectId('WH-01'),
            name: 'Main',
            location: ObjectReference::byId('HQ'),
            negativeInventoryEnabled: false,
        ));
        $warehouses->update(new ObjectKey('5'), UpdateWarehouse::name('Main yard')->withManager(null));
        $warehouse = $warehouses->get(new ObjectKey('5'));

        self::assertSame([
            'id' => 'WH-01',
            'name' => 'Main',
            'location' => ['id' => 'HQ'],
            'enableNegativeInv' => false,
        ], $this->jsonBody($http->requests[0]));
        self::assertSame(['name' => 'Main yard', 'manager' => null], $this->jsonBody($http->requests[1]));
        self::assertSame('HQ', $warehouse->location?->id?->value);
        self::assertFalse($warehouse->negativeInventoryEnabled);
    }

    public function test_product_line_and_units_of_measure(): void
    {
        [$client, $http] = $this->client(
            $this->mutation('3'),
            $this->json(['ia::result' => [
                'key' => '1',
                'id' => 'Count',
                'baseUnit' => 'Each',
                'defaults' => [
                    'purchaseOrder' => ['key' => '7', 'id' => 'Box'],
                ],
            ]]),
            $this->mutation('1'),
            $this->mutation('7'),
        );

        $client->inventory->productLines->create(new CreateProductLine(
            id: new ObjectId('TOOLS'),
            description: 'Hand tools',
        ));
        $group = $client->inventory->unitOfMeasureGroups->get(new ObjectKey('1'));
        $client->inventory->unitOfMeasureGroups->update(
            new ObjectKey('1'),
            UpdateUnitOfMeasureGroup::defaults(purchaseOrder: ObjectReference::byId('Box')),
        );
        $client->inventory->unitsOfMeasure->create(new CreateUnitOfMeasure(
            id: new ObjectId('Box'),
            group: ObjectReference::byId('Count'),
            conversionFactor: new Decimal('12'),
        ));

        self::assertSame(['id' => 'TOOLS', 'description' => 'Hand tools'], $this->jsonBody($http->requests[0]));
        self::assertSame('Each', $group->baseUnit);
        self::assertSame('Box', $group->defaultPurchasingUnit?->id?->value);
        self::assertNull($group->defaultInventoryUnit);
        self::assertSame(['defaults' => ['purchaseOrder' => ['id' => 'Box']]], $this->jsonBody($http->requests[2]));
        self::assertSame(
            ['id' => 'Box', 'parent' => ['id' => 'Count'], 'conversionFactor' => '12'],
            $this->jsonBody($http->requests[3]),
        );
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/inventory-control/unit-of-measure', $this->url($http->requests[3]));
    }
}
