<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Unit;

use Matchory\Elasticsearch\Model;
use Matchory\Elasticsearch\Builder;
use Matchory\Elasticsearch\Tests\Support\Factories\ResponseFactory;
use Matchory\Elasticsearch\Tests\Support\Traits\AssertsElasticsearch;
use Matchory\Elasticsearch\Tests\Support\Traits\ConfiguresElasticsearch;
use Matchory\Elasticsearch\Tests\TestCase;

/**
 * Advanced Query Features Tests
 *
 * Tests advanced query functionality including complex queries,
 * caching, and specialized query types
 */
class AdvancedQueryFeaturesTest extends TestCase
{
    use ConfiguresElasticsearch;
    use AssertsElasticsearch;

    private Builder $query;
    private Model $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new Model();
        $this->query = $this->model->newQuery();
    }

    // Search Query Tests

    /**
     * Test basic search query
     */
    public function testBasicSearchQuery(): void
    {
        $query = $this->query->search('elasticsearch tutorial');
        $result = $query->toArray();

        $expectedMust = [
            'query_string' => [
                'query' => 'elasticsearch tutorial',
            ],
        ];

        $this->assertContains($expectedMust, $result['body']['query']['bool']['must']);
    }

    /**
     * Test search query with boost
     */
    public function testSearchQueryWithBoost(): void
    {
        $query = $this->query->search('elasticsearch', null, 2);
        $result = $query->toArray();

        $expectedMust = [
            'query_string' => [
                'query' => 'elasticsearch',
                'boost' => 2,
            ],
        ];

        $this->assertContains($expectedMust, $result['body']['query']['bool']['must']);
    }

    /**
     * Test search query with fields
     */
    public function testSearchQueryWithFields(): void
    {
        // The search method expects a callable to configure fields
        $query = $this->query->search('elasticsearch', function ($search) {
            $search->fields(['title' => 2, 'content' => 1]);
        });
        $result = $query->toArray();

        $expectedMust = [
            'query_string' => [
                'query' => 'elasticsearch',
                'fields' => ['title^2', 'content'],
            ],
        ];

        $this->assertContains($expectedMust, $result['body']['query']['bool']['must']);
    }

    // Where Between Tests

    /**
     * Test where between
     */
    public function testWhereBetween(): void
    {
        $query = $this->query->whereBetween('price', [10, 100]);
        $result = $query->toArray();

        $expectedFilter = [
            'range' => [
                'price' => [
                    'gte' => 10,
                    'lte' => 100,
                ],
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test where not between
     */
    public function testWhereNotBetween(): void
    {
        $query = $this->query->whereNotBetween('price', [10, 100]);
        $result = $query->toArray();

        $expectedMustNot = [
            'range' => [
                'price' => [
                    'gte' => 10,
                    'lte' => 100,
                ],
            ],
        ];

        $this->assertContains($expectedMustNot, $result['body']['query']['bool']['must_not']);
    }

    // Where In Tests

    /**
     * Test where in
     */
    public function testWhereIn(): void
    {
        $query = $this->query->whereIn('category', ['tech', 'science', 'news']);
        $result = $query->toArray();

        $expectedFilter = [
            'terms' => [
                'category' => ['tech', 'science', 'news'],
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test where not in
     */
    public function testWhereNotIn(): void
    {
        $query = $this->query->whereNotIn('status', ['draft', 'archived']);
        $result = $query->toArray();

        $expectedMustNot = [
            'terms' => [
                'status' => ['draft', 'archived'],
            ],
        ];

        $this->assertContains($expectedMustNot, $result['body']['query']['bool']['must_not']);
    }

    // Where Not Tests

    /**
     * Test where not
     */
    public function testWhereNot(): void
    {
        $query = $this->query->whereNot('status', 'draft');
        $result = $query->toArray();

        $expectedMustNot = [
            'term' => [
                'status' => 'draft',
            ],
        ];

        $this->assertContains($expectedMustNot, $result['body']['query']['bool']['must_not']);
    }

    /**
     * Test where not with closure
     *
     * Note: When passing a closure to whereNot(), it operates directly on the
     * query builder (via tap), allowing fluent building within the callback.
     * The closure adds filters directly, not into must_not.
     */
    public function testWhereNotWithClosure(): void
    {
        $query = $this->query->whereNot(function (Builder $q) {
            $q->where('status', 'draft')
              ->where('category', 'temp');
        });

        $result = $query->toArray();

        // Verify query structure exists
        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('query', $result['body']);
        $this->assertArrayHasKey('bool', $result['body']['query']);

        $bool = $result['body']['query']['bool'];

        // The closure adds filters directly (via where() calls)
        $this->assertArrayHasKey('filter', $bool);
        $this->assertNotEmpty($bool['filter']);
    }

    // Caching Tests

    /**
     * Test query caching with TTL
     */
    public function testQueryCachingWithTtl(): void
    {
        $query = $this->query->remember(3600);

        // Verify cache properties are set
        $this->assertEquals(3600, $this->getPrivateProperty($query, 'cacheTtl'));
        $this->assertNull($this->getPrivateProperty($query, 'cacheKey'));
    }

    /**
     * Test query caching with custom key
     */
    public function testQueryCachingWithCustomKey(): void
    {
        $query = $this->query->remember(3600, 'custom_cache_key');

        $this->assertEquals(3600, $this->getPrivateProperty($query, 'cacheTtl'));
        $this->assertEquals('custom_cache_key', $this->getPrivateProperty($query, 'cacheKey'));
    }

    /**
     * Test remember forever
     */
    public function testRememberForever(): void
    {
        $query = $this->query->rememberForever('forever_key');

        $this->assertEquals(-1, $this->getPrivateProperty($query, 'cacheTtl'));
        $this->assertEquals('forever_key', $this->getPrivateProperty($query, 'cacheKey'));
    }

    /**
     * Test cache key generation
     */
    public function testCacheKeyGeneration(): void
    {
        $query = $this->query
            ->where('status', 'published')
            ->orderBy('created_at');

        $cacheKey = $query->generateCacheKey();

        $this->assertIsString($cacheKey);
        $this->assertEquals(32, strlen($cacheKey)); // MD5 hash length

        // Same query built on a fresh instance should generate same cache key
        $freshModel = new Model();
        $query2 = $freshModel->newQuery()
            ->where('status', 'published')
            ->orderBy('created_at');

        $this->assertEquals($cacheKey, $query2->generateCacheKey());
    }

    /**
     * Test cache prefix
     */
    public function testCachePrefix(): void
    {
        $query = $this->query
            ->cachePrefix('custom_prefix')
            ->remember(3600, 'test_key');

        $fullCacheKey = $query->getCacheKey();

        $this->assertStringStartsWith('custom_prefix.', $fullCacheKey);
        $this->assertStringEndsWith('test_key', $fullCacheKey);
    }

    // Complex Query Combinations

    /**
     * Test complex query with multiple conditions
     */
    public function testComplexQueryWithMultipleConditions(): void
    {
        $query = $this->query
            ->where('status', 'published')
            ->whereBetween('created_at', '2024-01-01', '2024-12-31')
            ->whereIn('category', ['tech', 'science'])
            ->whereExists('featured_image')
            ->whereNot('author_id', '=', 123)
            ->search('elasticsearch')
            ->orderBy('_score', 'desc')
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->skip(40);

        $result = $query->toArray();

        // Verify structure
        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('query', $result['body']);
        $this->assertArrayHasKey('bool', $result['body']['query']);

        $bool = $result['body']['query']['bool'];

        // Check filters - status, created_at, category are in filter
        $this->assertArrayHasKey('filter', $bool);
        $this->assertGreaterThanOrEqual(3, count($bool['filter']));

        // Check must conditions - search query
        $this->assertArrayHasKey('must', $bool);

        // Check must_not conditions - author_id
        $this->assertArrayHasKey('must_not', $bool);
        $this->assertGreaterThanOrEqual(1, count($bool['must_not']));

        // Check sorting
        $this->assertArrayHasKey('sort', $result['body']);
        $this->assertCount(2, $result['body']['sort']);

        // Check pagination
        $this->assertEquals(20, $result['size']);
        $this->assertEquals(40, $result['from']);
    }

    /**
     * Test query with aggregations and filters
     */
    public function testQueryWithAggregationsAndFilters(): void
    {
        $query = $this->query
            ->where('status', 'published')
            ->aggregate('categories', 'category')
            ->aggregate('avg_views', ['avg' => ['field' => 'views']])
            ->aggregate('date_histogram', [
                'date_histogram' => [
                    'field' => 'created_at',
                    'calendar_interval' => 'month',
                ],
            ]);

        $result = $query->toArray();

        // Should have filters
        $this->assertContains(['term' => ['status' => 'published']], $result['body']['query']['bool']['filter']);

        // Should have aggregations
        $this->assertArrayHasKey('aggs', $result['body']);
        $this->assertCount(3, $result['body']['aggs']);
        $this->assertArrayHasKey('categories', $result['body']['aggs']);
        $this->assertArrayHasKey('avg_views', $result['body']['aggs']);
        $this->assertArrayHasKey('date_histogram', $result['body']['aggs']);
    }

    /**
     * Test firstWhere method
     */
    public function testFirstWhereMethod(): void
    {
        $searchResponse = ResponseFactory::searchResponse([
            ['_id' => '1', '_source' => ['title' => 'Test Document']],
        ]);

        $this->getMockClient()->setResponse('search', $searchResponse);

        $result = $this->query->firstWhere('status', 'published');

        $this->assertInstanceOf(Model::class, $result);
        $this->assertEquals('Test Document', $result->title);

        // Verify the query was built correctly
        $this->assertTrue($this->getMockClient()->wasMethodCalled('search'));
        $calls = $this->getMockClient()->getMethodCalls('search');
        $params = $calls[0];

        $this->assertEquals(1, $params['size']); // Should limit to 1 result
    }

    /**
     * Test query cloning preserves state
     */
    public function testQueryCloningPreservesState(): void
    {
        $originalQuery = $this->query
            ->where('status', 'published')
            ->orderBy('created_at')
            ->size(20);

        $clonedQuery = clone $originalQuery;
        $clonedQuery->where('category', 'tech');

        $originalResult = $originalQuery->toArray();
        $clonedResult = $clonedQuery->toArray();

        // Original should not have category filter
        $originalFilters = $originalResult['body']['query']['bool']['filter'];
        $this->assertNotContains(['term' => ['category' => 'tech']], $originalFilters);

        // Cloned should have both filters
        $clonedFilters = $clonedResult['body']['query']['bool']['filter'];
        $this->assertContains(['term' => ['status' => 'published']], $clonedFilters);
        $this->assertContains(['term' => ['category' => 'tech']], $clonedFilters);
    }

    /**
     * Helper method to access private properties for testing
     */
    private function getPrivateProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        return $property->getValue($object);
    }
}
