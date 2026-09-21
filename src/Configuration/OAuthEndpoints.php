<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Configuration;

use ControlAir\Intacct\Exceptions\ConfigurationException;

final readonly class OAuthEndpoints
{
    private const BASE_URI = 'https://api.intacct.com/ia/api/v1/oauth2';

    public function __construct(
        public string $authorizeUri = self::BASE_URI.'/authorize',
        public string $tokenUri = self::BASE_URI.'/token',
        public string $revokeUri = self::BASE_URI.'/revoke',
        public string $introspectUri = self::BASE_URI.'/introspect',
    ) {
        foreach (get_object_vars($this) as $name => $uri) {
            if (filter_var($uri, FILTER_VALIDATE_URL) === false) {
                throw new ConfigurationException(sprintf('The OAuth %s must be an absolute URL.', $name));
            }
        }
    }
}
