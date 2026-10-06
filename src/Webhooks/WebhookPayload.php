<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Webhooks;

use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Support\ArrayReader;

/**
 * The body a trigger delivered. Its shape is whatever the trigger's document template
 * produces, which is usually, but not necessarily, a JSON object of the REST object.
 */
final readonly class WebhookPayload
{
    /** @var array<string, mixed>|null */
    public ?array $data;

    public function __construct(
        public string $raw,
        public ?string $contentType = null,
    ) {
        $this->data = $raw === '' ? null : ArrayReader::object(json_decode($raw, true));
    }

    public function isJson(): bool
    {
        return $this->data !== null;
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        return $this->data
            ?? throw new MappingException('The webhook payload is not a JSON object.');
    }

    /**
     * Maps the payload with a resource DTO mapper, e.g. `ClassDimension::fromArray(...)`.
     * This works when the trigger's template sends the REST object's own field names.
     *
     * @template T
     *
     * @param  callable(array<string, mixed>): T  $mapper
     * @return T
     */
    public function map(callable $mapper): mixed
    {
        return $mapper($this->json());
    }
}
