<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\CompanyConfiguration;

use ControlAir\Intacct\Auth\Tokens\AccessToken;
use ControlAir\Intacct\Auth\Tokens\StaticAccessTokenProvider;
use ControlAir\Intacct\IntacctClient;
use ControlAir\Intacct\Resources\CompanyConfiguration\Dimensions\DimensionDefinition;
use ControlAir\Intacct\Tests\Support\QueueHttpClient;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IntacctClient::class)]
#[CoversClass(DimensionDefinition::class)]
final class CompanyConfigurationTest extends TestCase
{
    public function test_the_facade_exposes_and_maps_the_dimension_catalog(): void
    {
        [$client, $http] = $this->client(new Response(200, [], json_encode([
            'ia::result' => [[
                'dimensionName' => 'PROJECT',
                'dimensionLabel' => 'Project',
                'termName' => 'Project',
                'isUserDefinedDimension' => false,
                'isEnabledInGL' => true,
                'dimensionEndpoint' => '/objects/projects/project',
            ]],
            'ia::meta' => ['totalCount' => 1],
        ], JSON_THROW_ON_ERROR)));

        $catalog = $client->dimensions->list();

        self::assertCount(1, $catalog->dimensions);
        self::assertSame('Project', $catalog->find('PROJECT')?->label);
        self::assertTrue($catalog->dimensions[0]->enabledInGeneralLedger);
        self::assertSame('https://api.intacct.com/ia/api/v1/services/company-config/dimensions/list', (string) $http->requests[0]->getUri());
    }

    public function test_employee_sensitive_data_is_wrapped_and_redacted(): void
    {
        [$client] = $this->client(new Response(200, [], json_encode([
            'ia::result' => [
                'key' => '7',
                'id' => 'EMP-007',
                'name' => 'Grace Hopper',
                'status' => 'active',
                'SSN' => '111-22-3333',
                'department' => ['key' => '3', 'id' => 'ENG'],
            ],
            'ia::meta' => ['totalCount' => 1],
        ], JSON_THROW_ON_ERROR)));

        $employee = $client->employees->get(new ObjectKey('7'));

        self::assertSame('EMP-007', $employee->id->value);
        self::assertSame('111-22-3333', $employee->ssn?->reveal());
        self::assertSame(['value' => '[redacted]'], $employee->ssn->__debugInfo());
        self::assertSame('ENG', $employee->department?->id?->value);
    }

    /** @return array{IntacctClient, QueueHttpClient} */
    private function client(Response ...$responses): array
    {
        $http = new QueueHttpClient(...$responses);
        $factory = new HttpFactory;

        return [
            new IntacctClient(
                new StaticAccessTokenProvider(new AccessToken('token')),
                $http,
                $factory,
                $factory,
            ),
            $http,
        ];
    }
}
