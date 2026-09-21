<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Exceptions;

final class ApiException extends \RuntimeException implements IntacctException
{
    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $additionalInfo
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorId = null,
        public readonly ?string $supportId = null,
        public readonly array $additionalInfo = [],
        public readonly array $response = [],
    ) {
        parent::__construct($message);
    }
}
