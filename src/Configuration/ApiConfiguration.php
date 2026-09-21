<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Configuration;

use ControlAir\Intacct\Exceptions\ConfigurationException;

final readonly class ApiConfiguration
{
    public function __construct(
        public string $baseUri = 'https://api.intacct.com/ia/api/v1',
        public ?string $entityId = null,
        public string $userAgent = 'intacct-php-sdk',
    ) {
        if (filter_var($this->baseUri, FILTER_VALIDATE_URL) === false) {
            throw new ConfigurationException('The API base URI must be an absolute URL.');
        }

        if ($this->entityId !== null && trim($this->entityId) === '') {
            throw new ConfigurationException('The entity ID cannot be empty when provided.');
        }

        if (trim($this->userAgent) === '') {
            throw new ConfigurationException('The user agent cannot be empty.');
        }
    }

    public function uri(string $path): string
    {
        return rtrim($this->baseUri, '/').'/'.ltrim($path, '/');
    }
}
