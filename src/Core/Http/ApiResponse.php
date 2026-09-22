<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Http;

/** A successful (2xx) Sage Intacct response, including HTTP 207 multi-status. */
final readonly class ApiResponse
{
    public const MULTI_STATUS = 207;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, list<string>>  $headers
     */
    public function __construct(
        public int $statusCode,
        public array $payload,
        public array $headers = [],
    ) {}

    public function isMultiStatus(): bool
    {
        return $this->statusCode === self::MULTI_STATUS;
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $header => $values) {
            if (strcasecmp($header, $name) === 0) {
                return implode(', ', $values);
            }
        }

        return null;
    }
}
