<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Auth\Tokens;

use ControlAir\Intacct\Auth\Contracts\TokenStore;

final class InMemoryTokenStore implements TokenStore
{
    /** @var array<string, TokenSet> */
    private array $tokens = [];

    public function get(TokenKey $key): ?TokenSet
    {
        return $this->tokens[$key->value] ?? null;
    }

    public function put(TokenKey $key, TokenSet $tokens): void
    {
        $this->tokens[$key->value] = $tokens;
    }

    public function forget(TokenKey $key): void
    {
        unset($this->tokens[$key->value]);
    }
}
