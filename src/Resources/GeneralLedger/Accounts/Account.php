<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\GeneralLedger\Accounts;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class Account
{
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public string $name,
        public ?RecordStatus $status,
        public ?AccountType $accountType,
        public ?NormalBalance $normalBalance,
        public ?ClosingType $closingType,
        public ?ObjectReference $closeToGLAccount,
        public ?AlternativeGLAccount $alternativeGLAccount,
        public ?string $category,
        public ?ConstructionCategory $constructionCategory,
        public ?bool $disallowDirectPosting,
        public ?bool $enableGLMatching,
        public ?bool $isGermanTaxAccount,
        public ?bool $isTaxable,
        public ?string $reconciliationSequence,
        public ?string $taxCode,
        public ?string $mrcCode,
        public ?AccountRequiredDimensions $requireDimensions,
        public ?ObjectReference $entity,
        public ?string $href,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $status = ArrayReader::string($data, 'status');
        $accountType = ArrayReader::string($data, 'accountType');
        $normalBalance = ArrayReader::string($data, 'normalBalance');
        $closingType = ArrayReader::string($data, 'closingType');
        $alternative = ArrayReader::string($data, 'alternativeGLAccount');
        $construction = ArrayReader::string($data, 'constructionCategory');
        $requireDimensions = ArrayReader::object($data['requireDimensions'] ?? null);

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            name: ArrayReader::requiredString($data, 'name'),
            status: $status === null ? null : RecordStatus::tryFrom($status),
            accountType: $accountType === null ? null : AccountType::tryFrom($accountType),
            normalBalance: $normalBalance === null ? null : NormalBalance::tryFrom($normalBalance),
            closingType: $closingType === null ? null : ClosingType::tryFrom($closingType),
            closeToGLAccount: ArrayReader::reference($data, 'closeToGLAccount'),
            alternativeGLAccount: $alternative === null ? null : AlternativeGLAccount::tryFrom($alternative),
            category: ArrayReader::string($data, 'category'),
            constructionCategory: $construction === null ? null : ConstructionCategory::tryFrom($construction),
            disallowDirectPosting: ArrayReader::bool($data, 'disallowDirectPosting'),
            enableGLMatching: ArrayReader::bool($data, 'enableGLMatching'),
            isGermanTaxAccount: ArrayReader::bool($data, 'isGermanTaxAccount'),
            isTaxable: ArrayReader::bool($data, 'isTaxable'),
            reconciliationSequence: ArrayReader::string($data, 'reconciliationSequence'),
            taxCode: ArrayReader::string($data, 'taxCode'),
            mrcCode: ArrayReader::string($data, 'mrcCode'),
            requireDimensions: $requireDimensions === null ? null : AccountRequiredDimensions::fromArray($requireDimensions),
            entity: ArrayReader::reference($data, 'entity'),
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
