<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Integration;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Matchory\Elasticsearch\Tests\Support\Factories\ResponseFactory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Scout Model Indexing Tests
 *
 * Tests model indexing, searching, and result handling through Scout,
 * focusing on the integration between Scout and the Elasticsearch engine.
 */
class ScoutModelIndexingTest extends ScoutTestCase
{
    protected string $testIndex = 'test_scout_models';

    #[Test]
    public function it_can_index_single_model(): void
    {
        $model = $this->createSearchableModel('1', [
            'title' => 'Test Document',
            'content' => 'This is a test document for indexing',
            'category' => 'test',
        ]);

        $models = new Collection([$model]);

        $this->mockClient->setResponse('bulk', ResponseFactory::bulk([
            'took' => 5,
            'errors' => false,
            'items' => [
                [
                    'update' => [
                        '_index' => $this->testIndex,
                        '_id' => '1',
                        '_version' => 1,
                        'result' => 'created',
                    ],
                ],
            ],
        ]));

        $this->engine->update($models);

        $this->assertTrue($this->mockClient->wasMethodCalled('bulk'));

        $bulkCalls = $this->mockClient->getMethodCalls('bulk');
        $bulkParams = $bulkCalls[0];

        $this->assertArrayHasKey('body', $bulkParams);
        $body = $bulkParams['body'];

        // Should have 2 items: update operation + document data
        $this->assertCount(2, $body);

        // Check update operation
        $updateOp = $body[0];
        $this->assertArrayHasKey('update', $updateOp);
        $this->assertEquals('1', $updateOp['update']['_id']);
        $this->assertEquals($this->testIndex, $updateOp['update']['_index']);

        // Check document data
        $docData = $body[1];
        $this->assertArrayHasKey('doc', $docData);
        $this->assertTrue($docData['doc_as_upsert']);
        $this->assertEquals('Test Document', $docData['doc']['title']);
        $this->assertEquals('This is a test document for indexing', $docData['doc']['content']);
        $this->assertEquals('test', $docData['doc']['category']);
    }

    #[Test]
    public function it_can_index_multiple_models(): void
    {
        $models = new Collection([
            $this->createSearchableModel('1', ['title' => 'First Document']),
            $this->createSearchableModel('2', ['title' => 'Second Document']),
            $this->createSearchableModel('3', ['title' => 'Third Document']),
        ]);

        $this->mockClient->setResponse('bulk', ResponseFactory::bulk([
            'took' => 15,
            'errors' => false,
            'items' => [
                ['update' => ['_id' => '1', 'result' => 'created']],
                ['update' => ['_id' => '2', 'result' => 'created']],
                ['update' => ['_id' => '3', 'result' => 'created']],
            ],
        ]));

        $this->engine->update($models);

        $this->assertTrue($this->mockClient->wasMethodCalled('bulk'));

        $bulkCalls = $this->mockClient->getMethodCalls('bulk');
        $bulkParams = $bulkCalls[0];

        // Should have 6 items: 3 models * 2 operations each
        $this->assertCount(6, $bulkParams['body']);

        // Verify all models are included
        $updateOps = array_filter($bulkParams['body'], fn($item) => isset($item['update']));
        $this->assertCount(3, $updateOps);
    }

    #[Test]
    public function it_can_delete_models_from_index(): void
    {
        $models = new Collection([
            $this->createSearchableModel('1', ['title' => 'Document to Delete 1']),
            $this->createSearchableModel('2', ['title' => 'Document to Delete 2']),
        ]);

        $this->mockClient->setResponse('bulk', ResponseFactory::bulk([
            'took' => 8,
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
        $body = $bulkParams['body'];

        // Debug output
        if (count($body) !== 2) {
            echo "Expected 2 operations, got " . count($body) . "\n";
            echo "Body content: " . json_encode($body) . "\n";
        }

        // Should have 2 delete operations
        $this->assertCount(2, $body);

        foreach ($body as $i => $operation) {
            $this->assertArrayHasKey('delete', $operation);
            $this->assertEquals($this->testIndex, $operation['delete']['_index']);

            $this->assertContains($operation['delete']['_id'], ['1', '2']);
        }
    }

    #[Test]
    public function it_uses_model_searchable_array_for_indexing(): void
    {
        $model = $this->createSearchableModel('1', [
            'title' => 'Test Document',
            'content' => 'Content',
            'private_field' => 'Should not be indexed',
        ]);

        // Override toSearchableArray to exclude private_field
        $model = new class extends Model {
            protected $fillable = ['title', 'content', 'private_field'];

            public function toSearchableArray(): array
            {
                return [
                    'title' => $this->title,
                    'content' => $this->content,
                    // private_field is intentionally excluded
                ];
            }
        };

        $model->setAttribute('id', '1');
        $model->setAttribute('title', 'Test Document');
        $model->setAttribute('content', 'Content');
        $model->setAttribute('private_field', 'Should not be indexed');

        $models = new Collection([$model]);

        $this->mockClient->setResponse('bulk', ResponseFactory::bulk());

        $this->engine->update($models);

        $bulkCalls = $this->mockClient->getMethodCalls('bulk');
        $bulkParams = $bulkCalls[0];
        $docData = $bulkParams['body'][1];

        // Should only include fields from toSearchableArray
        $this->assertArrayHasKey('title', $docData['doc']);
        $this->assertArrayHasKey('content', $docData['doc']);
        $this->assertArrayNotHasKey('private_field', $docData['doc']);
    }

    #[Test]
    public function it_can_search_indexed_models(): void
    {
        $builder = $this->createScoutBuilder('test search query');

        $searchResponse = ResponseFactory::search([
            'hits' => [
                [
                    '_id' => '1',
                    '_score' => 1.5,
                    '_source' => ['title' => 'Matching Document 1', 'content' => 'test content'],
                ],
                [
                    '_id' => '2',
                    '_score' => 1.2,
                    '_source' => ['title' => 'Matching Document 2', 'content' => 'test content'],
                ],
            ],
            'total' => 2,
        ]);

        $this->mockClient->setResponse('search', $searchResponse);

        $result = $this->engine->search($builder);

        $this->assertTrue($this->mockClient->wasMethodCalled('search'));
        $this->assertIsArray($result);
        $this->assertEquals(2, $result['hits']['total']['value']);
        $this->assertCount(2, $result['hits']['hits']);

        // Verify search parameters
        $searchCalls = $this->mockClient->getMethodCalls('search');
        $searchParams = $searchCalls[0];

        $this->assertEquals($this->testIndex, $searchParams['index']);
        $this->assertArrayHasKey('body', $searchParams);
        $this->assertArrayHasKey('query', $searchParams['body']);

        $query = $searchParams['body']['query'];
        $this->assertArrayHasKey('bool', $query);
        $this->assertArrayHasKey('must', $query['bool']);

        $mustClauses = $query['bool']['must'];
        $this->assertCount(1, $mustClauses);
        // ScoutEngine uses simple_query_string for safe handling of user input
        $this->assertArrayHasKey('simple_query_string', $mustClauses[0]);
        $this->assertEquals('test search query', $mustClauses[0]['simple_query_string']['query']);
    }

    #[Test]
    public function it_handles_search_with_where_clauses(): void
    {
        $builder = $this->createScoutBuilder('filtered search');
        $builder->where('category', 'electronics');
        $builder->where('status', 'active');

        $searchResponse = ResponseFactory::search([
            'hits' => [
                ['_id' => '1', '_source' => ['title' => 'Laptop', 'category' => 'electronics']],
            ],
            'total' => 1,
        ]);

        $this->mockClient->setResponse('search', $searchResponse);

        $result = $this->engine->search($builder);

        $searchCalls = $this->mockClient->getMethodCalls('search');
        $searchParams = $searchCalls[0];

        $mustClauses = $searchParams['body']['query']['bool']['must'];

        // Should have query_string + 2 filters
        $this->assertCount(3, $mustClauses);

        // Check filters are properly formatted
        $filters = array_slice($mustClauses, 1); // Skip query_string

        $this->assertArrayHasKey('match_phrase', $filters[0]);
        $this->assertArrayHasKey('category', $filters[0]['match_phrase']);
        $this->assertEquals('electronics', $filters[0]['match_phrase']['category']);

        $this->assertArrayHasKey('match_phrase', $filters[1]);
        $this->assertArrayHasKey('status', $filters[1]['match_phrase']);
        $this->assertEquals('active', $filters[1]['match_phrase']['status']);
    }

    #[Test]
    public function it_handles_search_with_limit(): void
    {
        $builder = $this->createScoutBuilder('limited search');
        $builder->take(5);

        $searchResponse = ResponseFactory::search([
            'hits' => array_map(fn($i) => [
                '_id' => (string) $i,
                '_source' => ['title' => "Document {$i}"],
            ], range(1, 5)),
            'total' => 100, // More results available
        ]);

        $this->mockClient->setResponse('search', $searchResponse);

        $result = $this->engine->search($builder);

        $searchCalls = $this->mockClient->getMethodCalls('search');
        $searchParams = $searchCalls[0];

        $this->assertEquals(5, $searchParams['body']['size']);
        $this->assertCount(5, $result['hits']['hits']);
    }

    #[Test]
    public function it_can_flush_all_models_of_type(): void
    {
        $model = $this->createSearchableModel('1');

        $this->mockClient->setResponse('deleteByQuery', [
            'took' => 50,
            'timed_out' => false,
            'total' => 150,
            'deleted' => 150,
            'batches' => 1,
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
    public function it_handles_bulk_indexing_errors_gracefully(): void
    {
        $models = new Collection([
            $this->createSearchableModel('1', ['title' => 'Valid Document']),
            $this->createSearchableModel('2', ['title' => 'Another Valid Document']),
        ]);

        $this->mockClient->setResponse('bulk', [
            'took' => 20,
            'errors' => true,
            'items' => [
                [
                    'update' => [
                        '_id' => '1',
                        'result' => 'created',
                    ],
                ],
                [
                    'update' => [
                        '_id' => '2',
                        'error' => [
                            'type' => 'version_conflict_engine_exception',
                            'reason' => 'version conflict',
                        ],
                    ],
                ],
            ],
        ]);

        // Should not throw an exception
        $this->engine->update($models);

        $this->assertTrue($this->mockClient->wasMethodCalled('bulk'));
    }

    #[Test]
    public function it_preserves_model_key_in_elasticsearch_id(): void
    {
        $model = $this->createSearchableModel('custom-key-123', [
            'title' => 'Document with Custom Key',
        ]);

        $models = new Collection([$model]);

        $this->mockClient->setResponse('bulk', ResponseFactory::bulk());

        $this->engine->update($models);

        $bulkCalls = $this->mockClient->getMethodCalls('bulk');
        $bulkParams = $bulkCalls[0];
        $updateOp = $bulkParams['body'][0];

        $this->assertEquals('custom-key-123', $updateOp['update']['_id']);
    }

    #[Test]
    public function it_handles_empty_model_collection(): void
    {
        $emptyCollection = new Collection([]);

        $this->mockClient->setResponse('bulk', ResponseFactory::bulk([
            'took' => 0,
            'errors' => false,
            'items' => [],
        ]));

        // Should not throw an exception
        $this->engine->update($emptyCollection);
        $this->engine->delete($emptyCollection);

        // Bulk should still be called but with empty body
        $this->assertTrue($this->mockClient->wasMethodCalled('bulk'));

        $bulkCalls = $this->mockClient->getMethodCalls('bulk');
        $this->assertCount(2, $bulkCalls); // update + delete calls

        foreach ($bulkCalls as $call) {
            $this->assertArrayHasKey('body', $call);
            $this->assertEmpty($call['body']);
        }
    }

    /**
     * Create a Scout Builder instance for testing
     */
    private function createScoutBuilder(string $query): Builder
    {
        $model = $this->createSearchableModel('1');
        return new Builder($model, $query);
    }

    /**
     * Create a searchable model instance
     */
    private function createSearchableModel(string $id, array $attributes = []): Model
    {
        $model = new class extends Model {
            protected $fillable = ['title', 'content', 'category', 'status', 'private_field'];
            protected $keyType = 'string'; // Ensure key is treated as string

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
}
