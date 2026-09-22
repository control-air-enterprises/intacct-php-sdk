<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Composite;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\HttpMethod;

/**
 * Sends 2 to 10 sub-requests in one call to POST /services/core/composite.
 *
 * Sub-requests run in order and stop at the first failure without rolling back earlier
 * ones; composite requests have no atomic mode. A partial failure is returned as a
 * CompositeResult (HTTP 207) rather than thrown. Only request-level failures throw.
 */
final readonly class CompositeClient
{
    public const PATH = 'services/core/composite';

    public function __construct(private ApiTransport $transport) {}

    public function execute(CompositeRequest $request): CompositeResult
    {
        $response = $this->transport->send(HttpMethod::Post, self::PATH, json: $request->toArray());

        return CompositeResult::fromPayload($response->payload, $response->statusCode);
    }
}
