<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Auth\OAuth;

use ControlAir\Intacct\Support\Assert;

final readonly class AuthorizationCodeGrant
{
    public function __construct(
        #[\SensitiveParameter]
        public string $code,
        public string $redirectUri,
        public ?PkcePair $pkce = null,
    ) {
        Assert::notBlank($this->code, 'The authorization code');
        Assert::absoluteUri($this->redirectUri, 'The redirect URI');
    }

    /** @return array{code: string, redirectUri: string, pkce: PkcePair|null} */
    public function __debugInfo(): array
    {
        return [
            'code' => '[redacted]',
            'redirectUri' => $this->redirectUri,
            'pkce' => $this->pkce,
        ];
    }
}
