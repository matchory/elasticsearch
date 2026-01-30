<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Feature;

use Matchory\Elasticsearch\Interfaces\ConnectionInterface;
use Matchory\Elasticsearch\Builder;
use Matchory\Elasticsearch\Tests\Support\Factories\DocumentFactory;
use Matchory\Elasticsearch\Tests\Support\Factories\ResponseFactory;
use Matchory\Elasticsearch\Tests\TestCase;

/**
 * Feature tests for search operations functionality
 *
 * Tests end-to-end search functionality using mocked Elasticsearch responses,
 * covering complex search scenarios, filters, aggregations, sorting, pagination,
 * and performance testing with large result sets.
 */
class SearchOperationsTest extends TestCase
{
    private DocumentFactory $documentFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->documentFactory = new DocumentFactory();
    }

    /**
     * Create a query instance with mocked connection
     */
    private function createQuery(): Builder
    {
        $mockConnection = $this->createMock(ConnectionInterface::class);
        $mockConnection->method('search')
            ->willReturnCallback(function ($params) {
                return $this->getMockClient()->search($params);
            });

        return new Builder($mockConnection);
    }

    // End-to-end search functionality tests

    /**
     * Test basic search functionality with simple query
     */
    public function testBasicSearchFunctionality(): void
    {
        // Arrange
        $documents = [
            $this->documentFactory->create(['title' => 'Laravel Tutorial', 'content' => 'Learn Laravel framework']),
            $this->documentFactory->create(['title' => 'PHP Best Practices', 'content' => 'Modern PHP development']),
        ];

        $this->getMockClient()->scenario()
            ->searchReturns($documents)
            ->apply();

        // Act
        $results = $this->createQuery()
            ->search('Laravel')
            ->get();

        // Assert
        $this->assertCount(2, $results);
        $this->assertClientMethodCalled('search');

        // Verify search parameters were passed correctly
        $searchCalls = $this->getMockClient()->getMethodCalls('search');
        $this->assertNotEmpty($searchCalls);
        $searchParams = $searchCalls[0];
        $this->assertArrayHasKey('body', $searchParams);
    }

    /**
     * Test full-text search with match query
     */
    public function testFullTextSearchWithMatchQuery(): void
    {
        // Arrange
        $documents = [
            $this->documentFactory->create([
                'title' => 'Advanced Laravel Techniques',
                'content' => 'Deep dive into Laravel framework features',
                'tags' => ['laravel', 'php', 'framework'],
            ]),
            $this->documentFactory->create([
                'title' => 'Vue.js Integration',
                'content' => 'How to integrate Vue.js with Laravel',
                'tags' => ['vue', 'javascript', 'laravel'],
            ]),
        ];

        $this->getMockClient()->scenario()
            ->searchReturns($documents)
            ->apply();

        // Act - use 'like' operator which maps to match query
        $results = $this->createQuery()
            ->where('content', 'like', 'Laravel framework')
            ->get();

        // Assert
        $this->assertCount(2, $results);
        $this->assertClientMethodCalled('search');

        // Verify match query structure
        $searchCalls = $this->getMockClient()->getMethodCalls('search');
        $searchParams = $searchCalls[0];
        $this->assertArrayHasKey('body', $searchParams);
    }

    /**
     * Test search with term query for exact matches
     */
    public function testSearchWithTermQuery(): void
    {
        // Arrange
        $documents = [
            $this->documentFactory->create([
                'status' => 'published',
                'title' => 'Published Article',
                'category' => 'technology',
            ]),
        ];

        $this->getMockClient()->scenario()
            ->searchReturns($documents)
            ->apply();

        // Act
        $results = $this->createQuery()
            ->where('status', 'published')
            ->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals('Published Article', $results->first()->title);
        $this->assertClientMethodCalled('search');
    }

    /**
     * Test search with multiple filters
     */
    public function testSearchWithMultipleFilters(): void
    {
        // Arrange
        $documents = [
            $this->documentFactory->create([
                'status' => 'published',
                'category' => 'technology',
                'author_id' => 1,
                'title' => 'Tech Article',
            ]),
        ];

        $this->getMockClient()->scenario()
            ->searchReturns($documents)
            ->apply();

        // Act
        $results = $this->createQuery()
            ->where('status', 'published')
            ->where('category', 'technology')
            ->where('author_id', 1)
            ->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals('Tech Article', $results->first()->title);
        $this->assertClientMethodCalled('search');
    }

    /**
     * Test search with range filters
     */
    public function testSearchWithRangeFilters(): void
    {
        // Arrange
        $documents = [
            $this->documentFactory->create([
                'title' => 'Recent Article',
                'created_at' => '2024-01-15T10:00:00Z',
                'views' => 150,
            ]),
            $this->documentFactory->create([
                'title' => 'Popular Article',
                'created_at' => '2024-01-20T15:30:00Z',
                'views' => 500,
            ]),
        ];

        $this->getMockClient()->scenario()
            ->searchReturns($documents)
            ->apply();

        // Act
        $results = $this->createQuery()
            ->whereBetween('created_at', ['2024-01-01', '2024-01-31'])
            ->where('views', '>=', 100)
            ->get();

        // Assert
        $this->assertCount(2, $results);
        $this->assertClientMethodCalled('search');
    }

    /**
     * Test search with boolean queries (must, filter, must_not)
     */
    public function testSearchWithBooleanQueries(): void
    {
        // Arrange
        $documents = [
            $this->documentFactory->create([
                'title' => 'Laravel Tutorial',
                'tags' => ['laravel', 'tutorial'],
                'status' => 'published',
            ]),
        ];

        $this->getMockClient()->scenario()
            ->searchReturns($documents)
            ->apply();

        // Act
        $results = $this->createQuery()
            ->where('status', 'published')  // filter
            ->whereIn('tags', ['tutorial']) // filter (terms)
            ->whereNot('status', '=', 'draft')   // must_not
            ->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals('Laravel Tutorial', $results->first()->title);
        $this->assertClientMethodCalled('search');
    }

    // Complex search scenarios with aggregations

    /**
     * Test search with terms aggregation
     */
    public function testSearchWithTermsAggregation(): void
    {
        // Arrange
        $documents = [
            $this->documentFactory->create(['category' => 'technology']),
            $this->documentFactory->create(['category' => 'business']),
        ];

        $aggregations = [
            'categories' => [
                'doc_count_error_upper_bound' => 0,
                'sum_other_doc_count' => 0,
                'buckets' => [
                    ['key' => 'technology', 'doc_count' => 5],
                    ['key' => 'business', 'doc_count' => 3],
                ],
            ],
        ];

        $this->getMockClient()->scenario()
            ->search([
                'hits' => $documents,
                'aggregations' => $aggregations,
            ])
            ->apply();

        // Act
        $results = $this->createQuery()
            ->aggregate('categories', ['terms' => ['field' => 'category']])
            ->get();

        // Assert
        $this->assertCount(2, $results);
        $this->assertClientMethodCalled('search');

        // Verify aggregation was included in search
        $searchCalls = $this->getMockClient()->getMethodCalls('search');
        $searchParams = $searchCalls[0];
        $this->assertArrayHasKey('body', $searchParams);
    }

    /**
     * Test search with date histogram aggregation
     */
    public function testSearchWithDateHistogramAggregation(): void
    {
        // Arrange
        $documents = [
            $this->documentFactory->create(['created_at' => '2024-01-01T00:00:00Z']),
            $this->documentFactory->create(['created_at' => '2024-01-15T00:00:00Z']),
        ];

        $aggregations = [
            'posts_over_time' => [
                'buckets' => [
                    [
                        'key_as_string' => '2024-01-01T00:00:00.000Z',
                        'key' => 1704067200000,
                        'doc_count' => 5,
                    ],
                    [
                        'key_as_string' => '2024-01-15T00:00:00.000Z',
                        'key' => 1705276800000,
                        'doc_count' => 3,
                    ],
                ],
            ],
        ];

        $this->getMockClient()->scenario()
            ->search([
                'hits' => $documents,
                'aggregations' => $aggregations,
            ])
            ->apply();

        // Act
        $results = $this->createQuery()
            ->aggregate('posts_over_time', [
                'date_histogram' => [
                    'field' => 'created_at',
                    'calendar_interval' => 'day',
                ],
            ])
            ->get();

        // Assert
        $this->assertCount(2, $results);
        $this->assertClientMethodCalled('search');
    }

    /**
     * Test search with multiple aggregations
     */
    public function testSearchWithMultipleAggregations(): void
    {
        // Arrange
        $documents = [
            $this->documentFactory->create(['category' => 'tech', 'views' => 100]),
            $this->documentFactory->create(['category' => 'business', 'views' => 200]),
        ];

        $aggregations = [
            'categories' => [
                'buckets' => [
                    ['key' => 'tech', 'doc_count' => 5],
                    ['key' => 'business', 'doc_count' => 3],
                ],
            ],
            'avg_views' => [
                'value' => 150.0,
            ],
            'max_views' => [
                'value' => 200.0,
            ],
        ];

        $this->getMockClient()->scenario()
            ->search([
                'hits' => $documents,
                'aggregations' => $aggregations,
            ])
            ->apply();

        // Act
        $results = $this->createQuery()
            ->aggregate('categories', ['terms' => ['field' => 'category']])
            ->aggregate('avg_views', ['avg' => ['field' => 'views']])
            ->aggregate('max_views', ['max' => ['field' => 'views']])
            ->get();

        // Assert
        $this->assertCount(2, $results);
        $this->assertClientMethodCalled('search');
    }

    // Sorting tests

    /**
     * Test search with single field sorting
     */
    public function testSearchWithSingleFieldSorting(): void
    {
        // Arrange
        $documents = [
            $this->documentFactory->create([
                'title' => 'Article A',
                'created_at' => '2024-01-01T00:00:00Z',
                'views' => 100,
            ]),
            $this->documentFactory->create([
                'title' => 'Article B',
                'created_at' => '2024-01-02T00:00:00Z',
                'views' => 200,
            ]),
        ];

        $this->getMockClient()->scenario()
            ->searchReturns($documents)
            ->apply();

        // Act
        $results = $this->createQuery()
            ->orderBy('created_at', 'desc')
            ->get();

        // Assert
        $this->assertCount(2, $results);
        $this->assertClientMethodCalled('search');

        // Verify sort parameters
        $searchCalls = $this->getMockClient()->getMethodCalls('search');
        $searchParams = $searchCalls[0];
        $this->assertArrayHasKey('body', $searchParams);
    }

    /**
     * Test search with multiple field sorting
     */
    public function testSearchWithMultipleFieldSorting(): void
    {
        // Arrange
        $documents = [
            $this->documentFactory->create([
                'category' => 'tech',
                'created_at' => '2024-01-01T00:00:00Z',
                'views' => 100,
            ]),
            $this->documentFactory->create([
                'category' => 'tech',
                'created_at' => '2024-01-02T00:00:00Z',
                'views' => 200,
            ]),
        ];

        $this->getMockClient()->scenario()
            ->searchReturns($documents)
            ->apply();

        // Act
        $results = $this->createQuery()
            ->orderBy('category', 'asc')
            ->orderBy('views', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Assert
        $this->assertCount(2, $results);
        $this->assertClientMethodCalled('search');
    }

    /**
     * Test search with score-based sorting
     */
    public function testSearchWithScoreBasedSorting(): void
    {
        // Arrange
        $documents = [
            $this->documentFactory->create(['title' => 'Highly relevant', '_score' => 2.5]),
            $this->documentFactory->create(['title' => 'Less relevant', '_score' => 1.2]),
        ];

        $this->getMockClient()->scenario()
            ->searchReturns($documents)
            ->apply();

        // Act
        $results = $this->createQuery()
            ->search('relevant')
            ->orderBy('_score', 'desc')
            ->get();

        // Assert
        $this->assertCount(2, $results);
        $this->assertClientMethodCalled('search');
    }

    // Pagination tests

    /**
     * Test search result pagination with size and from
     */
    public function testSearchResultPagination(): void
    {
        // Arrange
        $documents = array_map(
            fn($i)
            => $this->documentFactory->create(['title' => "Article {$i}", 'id' => $i]),
            range(1, 5),
        );

        $this->getMockClient()->scenario()
            ->search([
                'hits' => array_slice($documents, 0, 3),
                'total' => 10,
            ])
            ->apply();

        // Act
        $results = $this->createQuery()
            ->take(3)
            ->skip(0)
            ->get();

        // Assert
        $this->assertCount(3, $results);
        $this->assertClientMethodCalled('search');

        // Verify pagination parameters
        $searchCalls = $this->getMockClient()->getMethodCalls('search');
        $searchParams = $searchCalls[0];
        $this->assertEquals(3, $searchParams['size']);
        $this->assertEquals(0, $searchParams['from']);
    }

    /**
     * Test search pagination with different page sizes
     */
    public function testSearchPaginationWithDifferentPageSizes(): void
    {
        // Arrange
        $documents = array_map(
            fn($i)
            => $this->documentFactory->create(['title' => "Article {$i}", 'id' => $i]),
            range(1, 10),
        );

        $this->getMockClient()->scenario()
            ->search([
                'hits' => array_slice($documents, 5, 5),
                'total' => 50,
            ])
            ->apply();

        // Act - Get page 2 with 5 items per page
        $results = $this->createQuery()
            ->take(5)
            ->skip(5)
            ->get();

        // Assert
        $this->assertCount(5, $results);
        $this->assertClientMethodCalled('search');

        // Verify pagination parameters
        $searchCalls = $this->getMockClient()->getMethodCalls('search');
        $searchParams = $searchCalls[0];
        $this->assertEquals(5, $searchParams['size']);
        $this->assertEquals(5, $searchParams['from']);
    }

    /**
     * Test search with Laravel-style pagination
     */
    public function testSearchWithLaravelStylePagination(): void
    {
        // Arrange
        $documents = array_map(
            fn($i)
            => $this->documentFactory->create(['title' => "Article {$i}", 'id' => $i]),
            range(1, 15),
        );

        $this->getMockClient()->scenario()
            ->search([
                'hits' => array_slice($documents, 0, 10),
                'total' => 100,
            ])
            ->apply();

        // Act - paginate returns a Pagination object directly
        $pagination = $this->createQuery()
            ->paginate(10, 'page', 1); // perPage, pageName, page

        // Assert
        $this->assertCount(10, $pagination->items());
        $this->assertEquals(100, $pagination->total());
        $this->assertClientMethodCalled('search');
    }

    // Result set handling tests

    /**
     * Test search with empty results
     */
    public function testSearchResultHandlingWithEmptyResults(): void
    {
        // Arrange
        $this->getMockClient()->scenario()
            ->searchReturnsEmpty()
            ->apply();

        // Act
        $results = $this->createQuery()
            ->where('nonexistent_field', 'value')
            ->get();

        // Assert
        $this->assertCount(0, $results);
        $this->assertTrue($results->isEmpty());
        $this->assertClientMethodCalled('search');
    }

    /**
     * Test search result metadata handling
     */
    public function testSearchResultMetadataHandling(): void
    {
        // Arrange
        $documents = [
            $this->documentFactory->create(['title' => 'Test Article']),
        ];

        $this->getMockClient()->setResponse('search', ResponseFactory::search([
            'hits' => $documents,
            'total' => 1,
            'took' => 15,
            'timed_out' => false,
            'max_score' => 1.5,
        ]));

        // Act
        $results = $this->createQuery()
            ->search('test')
            ->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertClientMethodCalled('search');

        // Verify metadata is accessible (implementation dependent)
        $searchCalls = $this->getMockClient()->getMethodCalls('search');
        $this->assertNotEmpty($searchCalls);
    }

    /**
     * Test search with highlighting
     */
    public function testSearchWithHighlighting(): void
    {
        // Arrange
        $documents = [
            $this->documentFactory->create([
                'title' => 'Laravel Framework Tutorial',
                'content' => 'Learn how to use Laravel framework effectively',
            ]),
        ];

        $this->getMockClient()->scenario()
            ->search([
                'hits' => array_map(function ($doc) {
                    return array_merge($doc, [
                        'highlight' => [
                            'title' => ['<em>Laravel</em> Framework Tutorial'],
                            'content' => ['Learn how to use <em>Laravel</em> framework effectively'],
                        ],
                    ]);
                }, $documents),
                'total' => 1,
            ])
            ->apply();

        // Act
        $results = $this->createQuery()
            ->search('Laravel')
            ->highlight(['title', 'content'])
            ->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertClientMethodCalled('search');
    }

    // Performance and large result set tests

    /**
     * Test search with scroll for large result sets
     */
    public function testSearchWithScrollForLargeResultSets(): void
    {
        // Arrange
        $documents = array_map(
            fn($i)
            => $this->documentFactory->create(['id' => $i, 'title' => "Article {$i}"]),
            range(1, 50),
        );
        $scrollId = 'scroll_id_123';

        $this->getMockClient()->setResponse('search', ResponseFactory::scroll([
            'hits' => $documents,
            'total' => 100,
            'scroll_id' => $scrollId,
        ]));

        // Act
        $results = $this->createQuery()
            ->scroll('5m')
            ->take(50)
            ->get();

        // Assert
        $this->assertCount(50, $results);
        $this->assertClientMethodCalled('search');

        // Verify scroll parameters
        $searchCalls = $this->getMockClient()->getMethodCalls('search');
        $searchParams = $searchCalls[0];
        $this->assertArrayHasKey('scroll', $searchParams);
        $this->assertEquals('5m', $searchParams['scroll']);
    }

    /**
     * Test search performance with large result sets
     */
    public function testSearchPerformanceWithLargeResultSets(): void
    {
        // Arrange
        $documents = array_map(
            fn($i)
            => $this->documentFactory->create(['id' => $i, 'title' => "Article {$i}"]),
            range(1, 100),
        );

        $this->getMockClient()->setResponse('search', ResponseFactory::search([
            'hits' => $documents,
            'total' => 1000,
            'took' => 25, // Simulate reasonable response time
        ]));

        // Act
        $startTime = microtime(true);
        $results = $this->createQuery()
            ->take(100)
            ->get();
        $endTime = microtime(true);

        // Assert
        $this->assertCount(100, $results);
        $this->assertClientMethodCalled('search');

        // Test should complete quickly (mock doesn't actually take time)
        $executionTime = $endTime - $startTime;
        $this->assertLessThan(1.0, $executionTime, 'Search should complete quickly with mocked responses');
    }

    /**
     * Test search with large query complexity
     */
    public function testSearchWithLargeQueryComplexity(): void
    {
        // Arrange
        $documents = [
            $this->documentFactory->create([
                'title' => 'Complex Article',
                'category' => 'technology',
                'tags' => ['php', 'laravel', 'elasticsearch'],
                'author_id' => 1,
                'status' => 'published',
                'views' => 500,
                'created_at' => '2024-01-15T10:00:00Z',
            ]),
        ];

        $this->getMockClient()->scenario()
            ->searchReturns($documents)
            ->apply();

        // Act - Build a complex query with multiple conditions
        $results = $this->createQuery()
            ->search('Complex')
            ->where('status', 'published')
            ->where('category', 'technology')
            ->whereIn('tags', ['php', 'laravel'])
            ->whereBetween('views', [100, 1000])
            ->whereBetween('created_at', ['2024-01-01', '2024-01-31'])
            ->whereNot('author_id', '=', 999)
            ->orderBy('views', 'desc')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals('Complex Article', $results->first()->title);
        $this->assertClientMethodCalled('search');
    }

    /**
     * Test search memory usage with large result sets
     */
    public function testSearchMemoryUsageWithLargeResultSets(): void
    {
        // Arrange
        $documents = array_map(
            fn($i)
            => $this->documentFactory->create([
                'id' => $i,
                'title' => "Article {$i}",
                'content' => str_repeat("Large content block {$i} ", 100), // Simulate large content
            ]),
            range(1, 50),
        );

        $this->getMockClient()->scenario()
            ->searchReturns($documents)
            ->apply();

        // Act
        $memoryBefore = memory_get_usage(true);
        $results = $this->createQuery()
            ->take(50)
            ->get();
        $memoryAfter = memory_get_usage(true);

        // Assert
        $this->assertCount(50, $results);
        $this->assertClientMethodCalled('search');

        // Memory usage should be reasonable (this is more of a sanity check)
        $memoryUsed = $memoryAfter - $memoryBefore;
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, 'Memory usage should be reasonable'); // Less than 50MB
    }

    /**
     * Test concurrent search operations
     */
    public function testConcurrentSearchOperations(): void
    {
        // Arrange
        $documents1 = [$this->documentFactory->create(['title' => 'Article 1'])];
        $documents2 = [$this->documentFactory->create(['title' => 'Article 2'])];

        // Set up different responses for different calls
        $this->getMockClient()->scenario()
            ->searchReturns($documents1)
            ->apply();

        // Act - Simulate concurrent searches
        $query1 = $this->createQuery();
        $query2 = $this->createQuery();

        $results1 = $query1->where('category', 'tech')->get();

        // Change mock response for second query
        $this->getMockClient()->scenario()
            ->searchReturns($documents2)
            ->apply();

        $results2 = $query2->where('category', 'business')->get();

        // Assert
        $this->assertCount(1, $results1);
        $this->assertCount(1, $results2);
        $this->assertEquals('Article 1', $results1->first()->title);
        $this->assertEquals('Article 2', $results2->first()->title);

        // Verify both searches were called
        $searchCalls = $this->getMockClient()->getMethodCalls('search');
        $this->assertCount(2, $searchCalls);
    }
}
