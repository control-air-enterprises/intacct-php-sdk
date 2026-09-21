<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Auth\Contracts;

use ControlAir\Intacct\Auth\Tokens\TokenKey;
use ControlAir\Intacct\Auth\Tokens\TokenSet;

interface TokenStore
{
    public function get(TokenKey $key): ?TokenSet;

    public function put(TokenKey $key, TokenSet $tokens): void;

    public function forget(TokenKey $key): void;
}
