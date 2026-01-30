<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Integration;

use Matchory\Elasticsearch\Collection;
use Matchory\Elasticsearch\Facades\Elasticsearch;
use Matchory\Elasticsearch\Interfaces\ConnectionInterface;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;
use Matchory\Elasticsearch\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Matchory\Elasticsearch\Builder;
use Matchory\Elasticsearch\Tests\TestCase;

/**
 * Tests for Elasticsearch facade resolution and method delegation
 *
 * Validates that the facade correctly resolves to the connection resolver
 * and properly delegates method calls to the underlying services.
 */
class ElasticsearchFacadeTest extends TestCase
{
    /**
     * Test that facade resolves to the correct service
     */
    public function test_facade_resolves_to_connection_resolver(): void
    {
        $facadeRoot = Elasticsearch::getFacadeRoot();

        $this->assertInstanceOf(ConnectionResolverInterface::class, $facadeRoot);

        // Test that it's the same instance as registered in container
        $resolver = $this->app->make(ConnectionResolverInterface::class);
        $this->assertSame($resolver, $facadeRoot);
    }

    /**
     * Test facade accessor returns correct binding key
     */
    public function test_facade_accessor(): void
    {
        // Use reflection to access the protected method
        $reflection = new \ReflectionClass(Elasticsearch::class);
        $method = $reflection->getMethod('getFacadeAccessor');
        $method->setAccessible(true);
        $accessor = $method->invoke(null);

        $this->assertEquals(ConnectionResolverInterface::class, $accessor);
    }

    /**
     * Test connection method delegation
     */
    public function test_connection_method_delegation(): void
    {
        $connection = Elasticsearch::connection();

        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    /**
     * Test connection method with specific connection name
     */
    public function test_connection_method_with_name(): void
    {
        $connection = Elasticsearch::connection('default');

        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    /**
     * Test default connection methods
     */
    public function test_default_connection_methods(): void
    {
        // Test getDefaultConnection
        $defaultName = Elasticsearch::getDefaultConnection();
        $this->assertIsString($defaultName);

        // Test setDefaultConnection
        Elasticsearch::setDefaultConnection('test');
        $newDefault = Elasticsearch::getDefaultConnection();
        $this->assertEquals('test', $newDefault);

        // Reset to original
        Elasticsearch::setDefaultConnection($defaultName);
    }

    /**
     * Test query builder method delegation
     */
    public function test_query_builder_method_delegation(): void
    {
        // Test newQuery method
        $query = Elasticsearch::newQuery();
        $this->assertInstanceOf(Builder::class, $query);

        // Test index method
        $query = Elasticsearch::index('test_index');
        $this->assertInstanceOf(Builder::class, $query);

        // Test scroll method
        $query = Elasticsearch::scroll('1m');
        $this->assertInstanceOf(Builder::class, $query);

        // Test scrollId method
        $query = Elasticsearch::scrollId('scroll123');
        $this->assertInstanceOf(Builder::class, $query);

        // Test searchType method
        $query = Elasticsearch::searchType('dfs_query_then_fetch');
        $this->assertInstanceOf(Builder::class, $query);
    }

    /**
     * Test query filtering method delegation
     */
    public function test_query_filtering_method_delegation(): void
    {
        // Test where method
        $query = Elasticsearch::where('field', 'value');
        $this->assertInstanceOf(Builder::class, $query);

        // Test whereNot method
        $query = Elasticsearch::whereNot('field', 'value');
        $this->assertInstanceOf(Builder::class, $query);

        // Test whereBetween method
        $query = Elasticsearch::whereBetween('field', 1, 10);
        $this->assertInstanceOf(Builder::class, $query);

        // Test whereNotBetween method
        $query = Elasticsearch::whereNotBetween('field', 1, 10);
        $this->assertInstanceOf(Builder::class, $query);

        // Test whereIn method
        $query = Elasticsearch::whereIn('field', [1, 2, 3]);
        $this->assertInstanceOf(Builder::class, $query);

        // Test whereNotIn method
        $query = Elasticsearch::whereNotIn('field', [1, 2, 3]);
        $this->assertInstanceOf(Builder::class, $query);

        // Test whereExists method
        $query = Elasticsearch::whereExists('field');
        $this->assertInstanceOf(Builder::class, $query);
    }

    /**
     * Test query ordering and selection method delegation
     */
    public function test_query_ordering_and_selection_methods(): void
    {
        // Test orderBy method
        $query = Elasticsearch::orderBy('field', 'desc');
        $this->assertInstanceOf(Builder::class, $query);

        // Test select method
        $query = Elasticsearch::select('field1', 'field2');
        $this->assertInstanceOf(Builder::class, $query);

        // Test unselect method
        $query = Elasticsearch::unselect('field1');
        $this->assertInstanceOf(Builder::class, $query);
    }

    /**
     * Test search and query method delegation
     */
    public function test_search_and_query_methods(): void
    {
        // Test search method via newQuery() - search is a Query builder method
        $query = Elasticsearch::newQuery()->search('test query');
        $this->assertInstanceOf(Builder::class, $query);

        // Test nested method
        $query = Elasticsearch::nested('nested_field');
        $this->assertInstanceOf(Builder::class, $query);

        // Test highlight method
        $query = Elasticsearch::highlight('field1', 'field2');
        $this->assertInstanceOf(Builder::class, $query);

        // Test body method
        $query = Elasticsearch::body(['query' => ['match_all' => []]]);
        $this->assertInstanceOf(Builder::class, $query);

        // Test distance method
        $query = Elasticsearch::distance('location', [0, 0], '10km');
        $this->assertInstanceOf(Builder::class, $query);
    }

    /**
     * Test aggregation and grouping method delegation
     */
    public function test_aggregation_and_grouping_methods(): void
    {
        // Test groupBy method
        $query = Elasticsearch::groupBy('category');
        $this->assertInstanceOf(Builder::class, $query);
    }

    /**
     * Test pagination and limiting method delegation
     */
    public function test_pagination_and_limiting_methods(): void
    {
        // Test skip method
        $query = Elasticsearch::skip(10);
        $this->assertInstanceOf(Builder::class, $query);

        // Test take method
        $query = Elasticsearch::take(20);
        $this->assertInstanceOf(Builder::class, $query);

        // Test key method
        $query = Elasticsearch::key('doc123');
        $this->assertInstanceOf(Builder::class, $query);
    }

    /**
     * Test scope method delegation
     */
    public function test_scope_method_delegation(): void
    {
        // Test withGlobalScope method
        $query = Elasticsearch::withGlobalScope('test', function ($query) {
            return $query->where('active', true);
        });
        $this->assertInstanceOf(Builder::class, $query);

        // Test withoutGlobalScope method
        $query = Elasticsearch::withoutGlobalScope('test');
        $this->assertInstanceOf(Builder::class, $query);

        // Test withoutGlobalScopes method
        $query = Elasticsearch::withoutGlobalScopes(['test1', 'test2']);
        $this->assertInstanceOf(Builder::class, $query);

        // Note: scopes() method requires named scopes defined on the model,
        // which is not testable via the facade directly without a model with scopes
    }

    /**
     * Test caching method delegation
     */
    public function test_caching_method_delegation(): void
    {
        // Test remember method
        $query = Elasticsearch::remember(3600);
        $this->assertInstanceOf(Builder::class, $query);

        // Test rememberForever method
        $query = Elasticsearch::rememberForever();
        $this->assertInstanceOf(Builder::class, $query);
    }

    /**
     * Test execution method delegation with mocked responses
     */
    public function test_execution_method_delegation(): void
    {
        // Set up mock responses with proper structure
        $this->getMockClient()->setResponse('search', [
            'took' => 5,
            'timed_out' => false,
            '_shards' => ['total' => 1, 'successful' => 1, 'skipped' => 0, 'failed' => 0],
            'hits' => [
                'total' => ['value' => 2, 'relation' => 'eq'],
                'max_score' => 1.0,
                'hits' => [
                    ['_id' => '1', '_index' => 'test_index', '_score' => 1.0, '_source' => ['title' => 'Test 1']],
                    ['_id' => '2', '_index' => 'test_index', '_score' => 1.0, '_source' => ['title' => 'Test 2']],
                ],
            ],
        ]);

        $this->getMockClient()->setResponse('count', [
            'count' => 5,
            '_shards' => ['total' => 1, 'successful' => 1, 'skipped' => 0, 'failed' => 0],
        ]);

        // Test get method
        $results = Elasticsearch::index('test_index')->get();
        $this->assertInstanceOf(Collection::class, $results);

        // Test count method
        $count = Elasticsearch::index('test_index')->count();
        $this->assertIsInt($count);

        // Test first method
        $this->getMockClient()->setResponse('search', [
            'took' => 2,
            'timed_out' => false,
            '_shards' => ['total' => 1, 'successful' => 1, 'skipped' => 0, 'failed' => 0],
            'hits' => [
                'total' => ['value' => 1, 'relation' => 'eq'],
                'max_score' => 1.0,
                'hits' => [
                    ['_id' => '1', '_index' => 'test_index', '_score' => 1.0, '_source' => ['title' => 'First Test']],
                ],
            ],
        ]);

        $first = Elasticsearch::index('test_index')->first();
        $this->assertInstanceOf(Model::class, $first);
    }

    /**
     * Test pagination method delegation
     */
    public function test_pagination_method_delegation(): void
    {
        // Set up mock response for pagination with proper structure
        $this->getMockClient()->setResponse('search', [
            'took' => 10,
            'timed_out' => false,
            '_shards' => ['total' => 1, 'successful' => 1, 'skipped' => 0, 'failed' => 0],
            'hits' => [
                'total' => ['value' => 100, 'relation' => 'eq'],
                'max_score' => 1.0,
                'hits' => array_fill(0, 10, ['_id' => '1', '_index' => 'test_index', '_score' => 1.0, '_source' => ['title' => 'Test']]),
            ],
        ]);

        $paginated = Elasticsearch::index('test_index')->paginate(10);
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginated);
    }

    /**
     * Test CRUD operation method delegation
     */
    public function test_crud_operation_method_delegation(): void
    {
        // Set up mock responses for CRUD operations
        $this->getMockClient()->setResponse('index', [
            '_id' => '1',
            '_index' => 'test_index',
            'result' => 'created',
        ]);

        $this->getMockClient()->setResponse('update', [
            '_id' => '1',
            '_index' => 'test_index',
            'result' => 'updated',
        ]);

        $this->getMockClient()->setResponse('delete', [
            '_id' => '1',
            '_index' => 'test_index',
            'result' => 'deleted',
        ]);

        // Test insert method
        $result = Elasticsearch::index('test_index')->insert(['title' => 'New Document']);
        $this->assertIsObject($result);

        // Test update method
        $result = Elasticsearch::index('test_index')->update(['title' => 'Updated Document'], '1');
        $this->assertIsObject($result);

        // Test delete method
        $result = Elasticsearch::index('test_index')->delete('1');
        $this->assertIsObject($result);
    }

    /**
     * Test script operation method delegation
     */
    public function test_script_operation_method_delegation(): void
    {
        // Set up mock responses for script operations
        $this->getMockClient()->setResponse('updateByQuery', [
            'updated' => 1,
        ]);

        // Test script method
        $result = Elasticsearch::index('test_index')->script('ctx._source.counter += params.increment', ['increment' => 1]);
        $this->assertIsObject($result);

        // Test increment method
        $result = Elasticsearch::index('test_index')->increment('counter', 2);
        $this->assertIsObject($result);

        // Test decrement method
        $result = Elasticsearch::index('test_index')->decrement('counter', 1);
        $this->assertIsObject($result);
    }

    /**
     * Test index management method delegation
     */
    public function test_index_management_method_delegation(): void
    {
        // Set up mock responses for index operations
        $this->getMockClient()->setResponse('indices.create', [
            'acknowledged' => true,
            'shards_acknowledged' => true,
            'index' => 'test_index',
        ]);

        $this->getMockClient()->setResponse('indices.delete', [
            'acknowledged' => true,
        ]);

        // Test createIndex method
        $result = Elasticsearch::createIndex('test_index');
        $this->assertIsArray($result);

        // Test dropIndex method
        $result = Elasticsearch::dropIndex('test_index');
        $this->assertIsArray($result);
    }

    /**
     * Test that facade methods return expected types
     */
    public function test_facade_method_return_types(): void
    {
        // Test methods that should return Query instances
        $queryMethods = [
            'newQuery', 'index', 'scroll', 'scrollId', 'searchType', 'ignore',
            'orderBy', 'select', 'unselect', 'where', 'whereNot',
            'whereBetween', 'whereNotBetween', 'whereIn', 'whereNotIn', 'whereExists',
            'distance', 'nested', 'highlight', 'body', 'groupBy', 'key',
            'skip', 'take', 'withGlobalScope', 'withoutGlobalScope', 'withoutGlobalScopes',
            'applyScopes', 'remember', 'rememberForever',
        ];

        foreach ($queryMethods as $method) {
            $result = match ($method) {
                'index', 'scroll', 'scrollId' => Elasticsearch::$method('test'),
                'searchType' => Elasticsearch::$method('query_then_fetch'),
                'orderBy' => Elasticsearch::$method('field', 'asc'),
                'select', 'unselect', 'ignore', 'highlight' => Elasticsearch::$method('field'),
                'where', 'whereNot' => Elasticsearch::$method('field', 'value'),
                'whereBetween', 'whereNotBetween' => Elasticsearch::$method('field', 1, 10),
                'whereIn', 'whereNotIn' => Elasticsearch::$method('field', [1, 2, 3]),
                'whereExists' => Elasticsearch::$method('field'),
                'distance' => Elasticsearch::$method('location', [0, 0], '10km'),
                'nested', 'groupBy', 'key' => Elasticsearch::$method('test'),
                'body' => Elasticsearch::$method(['query' => ['match_all' => []]]),
                'skip', 'take' => Elasticsearch::$method(10),
                'withGlobalScope' => Elasticsearch::$method('test', fn($q) => $q),
                'withoutGlobalScope' => Elasticsearch::$method('test'),
                'withoutGlobalScopes' => Elasticsearch::$method(['test']),
                'remember' => Elasticsearch::$method(3600),
                default => Elasticsearch::$method(),
            };

            $this->assertInstanceOf(Builder::class, $result, "Method {$method} should return Query instance");
        }
    }

    /**
     * Test facade method chaining
     */
    public function test_facade_method_chaining(): void
    {
        $query = Elasticsearch::index('test_index')
            ->where('status', 'published')
            ->orderBy('created_at', 'desc')
            ->take(10);

        $this->assertInstanceOf(Builder::class, $query);
    }

    /**
     * Test facade with different connection
     */
    public function test_facade_with_different_connection(): void
    {
        $connection = Elasticsearch::connection('default');
        $this->assertInstanceOf(ConnectionInterface::class, $connection);

        // Test that we can chain methods on the connection
        $query = $connection->newQuery();
        $this->assertInstanceOf(Builder::class, $query);
    }
}
