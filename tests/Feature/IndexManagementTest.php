<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Feature;

use Matchory\Elasticsearch\Index;
use Matchory\Elasticsearch\Tests\Support\Factories\IndexConfigurationFactory;
use Matchory\Elasticsearch\Tests\Support\Factories\ResponseFactory;
use Matchory\Elasticsearch\Tests\TestCase;

/**
 * Index Management Feature Tests
 *
 * Tests for index creation, deletion, update operations, mapping definition,
 * application, validation, settings configuration, and alias management.
 *
 * Requirements: 7.1, 7.2, 7.3, 7.4
 */
class IndexManagementTest extends TestCase
{
    private IndexConfigurationFactory $indexFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->indexFactory = new IndexConfigurationFactory();
    }

    /**
     * Create an Index instance with a mocked connection
     */
    private function createIndexWithMockConnection(string $indexName): Index
    {
        $index = new Index($indexName);

        // Use the MockElasticsearchClient directly - it already has a MockIndicesNamespace
        $mockConnection = new \Matchory\Elasticsearch\Connection($this->getMockClient());

        $index->setConnection($mockConnection);

        return $index;
    }

    /**
     * Test index creation with basic configuration
     *
     * @test
     * @covers \Matchory\Elasticsearch\Index::create
     */
    public function it_creates_index_with_basic_configuration(): void
    {
        // Arrange
        $indexName = 'test_basic_index';
        $expectedResponse = ResponseFactory::createIndex(['index' => $indexName]);

        $this->getMockClient()->setResponse('indices.create', $expectedResponse);

        $index = $this->createIndexWithMockConnection($indexName);

        // Act
        $response = $index->create();

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.create', [
            'index' => $indexName,
            'body' => [
                'settings' => [
                    'number_of_shards' => 5,
                    'number_of_replicas' => 0,
                ],
            ],
        ]));
    }

    /**
     * Test index creation with custom shards and replicas
     *
     * @test
     * @covers \Matchory\Elasticsearch\Index::create
     * @covers \Matchory\Elasticsearch\Index::shards
     * @covers \Matchory\Elasticsearch\Index::replicas
     */
    public function it_creates_index_with_custom_shards_and_replicas(): void
    {
        // Arrange
        $indexName = 'test_custom_index';
        $shards = 3;
        $replicas = 2;
        $expectedResponse = ResponseFactory::createIndex(['index' => $indexName]);

        $this->getMockClient()->setResponse('indices.create', $expectedResponse);

        $index = $this->createIndexWithMockConnection($indexName);
        $index->shards($shards)->replicas($replicas);

        // Act
        $response = $index->create();

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.create', [
            'index' => $indexName,
            'body' => [
                'settings' => [
                    'number_of_shards' => $shards,
                    'number_of_replicas' => $replicas,
                ],
            ],
        ]));
    }

    /**
     * Test index creation with mappings
     *
     * @test
     * @covers \Matchory\Elasticsearch\Index::create
     * @covers \Matchory\Elasticsearch\Index::mapping
     */
    public function it_creates_index_with_mappings(): void
    {
        // Arrange
        $indexName = 'test_mapped_index';
        $mappings = [
            'properties' => [
                'title' => ['type' => 'text'],
                'content' => ['type' => 'text'],
                'created_at' => ['type' => 'date'],
                'status' => ['type' => 'keyword'],
            ],
        ];
        $expectedResponse = ResponseFactory::createIndex(['index' => $indexName]);

        $this->getMockClient()->setResponse('indices.create', $expectedResponse);

        $index = $this->createIndexWithMockConnection($indexName);
        $index->mapping($mappings);

        // Act
        $response = $index->create();

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.create', [
            'index' => $indexName,
            'body' => [
                'settings' => [
                    'number_of_shards' => 5,
                    'number_of_replicas' => 0,
                ],
                'mappings' => $mappings,
            ],
        ]));
    }

    /**
     * Test index creation with aliases
     *
     * @test
     * @covers \Matchory\Elasticsearch\Index::create
     * @covers \Matchory\Elasticsearch\Index::alias
     */
    public function it_creates_index_with_aliases(): void
    {
        // Arrange
        $indexName = 'test_aliased_index';
        $alias1 = 'test_alias_1';
        $alias2 = 'test_alias_2';
        $expectedResponse = ResponseFactory::createIndex(['index' => $indexName]);

        $this->getMockClient()->setResponse('indices.create', $expectedResponse);

        $index = $this->createIndexWithMockConnection($indexName);
        $index->alias($alias1)->alias($alias2);

        // Act
        $response = $index->create();

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $calls = $this->getMockClient()->getMethodCalls('indices.create');
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertEquals($indexName, $call['index']);
        $this->assertArrayHasKey('aliases', $call['body']);
        $this->assertArrayHasKey($alias1, $call['body']['aliases']);
        $this->assertArrayHasKey($alias2, $call['body']['aliases']);
    }

    /**
     * Test index creation with complex alias configuration
     *
     * @test
     * @covers \Matchory\Elasticsearch\Index::alias
     */
    public function it_creates_index_with_complex_alias_configuration(): void
    {
        // Arrange
        $indexName = 'test_complex_alias_index';
        $alias = 'filtered_alias';
        $aliasOptions = [
            'filter' => [
                'term' => ['status' => 'published'],
            ],
            'routing' => 'user1',
        ];
        $expectedResponse = ResponseFactory::createIndex(['index' => $indexName]);

        $this->getMockClient()->setResponse('indices.create', $expectedResponse);

        $index = $this->createIndexWithMockConnection($indexName);
        $index->alias($alias, $aliasOptions);

        // Act
        $response = $index->create();

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $calls = $this->getMockClient()->getMethodCalls('indices.create');
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertEquals($aliasOptions, $call['body']['aliases'][$alias]);
    }

    /**
     * Test index creation with fluent configuration
     *
     * @test
     * @covers \Matchory\Elasticsearch\Index::create
     * @covers \Matchory\Elasticsearch\Index::shards
     * @covers \Matchory\Elasticsearch\Index::replicas
     * @covers \Matchory\Elasticsearch\Index::mapping
     * @covers \Matchory\Elasticsearch\Index::alias
     */
    public function it_creates_index_with_fluent_configuration(): void
    {
        // Arrange
        $indexName = 'test_fluent_index';
        $expectedResponse = ResponseFactory::createIndex(['index' => $indexName]);

        $this->getMockClient()->setResponse('indices.create', $expectedResponse);

        $index = $this->createIndexWithMockConnection($indexName);
        $index->shards(2)
              ->replicas(1)
              ->mapping([
                  'properties' => [
                      'name' => ['type' => 'text'],
                      'email' => ['type' => 'keyword'],
                  ],
              ])
              ->alias('users');

        // Act
        $response = $index->create();

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $calls = $this->getMockClient()->getMethodCalls('indices.create');
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertEquals(2, $call['body']['settings']['number_of_shards']);
        $this->assertEquals(1, $call['body']['settings']['number_of_replicas']);
        $this->assertArrayHasKey('mappings', $call['body']);
        $this->assertArrayHasKey('aliases', $call['body']);
        $this->assertArrayHasKey('users', $call['body']['aliases']);
    }

    /**
     * Test index deletion
     *
     * @test
     * @covers \Matchory\Elasticsearch\Index::drop
     */
    public function it_deletes_existing_index(): void
    {
        // Arrange
        $indexName = 'test_delete_index';
        $expectedResponse = ResponseFactory::deleteIndex();

        $this->getMockClient()->setResponse('indices.delete', $expectedResponse);

        $index = $this->createIndexWithMockConnection($indexName);

        // Act
        $response = $index->drop();

        // Assert
        $this->assertEquals($expectedResponse, $response);
        // In ES v9, ignore errors are handled via setResponseException(false)
        // instead of passing a 'client' => ['ignore' => ...] parameter
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.delete', [
            'index' => $indexName,
        ]));
    }

    /**
     * Test index deletion with ignored errors
     *
     * @test
     * @covers \Matchory\Elasticsearch\Index::drop
     * @covers \Matchory\Elasticsearch\Index::ignores
     */
    public function it_deletes_index_with_ignored_errors(): void
    {
        // Arrange
        $indexName = 'test_delete_ignore_index';
        $ignoredErrors = [404, 400];
        $expectedResponse = ResponseFactory::deleteIndex();

        $this->getMockClient()->setResponse('indices.delete', $expectedResponse);

        $index = $this->createIndexWithMockConnection($indexName);
        $index->ignores(...$ignoredErrors);

        // Act
        $response = $index->drop();

        // Assert
        $this->assertEquals($expectedResponse, $response);
        // In ES v9, ignore errors are handled via setResponseException(false)
        // instead of passing a 'client' => ['ignore' => ...] parameter
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.delete', [
            'index' => $indexName,
        ]));
    }

    /**
     * Test index existence check
     *
     * @test
     * @covers \Matchory\Elasticsearch\Index::exists
     */
    public function it_checks_if_index_exists(): void
    {
        // Arrange
        $indexName = 'test_exists_index';
        $this->getMockClient()->setResponse('indices.exists', true);

        $index = $this->createIndexWithMockConnection($indexName);

        // Act
        $exists = $index->exists();

        // Assert
        $this->assertTrue($exists);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.exists', [
            'index' => $indexName,
        ]));
    }

    /**
     * Test index existence check returns false for non-existent index
     *
     * @test
     * @covers \Matchory\Elasticsearch\Index::exists
     */
    public function it_returns_false_for_non_existent_index(): void
    {
        // Arrange
        $indexName = 'test_non_existent_index';
        $this->getMockClient()->setResponse('indices.exists', false);

        $index = $this->createIndexWithMockConnection($indexName);

        // Act
        $exists = $index->exists();

        // Assert
        $this->assertFalse($exists);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.exists', [
            'index' => $indexName,
        ]));
    }

    /**
     * Test complex index creation with all features
     *
     * @test
     * @covers \Matchory\Elasticsearch\Index::create
     */
    public function it_creates_complex_index_with_all_features(): void
    {
        // Arrange
        $config = $this->indexFactory->createBlogIndex('complex_blog_index');
        $expectedResponse = ResponseFactory::createIndex(['index' => $config['index']]);

        $this->getMockClient()->setResponse('indices.create', $expectedResponse);

        $index = $this->createIndexWithMockConnection($config['index']);
        $index->shards(3)
              ->replicas(1)
              ->mapping($config['body']['mappings'])
              ->alias('blog')
              ->alias('content', ['routing' => 'blog']);

        // Act
        $response = $index->create();

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $calls = $this->getMockClient()->getMethodCalls('indices.create');
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertEquals($config['index'], $call['index']);
        $this->assertEquals(3, $call['body']['settings']['number_of_shards']);
        $this->assertEquals(1, $call['body']['settings']['number_of_replicas']);
        $this->assertArrayHasKey('mappings', $call['body']);
        $this->assertArrayHasKey('aliases', $call['body']);
        $this->assertArrayHasKey('blog', $call['body']['aliases']);
        $this->assertArrayHasKey('content', $call['body']['aliases']);
    }

    /**
     * Test index creation with field types validation
     *
     * @test
     * @covers \Matchory\Elasticsearch\Index::mapping
     */
    public function it_creates_index_with_various_field_types(): void
    {
        // Arrange
        $config = $this->indexFactory->createFieldTypesIndex('field_types_index');
        $expectedResponse = ResponseFactory::createIndex(['index' => $config['index']]);

        $this->getMockClient()->setResponse('indices.create', $expectedResponse);

        $index = $this->createIndexWithMockConnection($config['index']);
        $index->mapping($config['body']['mappings']);

        // Act
        $response = $index->create();

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $calls = $this->getMockClient()->getMethodCalls('indices.create');
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $mappings = $call['body']['mappings'];

        // Verify various field types are present
        $this->assertEquals('text', $mappings['properties']['text_field']['type']);
        $this->assertEquals('keyword', $mappings['properties']['keyword_field']['type']);
        $this->assertEquals('integer', $mappings['properties']['integer_field']['type']);
        $this->assertEquals('float', $mappings['properties']['float_field']['type']);
        $this->assertEquals('boolean', $mappings['properties']['boolean_field']['type']);
        $this->assertEquals('date', $mappings['properties']['date_field']['type']);
        $this->assertEquals('object', $mappings['properties']['object_field']['type']);
        $this->assertEquals('geo_point', $mappings['properties']['geo_point_field']['type']);
        $this->assertEquals('completion', $mappings['properties']['completion_field']['type']);
    }

    /**
     * Test index creation with performance settings
     *
     * @test
     * @covers \Matchory\Elasticsearch\Index::create
     */
    public function it_creates_performance_optimized_index(): void
    {
        // Arrange
        $config = $this->indexFactory->createPerformanceIndex('performance_index', 5, 2);
        $expectedResponse = ResponseFactory::createIndex(['index' => $config['index']]);

        $this->getMockClient()->setResponse('indices.create', $expectedResponse);

        $index = $this->createIndexWithMockConnection($config['index']);
        $index->shards(5)->replicas(2);

        // Act
        $response = $index->create();

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $calls = $this->getMockClient()->getMethodCalls('indices.create');
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertEquals(5, $call['body']['settings']['number_of_shards']);
        $this->assertEquals(2, $call['body']['settings']['number_of_replicas']);
    }

    /**
     * Test index creation with minimal configuration
     *
     * @test
     * @covers \Matchory\Elasticsearch\Index::create
     */
    public function it_creates_minimal_index(): void
    {
        // Arrange
        $config = $this->indexFactory->createMinimalIndex('minimal_index');
        $expectedResponse = ResponseFactory::createIndex(['index' => $config['index']]);

        $this->getMockClient()->setResponse('indices.create', $expectedResponse);

        $index = $this->createIndexWithMockConnection($config['index']);
        $index->mapping($config['body']['mappings']);

        // Act
        $response = $index->create();

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $calls = $this->getMockClient()->getMethodCalls('indices.create');
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertCount(2, $call['body']['mappings']['properties']); // Only id and title
    }

    /**
     * Test index name retrieval
     *
     * @test
     * @covers \Matchory\Elasticsearch\Index::getName
     */
    public function it_returns_correct_index_name(): void
    {
        // Arrange
        $indexName = 'test_name_index';
        $index = new Index($indexName);

        // Act
        $name = $index->getName();

        // Assert
        $this->assertEquals($indexName, $name);
    }

    /**
     * Test alias with string routing option
     *
     * @test
     * @covers \Matchory\Elasticsearch\Index::alias
     */
    public function it_creates_alias_with_string_routing(): void
    {
        // Arrange
        $indexName = 'test_string_routing_index';
        $alias = 'routed_alias';
        $routing = 'user123';
        $expectedResponse = ResponseFactory::createIndex(['index' => $indexName]);

        $this->getMockClient()->setResponse('indices.create', $expectedResponse);

        $index = $this->createIndexWithMockConnection($indexName);
        $index->alias($alias, $routing);

        // Act
        $response = $index->create();

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $calls = $this->getMockClient()->getMethodCalls('indices.create');
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertEquals($routing, $call['body']['aliases'][$alias]);
    }

    /**
     * Test alias with null options creates empty object
     *
     * @test
     * @covers \Matchory\Elasticsearch\Index::alias
     */
    public function it_creates_alias_with_null_options(): void
    {
        // Arrange
        $indexName = 'test_null_alias_index';
        $alias = 'simple_alias';
        $expectedResponse = ResponseFactory::createIndex(['index' => $indexName]);

        $this->getMockClient()->setResponse('indices.create', $expectedResponse);

        $index = $this->createIndexWithMockConnection($indexName);
        $index->alias($alias, null);

        // Act
        $response = $index->create();

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $calls = $this->getMockClient()->getMethodCalls('indices.create');
        $this->assertCount(1, $calls);

        $call = $calls[0];
        $this->assertInstanceOf(\ArrayObject::class, $call['body']['aliases'][$alias]);
    }
}
