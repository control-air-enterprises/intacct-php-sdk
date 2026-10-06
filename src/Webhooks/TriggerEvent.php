<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Webhooks;

/** The record event that fired a Platform Trigger. */
enum TriggerEvent: string
{
    case AfterCreate = 'after_create';
    case AfterUpdate = 'after_update';
    case AfterDelete = 'after_delete';

    /**
     * Sage spells events both `after_create` and `after.create`, so separators and
     * case are normalized. Returns null for an event this SDK does not know yet.
     */
    public static function parse(?string $event): ?self
    {
        if ($event === null) {
            return null;
        }

        return self::tryFrom(strtolower((string) preg_replace('/[\s.\-]+/', '_', trim($event))));
    }
}
