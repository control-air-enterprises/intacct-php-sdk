<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Query;

use ControlAir\Intacct\Auth\Tokens\AccessToken;
use ControlAir\Intacct\Auth\Tokens\StaticAccessTokenProvider;
use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\Filter;
use ControlAir\Intacct\Core\Query\Paginator;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Resources\Projects\Project;
use ControlAir\Intacct\Resources\Projects\ProjectsClient;
use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\Tests\Support\QueueHttpClient;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

#[CoversClass(Paginator::class)]
#[CoversClass(ResourceQuery::class)]
final class PaginatorTest extends TestCase
{
    public function test_it_yields_every_item_across_pages_in_order(): void
    {
        [$projects, $http] = $this->client(
            $this->page(['P-1', 'P-2'], start: 1, next: 3),
            $this->page(['P-3', 'P-4'], start: 3, next: 5),
            $this->page(['P-5'], start: 5, next: null),
        );

        $paginator = Paginator::over(
            $projects->query(...),
            new ResourceQuery(filters: [Filter::equal('status', 'active')], size: 2),
        );

        $ids = [];

        foreach ($paginator->items() as $index => $project) {
            self::assertInstanceOf(Project::class, $project);
            $ids[$index] = $project->id->value;
        }

        self::assertSame(['P-1', 'P-2', 'P-3', 'P-4', 'P-5'], $ids);
        self::assertCount(3, $http->requests);
        self::assertSame([1, 3, 5], array_map($this->start(...), $http->requests));

        foreach ($http->requests as $request) {
            $body = $this->jsonBody($request);
            self::assertSame(2, $body['size']);
            self::assertSame([['$eq' => ['status' => 'active']]], $body['filters']);
        }
    }

    public function test_it_yields_pages_and_is_iterable(): void
    {
        [$projects, $http] = $this->client(
            $this->page(['P-1', 'P-2'], start: 1, next: 3),
            $this->page(['P-3'], start: 3, next: null),
            $this->page(['P-1', 'P-2'], start: 1, next: 3),
            $this->page(['P-3'], start: 3, next: null),
        );

        $paginator = Paginator::over($projects->query(...), new ResourceQuery(size: 2));

        $pages = iterator_to_array($paginator->pages());
        $items = iterator_to_array($paginator);

        self::assertCount(2, $pages);
        self::assertSame(3, $pages[1]->meta->start);
        self::assertSame([0, 1, 2], array_keys($items));
        self::assertSame('P-3', $items[2]->id->value);
        self::assertCount(4, $http->requests);
    }

    public function test_it_stops_when_next_is_null(): void
    {
        [$projects, $http] = $this->client(
            $this->page(['P-1', 'P-2'], start: 1, next: null),
            $this->page(['P-3'], start: 3, next: null),
        );

        $items = iterator_to_array(Paginator::over($projects->query(...))->items());

        self::assertCount(2, $items);
        self::assertCount(1, $http->requests);
    }

    public function test_it_stops_on_an_empty_page(): void
    {
        [$projects, $http] = $this->client(
            $this->page(['P-1', 'P-2'], start: 1, next: 3),
            $this->page([], start: 3, next: 5),
            $this->page(['P-5'], start: 5, next: null),
        );

        $pages = iterator_to_array(Paginator::over($projects->query(...))->pages());

        self::assertCount(2, $pages);
        self::assertSame([], $pages[1]->items);
        self::assertCount(2, $http->requests);
    }

    public function test_it_stops_when_next_does_not_advance(): void
    {
        [$projects, $http] = $this->client(
            $this->page(['P-1', 'P-2'], start: 1, next: 3),
            $this->page(['P-3', 'P-4'], start: 3, next: 3),
            $this->page(['P-5'], start: 5, next: null),
        );

        $items = iterator_to_array(Paginator::over($projects->query(...), new ResourceQuery(size: 2))->items());

        self::assertCount(4, $items);
        self::assertSame([1, 3], array_map($this->start(...), $http->requests));
    }

    public function test_it_stops_when_next_moves_backwards(): void
    {
        [$projects, $http] = $this->client(
            $this->page(['P-1', 'P-2'], start: 1, next: 3),
            $this->page(['P-3', 'P-4'], start: 3, next: 1),
        );

        $items = iterator_to_array(Paginator::over($projects->query(...), new ResourceQuery(size: 2))->items());

        self::assertCount(4, $items);
        self::assertCount(2, $http->requests);
    }

    public function test_it_stops_at_the_max_pages_guard(): void
    {
        [$projects, $http] = $this->client(
            $this->page(['P-1', 'P-2'], start: 1, next: 3),
            $this->page(['P-3', 'P-4'], start: 3, next: 5),
            $this->page(['P-5', 'P-6'], start: 5, next: 7),
        );

        $paginator = Paginator::over($projects->query(...), new ResourceQuery(size: 2), maxPages: 2);
        $items = iterator_to_array($paginator->items());

        self::assertSame(['P-1', 'P-2', 'P-3', 'P-4'], array_map(
            static fn (Project $project): string => $project->id->value,
            $items,
        ));
        self::assertCount(2, $http->requests);

        [$projects, $http] = $this->client(
            $this->page(['P-1', 'P-2'], start: 1, next: 3),
            $this->page(['P-3', 'P-4'], start: 3, next: 5),
        );

        $pages = iterator_to_array(
            Paginator::over($projects->query(...))->withMaxPages(1)->pages(),
        );

        self::assertCount(1, $pages);
        self::assertCount(1, $http->requests);
    }

    public function test_it_rejects_an_invalid_max_pages_guard(): void
    {
        [$projects] = $this->client();

        $this->expectException(InvalidArgument::class);

        Paginator::over($projects->query(...), maxPages: 0);
    }

    public function test_it_fetches_pages_lazily(): void
    {
        [$projects, $http] = $this->client(
            $this->page(['P-1', 'P-2'], start: 1, next: 3),
            $this->page(['P-3'], start: 3, next: null),
        );

        $items = Paginator::over($projects->query(...), new ResourceQuery(size: 2))->items();

        self::assertCount(0, $http->requests);

        self::assertSame('P-1', $items->current()->id->value);
        self::assertCount(1, $http->requests);

        $items->next();
        self::assertSame('P-2', $items->current()->id->value);
        self::assertCount(1, $http->requests);

        $items->next();
        self::assertSame('P-3', $items->current()->id->value);
        self::assertCount(2, $http->requests);
        self::assertSame(3, $this->start($http->requests[1]));

        $items->next();
        self::assertFalse($items->valid());
        self::assertCount(2, $http->requests);
    }

    public function test_resource_query_with_start_and_size_preserve_other_criteria(): void
    {
        $query = new ResourceQuery(filters: [Filter::equal('status', 'active')], filterExpression: 'or', size: 50);

        $moved = $query->withStart(101);
        $resized = $query->withSize(500);

        self::assertSame(101, $moved->start);
        self::assertSame(50, $moved->size);
        self::assertSame($query->filters, $moved->filters);
        self::assertSame('or', $moved->filterExpression);
        self::assertSame(1, $resized->start);
        self::assertSame(500, $resized->size);
        self::assertSame(1, $query->start);

        $this->expectException(InvalidArgument::class);

        $query->withSize(4001);
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

    /** @param list<string> $ids */
    private function page(array $ids, int $start, ?int $next): Response
    {
        return new Response(200, [], json_encode([
            'ia::result' => array_map(
                static fn (string $id): array => ['key' => substr($id, 2), 'id' => $id, 'name' => 'Project '.$id],
                $ids,
            ),
            'ia::meta' => [
                'totalCount' => 5,
                'start' => $start,
                'pageSize' => count($ids),
                'next' => $next,
                'previous' => null,
            ],
        ], JSON_THROW_ON_ERROR));
    }

    private function start(RequestInterface $request): mixed
    {
        return $this->jsonBody($request)['start'] ?? null;
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
