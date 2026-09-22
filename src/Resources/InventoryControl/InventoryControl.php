<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Resources\InventoryControl\Items\ItemsClient;
use ControlAir\Intacct\Resources\InventoryControl\ProductLines\ProductLinesClient;
use ControlAir\Intacct\Resources\InventoryControl\UnitsOfMeasure\UnitOfMeasureGroupsClient;
use ControlAir\Intacct\Resources\InventoryControl\UnitsOfMeasure\UnitsOfMeasureClient;
use ControlAir\Intacct\Resources\InventoryControl\Warehouses\WarehousesClient;

final readonly class InventoryControl
{
    public ItemsClient $items;

    public WarehousesClient $warehouses;

    public ProductLinesClient $productLines;

    public UnitOfMeasureGroupsClient $unitOfMeasureGroups;

    public UnitsOfMeasureClient $unitsOfMeasure;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->items = new ItemsClient($transport, $queries);
        $this->warehouses = new WarehousesClient($transport, $queries);
        $this->productLines = new ProductLinesClient($transport, $queries);
        $this->unitOfMeasureGroups = new UnitOfMeasureGroupsClient($transport, $queries);
        $this->unitsOfMeasure = new UnitsOfMeasureClient($transport, $queries);
    }
}
