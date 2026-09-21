<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Auth\Contracts;

use ControlAir\Intacct\Auth\Tokens\AccessToken;

interface AccessTokenProvider
{
    public function getAccessToken(): AccessToken;
}
