<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Purchasing\Documents;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class PurchasingDocument
{
    /**
     * @param  list<PurchasingDocumentLine>  $lines  Empty on query results; read the document to load its lines.
     */
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public ?string $documentNumber,
        public ?PurchasingDocumentState $state,
        public ?LocalDate $transactionDate,
        public ?LocalDate $dueDate,
        public ?ObjectReference $vendor,
        public ?ObjectReference $transactionDefinition,
        public ?ObjectReference $sourceDocument,
        public ?string $referenceNumber,
        public ?string $vendorDocumentNumber,
        public ?string $memo,
        public ?string $notes,
        public ?string $currency,
        public ?Decimal $subtotal,
        public ?Decimal $total,
        public ?PaymentStatus $paymentStatus,
        public array $lines,
        public ?string $href,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $state = ArrayReader::string($data, 'state');
        $paymentStatus = ArrayReader::string($data, 'paymentStatus');

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            documentNumber: ArrayReader::string($data, 'documentNumber'),
            state: $state === null ? null : PurchasingDocumentState::tryFrom($state),
            transactionDate: ArrayReader::date($data, 'txnDate'),
            dueDate: ArrayReader::date($data, 'dueDate'),
            vendor: ArrayReader::reference($data, 'vendor'),
            transactionDefinition: ArrayReader::reference($data, 'txnDefinition'),
            sourceDocument: ArrayReader::reference($data, 'sourceDocument'),
            referenceNumber: ArrayReader::string($data, 'referenceNumber'),
            vendorDocumentNumber: ArrayReader::string($data, 'vendorDocumentNumber'),
            memo: ArrayReader::string($data, 'memo'),
            notes: ArrayReader::string($data, 'notes'),
            currency: ArrayReader::string($data, 'txnCurrency'),
            subtotal: ArrayReader::decimal($data, 'subtotal'),
            total: ArrayReader::decimal($data, 'total'),
            paymentStatus: $paymentStatus === null ? null : PaymentStatus::tryFrom($paymentStatus),
            lines: array_map(
                PurchasingDocumentLine::fromArray(...),
                ArrayReader::list($data, 'lines'),
            ),
            href: ArrayReader::string($data, 'href'),
        );
    }
}
