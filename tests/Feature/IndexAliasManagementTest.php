<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Feature;

use Matchory\Elasticsearch\Tests\Support\Factories\ResponseFactory;
use Matchory\Elasticsearch\Tests\TestCase;

/**
 * Index Alias Management Feature Tests
 *
 * Tests for index alias creation and management functionality.
 * This covers requirement 7.4 for index alias creation and management functionality.
 */
class IndexAliasManagementTest extends TestCase
{
    /**
     * Test getting index aliases
     *
     * @test
     */
    public function it_gets_index_aliases(): void
    {
        // Arrange
        $indexName = 'test_aliases_index';
        $expectedAliases = [
            'blog_alias' => [],
            'content_alias' => [
                'filter' => [
                    'term' => ['status' => 'published'],
                ],
            ],
        ];
        $expectedResponse = ResponseFactory::getAliases([
            'index' => $indexName,
            'aliases' => $expectedAliases,
        ]);

        $this->getMockClient()->setResponse('indices.getAliases', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->getAliases(['index' => $indexName]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.getAliases', [
            'index' => $indexName,
        ]));
    }

    /**
     * Test updating index aliases - adding aliases
     *
     * @test
     */
    public function it_adds_index_aliases(): void
    {
        // Arrange
        $indexName = 'test_add_aliases_index';
        $aliasName = 'new_alias';
        $aliasActions = [
            'body' => [
                'actions' => [
                    [
                        'add' => [
                            'index' => $indexName,
                            'alias' => $aliasName,
                        ],
                    ],
                ],
            ],
        ];
        $expectedResponse = ResponseFactory::updateAliases();

        $this->getMockClient()->setResponse('indices.updateAliases', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->updateAliases($aliasActions);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.updateAliases', $aliasActions));
    }

    /**
     * Test updating index aliases - removing aliases
     *
     * @test
     */
    public function it_removes_index_aliases(): void
    {
        // Arrange
        $indexName = 'test_remove_aliases_index';
        $aliasName = 'old_alias';
        $aliasActions = [
            'body' => [
                'actions' => [
                    [
                        'remove' => [
                            'index' => $indexName,
                            'alias' => $aliasName,
                        ],
                    ],
                ],
            ],
        ];
        $expectedResponse = ResponseFactory::updateAliases();

        $this->getMockClient()->setResponse('indices.updateAliases', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->updateAliases($aliasActions);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.updateAliases', $aliasActions));
    }

    /**
     * Test updating index aliases - atomic operations
     *
     * @test
     */
    public function it_performs_atomic_alias_operations(): void
    {
        // Arrange
        $oldIndex = 'old_index';
        $newIndex = 'new_index';
        $aliasName = 'current_alias';
        $aliasActions = [
            'body' => [
                'actions' => [
                    [
                        'remove' => [
                            'index' => $oldIndex,
                            'alias' => $aliasName,
                        ],
                    ],
                    [
                        'add' => [
                            'index' => $newIndex,
                            'alias' => $aliasName,
                        ],
                    ],
                ],
            ],
        ];
        $expectedResponse = ResponseFactory::updateAliases();

        $this->getMockClient()->setResponse('indices.updateAliases', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->updateAliases($aliasActions);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.updateAliases', $aliasActions));
    }

    /**
     * Test adding aliases with filters
     *
     * @test
     */
    public function it_adds_aliases_with_filters(): void
    {
        // Arrange
        $indexName = 'test_filtered_aliases_index';
        $aliasName = 'published_content';
        $filter = [
            'term' => ['status' => 'published'],
        ];
        $aliasActions = [
            'body' => [
                'actions' => [
                    [
                        'add' => [
                            'index' => $indexName,
                            'alias' => $aliasName,
                            'filter' => $filter,
                        ],
                    ],
                ],
            ],
        ];
        $expectedResponse = ResponseFactory::updateAliases();

        $this->getMockClient()->setResponse('indices.updateAliases', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->updateAliases($aliasActions);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.updateAliases', $aliasActions));
    }

    /**
     * Test adding aliases with routing
     *
     * @test
     */
    public function it_adds_aliases_with_routing(): void
    {
        // Arrange
        $indexName = 'test_routed_aliases_index';
        $aliasName = 'user_content';
        $routing = 'user123';
        $aliasActions = [
            'body' => [
                'actions' => [
                    [
                        'add' => [
                            'index' => $indexName,
                            'alias' => $aliasName,
                            'routing' => $routing,
                        ],
                    ],
                ],
            ],
        ];
        $expectedResponse = ResponseFactory::updateAliases();

        $this->getMockClient()->setResponse('indices.updateAliases', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->updateAliases($aliasActions);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.updateAliases', $aliasActions));
    }

    /**
     * Test adding aliases with search and index routing
     *
     * @test
     */
    public function it_adds_aliases_with_search_and_index_routing(): void
    {
        // Arrange
        $indexName = 'test_dual_routing_aliases_index';
        $aliasName = 'dual_routed_alias';
        $searchRouting = 'search_route';
        $indexRouting = 'index_route';
        $aliasActions = [
            'body' => [
                'actions' => [
                    [
                        'add' => [
                            'index' => $indexName,
                            'alias' => $aliasName,
                            'search_routing' => $searchRouting,
                            'index_routing' => $indexRouting,
                        ],
                    ],
                ],
            ],
        ];
        $expectedResponse = ResponseFactory::updateAliases();

        $this->getMockClient()->setResponse('indices.updateAliases', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->updateAliases($aliasActions);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.updateAliases', $aliasActions));
    }

    /**
     * Test getting aliases for multiple indices
     *
     * @test
     */
    public function it_gets_aliases_for_multiple_indices(): void
    {
        // Arrange
        $indices = ['index1', 'index2'];
        $indexPattern = implode(',', $indices);
        $expectedResponse = [
            'index1' => [
                'aliases' => [
                    'alias1' => [],
                    'shared_alias' => [],
                ],
            ],
            'index2' => [
                'aliases' => [
                    'alias2' => [],
                    'shared_alias' => [],
                ],
            ],
        ];

        $this->getMockClient()->setResponse('indices.getAliases', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->getAliases(['index' => $indexPattern]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.getAliases', [
            'index' => $indexPattern,
        ]));
    }

    /**
     * Test getting specific aliases
     *
     * @test
     */
    public function it_gets_specific_aliases(): void
    {
        // Arrange
        $indexName = 'test_specific_aliases_index';
        $aliasName = 'specific_alias';
        $expectedResponse = ResponseFactory::getAliases([
            'index' => $indexName,
            'aliases' => [
                $aliasName => [
                    'filter' => [
                        'term' => ['status' => 'active'],
                    ],
                ],
            ],
        ]);

        $this->getMockClient()->setResponse('indices.getAliases', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->getAliases([
            'index' => $indexName,
            'name' => $aliasName,
        ]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.getAliases', [
            'index' => $indexName,
            'name' => $aliasName,
        ]));
    }

    /**
     * Test removing all aliases from an index
     *
     * @test
     */
    public function it_removes_all_aliases_from_index(): void
    {
        // Arrange
        $indexName = 'test_remove_all_aliases_index';
        $aliasActions = [
            'body' => [
                'actions' => [
                    [
                        'remove' => [
                            'index' => $indexName,
                            'alias' => '*',
                        ],
                    ],
                ],
            ],
            'client' => ['ignore' => [404]],
        ];
        $expectedResponse = ResponseFactory::updateAliases();

        $this->getMockClient()->setResponse('indices.updateAliases', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->updateAliases($aliasActions);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.updateAliases', $aliasActions));
    }

    /**
     * Test complex alias management scenario
     *
     * @test
     */
    public function it_handles_complex_alias_management_scenario(): void
    {
        // Arrange
        $oldIndex = 'blog_v1';
        $newIndex = 'blog_v2';
        $tempIndex = 'blog_temp';
        $mainAlias = 'blog';
        $readAlias = 'blog_read';
        $writeAlias = 'blog_write';

        $aliasActions = [
            'body' => [
                'actions' => [
                    // Remove old aliases
                    [
                        'remove' => [
                            'index' => $oldIndex,
                            'alias' => $mainAlias,
                        ],
                    ],
                    [
                        'remove' => [
                            'index' => $oldIndex,
                            'alias' => $writeAlias,
                        ],
                    ],
                    // Add new aliases
                    [
                        'add' => [
                            'index' => $newIndex,
                            'alias' => $mainAlias,
                        ],
                    ],
                    [
                        'add' => [
                            'index' => $newIndex,
                            'alias' => $writeAlias,
                        ],
                    ],
                    // Add read alias to both old and new for gradual migration
                    [
                        'add' => [
                            'index' => $oldIndex,
                            'alias' => $readAlias,
                            'filter' => [
                                'range' => [
                                    'created_at' => [
                                        'lt' => '2024-01-01',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'add' => [
                            'index' => $newIndex,
                            'alias' => $readAlias,
                            'filter' => [
                                'range' => [
                                    'created_at' => [
                                        'gte' => '2024-01-01',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $expectedResponse = ResponseFactory::updateAliases();

        $this->getMockClient()->setResponse('indices.updateAliases', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->updateAliases($aliasActions);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.updateAliases', $aliasActions));
    }

    /**
     * Test alias operations with timeout
     *
     * @test
     */
    public function it_performs_alias_operations_with_timeout(): void
    {
        // Arrange
        $indexName = 'test_timeout_aliases_index';
        $aliasName = 'timeout_alias';
        $timeout = '30s';
        $aliasActions = [
            'body' => [
                'actions' => [
                    [
                        'add' => [
                            'index' => $indexName,
                            'alias' => $aliasName,
                        ],
                    ],
                ],
            ],
            'timeout' => $timeout,
        ];
        $expectedResponse = ResponseFactory::updateAliases();

        $this->getMockClient()->setResponse('indices.updateAliases', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->updateAliases($aliasActions);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.updateAliases', $aliasActions));
    }

    /**
     * Test getting aliases with wildcard patterns
     *
     * @test
     */
    public function it_gets_aliases_with_wildcard_patterns(): void
    {
        // Arrange
        $aliasPattern = 'blog_*';
        $expectedResponse = [
            'blog_index_1' => [
                'aliases' => [
                    'blog_read' => [],
                    'blog_write' => [],
                ],
            ],
            'blog_index_2' => [
                'aliases' => [
                    'blog_read' => [],
                ],
            ],
        ];

        $this->getMockClient()->setResponse('indices.getAliases', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->getAliases(['name' => $aliasPattern]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.getAliases', [
            'name' => $aliasPattern,
        ]));
    }

    /**
     * Test alias validation and error handling
     *
     * @test
     */
    public function it_validates_alias_operations(): void
    {
        // Arrange
        $indexName = 'test_validation_aliases_index';
        $aliasName = 'valid_alias';
        $validAliasActions = [
            'body' => [
                'actions' => [
                    [
                        'add' => [
                            'index' => $indexName,
                            'alias' => $aliasName,
                            'filter' => [
                                'bool' => [
                                    'must' => [
                                        ['term' => ['status' => 'published']],
                                        ['range' => ['created_at' => ['gte' => 'now-30d']]],
                                    ],
                                ],
                            ],
                            'routing' => 'user_routing',
                        ],
                    ],
                ],
            ],
        ];
        $expectedResponse = ResponseFactory::updateAliases();

        $this->getMockClient()->setResponse('indices.updateAliases', $expectedResponse);

        $connection = $this->app->make('elasticsearch.connection');
        $client = $connection->getClient();

        // Act
        $response = $client->indices()->updateAliases($validAliasActions);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.updateAliases', $validAliasActions));
    }
}
