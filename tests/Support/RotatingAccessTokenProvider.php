<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Support;

use ControlAir\Intacct\Auth\Contracts\RevocableAccessTokenProvider;
use ControlAir\Intacct\Auth\Tokens\AccessToken;

final class RotatingAccessTokenProvider implements RevocableAccessTokenProvider
{
    /** @var list<AccessToken> */
    public array $invalidated = [];

    /** @var list<AccessToken> */
    private array $tokens;

    public function __construct(AccessToken ...$tokens)
    {
        $this->tokens = array_values($tokens);
    }

    public function getAccessToken(): AccessToken
    {
        if ($this->tokens === []) {
            throw new \RuntimeException('No access token is left to hand out.');
        }

        return $this->tokens[0];
    }

    public function invalidate(AccessToken $rejected): void
    {
        $this->invalidated[] = $rejected;

        array_shift($this->tokens);
    }
}
