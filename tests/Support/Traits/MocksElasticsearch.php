<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Support\Traits;

use Matchory\Elasticsearch\Tests\Support\Mocks\MockElasticsearchClient;
use Matchory\Elasticsearch\Tests\Support\Factories\ResponseFactory;
use Matchory\Elasticsearch\Tests\Support\Factories\ErrorResponseFactory;
use Matchory\Elasticsearch\Tests\Support\Builders\MockScenarioBuilder;

/**
 * Trait for mocking Elasticsearch interactions in tests
 */
trait MocksElasticsearch
{
    protected MockElasticsearchClient $mockClient;
    protected ResponseFactory $responseFactory;
    protected ErrorResponseFactory $errorResponseFactory;
    protected MockScenarioBuilder $scenarioBuilder;

    /**
     * Set up Elasticsearch mocking
     */
    protected function setUpElasticsearchMocking(): void
    {
        $this->mockClient = new MockElasticsearchClient();
        $this->responseFactory = new ResponseFactory();
        $this->errorResponseFactory = new ErrorResponseFactory();
        $this->scenarioBuilder = new MockScenarioBuilder($this->mockClient);
    }

    /**
     * Get the mock Elasticsearch client
     *
     * @return MockElasticsearchClient
     */
    protected function getMockClient(): MockElasticsearchClient
    {
        return $this->mockClient;
    }

    /**
     * Get the response factory
     *
     * @return ResponseFactory
     */
    protected function getResponseFactory(): ResponseFactory
    {
        return $this->responseFactory;
    }

    /**
     * Get the error response factory
     *
     * @return ErrorResponseFactory
     */
    protected function getErrorResponseFactory(): ErrorResponseFactory
    {
        return $this->errorResponseFactory;
    }

    /**
     * Get the scenario builder
     *
     * @return MockScenarioBuilder
     */
    protected function getScenarioBuilder(): MockScenarioBuilder
    {
        return $this->scenarioBuilder;
    }

    /**
     * Mock a successful search response
     *
     * @param array $hits
     * @param int|null $total
     * @param array $aggregations
     * @return void
     */
    protected function mockSearchResponse(array $hits = [], ?int $total = null, array $aggregations = []): void
    {
        $total = $total ?? count($hits);
        $response = $this->responseFactory->search([
            'hits' => $hits,
            'total' => $total,
            'aggregations' => $aggregations,
        ]);
        $this->mockClient->setResponse('search', $response);
    }

    /**
     * Mock a successful index response
     *
     * @param string $id
     * @param string $index
     * @param string $result
     * @return void
     */
    protected function mockIndexResponse(string $id, string $index = 'test_index', string $result = 'created'): void
    {
        $response = $this->responseFactory->index([
            'id' => $id,
            'index' => $index,
            'result' => $result,
        ]);
        $this->mockClient->setResponse('index', $response);
    }

    /**
     * Mock a successful get response
     *
     * @param string $id
     * @param array $source
     * @param string $index
     * @return void
     */
    protected function mockGetResponse(string $id, array $source, string $index = 'test_index'): void
    {
        $response = $this->responseFactory->get([
            'id' => $id,
            'source' => $source,
            'index' => $index,
        ]);
        $this->mockClient->setResponse('get', $response);
    }

    /**
     * Mock a successful delete response
     *
     * @param string $id
     * @param string $index
     * @param string $result
     * @return void
     */
    protected function mockDeleteResponse(string $id, string $index = 'test_index', string $result = 'deleted'): void
    {
        $response = $this->responseFactory->delete([
            'id' => $id,
            'index' => $index,
            'result' => $result,
        ]);
        $this->mockClient->setResponse('delete', $response);
    }

    /**
     * Mock a successful bulk response
     *
     * @param array $items
     * @param bool $hasErrors
     * @return void
     */
    protected function mockBulkResponse(array $items = [], bool $hasErrors = false): void
    {
        $response = $this->responseFactory->bulk([
            'items' => $items,
            'errors' => $hasErrors,
        ]);
        $this->mockClient->setResponse('bulk', $response);
    }

    /**
     * Mock an error response
     *
     * @param string $method
     * @param string $errorType
     * @param string $reason
     * @param int $statusCode
     * @return void
     */
    protected function mockErrorResponse(
        string $method,
        string $errorType = 'index_not_found_exception',
        string $reason = 'Index not found',
        int $statusCode = 404,
    ): void {
        $exception = ErrorResponseFactory::create($errorType, [
            'message' => $reason,
        ]);
        $this->mockClient->setException($method, $exception);
    }

    /**
     * Mock a connection timeout error
     *
     * @param string $method
     * @return void
     */
    protected function mockConnectionTimeout(string $method = 'search'): void
    {
        $exception = ErrorResponseFactory::connectionTimeout();
        $this->mockClient->setException($method, $exception);
    }

    /**
     * Mock an index not found error
     *
     * @param string $index
     * @param string $method
     * @return void
     */
    protected function mockIndexNotFound(string $index, string $method = 'search'): void
    {
        $exception = ErrorResponseFactory::indexNotFound(['index' => $index]);
        $this->mockClient->setException($method, $exception);
    }

    /**
     * Mock a document not found error
     *
     * @param string $id
     * @param string $index
     * @return void
     */
    protected function mockDocumentNotFound(string $id, string $index = 'test_index'): void
    {
        $exception = ErrorResponseFactory::documentNotFound([
            'id' => $id,
            'index' => $index,
        ]);
        $this->mockClient->setException('get', $exception);
    }

    /**
     * Mock a mapping conflict error
     *
     * @param string $field
     * @param string $method
     * @return void
     */
    protected function mockMappingConflict(string $field, string $method = 'index'): void
    {
        $exception = ErrorResponseFactory::mappingConflict([
            'field' => $field,
        ]);
        $this->mockClient->setException($method, $exception);
    }

    /**
     * Mock a query parsing error
     *
     * @param string $query
     * @return void
     */
    protected function mockQueryParsingError(string $query): void
    {
        $exception = ErrorResponseFactory::queryParsingError([
            'query' => $query,
        ]);
        $this->mockClient->setException('search', $exception);
    }

    /**
     * Reset all mock responses
     *
     * @return void
     */
    protected function resetMockResponses(): void
    {
        $this->mockClient->reset();
    }

    /**
     * Assert that a method was called on the mock client
     *
     * @param string $method
     * @param int $times
     * @return void
     */
    protected function assertElasticsearchMethodCalled(string $method, int $times = 1): void
    {
        $calls = $this->mockClient->getMethodCalls($method);
        $this->assertCount($times, $calls, "Expected method '{$method}' to be called {$times} times");
    }

    /**
     * Assert that a method was called with specific parameters
     *
     * @param string $method
     * @param array $expectedParams
     * @return void
     */
    protected function assertElasticsearchMethodCalledWith(string $method, array $expectedParams): void
    {
        $calls = $this->mockClient->getCalls($method);
        $this->assertNotEmpty($calls, "Method {$method} was not called");

        $lastCall = end($calls);
        $this->assertEquals($expectedParams, $lastCall['params']);
    }

    /**
     * Get all calls made to a specific method
     *
     * @param string $method
     * @return array
     */
    protected function getElasticsearchCalls(string $method): array
    {
        return $this->mockClient->getMethodCalls($method);
    }

    /**
     * Get the last call made to a specific method
     *
     * @param string $method
     * @return array|null
     */
    protected function getLastElasticsearchCall(string $method): ?array
    {
        $calls = $this->mockClient->getMethodCalls($method);
        return empty($calls) ? null : end($calls);
    }
}
