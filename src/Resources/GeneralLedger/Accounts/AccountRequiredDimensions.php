<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\GeneralLedger\Accounts;

use ControlAir\Intacct\Support\ArrayReader;

/** The dimensions a posting to the account must carry. A null flag was not returned. */
final readonly class AccountRequiredDimensions
{
    public function __construct(
        public ?bool $class = null,
        public ?bool $contract = null,
        public ?bool $customer = null,
        public ?bool $department = null,
        public ?bool $employee = null,
        public ?bool $item = null,
        public ?bool $location = null,
        public ?bool $project = null,
        public ?bool $vendor = null,
        public ?bool $warehouse = null,
        public ?bool $asset = null,
        public ?bool $affiliateEntity = null,
        public ?bool $task = null,
        public ?bool $costType = null,
        public ?bool $loanAccount = null,
        public ?bool $isWorkOrderRequired = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            class: ArrayReader::bool($data, 'class'),
            contract: ArrayReader::bool($data, 'contract'),
            customer: ArrayReader::bool($data, 'customer'),
            department: ArrayReader::bool($data, 'department'),
            employee: ArrayReader::bool($data, 'employee'),
            item: ArrayReader::bool($data, 'item'),
            location: ArrayReader::bool($data, 'location'),
            project: ArrayReader::bool($data, 'project'),
            vendor: ArrayReader::bool($data, 'vendor'),
            warehouse: ArrayReader::bool($data, 'warehouse'),
            asset: ArrayReader::bool($data, 'asset'),
            affiliateEntity: ArrayReader::bool($data, 'affiliateEntity'),
            task: ArrayReader::bool($data, 'task'),
            costType: ArrayReader::bool($data, 'costType'),
            loanAccount: ArrayReader::bool($data, 'loanAccount'),
            isWorkOrderRequired: ArrayReader::bool($data, 'isWorkOrderRequired'),
        );
    }

    /**
     * The names of the dimensions flagged as required, in API field spelling.
     *
     * @return list<string>
     */
    public function required(): array
    {
        $flags = [
            'class' => $this->class,
            'contract' => $this->contract,
            'customer' => $this->customer,
            'department' => $this->department,
            'employee' => $this->employee,
            'item' => $this->item,
            'location' => $this->location,
            'project' => $this->project,
            'vendor' => $this->vendor,
            'warehouse' => $this->warehouse,
            'asset' => $this->asset,
            'affiliateEntity' => $this->affiliateEntity,
            'task' => $this->task,
            'costType' => $this->costType,
            'loanAccount' => $this->loanAccount,
            'isWorkOrderRequired' => $this->isWorkOrderRequired,
        ];

        return array_keys(array_filter($flags, static fn (?bool $value): bool => $value === true));
    }
}
