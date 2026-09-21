<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Http;

enum HttpMethod: string
{
    case Get = 'GET';
    case Post = 'POST';
    case Patch = 'PATCH';
    case Delete = 'DELETE';
}
