<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectChangeOrders;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectReference;

/**
 * The `internalReference` group shared by project change orders and change requests.
 * The people are employees.
 */
final readonly class ChangeOrderInternalReference
{
    /** @var list<string> */
    public const QUERY_FIELDS = [
        'internalReference.referenceNumber', 'internalReference.source', 'internalReference.sourceReferenceNumber',
        'internalReference.initiatedBy.key', 'internalReference.initiatedBy.id', 'internalReference.initiatedBy.name',
        'internalReference.issuedBy.key', 'internalReference.issuedBy.id', 'internalReference.issuedBy.name',
        'internalReference.issuedOnDate',
        'internalReference.approvedBy.key', 'internalReference.approvedBy.id', 'internalReference.approvedBy.name',
        'internalReference.approvedOnDate',
        'internalReference.signedBy.key', 'internalReference.signedBy.id', 'internalReference.signedBy.name',
        'internalReference.signedOnDate',
        'internalReference.verbalApprovalBy.key', 'internalReference.verbalApprovalBy.id',
        'internalReference.verbalApprovalBy.name',
    ];

    public function __construct(
        public ?string $referenceNumber = null,
        public ?string $source = null,
        public ?string $sourceReferenceNumber = null,
        public ?ObjectReference $initiatedBy = null,
        public ?ObjectReference $issuedBy = null,
        public ?LocalDate $issuedOnDate = null,
        public ?ObjectReference $approvedBy = null,
        public ?LocalDate $approvedOnDate = null,
        public ?ObjectReference $signedBy = null,
        public ?LocalDate $signedOnDate = null,
        public ?ObjectReference $verbalApprovalBy = null,
    ) {}

    /** @param array<string, mixed> $data The `internalReference` object. */
    public static function fromArray(array $data): self
    {
        return new self(
            referenceNumber: ArrayReader::string($data, 'referenceNumber'),
            source: ArrayReader::string($data, 'source'),
            sourceReferenceNumber: ArrayReader::string($data, 'sourceReferenceNumber'),
            initiatedBy: ArrayReader::reference($data, 'initiatedBy'),
            issuedBy: ArrayReader::reference($data, 'issuedBy'),
            issuedOnDate: ArrayReader::date($data, 'issuedOnDate'),
            approvedBy: ArrayReader::reference($data, 'approvedBy'),
            approvedOnDate: ArrayReader::date($data, 'approvedOnDate'),
            signedBy: ArrayReader::reference($data, 'signedBy'),
            signedOnDate: ArrayReader::date($data, 'signedOnDate'),
            verbalApprovalBy: ArrayReader::reference($data, 'verbalApprovalBy'),
        );
    }

    /**
     * Every field of the group, so a PATCH replaces the group and a null clears its field.
     *
     * @return array<string, string|array{key: string}|array{id: string}|null>
     */
    public function toWriteArray(): array
    {
        return [
            'referenceNumber' => $this->referenceNumber,
            'source' => $this->source,
            'sourceReferenceNumber' => $this->sourceReferenceNumber,
            'initiatedBy' => $this->initiatedBy?->toWriteArray(),
            'issuedBy' => $this->issuedBy?->toWriteArray(),
            'issuedOnDate' => $this->issuedOnDate?->value,
            'approvedBy' => $this->approvedBy?->toWriteArray(),
            'approvedOnDate' => $this->approvedOnDate?->value,
            'signedBy' => $this->signedBy?->toWriteArray(),
            'signedOnDate' => $this->signedOnDate?->value,
            'verbalApprovalBy' => $this->verbalApprovalBy?->toWriteArray(),
        ];
    }

    /**
     * The fields that are set, for a create payload; null when none are.
     *
     * @return array<string, string|array{key: string}|array{id: string}>|null
     */
    public function toCreateArray(): ?array
    {
        $fields = array_filter($this->toWriteArray(), static fn (mixed $value): bool => $value !== null);

        return $fields === [] ? null : $fields;
    }
}
