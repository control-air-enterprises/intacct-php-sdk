<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Composite;

use ControlAir\Intacct\Core\Http\HttpMethod;
use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Http\RequestHeaders;
use ControlAir\Intacct\Exceptions\InvalidArgument;

/** One sub-request of a composite request. */
final readonly class CompositeOperation
{
    /** The spec pattern, widened to /workflows/ which the bulk-requests guide also allows. */
    private const PATH_PATTERN = '#^/(objects|services|workflows)/[a-z_][a-z_0-9.\-:]+#';

    /**
     * @param  string  $path  an absolute API path such as "/objects/accounts-payable/vendor"; it may
     *                        contain references built with CompositeReference::to()
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $body  only for POST and PATCH
     * @param  array<string, string>  $headers  Authorization, Content-Type and Accept are inherited and cannot be set
     */
    public function __construct(
        public HttpMethod $method,
        public string $path,
        public ?array $body = null,
        public array $headers = [],
        public ?string $resultReference = null,
    ) {
        if (preg_match(self::PATH_PATTERN, $this->path) !== 1 || preg_match('/[\x00-\x1F\x7F]/', $this->path) === 1) {
            throw new InvalidArgument(sprintf(
                'Composite sub-request paths must start with /objects/, /services/ or /workflows/; "%s" given.',
                $this->path,
            ));
        }

        if ($this->body !== null && ! in_array($this->method, [HttpMethod::Post, HttpMethod::Patch], true)) {
            throw new InvalidArgument(sprintf('A %s composite sub-request cannot have a body.', $this->method->value));
        }

        RequestHeaders::validate($this->headers);

        if ($this->resultReference !== null) {
            CompositeReference::assertValidName($this->resultReference);
        }
    }

    /** @param array<string, string> $headers */
    public static function get(string $path, ?string $resultReference = null, array $headers = []): self
    {
        return new self(HttpMethod::Get, $path, null, $headers, $resultReference);
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>  $body
     * @param  array<string, string>  $headers
     */
    public static function post(
        string $path,
        array $body = [],
        ?string $resultReference = null,
        array $headers = [],
        ?IdempotencyKey $idempotencyKey = null,
    ): self {
        return new self(HttpMethod::Post, $path, $body, [...$headers, ...($idempotencyKey?->toHeaders() ?? [])], $resultReference);
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>  $body
     * @param  array<string, string>  $headers
     */
    public static function patch(
        string $path,
        array $body,
        ?string $resultReference = null,
        array $headers = [],
        ?IdempotencyKey $idempotencyKey = null,
    ): self {
        return new self(HttpMethod::Patch, $path, $body, [...$headers, ...($idempotencyKey?->toHeaders() ?? [])], $resultReference);
    }

    /** @param array<string, string> $headers */
    public static function delete(string $path, ?string $resultReference = null, array $headers = []): self
    {
        return new self(HttpMethod::Delete, $path, null, $headers, $resultReference);
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->method, $this->path, $this->body, [...$this->headers, $name => $value], $this->resultReference);
    }

    /** Sage Intacct honours Idempotency-Key for POST and PATCH only. */
    public function withIdempotencyKey(IdempotencyKey $key): self
    {
        if (! in_array($this->method, [HttpMethod::Post, HttpMethod::Patch], true)) {
            throw new InvalidArgument(sprintf('Idempotency keys are not supported for %s sub-requests.', $this->method->value));
        }

        return $this->withHeader(IdempotencyKey::HEADER, $key->value);
    }

    public function withResultReference(string $resultReference): self
    {
        return new self($this->method, $this->path, $this->body, $this->headers, $resultReference);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $operation = [
            'method' => $this->method->value,
            'path' => $this->path,
        ];

        if ($this->body !== null) {
            // The body is a JSON object; send {} rather than [] when it is empty.
            $operation['body'] = $this->body === [] ? new \stdClass : $this->body;
        }

        if ($this->resultReference !== null) {
            $operation['resultReference'] = $this->resultReference;
        }

        if ($this->headers !== []) {
            $operation['headers'] = $this->headers;
        }

        return $operation;
    }
}
