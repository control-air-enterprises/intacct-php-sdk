<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Attachments;

use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class CreateAttachment
{
    /**
     * @param  ObjectReference  $folder  The folder must already exist.
     * @param  list<AttachmentFile>  $files  Optional; an attachment can be created empty and filled later.
     */
    public function __construct(
        public string $name,
        public ObjectReference $folder,
        public ?ObjectId $id = null,
        public ?string $description = null,
        public array $files = [],
        public CustomFields $customFields = new CustomFields,
    ) {
        Assert::notBlank($this->name, 'The attachment name');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $payload = array_filter([
            'id' => $this->id?->value,
            'name' => $this->name,
            'description' => $this->description,
            'folder' => $this->folder->toWriteArray(),
            'files' => $this->files === [] ? null : array_map(
                static fn (AttachmentFile $file): array => $file->toWriteArray(),
                $this->files,
            ),
        ], static fn (mixed $value): bool => $value !== null);

        return [...$payload, ...$this->customFields->toWriteArray()];
    }
}
