<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Auth\Tokens;

use ControlAir\Intacct\Auth\Contracts\AccessTokenProvider;

final readonly class StaticAccessTokenProvider implements AccessTokenProvider
{
    public function __construct(private AccessToken $accessToken) {}

    public function getAccessToken(): AccessToken
    {
        return $this->accessToken;
    }
}
