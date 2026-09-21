<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Projects;

use ControlAir\Intacct\Auth\Tokens\AccessToken;
use ControlAir\Intacct\Auth\Tokens\StaticAccessTokenProvider;
use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Resources\Projects\CreateProject;
use ControlAir\Intacct\Resources\Projects\ProjectsClient;
use ControlAir\Intacct\Resources\Projects\UpdateProject;
use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\Tests\Support\QueueHttpClient;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

#[CoversClass(ProjectsClient::class)]
final class ProjectsClientTest extends TestCase
{
    public function test_it_maps_a_project_response_to_a_typed_resource(): void
    {
        [$projects, $http] = $this->client(new Response(200, [], json_encode([
            'ia::result' => [
                'key' => '10',
                'id' => 'PROJ-001',
                'name' => 'Headquarters Renovation',
                'description' => 'Phase one',
                'projectCurrency' => 'USD',
                'status' => 'active',
                'startDate' => '2026-01-15',
                'contractAmount' => '125000.50',
                'manager' => ['key' => '5', 'id' => 'EMP-5', 'name' => 'Ada'],
                'href' => '/objects/projects/project/10',
            ],
            'ia::meta' => ['totalCount' => 1],
        ], JSON_THROW_ON_ERROR)));

        $project = $projects->get(new ObjectKey('10'));

        self::assertSame('PROJ-001', $project->id->value);
        self::assertSame('Headquarters Renovation', $project->name);
        self::assertSame('125000.50', $project->contractAmount?->value);
        self::assertSame(RecordStatus::Active, $project->status);
        self::assertSame('EMP-5', $project->manager?->id?->value);
        self::assertSame('GET', $http->requests[0]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/projects/project/10', (string) $http->requests[0]->getUri());
    }

    public function test_it_uses_typed_create_update_and_delete_payloads(): void
    {
        $response = static fn (string $key): Response => new Response(200, [], json_encode([
            'ia::result' => ['key' => $key, 'id' => 'PROJ-001'],
            'ia::meta' => ['totalSuccess' => 1, 'totalError' => 0],
        ], JSON_THROW_ON_ERROR));
        [$projects, $http] = $this->client($response('10'), $response('10'), $response('10'));

        $created = $projects->create(new CreateProject(
            id: new ObjectId('PROJ-001'),
            name: 'Headquarters Renovation',
            status: RecordStatus::Active,
            startDate: new LocalDate('2026-01-15'),
            manager: ObjectReference::byId('EMP-5'),
        ));
        $updated = $projects->update(
            new ObjectKey('10'),
            UpdateProject::name('HQ Renovation')->withDescription(null),
        );
        $deleted = $projects->delete(new ObjectKey('10'));

        self::assertSame('10', $created->reference->key?->value);
        self::assertSame('10', $updated->reference->key?->value);
        self::assertSame('10', $deleted->reference->key?->value);
        self::assertSame('POST', $http->requests[0]->getMethod());
        self::assertSame([
            'id' => 'PROJ-001',
            'name' => 'Headquarters Renovation',
            'status' => 'active',
            'startDate' => '2026-01-15',
            'manager' => ['id' => 'EMP-5'],
        ], $this->jsonBody($http->requests[0]));
        self::assertSame('PATCH', $http->requests[1]->getMethod());
        self::assertSame(['name' => 'HQ Renovation', 'description' => null], $this->jsonBody($http->requests[1]));
        self::assertSame('DELETE', $http->requests[2]->getMethod());
    }

    /** @return array{ProjectsClient, QueueHttpClient} */
    private function client(Response ...$responses): array
    {
        $http = new QueueHttpClient(...$responses);
        $factory = new HttpFactory;
        $transport = new ApiTransport(
            new StaticAccessTokenProvider(new AccessToken('token')),
            $http,
            $factory,
            $factory,
        );

        return [new ProjectsClient($transport, new QueryClient($transport)), $http];
    }

    /** @return array<string, mixed> */
    private function jsonBody(RequestInterface $request): array
    {
        $body = ArrayReader::object(json_decode(
            (string) $request->getBody(),
            true,
            flags: JSON_THROW_ON_ERROR,
        ));

        self::assertNotNull($body);

        return $body;
    }
}
