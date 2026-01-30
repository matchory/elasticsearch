<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Integration;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Matchory\Elasticsearch\ScoutEngine;
use Matchory\Elasticsearch\Tests\Support\Factories\ResponseFactory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Scout Engine Integration Tests
 *
 * Tests the ScoutEngine implementation and Laravel Scout integration,
 * including model indexing, searching, and result handling.
 */
class ScoutEngineTest extends ScoutTestCase
{
    protected string $testIndex = 'test_scout_engine';

    #[Test]
    public function it_can_be_instantiated_with_client_and_index(): void
    {
        $client = $this->mockClient;
        $index = 'test_index';
        $engine = new ScoutEngine($client, $index);

        $this->assertInstanceOf(ScoutEngine::class, $engine);
    }

    #[Test]
    public function it_extends_scout_engine_base_class(): void
    {
        $this->assertInstanceOf(\Laravel\Scout\Engines\Engine::class, $this->engine);
    }

    #[Test]
    public function it_can_update_models_in_index(): void
    {
        $models = new Collection([
            $this->createTestModel('1', ['title' => 'Test Document 1']),
            $this->createTestModel('2', ['title' => 'Test Document 2']),
        ]);

        $this->mockClient->setResponse('bulk', ResponseFactory::bulk([
            'took' => 10,
            'errors' => false,
            'items' => [
                ['update' => ['_id' => '1', 'result' => 'updated']],
                ['update' => ['_id' => '2', 'result' => 'updated']],
            ],
        ]));

        $this->engine->update($models);

        $this->assertTrue($this->mockClient->wasMethodCalled('bulk'));

        $bulkCalls = $this->mockClient->getMethodCalls('bulk');
        $this->assertCount(1, $bulkCalls);

        $bulkParams = $bulkCalls[0];
        $this->assertArrayHasKey('body', $bulkParams);
        $this->assertCount(4, $bulkParams['body']); // 2 models * 2 operations each
    }

    #[Test]
    public function it_can_delete_models_from_index(): void
    {
        $models = new Collection([
            $this->createTestModel('1'),
            $this->createTestModel('2'),
        ]);

        $this->mockClient->setResponse('bulk', ResponseFactory::bulk([
            'took' => 5,
            'errors' => false,
            'items' => [
                ['delete' => ['_id' => '1', 'result' => 'deleted']],
                ['delete' => ['_id' => '2', 'result' => 'deleted']],
            ],
        ]));

        $this->engine->delete($models);

        $this->assertTrue($this->mockClient->wasMethodCalled('bulk'));

        $bulkCalls = $this->mockClient->getMethodCalls('bulk');
        $bulkParams = $bulkCalls[0];
        $this->assertArrayHasKey('body', $bulkParams);
        $this->assertCount(2, $bulkParams['body']); // 2 delete operations
    }

    #[Test]
    public function it_can_flush_all_models_from_index(): void
    {
        $model = $this->createTestModel('1');

        $this->mockClient->setResponse('deleteByQuery', [
            'took' => 30,
            'timed_out' => false,
            'total' => 100,
            'deleted' => 100,
        ]);

        $this->engine->flush($model);

        $this->assertTrue($this->mockClient->wasMethodCalled('deleteByQuery'));

        $deleteByQueryCalls = $this->mockClient->getMethodCalls('deleteByQuery');
        $params = $deleteByQueryCalls[0];

        $this->assertEquals($this->testIndex, $params['index']);
        $this->assertArrayHasKey('body', $params);
        $this->assertArrayHasKey('query', $params['body']);
        $this->assertArrayHasKey('match_all', $params['body']['query']);
    }

    #[Test]
    public function it_can_perform_search_with_builder(): void
    {
        $builder = $this->createScoutBuilder('test query');

        $searchResponse = ResponseFactory::search([
            'hits' => [
                ['_id' => '1', '_source' => ['title' => 'Test Document 1']],
                ['_id' => '2', '_source' => ['title' => 'Test Document 2']],
            ],
            'total' => 2,
        ]);

        $this->mockClient->setResponse('search', $searchResponse);

        $result = $this->engine->search($builder);

        $this->assertTrue($this->mockClient->wasMethodCalled('search'));
        $this->assertIsArray($result);
        $this->assertEquals(2, $result['hits']['total']['value']);
    }

    #[Test]
    public function it_can_paginate_search_results(): void
    {
        $builder = $this->createScoutBuilder('test query');
        $perPage = 10;
        $page = 2;

        $searchResponse = ResponseFactory::search([
            'hits' => [
                ['_id' => '11', '_source' => ['title' => 'Test Document 11']],
                ['_id' => '12', '_source' => ['title' => 'Test Document 12']],
            ],
            'total' => 25,
        ]);

        $this->mockClient->setResponse('search', $searchResponse);

        $result = $this->engine->paginate($builder, $perPage, $page);

        $this->assertTrue($this->mockClient->wasMethodCalled('search'));
        $this->assertIsArray($result);

        // Check pagination parameters were applied
        $searchCalls = $this->mockClient->getMethodCalls('search');
        $searchParams = $searchCalls[0];

        $this->assertEquals(10, $searchParams['body']['from']); // (2 * 10) - 10
        $this->assertEquals(10, $searchParams['body']['size']);

        // Check nbPages calculation (25 total / 10 per page = 3 pages, ceiling)
        $this->assertEquals(3, $result['nbPages']);
    }

    #[Test]
    public function it_can_get_total_count_from_results(): void
    {
        $results = [
            'hits' => [
                'total' => 42,
            ],
        ];

        $count = $this->engine->getTotalCount($results);

        $this->assertEquals(42, $count);
    }

    #[Test]
    public function it_can_map_search_results_to_models(): void
    {
        $builder = $this->createScoutBuilder('test query');

        $results = [
            'hits' => [
                'total' => ['value' => 2],
                'hits' => [
                    ['_id' => '1', '_source' => ['title' => 'Test 1']],
                    ['_id' => '2', '_source' => ['title' => 'Test 2']],
                ],
            ],
        ];

        // Create a test model that can handle the query operations
        $testModel = $this->createTestModelForMapping();

        $mappedResults = $this->engine->map($builder, $results, $testModel);

        $this->assertInstanceOf(Collection::class, $mappedResults);
        $this->assertCount(2, $mappedResults);
    }

    #[Test]
    public function it_returns_empty_collection_when_no_search_results(): void
    {
        $builder = $this->createScoutBuilder('test query');

        $results = [
            'hits' => [
                'total' => ['value' => 0],
                'hits' => [],
            ],
        ];

        $testModel = $this->createTestModelForMapping();
        $mappedResults = $this->engine->map($builder, $results, $testModel);

        $this->assertInstanceOf(Collection::class, $mappedResults);
        $this->assertCount(0, $mappedResults);
    }

    #[Test]
    public function it_can_lazy_map_search_results(): void
    {
        $builder = $this->createScoutBuilder('test query');

        $results = [
            'hits' => [
                'total' => ['value' => 2],
                'hits' => [
                    ['_id' => '1', '_source' => ['title' => 'Test 1']],
                    ['_id' => '2', '_source' => ['title' => 'Test 2']],
                ],
            ],
        ];

        $testModel = $this->createTestModelForMapping();

        $lazyResults = $this->engine->lazyMap($builder, $results, $testModel);

        $this->assertInstanceOf(LazyCollection::class, $lazyResults);
        $this->assertCount(2, $lazyResults->all());
    }

    #[Test]
    public function it_returns_empty_lazy_collection_when_no_results(): void
    {
        $builder = $this->createScoutBuilder('test query');

        $results = [
            'hits' => [
                'total' => ['value' => 0],
                'hits' => [],
            ],
        ];

        $testModel = $this->createTestModelForMapping();
        $lazyResults = $this->engine->lazyMap($builder, $results, $testModel);

        $this->assertInstanceOf(LazyCollection::class, $lazyResults);
        $this->assertCount(0, $lazyResults->all());
    }

    #[Test]
    public function it_can_create_index(): void
    {
        $indexName = 'new_test_index';
        $options = [
            'mappings' => [
                'properties' => [
                    'title' => ['type' => 'text'],
                ],
            ],
        ];

        $this->mockClient->setResponse('indices.create', ResponseFactory::createIndex([
            'acknowledged' => true,
            'index' => $indexName,
        ]));

        $this->engine->createIndex($indexName, $options);

        $this->assertTrue($this->mockClient->wasMethodCalled('indices.create'));

        $createCalls = $this->mockClient->getMethodCalls('indices.create');
        $params = $createCalls[0];

        $this->assertEquals($indexName, $params['index']);
        $this->assertEquals($options, $params['body']);
    }

    #[Test]
    public function it_can_delete_index(): void
    {
        $indexName = 'index_to_delete';

        $this->mockClient->setResponse('indices.delete', ResponseFactory::deleteIndex([
            'acknowledged' => true,
        ]));

        $this->engine->deleteIndex($indexName);

        $this->assertTrue($this->mockClient->wasMethodCalled('indices.delete'));

        $deleteCalls = $this->mockClient->getMethodCalls('indices.delete');
        $params = $deleteCalls[0];

        $this->assertEquals($indexName, $params['index']);
    }

    #[Test]
    public function it_handles_search_with_filters(): void
    {
        $builder = $this->createScoutBuilder('test query');
        $builder->where('category', 'electronics');
        $builder->where('status', 'active');

        $searchResponse = ResponseFactory::search([
            'hits' => [
                ['_id' => '1', '_source' => ['title' => 'Laptop']],
            ],
            'total' => 1,
        ]);

        $this->mockClient->setResponse('search', $searchResponse);

        $result = $this->engine->search($builder);

        $this->assertTrue($this->mockClient->wasMethodCalled('search'));

        $searchCalls = $this->mockClient->getMethodCalls('search');
        $searchParams = $searchCalls[0];

        // Check that filters were applied
        $this->assertArrayHasKey('body', $searchParams);
        $this->assertArrayHasKey('query', $searchParams['body']);
        $this->assertArrayHasKey('bool', $searchParams['body']['query']);
        $this->assertArrayHasKey('must', $searchParams['body']['query']['bool']);

        $mustClauses = $searchParams['body']['query']['bool']['must'];
        $this->assertCount(3, $mustClauses); // query_string + 2 filters
    }

    #[Test]
    public function it_handles_search_with_limit(): void
    {
        $builder = $this->createScoutBuilder('test query');
        $builder->take(5);

        $searchResponse = ResponseFactory::search([
            'hits' => [
                ['_id' => '1', '_source' => ['title' => 'Test']],
            ],
            'total' => 1,
        ]);

        $this->mockClient->setResponse('search', $searchResponse);

        $result = $this->engine->search($builder);

        $this->assertTrue($this->mockClient->wasMethodCalled('search'));

        $searchCalls = $this->mockClient->getMethodCalls('search');
        $searchParams = $searchCalls[0];

        $this->assertEquals(5, $searchParams['body']['size']);
    }

    #[Test]
    public function it_extracts_ids_from_search_results(): void
    {
        $results = [
            'hits' => [
                'hits' => [
                    ['_id' => '1'],
                    ['_id' => '2'],
                ],
            ],
        ];

        $ids = $this->engine->mapIds($results);

        $this->assertInstanceOf(Collection::class, $ids);
        $this->assertCount(2, $ids);
        $this->assertEquals(['1', '2'], $ids->all());
    }

    #[Test]
    public function it_returns_empty_collection_for_empty_results(): void
    {
        $results = [];

        $ids = $this->engine->mapIds($results);

        $this->assertInstanceOf(Collection::class, $ids);
        $this->assertCount(0, $ids);
    }

    /**
     * Create a test Scout Builder instance
     */
    private function createScoutBuilder(string $query): Builder
    {
        $model = $this->createTestModel('1');
        $builder = new Builder($model, $query);

        return $builder;
    }

    /**
     * Create a test model instance
     */
    private function createTestModel(string $id, array $attributes = []): Model
    {
        $model = new class extends Model {
            protected $fillable = ['title', 'content', 'category', 'status'];

            public function toSearchableArray(): array
            {
                return $this->toArray();
            }
        };

        $model->setAttribute('id', $id);
        foreach ($attributes as $key => $value) {
            $model->setAttribute($key, $value);
        }

        return $model;
    }

    /**
     * Create a test model that can handle mapping operations
     */
    private function createTestModelForMapping(): Model
    {
        return new class extends Model {
            protected $fillable = ['title', 'content'];
            protected $table = 'test_models';

            public function toSearchableArray(): array
            {
                return $this->toArray();
            }

            // Override newQuery to return a mock query builder
            public function newQuery()
            {
                $query = parent::newQuery();

                // Mock the whereIn and get methods for testing
                return new class ($query) {
                    private $originalQuery;

                    public function __construct($originalQuery)
                    {
                        $this->originalQuery = $originalQuery;
                    }

                    public function whereIn($column, $values)
                    {
                        return $this;
                    }

                    public function get()
                    {
                        // Return test models based on the IDs that would be queried
                        return new Collection([
                            (new class extends Model {
                                protected $fillable = ['title'];
                                public function toSearchableArray(): array
                                {
                                    return $this->toArray();
                                }
                            })->forceFill(['id' => '1', 'title' => 'Test 1']),
                            (new class extends Model {
                                protected $fillable = ['title'];
                                public function toSearchableArray(): array
                                {
                                    return $this->toArray();
                                }
                            })->forceFill(['id' => '2', 'title' => 'Test 2']),
                        ])->keyBy('id');
                    }

                    public function __call($method, $arguments)
                    {
                        return $this->originalQuery->$method(...$arguments);
                    }
                };
            }
        };
    }
}
