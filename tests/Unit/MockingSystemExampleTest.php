<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Unit;

use Matchory\Elasticsearch\Tests\Support\Mocks\MockElasticsearchClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Example test demonstrating the complete mocking system
 */
class MockingSystemExampleTest extends TestCase
{
    private MockElasticsearchClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new MockElasticsearchClient();
    }

    public function testCompleteSearchScenario(): void
    {
        // Configure a realistic search scenario with documents
        $documents = [
            ['id' => '1', 'title' => 'First Document', 'category' => 'tech'],
            ['id' => '2', 'title' => 'Second Document', 'category' => 'science'],
        ];

        $this->client->scenario()
            ->searchReturns($documents, [
                'took' => 15,
                'max_score' => 2.5,
            ])
            ->apply();

        $response = $this->client->search([
            'index' => 'articles',
            'body' => [
                'query' => ['match' => ['title' => 'document']],
            ],
        ]);

        // Verify the response structure
        $this->assertEquals(15, $response['took']);
        $this->assertEquals(2, $response['hits']['total']['value']);
        $this->assertEquals(2.5, $response['hits']['max_score']);
        $this->assertCount(2, $response['hits']['hits']);

        // Verify document content
        $firstHit = $response['hits']['hits'][0];
        $this->assertEquals('1', $firstHit['_id']);
        $this->assertEquals('First Document', $firstHit['_source']['title']);
        $this->assertEquals('tech', $firstHit['_source']['category']);
    }

    public function testErrorHandlingScenario(): void
    {
        // Configure an index not found error
        $this->client->scenario()
            ->indexNotFound('search', ['index' => 'missing_index'])
            ->apply();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Index [missing_index] not found');
        $this->expectExceptionCode(404);

        $this->client->search(['index' => 'missing_index']);
    }

    public function testBulkOperationScenario(): void
    {
        // Configure a bulk operation with mixed results
        $items = [
            [
                'index' => [
                    '_index' => 'test_index',
                    '_id' => '1',
                    '_version' => 1,
                    'result' => 'created',
                    'status' => 201,
                ],
            ],
            [
                'index' => [
                    '_index' => 'test_index',
                    '_id' => '2',
                    'status' => 409,
                    'error' => [
                        'type' => 'version_conflict_engine_exception',
                        'reason' => 'version conflict',
                    ],
                ],
            ],
        ];

        $this->client->scenario()
            ->bulkWithItems($items, ['took' => 25])
            ->apply();

        $response = $this->client->bulk([
            'body' => [
                ['index' => ['_index' => 'test_index', '_id' => '1']],
                ['title' => 'Document 1'],
                ['index' => ['_index' => 'test_index', '_id' => '2']],
                ['title' => 'Document 2'],
            ],
        ]);

        $this->assertEquals(25, $response['took']);
        $this->assertTrue($response['errors']);
        $this->assertCount(2, $response['items']);

        // First item should be successful
        $this->assertEquals(201, $response['items'][0]['index']['status']);
        $this->assertEquals('created', $response['items'][0]['index']['result']);

        // Second item should have an error
        $this->assertEquals(409, $response['items'][1]['index']['status']);
        $this->assertArrayHasKey('error', $response['items'][1]['index']);
    }

    public function testIndexManagementScenario(): void
    {
        // Test index creation
        $response = $this->client->indices()->create([
            'index' => 'new_index',
            'body' => [
                'mappings' => [
                    'properties' => [
                        'title' => ['type' => 'text'],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($response['acknowledged']);
        $this->assertTrue($response['shards_acknowledged']);
        $this->assertEquals('new_index', $response['index']);

        // Test mapping retrieval
        $mappingResponse = $this->client->indices()->getMapping([
            'index' => 'new_index',
        ]);

        $this->assertArrayHasKey('new_index', $mappingResponse);
        $this->assertArrayHasKey('mappings', $mappingResponse['new_index']);
    }

    public function testMultipleMethodConfiguration(): void
    {
        // Configure multiple methods in one scenario
        $this->client->scenario()
            ->searchReturnsEmpty()
            ->getReturnsNotFound(['id' => '999'])
            ->indexCreated(['id' => '1', 'result' => 'created'])
            ->count(['count' => 0])
            ->apply();

        // Test search returns empty
        $searchResponse = $this->client->search();
        $this->assertEquals(0, $searchResponse['hits']['total']['value']);
        $this->assertEmpty($searchResponse['hits']['hits']);

        // Test get returns not found
        $getResponse = $this->client->get(['id' => '999']);
        $this->assertFalse($getResponse['found']);

        // Test index returns created
        $indexResponse = $this->client->index(['id' => '1', 'body' => ['title' => 'Test']]);
        $this->assertEquals('created', $indexResponse['result']);
        $this->assertEquals(1, $indexResponse['_version']);

        // Test count returns zero
        $countResponse = $this->client->count();
        $this->assertEquals(0, $countResponse['count']);
    }

    public function testCallTracking(): void
    {
        // Perform various operations
        $this->client->search(['index' => 'test1']);
        $this->client->search(['index' => 'test2', 'size' => 10]);
        $this->client->index(['index' => 'test1', 'id' => '1', 'body' => ['title' => 'Test']]);

        // Verify call tracking
        $this->assertTrue($this->client->wasMethodCalled('search'));
        $this->assertTrue($this->client->wasMethodCalled('index'));
        $this->assertFalse($this->client->wasMethodCalled('delete'));

        // Verify specific parameter matching
        $this->assertTrue($this->client->wasMethodCalled('search', ['index' => 'test1']));
        $this->assertTrue($this->client->wasMethodCalled('search', ['size' => 10]));
        $this->assertFalse($this->client->wasMethodCalled('search', ['index' => 'nonexistent']));

        // Check call counts
        $searchCalls = $this->client->getMethodCalls('search');
        $this->assertCount(2, $searchCalls);

        $indexCalls = $this->client->getMethodCalls('index');
        $this->assertCount(1, $indexCalls);
    }

    public function testScenarioReset(): void
    {
        // Configure initial scenario
        $this->client->scenario()
            ->searchReturns([['id' => '1', 'title' => 'Test']])
            ->apply();

        $response1 = $this->client->search();
        $this->assertCount(1, $response1['hits']['hits']);

        // Reset and configure new scenario
        $this->client->reset();
        $this->client->scenario()
            ->searchReturnsEmpty()
            ->apply();

        $response2 = $this->client->search();
        $this->assertCount(0, $response2['hits']['hits']);

        // Verify that we can track new calls after reset
        $this->assertTrue($this->client->wasMethodCalled('search'));

        // But verify that only the most recent call is tracked
        $searchCalls = $this->client->getMethodCalls('search');
        $this->assertCount(1, $searchCalls); // Only the call after reset
    }
}
