<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Auth\OAuth;

enum IntrospectionAuthentication
{
    case Bearer;
    case ClientCredentials;
}
