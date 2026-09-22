<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Attachments;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Support\Assert;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;

/**
 * Header changes plus file operations. Sage adds and removes files through the same
 * PATCH request; files that are not mentioned are kept.
 */
final readonly class UpdateAttachment
{
    /**
     * @param  array<string, mixed>  $changes
     * @param  list<array<string, string>>  $files
     */
    private function __construct(
        private array $changes,
        private array $files = [],
    ) {}

    public static function name(string $name): self
    {
        Assert::notBlank($name, 'The attachment name');

        return new self(['name' => $name]);
    }

    public static function description(?string $description): self
    {
        return new self(['description' => $description]);
    }

    /** Moves the attachment to another existing folder. */
    public static function folder(ObjectReference $folder): self
    {
        return new self(['folder' => $folder->toWriteArray()]);
    }

    public static function addFile(AttachmentFile $file): self
    {
        return new self([], [$file->toWriteArray()]);
    }

    public static function removeFile(ObjectKey $key): self
    {
        return new self([], [self::removal($key)]);
    }

    /** Starts a change set with one custom field, named with or without the `nsp::` prefix; null clears it. */
    public static function customField(string $name, mixed $value): self
    {
        return self::customFields((new CustomFields)->with($name, $value));
    }

    /** Starts a change set with custom fields; a null value clears its field. */
    public static function customFields(CustomFields $fields): self
    {
        $changes = $fields->toWriteArray();

        if ($changes === []) {
            throw new InvalidArgument('At least one custom field is required.');
        }

        return new self($changes);
    }

    public function withName(string $name): self
    {
        Assert::notBlank($name, 'The attachment name');

        return $this->with('name', $name);
    }

    public function withDescription(?string $description): self
    {
        return $this->with('description', $description);
    }

    public function withFolder(ObjectReference $folder): self
    {
        return $this->with('folder', $folder->toWriteArray());
    }

    public function withAddedFile(AttachmentFile $file): self
    {
        return new self($this->changes, [...$this->files, $file->toWriteArray()]);
    }

    public function withRemovedFile(ObjectKey $key): self
    {
        return new self($this->changes, [...$this->files, self::removal($key)]);
    }

    /** Sets a custom field, named with or without the `nsp::` prefix; null clears it. */
    public function withCustomField(string $name, mixed $value): self
    {
        return $this->withCustomFields((new CustomFields)->with($name, $value));
    }

    /** Sets custom fields; a null value clears its field. */
    public function withCustomFields(CustomFields $fields): self
    {
        $update = $this;

        foreach ($fields->toWriteArray() as $field => $value) {
            $update = $update->with($field, $value);
        }

        return $update;
    }

    /** @return non-empty-array<string, mixed> */
    public function toArray(): array
    {
        $payload = $this->files === [] ? $this->changes : [...$this->changes, 'files' => $this->files];

        /** @var non-empty-array<string, mixed> $payload every named constructor sets a header field or a file */
        return $payload;
    }

    private function with(string $field, mixed $value): self
    {
        return new self([...$this->changes, $field => $value], $this->files);
    }

    /** @return array{key: string, 'ia::operation': string} */
    private static function removal(ObjectKey $key): array
    {
        return ['key' => $key->value, 'ia::operation' => 'delete'];
    }
}
