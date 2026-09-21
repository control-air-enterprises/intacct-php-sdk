<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Auth\Tokens;

use DateTimeImmutable;

final readonly class TokenIntrospection
{
    public function __construct(
        public bool $active,
        public ?TokenType $tokenType = null,
        public ?string $clientId = null,
        public ?string $userId = null,
        public ?string $companyId = null,
        public ?DateTimeImmutable $expiresAt = null,
        public ?DateTimeImmutable $issuedAt = null,
        public ?string $companyKey = null,
        public ?string $entityId = null,
        public ?string $entityKey = null,
        public ?string $userKey = null,
        public ?bool $aiEnabled = null,
    ) {}
}
