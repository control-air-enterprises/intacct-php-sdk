<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\ValueObjects;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Dimensions;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CustomFields::class)]
#[CoversClass(Dimensions::class)]
final class CustomFieldsTest extends TestCase
{
    /** The GET vendor excerpt from the custom-fields guide, verbatim. */
    private const VENDOR_RESPONSE = '{"ia::result":{"key":"23","id":"California Local vID","name":"California Local Plc","retainagePercentage":20,"nsp::CUSTOM_CHECKBOX":false,"nsp::CUSTOM_EMAIL":null,"nsp::CUSTOM_CURRENCY":59,"nsp::CUSTOM_PERCENTAGE":7,"nsp::PICKLIST":"one","nsp::MULTI_PICKLIST":[],"nsp::SEQUENCE":"Inv-1000-Doc","nsp::r10258":{"key":null,"id":null},"nsp::r11192":{"key":null,"id":null},"href":"/objects/accounts-payable/vendor/23"},"ia::meta":{"totalCount":1,"totalSuccess":1,"totalError":0}}';

    public function test_names_are_normalized_to_carry_the_prefix(): void
    {
        $fields = (new CustomFields)->with('INDIRECT', true)->with('nsp::COLOR', 'red');

        self::assertSame('nsp::INDIRECT', CustomFields::name('INDIRECT'));
        self::assertSame('nsp::INDIRECT', CustomFields::name('nsp::INDIRECT'));
        self::assertSame(['nsp::INDIRECT' => true, 'nsp::COLOR' => 'red'], $fields->all());
        self::assertTrue($fields->get('nsp::INDIRECT'));
        self::assertSame('red', $fields->get('COLOR'));
        self::assertTrue($fields->has('COLOR'));
        self::assertFalse($fields->has('SIZE'));
        self::assertNull($fields->get('SIZE'));
    }

    public function test_the_constructor_normalizes_names_too(): void
    {
        $fields = new CustomFields(['INDIRECT' => true, 'nsp::COLOR' => 'red']);

        self::assertSame(['nsp::INDIRECT' => true, 'nsp::COLOR' => 'red'], $fields->toWriteArray());
    }

    public function test_with_returns_a_new_instance(): void
    {
        $empty = new CustomFields;
        $set = $empty->with('INDIRECT', true);
        $replaced = $set->with('nsp::INDIRECT', false);

        self::assertTrue($empty->isEmpty());
        self::assertFalse($set->isEmpty());
        self::assertTrue($set->get('INDIRECT'));
        self::assertSame(['nsp::INDIRECT' => false], $replaced->all());
    }

    #[DataProvider('blankNames')]
    public function test_blank_names_are_rejected(string $name): void
    {
        $this->expectException(InvalidArgument::class);

        (new CustomFields)->with($name, true);
    }

    /** @return iterable<string, array{string}> */
    public static function blankNames(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => ['  '];
        yield 'prefix only' => ['nsp::'];
        yield 'prefix and whitespace' => ['nsp:: '];
    }

    public function test_it_extracts_only_prefixed_keys_from_the_guide_vendor_response(): void
    {
        $payload = json_decode(self::VENDOR_RESPONSE, true, flags: JSON_THROW_ON_ERROR);

        $fields = CustomFields::fromArray($payload['ia::result']);

        self::assertSame([
            'nsp::CUSTOM_CHECKBOX' => false,
            'nsp::CUSTOM_EMAIL' => null,
            'nsp::CUSTOM_CURRENCY' => 59,
            'nsp::CUSTOM_PERCENTAGE' => 7,
            'nsp::PICKLIST' => 'one',
            'nsp::MULTI_PICKLIST' => [],
            'nsp::SEQUENCE' => 'Inv-1000-Doc',
            'nsp::r10258' => null,
            'nsp::r11192' => null,
        ], $fields->all());
        self::assertFalse($fields->has('retainagePercentage'));
        self::assertFalse($fields->has('href'));
        self::assertTrue($fields->has('r10258'));
        self::assertNull($fields->reference('r10258'));
    }

    public function test_relationship_objects_become_references(): void
    {
        $fields = CustomFields::fromArray([
            'id' => 'VEND-001',
            'nsp::referredBy' => ['key' => '7', 'id' => 'CUST-7', 'href' => '/objects/accounts-receivable/customer/7'],
            'nsp::MULTI_PICKLIST' => ['red', 'blue'],
            'nsp::UNKNOWN_SHAPE' => ['label' => 'no key or id'],
            'nsp::NESTED_LIST' => [['key' => '1']],
        ]);

        $reference = $fields->reference('referredBy');

        self::assertSame('7', $reference?->key?->value);
        self::assertSame('CUST-7', $reference->id?->value);
        self::assertSame('/objects/accounts-receivable/customer/7', $reference->href);
        self::assertSame(['red', 'blue'], $fields->get('MULTI_PICKLIST'));
        self::assertFalse($fields->has('UNKNOWN_SHAPE'));
        self::assertFalse($fields->has('NESTED_LIST'));
        self::assertSame([
            'nsp::referredBy' => ['key' => '7'],
            'nsp::MULTI_PICKLIST' => ['red', 'blue'],
        ], $fields->toWriteArray());
    }

    public function test_written_references_use_the_reference_write_shape(): void
    {
        $fields = (new CustomFields)
            ->with('r10258', ObjectReference::byId('EMP-5'))
            ->with('r11192', ObjectReference::byKey('12'))
            ->with('r12000', null);

        self::assertSame('EMP-5', $fields->reference('nsp::r10258')?->id?->value);
        self::assertSame([
            'nsp::r10258' => ['id' => 'EMP-5'],
            'nsp::r11192' => ['key' => '12'],
            'nsp::r12000' => null,
        ], $fields->toWriteArray());
    }

    public function test_reference_rejects_a_field_that_is_not_a_relationship(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('nsp::PICKLIST');

        (new CustomFields)->with('PICKLIST', 'one')->reference('PICKLIST');
    }

    public function test_it_accepts_scalars_null_and_lists_of_scalars(): void
    {
        $fields = (new CustomFields)
            ->with('CHECKBOX', true)
            ->with('EMAIL', null)
            ->with('CURRENCY', 59)
            ->with('PERCENTAGE', 7.5)
            ->with('PICKLIST', 'one')
            ->with('MULTI_PICKLIST', ['one', 'two']);

        self::assertSame([
            'nsp::CHECKBOX' => true,
            'nsp::EMAIL' => null,
            'nsp::CURRENCY' => 59,
            'nsp::PERCENTAGE' => 7.5,
            'nsp::PICKLIST' => 'one',
            'nsp::MULTI_PICKLIST' => ['one', 'two'],
        ], $fields->toWriteArray());
    }

    #[DataProvider('invalidValues')]
    public function test_it_rejects_values_it_cannot_write(mixed $value): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('nsp::FIELD');

        (new CustomFields)->with('FIELD', $value);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidValues(): iterable
    {
        yield 'object' => [new \stdClass];
        yield 'associative array' => [['key' => '1']];
        yield 'list of references' => [[ObjectReference::byKey('1')]];
        yield 'list with null' => [['one', null]];
        yield 'nested list' => [[['one']]];
        yield 'infinite float' => [INF];
        yield 'not a number' => [NAN];
    }

    public function test_the_constructor_validates_values(): void
    {
        $this->expectException(InvalidArgument::class);

        new CustomFields(['FIELD' => ['nested' => true]]);
    }

    public function test_dimensions_read_and_write_user_defined_dimensions(): void
    {
        // The dimensions object from the spec's advance-line example, verbatim.
        $data = json_decode(
            '{"department":{"key":"9","id":"11","name":"Accounting","href":"/objects/company-config/department/9"},"class":{"key":"1","id":"3","name":"Heath Care","href":"/objects/company-config/class/1"},"nsp::refcode_gl":{"key":"10004","href":"/objects/platform-apps/nsp::refcode_gl/10004"},"nsp::vssn":{"key":"10008","href":"/objects/platform-apps/nsp::vssn/10008"},"nsp::restriction":{"key":null}}',
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $data = ArrayReader::object($data);
        self::assertNotNull($data);

        $dimensions = Dimensions::fromArray($data);

        self::assertSame('11', $dimensions->department?->id?->value);
        self::assertSame('10004', $dimensions->userDefined('refcode_gl')?->key?->value);
        self::assertSame('/objects/platform-apps/nsp::vssn/10008', $dimensions->userDefined('nsp::vssn')?->href);
        self::assertTrue($dimensions->custom->has('restriction'));
        self::assertNull($dimensions->userDefined('restriction'));
        self::assertNull($dimensions->userDefined('missing'));
        self::assertSame([
            'department' => ['key' => '9'],
            'class' => ['key' => '1'],
            'nsp::refcode_gl' => ['key' => '10004'],
            'nsp::vssn' => ['key' => '10008'],
            'nsp::restriction' => null,
        ], $dimensions->toWriteArray());
    }

    public function test_dimensions_reject_user_defined_values_that_are_not_references(): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('nsp::travel_UDD');

        new Dimensions(custom: (new CustomFields)->with('travel_UDD', '10079'));
    }
}
