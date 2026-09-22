<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\CustomFields;

use ControlAir\Intacct\Core\Query\Paginator;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Resources\AccountsPayable\Vendors\CreateVendor;
use ControlAir\Intacct\Resources\AccountsPayable\Vendors\UpdateVendor;
use ControlAir\Intacct\Resources\InventoryControl\Items\CostMethod;
use ControlAir\Intacct\Resources\InventoryControl\Items\CreateItem;
use ControlAir\Intacct\Resources\InventoryControl\Items\ItemType;
use ControlAir\Intacct\Resources\InventoryControl\Items\UpdateItem;
use ControlAir\Intacct\Resources\Projects\CreateProject;
use ControlAir\Intacct\Resources\Projects\UpdateProject;
use ControlAir\Intacct\Resources\Purchasing\Documents\CreatePurchasingDocument;
use ControlAir\Intacct\Resources\Purchasing\Documents\CreatePurchasingDocumentLine;
use ControlAir\Intacct\Resources\Purchasing\Documents\UpdatePurchasingDocument;
use ControlAir\Intacct\Resources\Purchasing\Documents\UpdatePurchasingDocumentLine;
use ControlAir\Intacct\Tests\Support\ApiTestCase;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\Dimensions;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(CustomFields::class)]
#[CoversClass(ResourceQuery::class)]
#[CoversClass(Dimensions::class)]
final class ResourceCustomFieldsTest extends ApiTestCase
{
    public function test_a_vendor_read_maps_custom_fields_from_the_guide_example(): void
    {
        [$client] = $this->client($this->json([
            'ia::result' => [
                'key' => '23',
                'id' => 'California Local vID',
                'name' => 'California Local Plc',
                'retainagePercentage' => 20,
                'nsp::CUSTOM_CHECKBOX' => false,
                'nsp::CUSTOM_EMAIL' => null,
                'nsp::CUSTOM_CURRENCY' => 59,
                'nsp::CUSTOM_PERCENTAGE' => 7,
                'nsp::PICKLIST' => 'one',
                'nsp::MULTI_PICKLIST' => [],
                'nsp::SEQUENCE' => 'Inv-1000-Doc',
                'nsp::r10258' => ['key' => null, 'id' => null],
                'nsp::r11192' => ['key' => null, 'id' => null],
                'href' => '/objects/accounts-payable/vendor/23',
            ],
            'ia::meta' => ['totalCount' => 1, 'totalSuccess' => 1, 'totalError' => 0],
        ]));

        $vendor = $client->accountsPayable->vendors->get(new ObjectKey('23'));

        self::assertSame('California Local Plc', $vendor->name);
        self::assertFalse($vendor->customFields->get('CUSTOM_CHECKBOX'));
        self::assertTrue($vendor->customFields->has('CUSTOM_EMAIL'));
        self::assertNull($vendor->customFields->get('CUSTOM_EMAIL'));
        self::assertSame(59, $vendor->customFields->get('nsp::CUSTOM_CURRENCY'));
        self::assertSame('one', $vendor->customFields->get('PICKLIST'));
        self::assertSame([], $vendor->customFields->get('MULTI_PICKLIST'));
        self::assertSame('Inv-1000-Doc', $vendor->customFields->get('SEQUENCE'));
        self::assertNull($vendor->customFields->reference('r10258'));
        self::assertCount(9, $vendor->customFields->all());
        self::assertFalse($vendor->customFields->has('retainagePercentage'));
    }

    public function test_a_vendor_query_selects_additional_fields_and_maps_dotted_references(): void
    {
        [$client, $http] = $this->client($this->json([
            'ia::result' => [[
                'key' => '23',
                'id' => 'California Local vID',
                'name' => 'California Local Plc',
                'nsp::PICKLIST' => 'two',
                'nsp::r10258.key' => '5',
                'nsp::r10258.id' => 'EMP-5',
            ]],
            'ia::meta' => ['totalCount' => 1, 'start' => 1, 'pageSize' => 100, 'next' => null],
        ]));

        $page = $client->accountsPayable->vendors->query(
            (new ResourceQuery)->withFields('nsp::PICKLIST', 'nsp::r10258.key')->withFields('nsp::r10258.id', 'id'),
        );

        $fields = $this->jsonBody($http->requests[0])['fields'];
        self::assertIsArray($fields);
        self::assertSame(['nsp::PICKLIST', 'nsp::r10258.key', 'nsp::r10258.id'], array_slice($fields, -3));
        self::assertSame(1, count(array_keys($fields, 'id', true)));
        $vendor = $page->items[0];
        self::assertSame('two', $vendor->customFields->get('PICKLIST'));
        self::assertSame('5', $vendor->customFields->reference('r10258')?->key?->value);
        self::assertSame('EMP-5', $vendor->customFields->reference('r10258')->id?->value);
    }

    public function test_vendor_create_and_patch_payloads_carry_prefixed_custom_fields(): void
    {
        [$client, $http] = $this->client($this->mutation('23'), $this->mutation('23'));

        $client->accountsPayable->vendors->create(new CreateVendor(
            id: new ObjectId('VEND-001'),
            name: 'Acme Supply',
            customFields: (new CustomFields)
                ->with('CUSTOM_CHECKBOX', true)
                ->with('nsp::r10258', ObjectReference::byId('EMP-5')),
        ));
        // The PATCH example from the custom-fields guide, which mixes standard and custom fields.
        $client->accountsPayable->vendors->update(
            new ObjectKey('23'),
            UpdateVendor::customField('CUSTOM_CURRENCY', 59)
                ->withCreditLimit(new Decimal('1200000'))
                ->withOnHold(false)
                ->withNotes('Vendor to verify the number of units')
                ->withCustomField('nsp::CUSTOM_PERCENTAGE', 7)
                ->withCustomField('CUSTOM_EMAIL', null),
        );

        self::assertSame([
            'id' => 'VEND-001',
            'name' => 'Acme Supply',
            'nsp::CUSTOM_CHECKBOX' => true,
            'nsp::r10258' => ['id' => 'EMP-5'],
        ], $this->jsonBody($http->requests[0]));
        self::assertSame('PATCH', $http->requests[1]->getMethod());
        self::assertSame([
            'nsp::CUSTOM_CURRENCY' => 59,
            'creditLimit' => '1200000',
            'isOnHold' => false,
            'notes' => 'Vendor to verify the number of units',
            'nsp::CUSTOM_PERCENTAGE' => 7,
            'nsp::CUSTOM_EMAIL' => null,
        ], $this->jsonBody($http->requests[1]));
    }

    public function test_an_item_read_and_query_map_custom_fields(): void
    {
        [$client, $http] = $this->client(
            $this->json(['ia::result' => [
                'key' => '12',
                'id' => 'HAMMER16',
                'name' => 'Hammer',
                'nsp::COLOR' => 'red',
                'nsp::SIZES' => ['S', 'M'],
            ]]),
            $this->json([
                'ia::result' => [['key' => '12', 'id' => 'HAMMER16', 'name' => 'Hammer', 'nsp::COLOR' => 'blue']],
                'ia::meta' => ['totalCount' => 1],
            ]),
        );

        $item = $client->inventory->items->get(new ObjectKey('12'));
        $page = $client->inventory->items->query((new ResourceQuery)->withFields('nsp::COLOR'));

        self::assertSame('red', $item->customFields->get('COLOR'));
        self::assertSame(['S', 'M'], $item->customFields->get('SIZES'));
        self::assertSame('blue', $page->items[0]->customFields->get('COLOR'));
        $fields = $this->jsonBody($http->requests[1])['fields'];
        self::assertIsArray($fields);
        self::assertSame('nsp::COLOR', end($fields));
        self::assertContains('href', $fields);
    }

    public function test_item_create_and_patch_payloads_carry_prefixed_custom_fields(): void
    {
        [$client, $http] = $this->client($this->mutation('12'), $this->mutation('12'));

        $client->inventory->items->create(new CreateItem(
            id: new ObjectId('HAMMER16'),
            name: 'Hammer',
            itemType: ItemType::Inventory,
            costMethod: CostMethod::Average,
            customFields: new CustomFields(['COLOR' => 'red', 'nsp::SIZES' => ['S', 'M']]),
        ));
        $client->inventory->items->update(
            new ObjectKey('12'),
            UpdateItem::customFields(new CustomFields(['COLOR' => null]))->withTaxable(true),
        );

        self::assertSame([
            'id' => 'HAMMER16',
            'name' => 'Hammer',
            'itemType' => 'inventory',
            'costMethod' => 'average',
            'nsp::COLOR' => 'red',
            'nsp::SIZES' => ['S', 'M'],
        ], $this->jsonBody($http->requests[0]));
        self::assertSame([
            'nsp::COLOR' => null,
            'sales' => ['isTaxable' => true],
        ], $this->jsonBody($http->requests[1]));
    }

    public function test_an_update_cannot_start_from_empty_custom_fields(): void
    {
        $this->expectException(InvalidArgument::class);

        UpdateItem::customFields(new CustomFields);
    }

    public function test_a_project_read_create_and_patch_carry_custom_fields(): void
    {
        [$client, $http] = $this->client(
            $this->json(['ia::result' => [
                'key' => '10',
                'id' => 'PROJ-001',
                'name' => 'Headquarters Renovation',
                'nsp::INDIRECT' => true,
                'nsp::r20001' => ['key' => '4', 'id' => 'CUST-4', 'name' => 'Owner'],
            ]]),
            $this->mutation('10', 'PROJ-001'),
            $this->mutation('10', 'PROJ-001'),
        );

        $project = $client->projects->get(new ObjectKey('10'));
        $client->projects->create(new CreateProject(
            id: new ObjectId('PROJ-001'),
            name: 'Headquarters Renovation',
            customFields: $project->customFields->with('INDIRECT', false),
        ));
        $client->projects->update(
            new ObjectKey('10'),
            UpdateProject::name('HQ Renovation')->withCustomFields(
                (new CustomFields)->with('INDIRECT', true)->with('r20001', null),
            ),
        );

        self::assertTrue($project->customFields->get('INDIRECT'));
        self::assertSame('Owner', $project->customFields->reference('r20001')?->name);
        self::assertSame([
            'id' => 'PROJ-001',
            'name' => 'Headquarters Renovation',
            'nsp::INDIRECT' => false,
            'nsp::r20001' => ['key' => '4'],
        ], $this->jsonBody($http->requests[1]));
        self::assertSame([
            'name' => 'HQ Renovation',
            'nsp::INDIRECT' => true,
            'nsp::r20001' => null,
        ], $this->jsonBody($http->requests[2]));
    }

    public function test_additional_fields_survive_pagination(): void
    {
        [$client, $http] = $this->client(
            $this->json([
                'ia::result' => [['key' => '10', 'id' => 'PROJ-001', 'name' => 'A', 'nsp::INDIRECT' => true]],
                'ia::meta' => ['totalCount' => 2, 'start' => 1, 'pageSize' => 1, 'next' => 2],
            ]),
            $this->json([
                'ia::result' => [['key' => '11', 'id' => 'PROJ-002', 'name' => 'B', 'nsp::INDIRECT' => false]],
                'ia::meta' => ['totalCount' => 2, 'start' => 2, 'pageSize' => 1, 'next' => null],
            ]),
        );

        $projects = iterator_to_array(Paginator::over(
            $client->projects->query(...),
            (new ResourceQuery(size: 1))->withFields('nsp::INDIRECT'),
        )->items(), false);

        self::assertCount(2, $projects);
        self::assertFalse($projects[1]->customFields->get('INDIRECT'));
        $fields = $this->jsonBody($http->requests[1])['fields'];
        self::assertIsArray($fields);
        self::assertContains('nsp::INDIRECT', $fields);
    }

    public function test_a_document_read_maps_header_custom_fields_and_line_user_defined_dimensions(): void
    {
        [$client] = $this->client($this->json([
            'ia::result' => [
                'key' => '198',
                'id' => '198',
                'documentNumber' => 'VI#0011',
                'nsp::VENDOR_REFERENCE' => 'VREF-PO-2026-00382',
                'lines' => [[
                    'key' => '46',
                    'nsp::LINE_NOTE' => 'Rush',
                    'dimensions' => [
                        'department' => ['key' => '9', 'id' => '11', 'name' => 'Accounting'],
                        'nsp::refcode_gl' => ['key' => '10004', 'href' => '/objects/platform-apps/nsp::refcode_gl/10004'],
                        'nsp::restriction' => ['key' => null],
                    ],
                ]],
            ],
        ]));

        $document = $client->purchasing->documents('Vendor Invoice')->get(new ObjectKey('198'));
        $line = $document->lines[0];

        self::assertSame('VREF-PO-2026-00382', $document->customFields->get('VENDOR_REFERENCE'));
        self::assertSame('Rush', $line->customFields->get('LINE_NOTE'));
        self::assertFalse($line->customFields->has('refcode_gl'));
        self::assertSame('10004', $line->dimensions->userDefined('refcode_gl')?->key?->value);
        self::assertNull($line->dimensions->userDefined('restriction'));
        self::assertSame('11', $line->dimensions->department?->id?->value);
    }

    public function test_a_document_query_from_the_guide_selects_a_custom_field(): void
    {
        [$client, $http] = $this->client($this->json([
            'ia::result' => [
                ['key' => '191', 'id' => '191', 'documentNumber' => 'VI#0009', 'documentType' => 'Vendor Invoice', 'nsp::VENDOR_REFERENCE' => null, 'href' => '/objects/purchasing/document::Vendor%20Invoice/191'],
                ['key' => '198', 'id' => '198', 'documentNumber' => 'VI#0011', 'documentType' => 'Vendor Invoice', 'nsp::VENDOR_REFERENCE' => 'VREF-PO-2026-00382', 'href' => '/objects/purchasing/document::Vendor%20Invoice/198'],
            ],
            'ia::meta' => ['totalCount' => 4, 'start' => 1, 'pageSize' => 100, 'next' => null, 'previous' => null],
        ]));

        $page = $client->purchasing->documents('Vendor Invoice')->query(
            (new ResourceQuery)->withFields('documentType', 'nsp::VENDOR_REFERENCE'),
        );

        $body = $this->jsonBody($http->requests[0]);
        self::assertSame('purchasing/document::Vendor Invoice', $body['object']);
        self::assertIsArray($body['fields']);
        self::assertSame(['documentType', 'nsp::VENDOR_REFERENCE'], array_slice($body['fields'], -2));
        self::assertTrue($page->items[0]->customFields->has('VENDOR_REFERENCE'));
        self::assertNull($page->items[0]->customFields->get('VENDOR_REFERENCE'));
        self::assertSame('VREF-PO-2026-00382', $page->items[1]->customFields->get('VENDOR_REFERENCE'));
    }

    public function test_document_create_carries_header_and_line_custom_fields_and_user_defined_dimensions(): void
    {
        [$client, $http] = $this->client($this->mutation('198'));

        $client->purchasing->documents('Vendor Invoice')->create(new CreatePurchasingDocument(
            transactionDate: new LocalDate('2026-09-01'),
            vendor: ObjectReference::byId('VEND-001'),
            lines: [new CreatePurchasingDocumentLine(
                item: ObjectReference::byId('HAMMER16'),
                warehouse: ObjectReference::byId('WH-01'),
                location: ObjectReference::byId('HQ'),
                unit: 'Each',
                unitQuantity: new Decimal('20'),
                unitPrice: new Decimal('9.25'),
                dimensions: new Dimensions(
                    project: ObjectReference::byId('PROJ-001'),
                    custom: (new CustomFields)->with('refcode_gl', ObjectReference::byKey('10004')),
                ),
                customFields: (new CustomFields)->with('LINE_NOTE', 'Rush'),
            )],
            customFields: (new CustomFields)->with('VENDOR_REFERENCE', 'VREF-1'),
        ));

        self::assertSame([
            'txnDate' => '2026-09-01',
            'vendor' => ['id' => 'VEND-001'],
            'lines' => [[
                'unit' => 'Each',
                'unitQuantity' => '20',
                'unitPrice' => '9.25',
                'dimensions' => [
                    'project' => ['id' => 'PROJ-001'],
                    'nsp::refcode_gl' => ['key' => '10004'],
                    'item' => ['id' => 'HAMMER16'],
                    'warehouse' => ['id' => 'WH-01'],
                    'location' => ['id' => 'HQ'],
                ],
                'nsp::LINE_NOTE' => 'Rush',
            ]],
            'nsp::VENDOR_REFERENCE' => 'VREF-1',
        ], $this->jsonBody($http->requests[0]));
    }

    public function test_user_defined_dimensions_round_trip_through_a_document_patch(): void
    {
        [$client, $http] = $this->client(
            $this->json(['ia::result' => [
                'key' => '198',
                'id' => '198',
                'lines' => [[
                    'key' => '46',
                    'dimensions' => [
                        'department' => ['key' => '9', 'id' => '11'],
                        'nsp::refcode_gl' => ['key' => '10004', 'href' => '/objects/platform-apps/nsp::refcode_gl/10004'],
                        'nsp::vssn' => ['key' => '10008'],
                    ],
                ]],
            ]]),
            $this->mutation('198'),
        );
        $documents = $client->purchasing->documents('Vendor Invoice');

        $line = $documents->get(new ObjectKey('198'))->lines[0];
        $dimensions = $line->dimensions;
        $changed = new Dimensions(
            department: $dimensions->department,
            custom: $dimensions->custom->with('vssn', null)->with('restriction', ObjectReference::byKey('10011')),
        );
        $documents->update(
            new ObjectKey('198'),
            UpdatePurchasingDocument::memo('Recoded')
                ->withCustomField('VENDOR_REFERENCE', 'VREF-2')
                ->withUpdatedLine(
                    $line->key,
                    UpdatePurchasingDocumentLine::lineDescription('Hammers')
                        ->withDimensions($changed)
                        ->withCustomField('LINE_NOTE', null),
                )
                ->withCustomFields(new CustomFields(['APPROVER' => 'ada'])),
        );

        self::assertSame([
            'memo' => 'Recoded',
            'nsp::VENDOR_REFERENCE' => 'VREF-2',
            'nsp::APPROVER' => 'ada',
            'lines' => [[
                'key' => '46',
                'lineDescription' => 'Hammers',
                'dimensions' => [
                    'department' => ['key' => '9'],
                    'nsp::refcode_gl' => ['key' => '10004'],
                    'nsp::vssn' => null,
                    'nsp::restriction' => ['key' => '10011'],
                ],
                'nsp::LINE_NOTE' => null,
            ]],
        ], $this->jsonBody($http->requests[1]));
    }

    public function test_a_document_update_can_carry_only_custom_fields(): void
    {
        self::assertSame(
            ['nsp::VENDOR_REFERENCE' => 'VREF-3'],
            UpdatePurchasingDocument::customField('VENDOR_REFERENCE', 'VREF-3')->toArray(),
        );
    }

    /** @param class-string $class */
    #[DataProvider('resources')]
    public function test_every_resource_exposes_custom_fields(string $class): void
    {
        $property = new \ReflectionProperty($class, 'customFields');
        $parameters = (new \ReflectionClass($class))->getConstructor()?->getParameters() ?? [];
        $last = end($parameters);

        $type = $property->getType();

        self::assertTrue($property->isPublic());
        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        self::assertSame(CustomFields::class, $type->getName());
        self::assertNotFalse($last);
        self::assertSame('customFields', $last->getName());
        self::assertTrue($last->isDefaultValueAvailable());
    }

    /** @return iterable<string, array{class-string}> */
    public static function resources(): iterable
    {
        $nested = [
            'TermDiscount', 'TermDue', 'TermPenalty', 'DimensionDefinition',
            'AccountRequiredDimensions', 'ProjectBudget', 'ProjectResourcePricing',
        ];

        foreach (self::resourceClasses() as $name => $class) {
            if (str_starts_with($name, 'Update') || in_array($name, $nested, true)) {
                continue;
            }

            if (str_starts_with($name, 'Create') || method_exists($class, 'fromArray')) {
                yield $name => [$class];
            }
        }
    }

    /** @param class-string $class */
    #[DataProvider('updates')]
    public function test_every_update_can_set_custom_fields(string $class): void
    {
        foreach (['customField', 'customFields'] as $method) {
            self::assertTrue((new \ReflectionMethod($class, $method))->isStatic(), $method);
        }

        foreach (['withCustomField', 'withCustomFields'] as $method) {
            self::assertFalse((new \ReflectionMethod($class, $method))->isStatic(), $method);
        }

        $update = (new \ReflectionMethod($class, 'customField'))->invoke(null, 'FIRST', 1);
        self::assertInstanceOf($class, $update);
        $update = (new \ReflectionMethod($class, 'withCustomField'))->invoke($update, 'SECOND', null);
        self::assertInstanceOf($class, $update);
        $update = (new \ReflectionMethod($class, 'withCustomFields'))->invoke($update, new CustomFields(['THIRD' => 'x']));
        self::assertInstanceOf($class, $update);

        self::assertSame(
            ['nsp::FIRST' => 1, 'nsp::SECOND' => null, 'nsp::THIRD' => 'x'],
            (new \ReflectionMethod($class, 'toArray'))->invoke($update),
        );
    }

    /** @return iterable<string, array{class-string}> */
    public static function updates(): iterable
    {
        foreach (self::resourceClasses() as $name => $class) {
            if (str_starts_with($name, 'Update')) {
                yield $name => [$class];
            }
        }
    }

    /** @return array<string, class-string> */
    private static function resourceClasses(): array
    {
        $root = dirname(__DIR__, 2).'/src/Resources/';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $classes = [];

        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root), -4);
            $class = 'ControlAir\\Intacct\\Resources\\'.str_replace('/', '\\', $relative);

            if (class_exists($class)) {
                $classes[$file->getBasename('.php')] = $class;
            }
        }

        ksort($classes);

        return $classes;
    }
}
