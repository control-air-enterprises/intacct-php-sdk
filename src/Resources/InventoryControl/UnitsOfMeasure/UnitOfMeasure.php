<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\InventoryControl\UnitsOfMeasure;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class UnitOfMeasure
{
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public ?string $abbreviation,
        public ?ObjectReference $group,
        public ?Decimal $conversionFactor,
        public ?int $decimalPlaces,
        public ?bool $base,
        public ?string $href,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            abbreviation: ArrayReader::string($data, 'abbreviation'),
            group: ArrayReader::reference($data, 'parent'),
            conversionFactor: ArrayReader::decimal($data, 'conversionFactor'),
            decimalPlaces: ArrayReader::int($data, 'numberOfDecimalPlaces'),
            base: ArrayReader::bool($data, 'isBase'),
            href: ArrayReader::string($data, 'href'),
        );
    }
}
