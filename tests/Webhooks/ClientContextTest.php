<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Webhooks;

use ControlAir\Intacct\Support\Base64;
use ControlAir\Intacct\Webhooks\ClientContext;
use ControlAir\Intacct\Webhooks\TriggerEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClientContext::class)]
#[CoversClass(TriggerEvent::class)]
#[CoversClass(Base64::class)]
final class ClientContextTest extends TestCase
{
    /** Verbatim X-ClientContext example from Sage's outbound webhook guide. */
    private const CONTEXT = 'eyJDT01QQU5ZIjoxOTkwNTYsIkVOVElUWSI6ZmFsc2UsIlVTRVIiOiI5IiwiT0JKRUNUTkFNRSI6IkNMQVNTIiwiUkVTVE9CSkVDVE5BTUUiOiJjb21wYW55LWNvbmZpZ1wvY2xhc3MiLCJET0NUWVBFIjoiIiwiSUQiOnt9LCJWSUQiOiI1MDAiLCJFVkVOVCI6ImFmdGVyX2NyZWF0ZSIsIkVWRU5UTkFNRSI6IkFmdGVyIGNyZWF0ZSBvciB1cGRhdGUgY2xhc3Mgbm90aWZpY2F0aW9uIiwiVElNRVNUQU1QIjoiMDFcLzE2XC8yMDI2IDIxOjI4OjAwIn0=';

    public function test_it_maps_sages_webhook_context_example(): void
    {
        $context = ClientContext::tryFromEncoded(self::CONTEXT);

        self::assertNotNull($context);
        self::assertSame('199056', $context->companyId());
        self::assertNull($context->entityId(), 'ENTITY is false at the top level.');
        self::assertSame('9', $context->userKey());
        self::assertSame('CLASS', $context->objectName());
        self::assertSame('company-config/class', $context->restObjectName());
        self::assertNull($context->documentType(), 'DOCTYPE is empty.');
        self::assertNull($context->recordId(), 'ID is an empty object.');
        self::assertSame('after_create', $context->event());
        self::assertSame(TriggerEvent::AfterCreate, $context->eventType());
        self::assertSame('After create or update class notification', $context->eventName());
        self::assertSame('01/16/2026 21:28:00', $context->timestamp());
        self::assertSame('500', $context->get('vid'));
        self::assertSame(199056, $context->raw['COMPANY']);
    }

    public function test_it_reads_lower_case_queue_style_keys(): void
    {
        $context = ClientContext::tryFromEncoded(base64_encode(
            '{"object":"bill","event":"after.create","key":"12345","id":"B1234","docType":"Purchase Order","entity":"Central"}',
        ));

        self::assertNotNull($context);
        self::assertSame('bill', $context->objectName());
        self::assertSame(TriggerEvent::AfterCreate, $context->eventType());
        self::assertSame('12345', $context->recordKey());
        self::assertSame('B1234', $context->recordId());
        self::assertSame('Purchase Order', $context->documentType());
        self::assertSame('Central', $context->entityId());
        self::assertNull($context->get('missing'));
    }

    /** @return iterable<string, array{string|null}> */
    public static function undecodableContexts(): iterable
    {
        yield 'absent' => [null];
        yield 'blank' => [' '];
        yield 'not base64' => ['%%%'];
        yield 'not JSON' => [base64_encode('plain text')];
        yield 'JSON list' => [base64_encode('[1,2]')];
        // Sage's queued-event example context is itself invalid JSON.
        yield 'sage queue example' => ['eyAib2JqZWN0IiA6ICJiaWxsIiwgImV2ZW50IiA6ICJhZnRlci5jcmVhdGUiLCAia2V5IiA6ICIxMjM0NSIgOiAiaWQiIDogIkIxMjM0IiB9'];
    }

    #[DataProvider('undecodableContexts')]
    public function test_an_undecodable_context_is_ignored(?string $value): void
    {
        self::assertNull(ClientContext::tryFromEncoded($value));
    }

    /** @return iterable<string, array{string|null, TriggerEvent|null}> */
    public static function events(): iterable
    {
        yield 'underscore' => ['after_update', TriggerEvent::AfterUpdate];
        yield 'dot' => ['after.delete', TriggerEvent::AfterDelete];
        yield 'upper case with spaces' => [' AFTER CREATE ', TriggerEvent::AfterCreate];
        yield 'unknown' => ['before_create', null];
        yield 'absent' => [null, null];
    }

    #[DataProvider('events')]
    public function test_it_normalizes_event_names(?string $event, ?TriggerEvent $expected): void
    {
        self::assertSame($expected, TriggerEvent::parse($event));
    }
}
