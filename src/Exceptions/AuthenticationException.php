<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Exceptions;

final class AuthenticationException extends \RuntimeException implements IntacctException
{
    /**
     * @param  array<string, mixed>  $response
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ?string $errorCode = null,
        public readonly ?string $supportId = null,
        public readonly array $response = [],
    ) {
        parent::__construct($message);
    }
}
