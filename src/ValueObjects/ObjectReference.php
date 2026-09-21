<?php

declare(strict_types=1);

namespace ControlAir\Intacct\ValueObjects;

use ControlAir\Intacct\Exceptions\InvalidArgument;

final readonly class ObjectReference
{
    public function __construct(
        public ?ObjectKey $key,
        public ?ObjectId $id,
        public ?string $name = null,
        public ?string $href = null,
    ) {
        if ($this->key === null && $this->id === null) {
            throw new InvalidArgument('An object reference requires a key or an ID.');
        }
    }

    public static function byKey(string $key): self
    {
        return new self(new ObjectKey($key), null);
    }

    public static function byId(string $id): self
    {
        return new self(null, new ObjectId($id));
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): ?self
    {
        $key = self::nullableScalarString($data['key'] ?? null);
        $id = self::nullableScalarString($data['id'] ?? null);

        if ($key === null && $id === null) {
            return null;
        }

        return new self(
            key: $key === null ? null : new ObjectKey($key),
            id: $id === null ? null : new ObjectId($id),
            name: self::nullableScalarString($data['name'] ?? null),
            href: self::nullableScalarString($data['href'] ?? null),
        );
    }

    /** @return array{key: string}|array{id: string} */
    public function toWriteArray(): array
    {
        if ($this->key !== null) {
            return ['key' => $this->key->value];
        }

        if ($this->id === null) {
            throw new \LogicException('Reference has no key or ID.');
        }

        return ['id' => $this->id->value];
    }

    private static function nullableScalarString(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }
}
