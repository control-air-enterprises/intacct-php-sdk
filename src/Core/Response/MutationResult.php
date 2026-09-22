<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Response;

use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class MutationResult
{
    public function __construct(
        public ObjectReference $reference,
        public ResponseMeta $meta,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $result = ArrayReader::object($payload['ia::result'] ?? null);
        $meta = ArrayReader::object($payload['ia::meta'] ?? null) ?? [];

        if ($result === null) {
            throw new MappingException('The mutation response does not contain an ia::result object.');
        }

        $reference = ObjectReference::fromArray($result);

        if ($reference === null) {
            throw new MappingException('The mutation response does not contain an object key or ID.');
        }

        return new self(
            $reference,
            ResponseMeta::fromArray($meta),
        );
    }

    /**
     * Sage Intacct answers a successful DELETE with 204 and no body, so the result refers
     * to the key that was deleted.
     */
    public static function forDeletedKey(ObjectKey $key, ?ResponseMeta $meta = null): self
    {
        return new self(
            new ObjectReference($key, null),
            $meta ?? ResponseMeta::fromArray([]),
        );
    }
}
