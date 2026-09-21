<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Auth\Tokens;

use ControlAir\Intacct\Auth\Contracts\TokenStore;
use ControlAir\Intacct\Auth\OAuth\AuthorizationCodeGrant;
use ControlAir\Intacct\Auth\OAuth\ClientCredentialsGrant;
use ControlAir\Intacct\Auth\OAuth\OAuthClient;
use ControlAir\Intacct\Auth\OAuth\RefreshTokenGrant;
use ControlAir\Intacct\Exceptions\MissingTokenException;
use Psr\Clock\ClockInterface;

final readonly class TokenManager
{
    public function __construct(
        private OAuthClient $oauth,
        private TokenStore $store,
        private ClockInterface $clock,
        private int $refreshLeewaySeconds = 60,
    ) {
        if ($this->refreshLeewaySeconds < 0) {
            throw new \InvalidArgumentException('The refresh leeway cannot be negative.');
        }
    }

    public function exchange(TokenKey $key, AuthorizationCodeGrant $grant): TokenSet
    {
        return $this->remember($key, $this->oauth->exchange($grant));
    }

    public function authenticate(TokenKey $key, ClientCredentialsGrant $grant): TokenSet
    {
        return $this->remember($key, $this->oauth->clientCredentials($grant));
    }

    public function remember(TokenKey $key, TokenSet $tokens): TokenSet
    {
        $this->store->put($key, $tokens);

        return $tokens;
    }

    public function getValidAccessToken(TokenKey $key): AccessToken
    {
        $tokens = $this->store->get($key)
            ?? throw new MissingTokenException(sprintf('No tokens are stored for "%s".', $key->value));

        if (! $tokens->expiresWithin($this->clock, $this->refreshLeewaySeconds)) {
            return $tokens->accessToken;
        }

        if ($tokens->refreshToken === null) {
            throw new MissingTokenException(sprintf(
                'The access token for "%s" is expiring and no refresh token is available.',
                $key->value,
            ));
        }

        $refreshed = $this->oauth->refresh(new RefreshTokenGrant($tokens->refreshToken));
        $this->store->put($key, $refreshed);

        return $refreshed->accessToken;
    }

    public function forget(TokenKey $key): void
    {
        $this->store->forget($key);
    }
}
