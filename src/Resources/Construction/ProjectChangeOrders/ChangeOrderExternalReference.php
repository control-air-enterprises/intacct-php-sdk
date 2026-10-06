<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\ProjectChangeOrders;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectReference;

/**
 * The `externalReference` group shared by project change orders and change requests.
 * The people are contacts, such as the owner's representative.
 */
final readonly class ChangeOrderExternalReference
{
    /** @var list<string> */
    public const QUERY_FIELDS = [
        'externalReference.referenceNumber',
        'externalReference.approvedBy.key', 'externalReference.approvedBy.id', 'externalReference.approvedOnDate',
        'externalReference.signedBy.key', 'externalReference.signedBy.id', 'externalReference.signedOnDate',
        'externalReference.verbalApprovalBy.key', 'externalReference.verbalApprovalBy.id',
    ];

    public function __construct(
        public ?string $referenceNumber = null,
        public ?ObjectReference $approvedBy = null,
        public ?LocalDate $approvedOnDate = null,
        public ?ObjectReference $signedBy = null,
        public ?LocalDate $signedOnDate = null,
        public ?ObjectReference $verbalApprovalBy = null,
    ) {}

    /** @param array<string, mixed> $data The `externalReference` object. */
    public static function fromArray(array $data): self
    {
        return new self(
            referenceNumber: ArrayReader::string($data, 'referenceNumber'),
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
