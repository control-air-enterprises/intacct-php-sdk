<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Auth\OAuth;

use ControlAir\Intacct\Support\Assert;

final readonly class ClientCredentialsGrant
{
    private function __construct(
        public ?string $username,
        #[\SensitiveParameter]
        public ?string $sessionId,
    ) {}

    public static function forUsername(string $userId, string $companyId, ?string $entityId = null): self
    {
        Assert::notBlank($userId, 'The user ID');
        Assert::notBlank($companyId, 'The company ID');

        if ($entityId !== null) {
            Assert::notBlank($entityId, 'The entity ID');
        }

        $username = sprintf('%s@%s', $userId, $companyId);

        if ($entityId !== null) {
            $username .= '|'.$entityId;
        }

        return new self($username, null);
    }

    public static function forSession(string $sessionId): self
    {
        Assert::notBlank($sessionId, 'The session ID');

        return new self(null, $sessionId);
    }

    /** @return array{username: string|null, sessionId: string|null} */
    public function __debugInfo(): array
    {
        return [
            'username' => $this->username,
            'sessionId' => $this->sessionId === null ? null : '[redacted]',
        ];
    }
}
