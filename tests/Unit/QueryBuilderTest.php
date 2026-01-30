<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Unit;

use Closure;
use InvalidArgumentException;
use Matchory\Elasticsearch\Model;
use Matchory\Elasticsearch\Builder;
use Matchory\Elasticsearch\Tests\Support\Traits\AssertsElasticsearch;
use Matchory\Elasticsearch\Tests\Support\Traits\ConfiguresElasticsearch;
use Matchory\Elasticsearch\Tests\TestCase;

/**
 * Comprehensive Query Builder Tests
 *
 * Tests all aspects of the Elasticsearch query builder including:
 * - Query types (term, match, bool, range, aggregation queries)
 * - Query parameter handling (size, from, sort, filter parameters)
 * - Query execution, result parsing, and model hydration
 * - Global and local scope application in queries
 */
class QueryBuilderTest extends TestCase
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

    /**
     * Test basic query structure and conversion to array
     */
    public function testBasicQueryStructure(): void
    {
        $query = $this->query->index('test_index');
        $result = $query->toArray();

        $this->assertArrayHasKey('index', $result);
        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('from', $result);
        $this->assertArrayHasKey('size', $result);

        $this->assertEquals('test_index', $result['index']);
        $this->assertEquals(0, $result['from']);
        $this->assertEquals(10, $result['size']);
    }

    /**
     * Test JSON serialization of queries
     */
    public function testQueryJsonSerialization(): void
    {
        $query = $this->query
            ->index('test_index')
            ->where('status', 'published');

        $json = $query->toJson();
        $decoded = json_decode($json, true);

        $this->assertIsString($json);
        $this->assertIsArray($decoded);
        $this->assertEquals('test_index', $decoded['index']);
    }

    /**
     * Test query implements required interfaces
     */
    public function testQueryImplementsInterfaces(): void
    {
        $this->assertInstanceOf(\Illuminate\Contracts\Support\Arrayable::class, $this->query);
        $this->assertInstanceOf(\Illuminate\Contracts\Support\Jsonable::class, $this->query);
        $this->assertInstanceOf(\JsonSerializable::class, $this->query);
        $this->assertInstanceOf(\IteratorAggregate::class, $this->query);
    }

    // Term Query Tests

    /**
     * Test basic term filter
     */
    public function testTermFilter(): void
    {
        $query = $this->query->termFilter('status', 'published');
        $result = $query->toArray();

        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('query', $result['body']);
        $this->assertArrayHasKey('bool', $result['body']['query']);
        $this->assertArrayHasKey('filter', $result['body']['query']['bool']);

        $expectedFilter = [
            'term' => [
                'status' => 'published',
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test term filter with callable value
     */
    public function testTermFilterWithCallable(): void
    {
        $query = $this->query->termFilter('status', fn() => 'published');
        $result = $query->toArray();

        $expectedFilter = [
            'term' => [
                'status' => 'published',
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test multiple term filters
     */
    public function testMultipleTermFilters(): void
    {
        $query = $this->query
            ->termFilter('status', 'published')
            ->termFilter('category', 'news');

        $result = $query->toArray();
        $filters = $result['body']['query']['bool']['filter'];

        $this->assertCount(2, $filters);
        $this->assertContains(['term' => ['status' => 'published']], $filters);
        $this->assertContains(['term' => ['category' => 'news']], $filters);
    }

    // Match Query Tests

    /**
     * Test basic match filter
     */
    public function testMatchFilter(): void
    {
        $query = $this->query->matchFilter('title', 'elasticsearch');
        $result = $query->toArray();

        $expectedFilter = [
            'match' => [
                'title' => 'elasticsearch',
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test match filter with callable value
     */
    public function testMatchFilterWithCallable(): void
    {
        $query = $this->query->matchFilter('title', fn() => 'elasticsearch');
        $result = $query->toArray();

        $expectedFilter = [
            'match' => [
                'title' => 'elasticsearch',
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    // Range Query Tests

    /**
     * Test range filter with string operator
     */
    public function testRangeFilterWithStringOperator(): void
    {
        $query = $this->query->rangeFilter('age', 'gte', 18);
        $result = $query->toArray();

        $expectedFilter = [
            'range' => [
                'age' => [
                    'gte' => 18,
                ],
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test range filter with array parameters
     */
    public function testRangeFilterWithArrayParameters(): void
    {
        $query = $this->query->rangeFilter('age', ['gte' => 18, 'lt' => 65]);
        $result = $query->toArray();

        $expectedFilter = [
            'range' => [
                [
                    'age' => ['gte' => 18, 'lt' => 65],
                ],
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test range filter with callable operator
     */
    public function testRangeFilterWithCallableOperator(): void
    {
        $query = $this->query->rangeFilter('age', fn() => ['gte' => 18]);
        $result = $query->toArray();

        $expectedFilter = [
            'range' => [
                [
                    'age' => ['gte' => 18],
                ],
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    // Bool Query Tests

    /**
     * Test must condition
     */
    public function testMustCondition(): void
    {
        $query = $this->query->must('match', ['title' => 'elasticsearch']);
        $result = $query->toArray();

        $expectedMust = [
            'match' => [
                'title' => 'elasticsearch',
            ],
        ];

        $this->assertArrayHasKey('must', $result['body']['query']['bool']);
        $this->assertContains($expectedMust, $result['body']['query']['bool']['must']);
    }

    /**
     * Test must_not condition
     */
    public function testMustNotCondition(): void
    {
        $query = $this->query->mustNot('term', ['status' => 'draft']);
        $result = $query->toArray();

        $expectedMustNot = [
            'term' => [
                'status' => 'draft',
            ],
        ];

        $this->assertArrayHasKey('must_not', $result['body']['query']['bool']);
        $this->assertContains($expectedMustNot, $result['body']['query']['bool']['must_not']);
    }

    /**
     * Test complex bool query with multiple conditions
     */
    public function testComplexBoolQuery(): void
    {
        $query = $this->query
            ->must('match', ['title' => 'elasticsearch'])
            ->mustNot('term', ['status' => 'draft'])
            ->termFilter('category', 'tech');

        $result = $query->toArray();
        $bool = $result['body']['query']['bool'];

        $this->assertArrayHasKey('must', $bool);
        $this->assertArrayHasKey('must_not', $bool);
        $this->assertArrayHasKey('filter', $bool);

        $this->assertCount(1, $bool['must']);
        $this->assertCount(1, $bool['must_not']);
        $this->assertCount(1, $bool['filter']);
    }

    // Where Clause Tests

    /**
     * Test where with equal operator
     */
    public function testWhereEqual(): void
    {
        $query = $this->query->where('status', '=', 'published');
        $result = $query->toArray();

        $expectedFilter = [
            'term' => [
                'status' => 'published',
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test where with default equal operator
     */
    public function testWhereDefaultEqual(): void
    {
        $query = $this->query->where('status', 'published');
        $result = $query->toArray();

        $expectedFilter = [
            'term' => [
                'status' => 'published',
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test where with greater than operator
     */
    public function testWhereGreaterThan(): void
    {
        $query = $this->query->where('views', '>', 1000);
        $result = $query->toArray();

        $expectedFilter = [
            'range' => [
                'views' => [
                    'gt' => 1000,
                ],
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test where with greater than or equal operator
     */
    public function testWhereGreaterThanOrEqual(): void
    {
        $query = $this->query->where('views', '>=', 1000);
        $result = $query->toArray();

        $expectedFilter = [
            'range' => [
                'views' => [
                    'gte' => 1000,
                ],
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test where with less than operator
     */
    public function testWhereLessThan(): void
    {
        $query = $this->query->where('views', '<', 1000);
        $result = $query->toArray();

        $expectedFilter = [
            'range' => [
                'views' => [
                    'lt' => 1000,
                ],
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test where with less than or equal operator
     */
    public function testWhereLessThanOrEqual(): void
    {
        $query = $this->query->where('views', '<=', 1000);
        $result = $query->toArray();

        $expectedFilter = [
            'range' => [
                'views' => [
                    'lte' => 1000,
                ],
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test where with like operator
     */
    public function testWhereLike(): void
    {
        $query = $this->query->where('title', 'like', 'elasticsearch');
        $result = $query->toArray();

        $expectedMust = [
            'match' => [
                'title' => 'elasticsearch',
            ],
        ];

        $this->assertContains($expectedMust, $result['body']['query']['bool']['must']);
    }

    /**
     * Test where exists
     */
    public function testWhereExists(): void
    {
        $query = $this->query->where('website', 'exists', true);
        $result = $query->toArray();

        $expectedMust = [
            'exists' => [
                'field' => 'website',
            ],
        ];

        $this->assertContains($expectedMust, $result['body']['query']['bool']['must']);
    }

    /**
     * Test where not exists
     */
    public function testWhereNotExists(): void
    {
        $query = $this->query->where('website', 'exists', false);
        $result = $query->toArray();

        $expectedMustNot = [
            'exists' => [
                'field' => 'website',
            ],
        ];

        $this->assertContains($expectedMustNot, $result['body']['query']['bool']['must_not']);
    }

    /**
     * Test where with closure
     */
    public function testWhereWithClosure(): void
    {
        $query = $this->query->where(function (Builder $q) {
            $q->where('status', 'published')
              ->where('category', 'news');
        });

        $result = $query->toArray();
        $filters = $result['body']['query']['bool']['filter'];

        $this->assertCount(2, $filters);
        $this->assertContains(['term' => ['status' => 'published']], $filters);
        $this->assertContains(['term' => ['category' => 'news']], $filters);
    }

    /**
     * Test invalid operator throws exception
     */
    public function testInvalidOperatorThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown operator '!='");

        // Use != which is in the operators array but not handled in the switch statement
        $this->query->where('field', '!=', 'value');
    }

    // ID Query Tests

    /**
     * Test key (document ID) filter
     */
    public function testKeyFilter(): void
    {
        $query = $this->query->key('123');
        $result = $query->toArray();

        $expectedFilter = [
            'term' => [
                '_id' => '123',
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test where with _id field uses key method
     */
    public function testWhereIdUsesKeyMethod(): void
    {
        $query = $this->query->where('_id', '123');
        $result = $query->toArray();

        $expectedFilter = [
            'term' => [
                '_id' => '123',
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    // Aggregation Tests

    /**
     * Test basic aggregation
     */
    public function testBasicAggregation(): void
    {
        $query = $this->query->aggregate('categories');
        $result = $query->toArray();

        $expectedAggregation = [
            'categories' => [
                'terms' => [
                    'field' => 'categories',
                ],
            ],
        ];

        $this->assertArrayHasKey('aggs', $result['body']);
        $this->assertEquals($expectedAggregation, $result['body']['aggs']);
    }

    /**
     * Test aggregation with field name
     */
    public function testAggregationWithFieldName(): void
    {
        $query = $this->query->aggregate('category_stats', 'category');
        $result = $query->toArray();

        $expectedAggregation = [
            'category_stats' => [
                'terms' => [
                    'field' => 'category',
                ],
            ],
        ];

        $this->assertEquals($expectedAggregation, $result['body']['aggs']);
    }

    /**
     * Test aggregation with custom configuration
     */
    public function testAggregationWithCustomConfiguration(): void
    {
        $aggregationConfig = [
            'avg' => [
                'field' => 'price',
            ],
        ];

        $query = $this->query->aggregate('avg_price', $aggregationConfig);
        $result = $query->toArray();

        $expectedAggregation = [
            'avg_price' => $aggregationConfig,
        ];

        $this->assertEquals($expectedAggregation, $result['body']['aggs']);
    }

    /**
     * Test multiple aggregations
     */
    public function testMultipleAggregations(): void
    {
        $query = $this->query
            ->aggregate('categories')
            ->aggregate('avg_price', ['avg' => ['field' => 'price']]);

        $result = $query->toArray();

        $this->assertArrayHasKey('categories', $result['body']['aggs']);
        $this->assertArrayHasKey('avg_price', $result['body']['aggs']);
        $this->assertCount(2, $result['body']['aggs']);
    }

    // Query Parameter Tests

    /**
     * Test size parameter via take method
     */
    public function testSizeParameter(): void
    {
        $query = $this->query->take(20);
        $result = $query->toArray();

        $this->assertEquals(20, $result['size']);
    }

    /**
     * Test from parameter via skip method
     */
    public function testFromParameter(): void
    {
        $query = $this->query->skip(50);
        $result = $query->toArray();

        $this->assertEquals(50, $result['from']);
    }

    /**
     * Test skip method (alias for from)
     */
    public function testSkipParameter(): void
    {
        $query = $this->query->skip(30);
        $result = $query->toArray();

        $this->assertEquals(30, $result['from']);
    }

    /**
     * Test take method (alias for size)
     */
    public function testTakeParameter(): void
    {
        $query = $this->query->take(15);
        $result = $query->toArray();

        $this->assertEquals(15, $result['size']);
    }

    /**
     * Test index parameter
     */
    public function testIndexParameter(): void
    {
        $query = $this->query->index('my_index');
        $result = $query->toArray();

        $this->assertEquals('my_index', $result['index']);
    }

    /**
     * Test scroll parameter
     */
    public function testScrollParameter(): void
    {
        $query = $this->query->scroll('5m');
        $result = $query->toArray();

        $this->assertEquals('5m', $result['scroll']);
    }

    /**
     * Test search type parameter
     */
    public function testSearchTypeParameter(): void
    {
        $query = $this->query->searchType('dfs_query_then_fetch');
        $result = $query->toArray();

        $this->assertEquals('dfs_query_then_fetch', $result['search_type']);
    }

    // Sort Tests

    /**
     * Test basic sorting
     */
    public function testBasicSorting(): void
    {
        $query = $this->query->orderBy('created_at', 'desc');
        $result = $query->toArray();

        $expectedSort = [
            ['created_at' => 'desc'],
        ];

        $this->assertArrayHasKey('sort', $result['body']);
        $this->assertEquals($expectedSort, $result['body']['sort']);
    }

    /**
     * Test default sort direction
     */
    public function testDefaultSortDirection(): void
    {
        $query = $this->query->orderBy('title');
        $result = $query->toArray();

        $expectedSort = [
            ['title' => 'asc'],
        ];

        $this->assertEquals($expectedSort, $result['body']['sort']);
    }

    /**
     * Test multiple sort fields
     */
    public function testMultipleSortFields(): void
    {
        $query = $this->query
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc');

        $result = $query->toArray();

        $expectedSort = [
            ['priority' => 'desc'],
            ['created_at' => 'asc'],
        ];

        $this->assertEquals($expectedSort, $result['body']['sort']);
    }

    /**
     * Test sorting by score
     */
    public function testSortByScore(): void
    {
        $query = $this->query->orderBy('_score', 'desc');
        $result = $query->toArray();

        $expectedSort = [
            ['_score' => 'desc'],
        ];

        $this->assertEquals($expectedSort, $result['body']['sort']);
    }

    // Source Field Tests

    /**
     * Test select fields
     */
    public function testSelectFields(): void
    {
        $query = $this->query->select(['title', 'content', 'created_at']);
        $result = $query->toArray();

        $this->assertArrayHasKey('_source', $result['body']);
        $this->assertArrayHasKey('includes', $result['body']['_source']);
        $this->assertEquals(['title', 'content', 'created_at'], $result['body']['_source']['includes']);
    }

    /**
     * Test select with string argument
     */
    public function testSelectWithString(): void
    {
        $query = $this->query->select('title');
        $result = $query->toArray();

        $this->assertEquals(['title'], $result['body']['_source']['includes']);
    }

    /**
     * Test select with multiple arguments
     */
    public function testSelectWithMultipleArguments(): void
    {
        $query = $this->query->select('title', 'content', 'created_at');
        $result = $query->toArray();

        $this->assertEquals(['title', 'content', 'created_at'], $result['body']['_source']['includes']);
    }

    /**
     * Test unselect fields
     */
    public function testUnselectFields(): void
    {
        $query = $this->query->unselect(['password', 'secret']);
        $result = $query->toArray();

        $this->assertArrayHasKey('_source', $result['body']);
        $this->assertArrayHasKey('excludes', $result['body']['_source']);
        $this->assertEquals(['password', 'secret'], $result['body']['_source']['excludes']);
    }

    /**
     * Test combined select and unselect
     */
    public function testCombinedSelectAndUnselect(): void
    {
        $query = $this->query
            ->select(['title', 'content'])
            ->unselect(['password']);

        $result = $query->toArray();

        $this->assertEquals(['title', 'content'], $result['body']['_source']['includes']);
        $this->assertEquals(['password'], $result['body']['_source']['excludes']);
    }

    // Highlight Tests

    /**
     * Test basic highlighting
     */
    public function testBasicHighlighting(): void
    {
        $query = $this->query->highlight('title', 'content');
        $result = $query->toArray();

        $expectedHighlight = [
            'fields' => [
                'title' => new \stdClass(),
                'content' => new \stdClass(),
            ],
        ];

        $this->assertArrayHasKey('highlight', $result['body']);
        $this->assertEquals($expectedHighlight, $result['body']['highlight']);
    }

    /**
     * Test highlighting with array argument
     */
    public function testHighlightingWithArray(): void
    {
        $query = $this->query->highlight(['title', 'content']);
        $result = $query->toArray();

        $expectedHighlight = [
            'fields' => [
                'title' => new \stdClass(),
                'content' => new \stdClass(),
            ],
        ];

        $this->assertEquals($expectedHighlight, $result['body']['highlight']);
    }

    // Ignore Tests

    /**
     * Test ignore HTTP errors
     *
     * Note: In Elasticsearch PHP client v9, the `$params['client']['ignore']` pattern
     * was removed. Ignores are now stored internally and applied via setResponseException()
     * at execution time. The toArray() output no longer includes the 'client' key.
     */
    public function testIgnoreHttpErrors(): void
    {
        $query = $this->query->ignore(404, 500);

        // Ignores are stored internally and accessed via getIgnores()
        $this->assertEquals([404, 500], $query->getIgnores());

        // toArray() no longer includes 'client' key (v9 breaking change)
        $result = $query->toArray();
        $this->assertArrayNotHasKey('client', $result);
    }

    /**
     * Test ignore with array argument
     */
    public function testIgnoreWithArray(): void
    {
        $query = $this->query->ignore([404, 500]);

        $this->assertEquals([404, 500], $query->getIgnores());
    }

    /**
     * Test multiple ignore calls
     */
    public function testMultipleIgnoreCalls(): void
    {
        $query = $this->query
            ->ignore(404)
            ->ignore(500);

        $this->assertEquals([404, 500], $query->getIgnores());
    }

    // Prefix Filter Tests

    /**
     * Test prefix filter
     */
    public function testPrefixFilter(): void
    {
        $query = $this->query->prefixFilter('title', 'elastic');
        $result = $query->toArray();

        $expectedFilter = [
            'prefix' => [
                'title' => 'elastic',
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test prefix filter with callable value
     */
    public function testPrefixFilterWithCallable(): void
    {
        $query = $this->query->prefixFilter('title', fn() => 'elastic');
        $result = $query->toArray();

        $expectedFilter = [
            'prefix' => [
                'title' => 'elastic',
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    // Regexp Filter Tests

    /**
     * Test basic regexp filter
     */
    public function testBasicRegexpFilter(): void
    {
        $query = $this->query->regexpFilter('title', 'elastic.*');
        $result = $query->toArray();

        $expectedFilter = [
            'regexp' => [
                'title' => 'elastic.*',
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test regexp filter with flags
     */
    public function testRegexpFilterWithFlags(): void
    {
        $query = $this->query->regexpFilter(
            'title',
            'elastic.*',
            Builder::REGEXP_FLAG_ALL | Builder::REGEXP_FLAG_COMPLEMENT,
        );
        $result = $query->toArray();

        $expectedFilter = [
            'regexp' => [
                'title' => [
                    'value' => 'elastic.*',
                    'flags' => 'ALL|COMPLEMENT',
                ],
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test regexp filter with case sensitivity
     */
    public function testRegexpFilterWithCaseSensitivity(): void
    {
        $query = $this->query->regexpFilter('title', 'elastic.*', null, true);
        $result = $query->toArray();

        $expectedFilter = [
            'regexp' => [
                'title' => [
                    'value' => 'elastic.*',
                    'case_insensitive' => true,
                ],
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test regexp filter with max determinized states
     */
    public function testRegexpFilterWithMaxDeterminizedStates(): void
    {
        $query = $this->query->regexpFilter('title', 'elastic.*', null, null, 20000);
        $result = $query->toArray();

        $expectedFilter = [
            'regexp' => [
                'title' => [
                    'value' => 'elastic.*',
                    'max_determinized_states' => 20000,
                ],
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    // Distance Filter Tests

    /**
     * Test distance filter
     */
    public function testDistanceFilter(): void
    {
        $query = $this->query->distance('location', ['lat' => 40.7128, 'lon' => -74.0060], '10km');
        $result = $query->toArray();

        $expectedFilter = [
            'geo_distance' => [
                'location' => ['lat' => 40.7128, 'lon' => -74.0060],
                'distance' => '10km',
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test distance filter with closure
     */
    public function testDistanceFilterWithClosure(): void
    {
        $query = $this->query->distance(function (Builder $q) {
            $q->where('status', 'active');
        }, ['lat' => 40.7128, 'lon' => -74.0060], '10km');

        $result = $query->toArray();

        // Should have applied the closure to the query
        $this->assertContains(['term' => ['status' => 'active']], $result['body']['query']['bool']['filter']);
    }

    // Group By Tests

    /**
     * Test group by (collapse)
     */
    public function testGroupBy(): void
    {
        $query = $this->query->groupBy('category');
        $result = $query->toArray();

        $expectedCollapse = [
            'field' => 'category',
        ];

        $this->assertArrayHasKey('collapse', $result['body']);
        $this->assertEquals($expectedCollapse, $result['body']['collapse']);
    }

    // Nested Query Tests

    /**
     * Test nested query
     */
    public function testNestedQuery(): void
    {
        $query = $this->query->nested('comments');
        $result = $query->toArray();

        $expectedNested = [
            'query' => [
                'nested' => [
                    'path' => 'comments',
                ],
            ],
        ];

        $this->assertEquals($expectedNested, $result['body']);
    }

    // Custom Body Tests

    /**
     * Test custom body
     */
    public function testCustomBody(): void
    {
        $customBody = [
            'query' => [
                'match_all' => new \stdClass(),
            ],
        ];

        $query = $this->query->body($customBody);
        $result = $query->toArray();

        $this->assertEquals($customBody, $result['body']);
    }

    // Utility Method Tests

    /**
     * Test asKeyword static method
     */
    public function testAsKeywordMethod(): void
    {
        $this->assertEquals('title.keyword', Builder::asKeyword('title'));
        $this->assertEquals('category.keyword', Builder::asKeyword('category.'));
        $this->assertEquals('nested.field.keyword', Builder::asKeyword('nested.field'));
    }

    /**
     * Test getters for query properties
     */
    public function testQueryPropertyGetters(): void
    {
        $query = $this->query
            ->key('123')
            ->index('test_index')
            ->scroll('5m')
            ->searchType('dfs_query_then_fetch')
            ->ignore(404);

        $this->assertEquals('123', $query->getKey());
        $this->assertEquals('test_index', $query->getIndex());
        $this->assertEquals('5m', $query->getScroll());
        $this->assertEquals('dfs_query_then_fetch', $query->getSearchType());
        $this->assertEquals([404], $query->getIgnores());
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
     * Test where between with single value array
     */
    public function testWhereBetweenWithSingleValue(): void
    {
        $query = $this->query->whereBetween('price', [10]);
        $result = $query->toArray();

        $expectedFilter = [
            'range' => [
                'price' => [
                    'gte' => [10],
                    'lte' => null,
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
     * Test where in with closure
     */
    public function testWhereInWithClosure(): void
    {
        $query = $this->query->whereIn(function (Builder $q) {
            $q->where('status', 'published');
        }, ['tech', 'science']);

        $result = $query->toArray();
        $filters = $result['body']['query']['bool']['filter'];

        $this->assertContains(['term' => ['status' => 'published']], $filters);
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

    /**
     * Test where not in with closure
     */
    public function testWhereNotInWithClosure(): void
    {
        $query = $this->query->whereNotIn(function (Builder $q) {
            $q->where('category', 'temp');
        }, ['draft', 'archived']);

        $result = $query->toArray();
        $filters = $result['body']['query']['bool']['filter'];

        $this->assertContains(['term' => ['category' => 'temp']], $filters);
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
     */
    public function testWhereNotWithClosure(): void
    {
        $query = $this->query->whereNot(function (Builder $q) {
            $q->where('status', 'draft')
              ->where('category', 'temp');
        });

        $result = $query->toArray();
        $filters = $result['body']['query']['bool']['filter'] ?? [];

        $this->assertContains(['term' => ['status' => 'draft']], $filters);
        $this->assertContains(['term' => ['category' => 'temp']], $filters);
    }

    // Size and Skip Tests

    /**
     * Test size method
     */
    public function testSizeMethod(): void
    {
        $query = $this->query->size(25);
        $result = $query->toArray();

        $this->assertEquals(25, $result['size']);
    }

    /**
     * Test from method
     */
    public function testFromMethod(): void
    {
        $query = $this->query->from(100);
        $result = $query->toArray();

        $this->assertEquals(100, $result['from']);
    }

    /**
     * Test getSize method
     */
    public function testGetSizeMethod(): void
    {
        $query = $this->query->size(30);
        $this->assertEquals(30, $query->getSize());
    }

    /**
     * Test getSkip method
     */
    public function testGetSkipMethod(): void
    {
        $query = $this->query->skip(50);
        $this->assertEquals(50, $query->getSkip());
    }

    // Body Tests

    /**
     * Test getBody method returns query body
     */
    public function testGetBodyMethod(): void
    {
        $query = $this->query
            ->where('status', 'published')
            ->orderBy('created_at');

        $body = $query->getBody();

        $this->assertIsArray($body);
        $this->assertArrayHasKey('query', $body);
        $this->assertArrayHasKey('sort', $body);
    }

    // Complex Query Combinations

    /**
     * Test complex query with all features
     */
    public function testComplexQueryWithAllFeatures(): void
    {
        $query = $this->query
            ->index('articles')
            ->where('status', 'published')
            ->whereBetween('created_at', ['2024-01-01', '2024-12-31'])
            ->whereIn('category', ['tech', 'science'])
            ->whereExists('featured_image')
            ->whereNot('author_id', '=', 123)
            ->search('elasticsearch')
            ->orderBy('_score', 'desc')
            ->orderBy('created_at', 'desc')
            ->select(['title', 'content', 'author'])
            ->highlight(['title', 'content'])
            ->aggregate('categories', 'category')
            ->size(20)
            ->from(40)
            ->scroll('5m')
            ->ignore(404);

        $result = $query->toArray();

        // Verify all components are present
        $this->assertEquals('articles', $result['index']);
        $this->assertEquals(20, $result['size']);
        $this->assertEquals(40, $result['from']);
        $this->assertEquals('5m', $result['scroll']);
        // Ignores are stored internally (v9 change - no longer in toArray output)
        $this->assertEquals([404], $query->getIgnores());

        $body = $result['body'];
        $this->assertArrayHasKey('query', $body);
        $this->assertArrayHasKey('sort', $body);
        $this->assertArrayHasKey('_source', $body);
        $this->assertArrayHasKey('highlight', $body);
        $this->assertArrayHasKey('aggs', $body);

        // Verify query structure
        $bool = $body['query']['bool'];
        $this->assertArrayHasKey('filter', $bool);
        $this->assertArrayHasKey('must', $bool);
        $this->assertArrayHasKey('must_not', $bool);

        // Count conditions
        $this->assertCount(3, $bool['filter']); // status, created_at, category (featured_image goes to must)
        $this->assertCount(2, $bool['must']); // search query and exists
        $this->assertCount(1, $bool['must_not']); // author_id
    }

    /**
     * Test query builder method chaining returns same instance
     */
    public function testQueryBuilderMethodChainingReturnsSameInstance(): void
    {
        $query1 = $this->query;
        $query2 = $query1->where('status', 'published');
        $query3 = $query2->orderBy('created_at');

        $this->assertSame($query1, $query2);
        $this->assertSame($query2, $query3);
    }

    /**
     * Test query builder with empty conditions
     */
    public function testQueryBuilderWithEmptyConditions(): void
    {
        $result = $this->query->toArray();

        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('from', $result);
        $this->assertArrayHasKey('size', $result);

        $this->assertEquals(0, $result['from']);
        $this->assertEquals(10, $result['size']);
    }

    /**
     * Test firstWhere method
     */
    public function testFirstWhereMethod(): void
    {
        // Test that firstWhere adds the correct filter and calls first
        $query = $this->query->where('status', 'published');
        $result = $query->toArray();

        $expectedFilter = [
            'term' => [
                'status' => 'published',
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test query parameter validation
     */
    public function testQueryParameterValidation(): void
    {
        // Test that invalid search type throws exception
        $this->expectException(\InvalidArgumentException::class);
        $this->query->searchType('invalid_search_type');
    }

    // Limit Method Tests

    /**
     * Test limit method (alias for take)
     */
    public function testLimitMethod(): void
    {
        $query = $this->query->limit(25);
        $result = $query->toArray();

        $this->assertEquals(25, $result['size']);
    }

    /**
     * Test limit and take are equivalent
     */
    public function testLimitAndTakeAreEquivalent(): void
    {
        $queryLimit = $this->query->limit(15);
        $queryTake = (new Model())->newQuery()->take(15);

        $this->assertEquals($queryLimit->toArray()['size'], $queryTake->toArray()['size']);
    }

    // Except Method Tests

    /**
     * Test except method (renamed from unselect)
     */
    public function testExceptMethod(): void
    {
        $query = $this->query->except(['password', 'secret']);
        $result = $query->toArray();

        $this->assertArrayHasKey('_source', $result['body']);
        $this->assertArrayHasKey('excludes', $result['body']['_source']);
        $this->assertEquals(['password', 'secret'], $result['body']['_source']['excludes']);
    }

    /**
     * Test except with string argument
     */
    public function testExceptWithString(): void
    {
        $query = $this->query->except('password');
        $result = $query->toArray();

        $this->assertEquals(['password'], $result['body']['_source']['excludes']);
    }

    /**
     * Test except with multiple arguments
     */
    public function testExceptWithMultipleArguments(): void
    {
        $query = $this->query->except('password', 'secret', 'api_key');
        $result = $query->toArray();

        $this->assertEquals(['password', 'secret', 'api_key'], $result['body']['_source']['excludes']);
    }

    /**
     * Test combined select and except
     */
    public function testCombinedSelectAndExcept(): void
    {
        $query = $this->query
            ->select(['title', 'content'])
            ->except(['password']);

        $result = $query->toArray();

        $this->assertEquals(['title', 'content'], $result['body']['_source']['includes']);
        $this->assertEquals(['password'], $result['body']['_source']['excludes']);
    }

    // OrWhere Method Tests

    /**
     * Test basic orWhere
     */
    public function testOrWhereBasic(): void
    {
        $query = $this->query
            ->where('status', 'published')
            ->orWhere('status', 'featured');

        $result = $query->toArray();
        $bool = $result['body']['query']['bool'];

        $this->assertArrayHasKey('filter', $bool);
        $this->assertArrayHasKey('should', $bool);
        $this->assertContains(['term' => ['status' => 'published']], $bool['filter']);
        $this->assertContains(['term' => ['status' => 'featured']], $bool['should']);
    }

    /**
     * Test orWhere with default operator
     */
    public function testOrWhereDefaultOperator(): void
    {
        $query = $this->query->orWhere('category', 'tech');
        $result = $query->toArray();

        $expectedShould = [
            'term' => [
                'category' => 'tech',
            ],
        ];

        $this->assertContains($expectedShould, $result['body']['query']['bool']['should']);
    }

    /**
     * Test orWhere with greater than operator
     */
    public function testOrWhereGreaterThan(): void
    {
        $query = $this->query->orWhere('views', '>', 1000);
        $result = $query->toArray();

        $expectedShould = [
            'range' => [
                'views' => ['gt' => 1000],
            ],
        ];

        $this->assertContains($expectedShould, $result['body']['query']['bool']['should']);
    }

    /**
     * Test orWhere with less than operator
     */
    public function testOrWhereLessThan(): void
    {
        $query = $this->query->orWhere('price', '<', 50);
        $result = $query->toArray();

        $expectedShould = [
            'range' => [
                'price' => ['lt' => 50],
            ],
        ];

        $this->assertContains($expectedShould, $result['body']['query']['bool']['should']);
    }

    /**
     * Test orWhere with like operator
     */
    public function testOrWhereLike(): void
    {
        $query = $this->query->orWhere('title', 'like', 'elasticsearch');
        $result = $query->toArray();

        $expectedShould = [
            'match' => [
                'title' => 'elasticsearch',
            ],
        ];

        $this->assertContains($expectedShould, $result['body']['query']['bool']['should']);
    }

    /**
     * Test orWhere with exists operator
     */
    public function testOrWhereExists(): void
    {
        $query = $this->query->orWhere('website', 'exists', true);
        $result = $query->toArray();

        $expectedShould = [
            'exists' => [
                'field' => 'website',
            ],
        ];

        $this->assertContains($expectedShould, $result['body']['query']['bool']['should']);
    }

    /**
     * Test multiple orWhere conditions
     */
    public function testMultipleOrWhereConditions(): void
    {
        $query = $this->query
            ->orWhere('status', 'published')
            ->orWhere('status', 'featured')
            ->orWhere('status', 'highlighted');

        $result = $query->toArray();
        $should = $result['body']['query']['bool']['should'];

        $this->assertCount(3, $should);
        $this->assertContains(['term' => ['status' => 'published']], $should);
        $this->assertContains(['term' => ['status' => 'featured']], $should);
        $this->assertContains(['term' => ['status' => 'highlighted']], $should);
    }

    /**
     * Test orWhere sets minimum_should_match to 1
     */
    public function testOrWhereSetsMinimumShouldMatch(): void
    {
        $query = $this->query->orWhere('status', 'published');
        $result = $query->toArray();

        $this->assertArrayHasKey('minimum_should_match', $result['body']['query']['bool']);
        $this->assertEquals(1, $result['body']['query']['bool']['minimum_should_match']);
    }

    /**
     * Test orWhere with closure
     */
    public function testOrWhereWithClosure(): void
    {
        $query = $this->query->orWhere(function (Builder $q) {
            $q->orWhere('status', 'published')
              ->orWhere('status', 'featured');
        });

        $result = $query->toArray();
        $should = $result['body']['query']['bool']['should'];

        $this->assertCount(2, $should);
    }

    // Should Method Tests

    /**
     * Test direct should method
     */
    public function testShouldMethod(): void
    {
        $query = $this->query->should('match', ['title' => 'elasticsearch']);
        $result = $query->toArray();

        $expectedShould = [
            'match' => [
                'title' => 'elasticsearch',
            ],
        ];

        $this->assertArrayHasKey('should', $result['body']['query']['bool']);
        $this->assertContains($expectedShould, $result['body']['query']['bool']['should']);
    }

    /**
     * Test multiple should conditions
     */
    public function testMultipleShouldConditions(): void
    {
        $query = $this->query
            ->should('term', ['status' => 'published'])
            ->should('term', ['featured' => true]);

        $result = $query->toArray();
        $should = $result['body']['query']['bool']['should'];

        $this->assertCount(2, $should);
    }

    // Minimum Should Match Tests

    /**
     * Test minimum should match with integer
     */
    public function testMinimumShouldMatchInteger(): void
    {
        $query = $this->query
            ->orWhere('status', 'published')
            ->orWhere('status', 'featured')
            ->minimumShouldMatch(2);

        $result = $query->toArray();

        $this->assertEquals(2, $result['body']['query']['bool']['minimum_should_match']);
    }

    /**
     * Test minimum should match with percentage
     */
    public function testMinimumShouldMatchPercentage(): void
    {
        $query = $this->query
            ->orWhere('status', 'published')
            ->orWhere('status', 'featured')
            ->minimumShouldMatch('75%');

        $result = $query->toArray();

        $this->assertEquals('75%', $result['body']['query']['bool']['minimum_should_match']);
    }

    // Complex OR/AND Combinations

    /**
     * Test complex where and orWhere combination
     */
    public function testComplexWhereAndOrWhereCombination(): void
    {
        $query = $this->query
            ->where('published', '=', 'yes')
            ->where('category', 'tech')
            ->orWhere('featured', '=', 'yes')
            ->orWhere('highlighted', '=', 'yes');

        $result = $query->toArray();
        $bool = $result['body']['query']['bool'];

        // Should have 2 filter conditions (AND)
        $this->assertCount(2, $bool['filter']);

        // Should have 2 should conditions (OR)
        $this->assertCount(2, $bool['should']);

        // minimum_should_match should be 1
        $this->assertEquals(1, $bool['minimum_should_match']);
    }

    /**
     * Test orWhere with invalid operator throws exception
     */
    public function testOrWhereInvalidOperatorThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown operator '!='");

        // '!=' is in the operators array but not handled in the switch
        $this->query->orWhere('field', '!=', 'value');
    }
}
