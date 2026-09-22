<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Http;

use ControlAir\Intacct\Exceptions\InvalidArgument;

/**
 * Validates caller-supplied request headers.
 *
 * Authorization, Content-Type and Accept are owned by the SDK: the transport sets them on
 * every request and Sage Intacct inherits them for composite sub-requests, so callers may
 * not override them.
 */
final class RequestHeaders
{
    public const RESERVED = ['authorization', 'content-type', 'accept'];

    public const TRANSACTION = 'X-IA-API-Param-Transaction';

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    public static function validate(array $headers): array
    {
        foreach ($headers as $name => $value) {
            $name = (string) $name;

            if (preg_match("/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/", $name) !== 1) {
                throw new InvalidArgument(sprintf('"%s" is not a valid HTTP header name.', $name));
            }

            if (self::isReserved($name)) {
                throw new InvalidArgument(sprintf(
                    'The "%s" header is managed by the SDK and cannot be overridden.',
                    $name,
                ));
            }

            if (preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $value) === 1) {
                throw new InvalidArgument(sprintf('The "%s" header value contains control characters.', $name));
            }
        }

        return $headers;
    }

    public static function isReserved(string $name): bool
    {
        return in_array(strtolower($name), self::RESERVED, true);
    }

    private function __construct() {}
}
