<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Auth\Tokens;

use ControlAir\Intacct\Auth\Contracts\AccessTokenProvider;

final readonly class ManagedAccessTokenProvider implements AccessTokenProvider
{
    public function __construct(
        private TokenManager $manager,
        private TokenKey $key,
    ) {}

    public function getAccessToken(): AccessToken
    {
        return $this->manager->getValidAccessToken($this->key);
    }
}
