<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Auth\OAuth;

use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Support\Assert;

final readonly class AuthorizationRequest
{
    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        public string $redirectUri,
        public string $state,
        public array $scopes = ['offline_access'],
        public ?PkcePair $pkce = null,
    ) {
        Assert::absoluteUri($this->redirectUri, 'The redirect URI');
        Assert::notBlank($this->state, 'The OAuth state');

        foreach ($this->scopes as $scope) {
            if (trim($scope) === '') {
                throw new InvalidArgument('OAuth scopes cannot contain an empty value.');
            }
        }

        if (count(array_unique($this->scopes)) !== count($this->scopes)) {
            throw new InvalidArgument('OAuth scopes must be unique.');
        }
    }
}
