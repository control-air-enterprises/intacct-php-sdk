<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Auth\Contracts;

use ControlAir\Intacct\Auth\Tokens\AccessToken;

interface RevocableAccessTokenProvider extends AccessTokenProvider
{
    /** Discards a token the API rejected so the next getAccessToken() obtains a new one. */
    public function invalidate(AccessToken $rejected): void;
}
