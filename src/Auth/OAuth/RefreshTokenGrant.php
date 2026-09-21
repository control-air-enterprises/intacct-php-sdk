<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Auth\OAuth;

use ControlAir\Intacct\Auth\Tokens\RefreshToken;
use ControlAir\Intacct\Support\Assert;

final readonly class RefreshTokenGrant
{
    public function __construct(
        public RefreshToken $refreshToken,
        public ?string $entityId = null,
    ) {
        if ($this->entityId !== null) {
            Assert::notBlank($this->entityId, 'The entity ID');
        }
    }
}
