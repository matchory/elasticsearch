<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Unit;

use Exception;
use Matchory\Elasticsearch\Tests\Support\Factories\ErrorResponseFactory;
use Matchory\Elasticsearch\Tests\Support\Factories\ResponseFactory;
use Matchory\Elasticsearch\Tests\Support\Mocks\MockElasticsearchClient;
use PHPUnit\Framework\TestCase;

/**
 * Test the MockElasticsearchClient implementation
 */
class MockElasticsearchClientTest extends TestCase
{
    private MockElasticsearchClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new MockElasticsearchClient();
    }

    public function testBasicSearchResponse(): void
    {
        $response = $this->client->search(['index' => 'test']);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('took', $response);
        $this->assertArrayHasKey('hits', $response);
        $this->assertTrue($this->client->wasMethodCalled('search'));
    }

    public function testCustomResponseSetting(): void
    {
        $customResponse = ['custom' => 'response'];
        $this->client->setResponse('search', $customResponse);

        $response = $this->client->search();

        $this->assertEquals($customResponse, $response);
    }

    public function testExceptionThrowing(): void
    {
        $exception = new Exception('Test exception');
        $this->client->setException('search', $exception);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Test exception');

        $this->client->search();
    }

    public function testScenarioBuilder(): void
    {
        $documents = [
            ['id' => '1', 'title' => 'Test Document 1'],
            ['id' => '2', 'title' => 'Test Document 2'],
        ];

        $this->client->scenario()
            ->searchReturns($documents)
            ->apply();

        $response = $this->client->search();

        $this->assertCount(2, $response['hits']['hits']);
        $this->assertEquals('Test Document 1', $response['hits']['hits'][0]['_source']['title']);
    }

    public function testResponseFactory(): void
    {
        $response = ResponseFactory::search([
            'hits' => [
                ['_id' => '1', '_source' => ['title' => 'Test']],
            ],
            'total' => 1,
        ]);

        $this->assertArrayHasKey('hits', $response);
        $this->assertEquals(1, $response['hits']['total']['value']);
        $this->assertCount(1, $response['hits']['hits']);
    }

    public function testErrorResponseFactory(): void
    {
        $exception = ErrorResponseFactory::indexNotFound(['index' => 'missing_index']);

        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertStringContainsString('missing_index', $exception->getMessage());
        $this->assertEquals(404, $exception->getCode());
    }

    public function testMethodCallTracking(): void
    {
        $params = ['index' => 'test', 'body' => ['query' => ['match_all' => []]]];

        $this->client->search($params);

        $this->assertTrue($this->client->wasMethodCalled('search'));
        $this->assertTrue($this->client->wasMethodCalled('search', ['index' => 'test']));
        $this->assertFalse($this->client->wasMethodCalled('search', ['index' => 'other']));

        $calls = $this->client->getMethodCalls('search');
        $this->assertCount(1, $calls);
        $this->assertEquals($params, $calls[0]);
    }

    public function testReset(): void
    {
        $this->client->search();
        $this->client->setResponse('search', ['custom' => 'response']);

        $this->assertTrue($this->client->wasMethodCalled('search'));

        $this->client->reset();

        $this->assertFalse($this->client->wasMethodCalled('search'));

        // Should return default response after reset
        $response = $this->client->search();
        $this->assertNotEquals(['custom' => 'response'], $response);
    }
}
