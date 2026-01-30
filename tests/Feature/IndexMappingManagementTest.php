<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Feature;

use Matchory\Elasticsearch\Tests\Support\Factories\IndexConfigurationFactory;
use Matchory\Elasticsearch\Tests\Support\Factories\ResponseFactory;
use Matchory\Elasticsearch\Tests\TestCase;

/**
 * Index Mapping Management Feature Tests
 *
 * Tests for index mapping definition, application, and validation.
 * This covers requirement 7.2 for index mapping definition, application, and validation.
 */
class IndexMappingManagementTest extends TestCase
{
    private IndexConfigurationFactory $indexFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->indexFactory = new IndexConfigurationFactory();
    }

    /**
     * Test getting index mappings
     *
     * @test
     */
    public function it_gets_index_mappings(): void
    {
        // Arrange
        $indexName = 'test_mappings_index';
        $expectedMappings = [
            'properties' => [
                'title' => ['type' => 'text'],
                'content' => ['type' => 'text'],
                'created_at' => ['type' => 'date'],
                'status' => ['type' => 'keyword'],
            ],
        ];
        $expectedResponse = ResponseFactory::getMapping([
            'index' => $indexName,
            'mappings' => $expectedMappings,
        ]);

        $this->getMockClient()->setResponse('indices.getMapping', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->getMapping(['index' => $indexName]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.getMapping', [
            'index' => $indexName,
        ]));
    }

    /**
     * Test putting new mappings
     *
     * @test
     */
    public function it_puts_new_mappings(): void
    {
        // Arrange
        $indexName = 'test_put_mappings_index';
        $newMappings = [
            'properties' => [
                'description' => [
                    'type' => 'text',
                    'analyzer' => 'standard',
                ],
                'tags' => [
                    'type' => 'keyword',
                ],
                'metadata' => [
                    'type' => 'object',
                    'properties' => [
                        'author' => ['type' => 'keyword'],
                        'category' => ['type' => 'keyword'],
                    ],
                ],
            ],
        ];
        $expectedResponse = ResponseFactory::putMapping();

        $this->getMockClient()->setResponse('indices.putMapping', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->putMapping([
            'index' => $indexName,
            'body' => $newMappings,
        ]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.putMapping', [
            'index' => $indexName,
            'body' => $newMappings,
        ]));
    }

    /**
     * Test putting mappings with field types validation
     *
     * @test
     */
    public function it_puts_mappings_with_various_field_types(): void
    {
        // Arrange
        $indexName = 'test_field_types_mappings_index';
        $fieldTypeMappings = [
            'properties' => [
                'text_field' => [
                    'type' => 'text',
                    'analyzer' => 'standard',
                    'fields' => [
                        'keyword' => [
                            'type' => 'keyword',
                            'ignore_above' => 256,
                        ],
                    ],
                ],
                'keyword_field' => ['type' => 'keyword'],
                'integer_field' => ['type' => 'integer'],
                'long_field' => ['type' => 'long'],
                'float_field' => ['type' => 'float'],
                'double_field' => ['type' => 'double'],
                'boolean_field' => ['type' => 'boolean'],
                'date_field' => [
                    'type' => 'date',
                    'format' => 'strict_date_optional_time||epoch_millis',
                ],
                'object_field' => [
                    'type' => 'object',
                    'properties' => [
                        'nested_text' => ['type' => 'text'],
                        'nested_number' => ['type' => 'integer'],
                    ],
                ],
                'nested_field' => [
                    'type' => 'nested',
                    'properties' => [
                        'name' => ['type' => 'text'],
                        'value' => ['type' => 'keyword'],
                    ],
                ],
                'geo_point_field' => ['type' => 'geo_point'],
                'ip_field' => ['type' => 'ip'],
                'completion_field' => [
                    'type' => 'completion',
                    'analyzer' => 'simple',
                    'preserve_separators' => true,
                    'preserve_position_increments' => true,
                    'max_input_length' => 50,
                ],
            ],
        ];
        $expectedResponse = ResponseFactory::putMapping();

        $this->getMockClient()->setResponse('indices.putMapping', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->putMapping([
            'index' => $indexName,
            'body' => $fieldTypeMappings,
        ]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.putMapping', [
            'index' => $indexName,
            'body' => $fieldTypeMappings,
        ]));
    }

    /**
     * Test putting mappings with analyzers
     *
     * @test
     */
    public function it_puts_mappings_with_custom_analyzers(): void
    {
        // Arrange
        $indexName = 'test_analyzer_mappings_index';
        $analyzerMappings = [
            'properties' => [
                'title' => [
                    'type' => 'text',
                    'analyzer' => 'custom_analyzer',
                    'search_analyzer' => 'search_analyzer',
                ],
                'content' => [
                    'type' => 'text',
                    'analyzer' => 'html_strip_analyzer',
                ],
                'tags' => [
                    'type' => 'text',
                    'analyzer' => 'keyword_analyzer',
                ],
            ],
        ];
        $expectedResponse = ResponseFactory::putMapping();

        $this->getMockClient()->setResponse('indices.putMapping', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->putMapping([
            'index' => $indexName,
            'body' => $analyzerMappings,
        ]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.putMapping', [
            'index' => $indexName,
            'body' => $analyzerMappings,
        ]));
    }

    /**
     * Test getting mappings for multiple indices
     *
     * @test
     */
    public function it_gets_mappings_for_multiple_indices(): void
    {
        // Arrange
        $indices = ['index1', 'index2'];
        $indexPattern = implode(',', $indices);
        $expectedResponse = [
            'index1' => [
                'mappings' => [
                    'properties' => [
                        'title' => ['type' => 'text'],
                        'content' => ['type' => 'text'],
                    ],
                ],
            ],
            'index2' => [
                'mappings' => [
                    'properties' => [
                        'name' => ['type' => 'text'],
                        'description' => ['type' => 'text'],
                    ],
                ],
            ],
        ];

        $this->getMockClient()->setResponse('indices.getMapping', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->getMapping(['index' => $indexPattern]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.getMapping', [
            'index' => $indexPattern,
        ]));
    }

    /**
     * Test putting mappings for multiple indices
     *
     * @test
     */
    public function it_puts_mappings_for_multiple_indices(): void
    {
        // Arrange
        $indices = ['index1', 'index2', 'index3'];
        $indexPattern = implode(',', $indices);
        $commonMappings = [
            'properties' => [
                'timestamp' => [
                    'type' => 'date',
                    'format' => 'strict_date_optional_time||epoch_millis',
                ],
                'status' => ['type' => 'keyword'],
            ],
        ];
        $expectedResponse = ResponseFactory::putMapping();

        $this->getMockClient()->setResponse('indices.putMapping', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->putMapping([
            'index' => $indexPattern,
            'body' => $commonMappings,
        ]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.putMapping', [
            'index' => $indexPattern,
            'body' => $commonMappings,
        ]));
    }

    /**
     * Test putting mappings with dynamic templates
     *
     * @test
     */
    public function it_puts_mappings_with_dynamic_templates(): void
    {
        // Arrange
        $indexName = 'test_dynamic_templates_index';
        $dynamicMappings = [
            'dynamic_templates' => [
                [
                    'strings_as_keywords' => [
                        'match_mapping_type' => 'string',
                        'mapping' => [
                            'type' => 'keyword',
                            'ignore_above' => 256,
                        ],
                    ],
                ],
                [
                    'integers_as_longs' => [
                        'match_mapping_type' => 'long',
                        'mapping' => [
                            'type' => 'long',
                        ],
                    ],
                ],
            ],
            'properties' => [
                'title' => ['type' => 'text'],
                'content' => ['type' => 'text'],
            ],
        ];
        $expectedResponse = ResponseFactory::putMapping();

        $this->getMockClient()->setResponse('indices.putMapping', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->putMapping([
            'index' => $indexName,
            'body' => $dynamicMappings,
        ]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.putMapping', [
            'index' => $indexName,
            'body' => $dynamicMappings,
        ]));
    }

    /**
     * Test putting mappings with meta information
     *
     * @test
     */
    public function it_puts_mappings_with_meta_information(): void
    {
        // Arrange
        $indexName = 'test_meta_mappings_index';
        $metaMappings = [
            '_meta' => [
                'version' => '1.0',
                'description' => 'Blog posts index',
                'created_by' => 'system',
                'created_at' => '2024-01-01',
            ],
            'properties' => [
                'title' => ['type' => 'text'],
                'content' => ['type' => 'text'],
                'author' => ['type' => 'keyword'],
            ],
        ];
        $expectedResponse = ResponseFactory::putMapping();

        $this->getMockClient()->setResponse('indices.putMapping', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->putMapping([
            'index' => $indexName,
            'body' => $metaMappings,
        ]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.putMapping', [
            'index' => $indexName,
            'body' => $metaMappings,
        ]));
    }

    /**
     * Test getting specific field mappings
     *
     * @test
     */
    public function it_gets_specific_field_mappings(): void
    {
        // Arrange
        $indexName = 'test_field_mappings_index';
        $fieldName = 'title';
        $expectedResponse = ResponseFactory::getMapping([
            'index' => $indexName,
            'mappings' => [
                'properties' => [
                    'title' => [
                        'type' => 'text',
                        'analyzer' => 'standard',
                    ],
                ],
            ],
        ]);

        $this->getMockClient()->setResponse('indices.getMapping', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->getMapping([
            'index' => $indexName,
            'fields' => $fieldName,
        ]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.getMapping', [
            'index' => $indexName,
            'fields' => $fieldName,
        ]));
    }

    /**
     * Test putting mappings using factory configurations
     *
     * @test
     */
    public function it_puts_mappings_using_factory_configurations(): void
    {
        // Arrange
        $indexName = 'test_factory_mappings_index';
        $blogConfig = $this->indexFactory->createBlogIndex($indexName);
        $mappings = $blogConfig['body']['mappings'];
        $expectedResponse = ResponseFactory::putMapping();

        $this->getMockClient()->setResponse('indices.putMapping', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->putMapping([
            'index' => $indexName,
            'body' => $mappings,
        ]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.putMapping', [
            'index' => $indexName,
            'body' => $mappings,
        ]));

        // Verify the mappings contain expected blog fields
        $calls = $this->getMockClient()->getMethodCalls('indices.putMapping');
        $call = $calls[0];
        $properties = $call['body']['properties'];

        $this->assertArrayHasKey('title', $properties);
        $this->assertArrayHasKey('content', $properties);
        $this->assertArrayHasKey('author', $properties);
        $this->assertArrayHasKey('category', $properties);
        $this->assertArrayHasKey('tags', $properties);
        $this->assertEquals('text', $properties['title']['type']);
        $this->assertEquals('keyword', $properties['category']['type']);
    }

    /**
     * Test putting mappings with validation
     *
     * @test
     */
    public function it_validates_mappings_before_putting(): void
    {
        // Arrange
        $indexName = 'test_validation_mappings_index';
        $validMappings = [
            'properties' => [
                'email' => [
                    'type' => 'keyword',
                    'normalizer' => 'lowercase',
                ],
                'age' => [
                    'type' => 'integer',
                    'min_value' => 0,
                    'max_value' => 150,
                ],
                'score' => [
                    'type' => 'float',
                    'coerce' => false,
                ],
            ],
        ];
        $expectedResponse = ResponseFactory::putMapping();

        $this->getMockClient()->setResponse('indices.putMapping', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->putMapping([
            'index' => $indexName,
            'body' => $validMappings,
        ]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.putMapping', [
            'index' => $indexName,
            'body' => $validMappings,
        ]));
    }

    /**
     * Test putting mappings with timeout
     *
     * @test
     */
    public function it_puts_mappings_with_timeout(): void
    {
        // Arrange
        $indexName = 'test_timeout_mappings_index';
        $mappings = [
            'properties' => [
                'title' => ['type' => 'text'],
                'content' => ['type' => 'text'],
            ],
        ];
        $timeout = '30s';
        $expectedResponse = ResponseFactory::putMapping();

        $this->getMockClient()->setResponse('indices.putMapping', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->putMapping([
            'index' => $indexName,
            'body' => $mappings,
            'timeout' => $timeout,
        ]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.putMapping', [
            'index' => $indexName,
            'body' => $mappings,
            'timeout' => $timeout,
        ]));
    }
}
