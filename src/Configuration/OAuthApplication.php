<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Configuration;

use ControlAir\Intacct\Exceptions\ConfigurationException;

final readonly class OAuthApplication
{
    public function __construct(
        public string $clientId,
        #[\SensitiveParameter]
        private ?string $clientSecret = null,
    ) {
        if (trim($this->clientId) === '') {
            throw new ConfigurationException('The OAuth client ID cannot be empty.');
        }

        if ($this->clientSecret !== null && $this->clientSecret === '') {
            throw new ConfigurationException('The OAuth client secret cannot be empty when provided.');
        }
    }

    public function clientSecret(): ?string
    {
        return $this->clientSecret;
    }

    public function requireClientSecret(): string
    {
        return $this->clientSecret
            ?? throw new ConfigurationException('This OAuth grant requires a client secret.');
    }

    /** @return array{clientId: string, clientSecret: string} */
    public function __debugInfo(): array
    {
        return [
            'clientId' => $this->clientId,
            'clientSecret' => '[redacted]',
        ];
    }
}
