<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Purchasing\TransactionDefinitions;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class TransactionDefinition
{
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public ?string $description,
        public ?DocumentClass $documentClass,
        public ?WorkflowCategory $workflowCategory,
        public ?InventoryUpdateType $inventoryUpdateType,
        public ?PostingMethod $postingMethod,
        public ?RecordStatus $status,
        public ?string $href,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $documentClass = ArrayReader::string($data, 'docClass');
        $workflowCategory = ArrayReader::string($data, 'workflowCategory');
        $inventoryUpdateType = ArrayReader::string($data, 'inventoryUpdateType');
        $postingMethod = ArrayReader::string($data, 'txnPostingMethod');
        $status = ArrayReader::string($data, 'status');

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            description: ArrayReader::string($data, 'description'),
            documentClass: $documentClass === null ? null : DocumentClass::tryFrom($documentClass),
            workflowCategory: $workflowCategory === null ? null : WorkflowCategory::tryFrom($workflowCategory),
            inventoryUpdateType: $inventoryUpdateType === null ? null : InventoryUpdateType::tryFrom($inventoryUpdateType),
            postingMethod: $postingMethod === null ? null : PostingMethod::tryFrom($postingMethod),
            status: $status === null ? null : RecordStatus::tryFrom($status),
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
