<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Query;

use ControlAir\Intacct\Auth\Tokens\AccessToken;
use ControlAir\Intacct\Auth\Tokens\StaticAccessTokenProvider;
use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\Filter;
use ControlAir\Intacct\Core\Query\OrderBy;
use ControlAir\Intacct\Core\Query\Query;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\SortDirection;
use ControlAir\Intacct\Tests\Support\QueueHttpClient;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryClient::class)]
final class QueryClientTest extends TestCase
{
    public function test_it_serializes_a_query_and_maps_pagination_metadata(): void
    {
        $http = new QueueHttpClient(new Response(200, [], json_encode([
            'ia::result' => [
                ['key' => '1', 'id' => 'P-001', 'name' => 'Alpha'],
                ['key' => '2', 'id' => 'P-002', 'name' => 'Beta'],
            ],
            'ia::meta' => [
                'totalCount' => 12,
                'start' => 5,
                'pageSize' => 2,
                'next' => 7,
                'previous' => 3,
            ],
        ], JSON_THROW_ON_ERROR)));
        $factory = new HttpFactory;
        $queries = new QueryClient(new ApiTransport(
            new StaticAccessTokenProvider(new AccessToken('token')),
            $http,
            $factory,
            $factory,
        ));

        $page = $queries->execute('projects/project', new Query(
            fields: ['key', 'id', 'name'],
            filters: [Filter::equal('status', 'active')],
            orderBy: [new OrderBy('name', SortDirection::Descending)],
            start: 5,
            size: 2,
        ));

        $body = json_decode((string) $http->requests[0]->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('POST', $http->requests[0]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/services/core/query', (string) $http->requests[0]->getUri());
        self::assertSame([
            'object' => 'projects/project',
            'fields' => ['key', 'id', 'name'],
            'start' => 5,
            'size' => 2,
            'filters' => [['$eq' => ['status' => 'active']]],
            'filterExpression' => 'and',
            'filterParameters' => [
                'includeHierarchyFields' => false,
                'caseSensitiveComparison' => true,
                'includePrivate' => false,
            ],
            'orderBy' => [['name' => 'desc']],
        ], $body);
        self::assertCount(2, $page->items);
        self::assertSame('P-001', $page->items[0]['id']);
        self::assertSame(12, $page->meta->totalCount);
        self::assertSame(7, $page->meta->next);
        self::assertTrue($page->hasNextPage());
    }
}
