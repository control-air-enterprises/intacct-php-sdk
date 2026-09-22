<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Purchasing\Documents;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\Dimensions;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class PurchasingDocumentLine
{
    public function __construct(
        public ObjectKey $key,
        public ?int $lineNumber,
        public ?ObjectReference $item,
        public ?string $unit,
        public ?Decimal $unitQuantity,
        public ?Decimal $unitPrice,
        public ?Decimal $quantityRemaining,
        public ?string $lineDescription,
        public ?string $memo,
        public Dimensions $dimensions,
        public ?ObjectReference $sourceDocumentLine,
        public ?string $href,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $dimensions = ArrayReader::object($data['dimensions'] ?? null) ?? [];

        return new self(
            key: ArrayReader::key($data),
            lineNumber: ArrayReader::int($data, 'lineNumber'),
            item: ArrayReader::reference($data, 'item') ?? ArrayReader::reference($dimensions, 'item'),
            unit: ArrayReader::string($data, 'unit'),
            unitQuantity: ArrayReader::decimal($data, 'unitQuantity'),
            unitPrice: ArrayReader::decimal($data, 'unitPrice'),
            quantityRemaining: ArrayReader::decimal($data, 'quantityRemaining'),
            lineDescription: ArrayReader::string($data, 'lineDescription'),
            memo: ArrayReader::string($data, 'memo'),
            dimensions: Dimensions::fromArray($dimensions),
            sourceDocumentLine: ArrayReader::reference($data, 'sourceDocumentLine'),
            href: ArrayReader::string($data, 'href'),
        );
    }
}
