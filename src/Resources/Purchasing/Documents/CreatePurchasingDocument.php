<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Purchasing\Documents;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class CreatePurchasingDocument
{
    private const CREATABLE_STATES = [
        PurchasingDocumentState::Pending,
        PurchasingDocumentState::Draft,
        PurchasingDocumentState::Submitted,
    ];

    /**
     * @param  ObjectReference  $vendor  Sage requires the vendor ID, not the record key.
     * @param  list<CreatePurchasingDocumentLine>  $lines  At least one line is required.
     * @param  PurchasingDocumentState|null  $state  Pending, draft or submitted; Sage defaults to pending.
     * @param  ObjectReference|null  $sourceDocument  The document being converted, such as a purchase order on a receipt.
     */
    public function __construct(
        public LocalDate $transactionDate,
        public ObjectReference $vendor,
        public array $lines,
        public ?PurchasingDocumentState $state = null,
        public ?string $documentNumber = null,
        public ?LocalDate $dueDate = null,
        public ?string $referenceNumber = null,
        public ?string $vendorDocumentNumber = null,
        public ?string $memo = null,
        public ?string $notes = null,
        public ?string $currency = null,
        public ?ObjectReference $paymentTerm = null,
        public ?ObjectReference $project = null,
        public ?ObjectReference $sourceDocument = null,
    ) {
        if ($this->vendor->id === null) {
            throw new InvalidArgument('A purchasing document vendor must be referenced by ID.');
        }

        if ($this->lines === []) {
            throw new InvalidArgument('A purchasing document requires at least one line.');
        }

        if ($this->state !== null && ! in_array($this->state, self::CREATABLE_STATES, true)) {
            throw new InvalidArgument(sprintf(
                'A purchasing document cannot be created in the "%s" state.',
                $this->state->value,
            ));
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'txnDate' => $this->transactionDate->value,
            'vendor' => $this->vendor->toWriteArray(),
            'state' => $this->state?->value,
            'documentNumber' => $this->documentNumber,
            'dueDate' => $this->dueDate?->value,
            'referenceNumber' => $this->referenceNumber,
            'vendorDocumentNumber' => $this->vendorDocumentNumber,
            'memo' => $this->memo,
            'notes' => $this->notes,
            'txnCurrency' => $this->currency,
            'paymentTerm' => $this->paymentTerm?->toWriteArray(),
            'project' => $this->project?->toWriteArray(),
            'sourceDocument' => $this->sourceDocument?->toWriteArray(),
            'lines' => array_map(
                static fn (CreatePurchasingDocumentLine $line): array => $line->toArray(),
                $this->lines,
            ),
        ], static fn (mixed $value): bool => $value !== null);
    }
}
