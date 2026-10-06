<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Webhooks;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\Support\Base64;

/**
 * Trigger and event metadata from the `X-ClientContext` header or a queued event's
 * `clientContext`.
 *
 * This metadata is NOT covered by the webhook signature, which only signs the body.
 * Use it to route an event, but read anything security-relevant from the verified
 * payload or by fetching the record through the API.
 *
 * Sage's examples use upper-case keys (`OBJECTNAME`) for webhooks and lower-case keys
 * (`object`) for queued events, so keys are matched case-insensitively. Fields the SDK
 * does not map, such as `VID`, remain available through `get()` and `raw`.
 */
final readonly class ClientContext
{
    /** @var array<string, mixed> keys upper-cased */
    private array $normalized;

    /** @param array<string, mixed> $raw */
    public function __construct(public array $raw)
    {
        $this->normalized = array_change_key_case($raw, CASE_UPPER);
    }

    /**
     * Decodes a base64 JSON context. Returns null when the value cannot be decoded,
     * because the context is unsigned metadata and must not break webhook handling.
     */
    public static function tryFromEncoded(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $json = Base64::decode($value);
        $data = $json === null ? null : ArrayReader::object(json_decode($json, true));

        return $data === null ? null : new self($data);
    }

    /** The Sage company (tenant) record number. */
    public function companyId(): ?string
    {
        return $this->string('COMPANY', 'COMPANYID');
    }

    /** The entity the event happened in, or null at the top level. */
    public function entityId(): ?string
    {
        return $this->string('ENTITY', 'ENTITYID');
    }

    /** The key of the user who caused the event. */
    public function userKey(): ?string
    {
        return $this->string('USER', 'USERKEY');
    }

    /** The legacy object name, such as `CLASS`. */
    public function objectName(): ?string
    {
        return $this->string('OBJECTNAME', 'OBJECT');
    }

    /** The REST object name, such as `company-config/class`. */
    public function restObjectName(): ?string
    {
        return $this->string('RESTOBJECTNAME');
    }

    /** The transaction definition for document objects, such as `Purchase Order`. */
    public function documentType(): ?string
    {
        return $this->string('DOCTYPE');
    }

    public function recordKey(): ?string
    {
        return $this->string('KEY', 'RECORDNO');
    }

    public function recordId(): ?string
    {
        return $this->string('ID');
    }

    /** The raw event name, such as `after_create`. */
    public function event(): ?string
    {
        return $this->string('EVENT');
    }

    public function eventType(): ?TriggerEvent
    {
        return TriggerEvent::parse($this->event());
    }

    /** The trigger's display name. */
    public function eventName(): ?string
    {
        return $this->string('EVENTNAME');
    }

    /**
     * The event time as Sage sends it, e.g. `01/16/2026 21:28:00`. Sage does not
     * document the time zone, so the SDK does not convert it.
     */
    public function timestamp(): ?string
    {
        return $this->string('TIMESTAMP');
    }

    /** Reads any context field, case-insensitively. */
    public function get(string $name): mixed
    {
        return $this->normalized[strtoupper($name)] ?? null;
    }

    private function string(string ...$names): ?string
    {
        foreach ($names as $name) {
            $value = ArrayReader::string($this->normalized, $name);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }
}
