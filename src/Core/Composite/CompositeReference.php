<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Composite;

use ControlAir\Intacct\Exceptions\InvalidArgument;

/**
 * Builds @{reference.path} placeholders that point at the result of an earlier sub-request
 * named with resultReference.
 *
 * Both forms seen in the Sage Intacct documentation are supported:
 *   CompositeReference::to('employee', 'department', 'key') // @{employee.department.key}
 *   CompositeReference::to('vendor', 1, 'key')              // @{vendor.1.key} (1-based result index)
 */
final class CompositeReference
{
    public static function to(string $reference, string|int ...$path): string
    {
        self::assertValidName($reference);

        if ($path === []) {
            throw new InvalidArgument('A composite reference needs at least one path segment, e.g. "key".');
        }

        $segments = [$reference];

        foreach ($path as $segment) {
            if (is_int($segment)) {
                if ($segment < 0) {
                    throw new InvalidArgument('A composite reference index cannot be negative.');
                }

                $segments[] = (string) $segment;

                continue;
            }

            if (preg_match('/^[^\s.{}@]+$/', $segment) !== 1) {
                throw new InvalidArgument(sprintf(
                    '"%s" is not a valid composite reference path segment; pass nested fields as separate segments.',
                    $segment,
                ));
            }

            $segments[] = $segment;
        }

        return '@{'.implode('.', $segments).'}';
    }

    public static function assertValidName(string $reference): void
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/', $reference) !== 1) {
            throw new InvalidArgument(sprintf(
                '"%s" is not a valid result reference; use letters, digits, "_" or "-".',
                $reference,
            ));
        }
    }

    private function __construct() {}
}
