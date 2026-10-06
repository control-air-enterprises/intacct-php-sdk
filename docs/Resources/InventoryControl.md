# Inventory Control

[Docs](../README.md) › [Resources](README.md)

Namespace: `ControlAir\Intacct\Resources\InventoryControl`

| Client | Object | Operations |
| --- | --- | --- |
| `inventory->items` | `inventory-control/item` | Get, query, create, update, delete |
| `inventory->warehouses` | `inventory-control/warehouse` | Get, query, create, update, delete |
| `inventory->productLines` | `inventory-control/product-line` | Get, query, create, update, delete |
| `inventory->unitOfMeasureGroups` | `inventory-control/unit-of-measure-group` | Get, query, create, update, delete |
| `inventory->unitsOfMeasure` | `inventory-control/unit-of-measure` | Get, query, create, update, delete |

Sage does not allow an item's type or cost method to change after creation, so `UpdateItem` does not offer them. Item-owned collections (warehouse details, vendors, kit components, cross-references) are not yet mapped.
