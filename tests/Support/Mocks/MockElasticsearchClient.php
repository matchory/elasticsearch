<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Support\Mocks;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Endpoints\Indices;
use Exception;
use Matchory\Elasticsearch\Tests\Support\Builders\MockScenarioBuilder;

/**
 * Mock Elasticsearch client for testing
 *
 * Provides a mock implementation of the Elasticsearch client that can be used
 * in tests without requiring a real Elasticsearch instance. Implements the
 * Elasticsearch client interface to ensure compatibility.
 */
class MockElasticsearchClient
{
    /**
     * @var array<string, array<int, array<mixed>>>
     */
    private array $methodCalls = [];

    /**
     * @var array<string, mixed>
     */
    private array $responses = [];

    /**
     * @var array<string, Exception>
     */
    private array $exceptions = [];

    /**
     * @var MockIndicesNamespace
     */
    private MockIndicesNamespace $indices;

    /**
     * @var bool
     */
    private bool $shouldThrowException = false;

    /**
     * @var string|null
     */
    private ?string $exceptionMethod = null;

    /**
     * @var bool Whether response exceptions are enabled (v9 compatibility)
     */
    private bool $responseExceptionEnabled = true;

    /**
     * @var bool Whether setResponseException was ever called
     */
    private bool $setResponseExceptionCalled = false;

    public function __construct()
    {
        $this->indices = new MockIndicesNamespace($this);
        $this->reset();
    }

    /**
     * Reset the mock client state
     */
    public function reset(): void
    {
        $this->methodCalls = [];
        $this->responses = [];
        $this->exceptions = [];
        $this->shouldThrowException = false;
        $this->exceptionMethod = null;
    }

    /**
     * Set a response for a specific method
     *
     * @param string $method
     * @param mixed $response
     */
    public function setResponse(string $method, mixed $response): void
    {
        $this->responses[$method] = $response;
    }

    /**
     * Set an exception to be thrown for a specific method
     *
     * @param string $method
     * @param Exception $exception
     */
    public function setException(string $method, Exception $exception): void
    {
        $this->exceptions[$method] = $exception;
    }

    /**
     * Configure the mock to throw an exception on the next method call
     *
     * @param string|null $method Specific method to throw on, or null for any method
     */
    public function shouldThrowException(?string $method = null): void
    {
        $this->shouldThrowException = true;
        $this->exceptionMethod = $method;
    }

    /**
     * Create a scenario builder for this mock client
     */
    public function scenario(): MockScenarioBuilder
    {
        return new MockScenarioBuilder($this);
    }

    /**
     * Check if a method was called
     *
     * @param string $method
     * @param array<mixed> $expectedParams
     */
    public function wasMethodCalled(string $method, array $expectedParams = []): bool
    {
        if (!isset($this->methodCalls[$method])) {
            return false;
        }

        if (empty($expectedParams)) {
            return true;
        }

        foreach ($this->methodCalls[$method] as $call) {
            if ($this->paramsMatch($call, $expectedParams)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all calls for a specific method
     *
     * @param string $method
     * @return array<int, array<mixed>>
     */
    public function getMethodCalls(string $method): array
    {
        return $this->methodCalls[$method] ?? [];
    }

    /**
     * Record a method call
     *
     * @param string $method
     * @param array<mixed> $params
     */
    private function recordCall(string $method, array $params = []): void
    {
        if (!isset($this->methodCalls[$method])) {
            $this->methodCalls[$method] = [];
        }

        $this->methodCalls[$method][] = $params;
    }

    /**
     * Check if parameters match expected parameters
     *
     * @param array<mixed> $actual
     * @param array<mixed> $expected
     */
    private function paramsMatch(array $actual, array $expected): bool
    {
        foreach ($expected as $key => $value) {
            if (!isset($actual[$key]) || $actual[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the default response for a method
     *
     * @param string $method
     * @return mixed
     * @throws Exception
     */
    private function getResponse(string $method): mixed
    {
        // Check if we should throw an exception
        if ($this->shouldThrowException && ($this->exceptionMethod === null || $this->exceptionMethod === $method)) {
            $this->shouldThrowException = false;
            $this->exceptionMethod = null;

            if (isset($this->exceptions[$method])) {
                throw $this->exceptions[$method];
            }

            throw new Exception("Mock exception for method: {$method}");
        }

        // Check for method-specific exception
        if (isset($this->exceptions[$method])) {
            throw $this->exceptions[$method];
        }

        return $this->responses[$method] ?? $this->getDefaultResponse($method);
    }

    /**
     * Get default response for common Elasticsearch methods
     *
     * @param string $method
     * @return mixed
     */
    private function getDefaultResponse(string $method): mixed
    {
        return match ($method) {
            'search' => [
                'took' => 1,
                'timed_out' => false,
                'hits' => [
                    'total' => ['value' => 0, 'relation' => 'eq'],
                    'max_score' => null,
                    'hits' => [],
                ],
            ],
            'index' => [
                '_index' => 'test_index',
                '_id' => '1',
                '_version' => 1,
                'result' => 'created',
            ],
            'get' => [
                '_index' => 'test_index',
                '_id' => '1',
                '_version' => 1,
                'found' => true,
                '_source' => [],
            ],
            'delete' => [
                '_index' => 'test_index',
                '_id' => '1',
                '_version' => 2,
                'result' => 'deleted',
            ],
            'update' => [
                '_index' => 'test_index',
                '_id' => '1',
                '_version' => 2,
                'result' => 'updated',
            ],
            'bulk' => [
                'took' => 1,
                'errors' => false,
                'items' => [],
            ],
            'count' => [
                'count' => 0,
                '_shards' => [
                    'total' => 1,
                    'successful' => 1,
                    'skipped' => 0,
                    'failed' => 0,
                ],
            ],
            'exists' => true,
            'explain' => [
                'matched' => true,
                'explanation' => [
                    'value' => 1.0,
                    'description' => 'match on required clause',
                ],
            ],
            'mget' => [
                'docs' => [],
            ],
            'msearch' => [
                'responses' => [],
            ],
            'scroll' => [
                'took' => 1,
                'timed_out' => false,
                '_scroll_id' => 'scroll_id_123',
                'hits' => [
                    'total' => ['value' => 0, 'relation' => 'eq'],
                    'max_score' => null,
                    'hits' => [],
                ],
            ],
            'clearScroll' => [
                'succeeded' => true,
                'num_freed' => 1,
            ],
            'info' => [
                'name' => 'test-node',
                'cluster_name' => 'test-cluster',
                'version' => [
                    'number' => '8.0.0',
                    'build_type' => 'docker',
                ],
            ],
            'updateByQuery' => [
                'took' => 10,
                'timed_out' => false,
                'total' => 5,
                'updated' => 5,
                'deleted' => 0,
                'batches' => 1,
                'version_conflicts' => 0,
                'noops' => 0,
                'failures' => [],
            ],
            'deleteByQuery' => [
                'took' => 10,
                'timed_out' => false,
                'total' => 5,
                'deleted' => 5,
                'batches' => 1,
                'version_conflicts' => 0,
                'noops' => 0,
                'failures' => [],
            ],
            default => [],
        };
    }

    /**
     * Mock search method
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function search(array $params = []): array
    {
        $this->recordCall('search', $params);
        return $this->getResponse('search');
    }

    /**
     * Mock index method
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function index(array $params): array
    {
        $this->recordCall('index', $params);
        return $this->getResponse('index');
    }

    /**
     * Mock get method
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function get(array $params): array
    {
        $this->recordCall('get', $params);
        return $this->getResponse('get');
    }

    /**
     * Mock delete method
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function delete(array $params): array
    {
        $this->recordCall('delete', $params);
        return $this->getResponse('delete');
    }

    /**
     * Mock update method
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function update(array $params): array
    {
        $this->recordCall('update', $params);
        return $this->getResponse('update');
    }

    /**
     * Mock bulk method
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function bulk(array $params): array
    {
        $this->recordCall('bulk', $params);
        return $this->getResponse('bulk');
    }

    /**
     * Get indices namespace
     */
    public function indices(): MockIndicesNamespace
    {
        return $this->indices;
    }

    /**
     * Mock exists method
     *
     * @param array<string, mixed> $params
     */
    public function exists(array $params): bool
    {
        $this->recordCall('exists', $params);
        $response = $this->getResponse('exists');
        return is_bool($response) ? $response : true;
    }

    /**
     * Mock count method
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function count(array $params = []): array
    {
        $this->recordCall('count', $params);
        return $this->getResponse('count');
    }

    /**
     * Mock explain method
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function explain(array $params): array
    {
        $this->recordCall('explain', $params);
        return $this->getResponse('explain');
    }

    /**
     * Mock mget method (multi-get)
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function mget(array $params): array
    {
        $this->recordCall('mget', $params);
        return $this->getResponse('mget');
    }

    /**
     * Mock msearch method (multi-search)
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function msearch(array $params): array
    {
        $this->recordCall('msearch', $params);
        return $this->getResponse('msearch');
    }

    /**
     * Mock scroll method
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function scroll(array $params): array
    {
        $this->recordCall('scroll', $params);
        return $this->getResponse('scroll');
    }

    /**
     * Mock clearScroll method
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function clearScroll(array $params): array
    {
        $this->recordCall('clearScroll', $params);
        return $this->getResponse('clearScroll');
    }

    /**
     * Handle dynamic method calls
     *
     * @param string $method
     * @param array<mixed> $arguments
     * @return mixed
     */
    public function __call(string $method, array $arguments): mixed
    {
        // Handle PHPUnit mock method calls gracefully - return $this for chaining
        // This allows tests written for PHPUnit mocks to work without modification
        $phpunitMockMethods = [
            'expects', 'method', 'with', 'withAnyParameters', 'willReturn',
            'willReturnSelf', 'willReturnArgument', 'willReturnCallback',
            'willReturnMap', 'willReturnOnConsecutiveCalls', 'willThrowException',
            'after', 'id', 'getMatcher', 'willReturnReference',
        ];

        if (in_array($method, $phpunitMockMethods, true)) {
            return $this;
        }

        $params = $arguments[0] ?? [];
        if (is_array($params)) {
            $this->recordCall($method, $params);
        } else {
            $this->recordCall($method, []);
        }
        return $this->getResponse($method);
    }

    /**
     * Public wrapper for recordCall to allow access from namespace classes
     */
    public function recordCallPublic(string $method, array $params = []): void
    {
        $this->recordCall($method, $params);
    }

    /**
     * Public wrapper for getResponse to allow access from namespace classes
     */
    public function getResponsePublic(string $method): mixed
    {
        return $this->getResponse($method);
    }

    /**
     * Set whether response exceptions are enabled (v9 compatibility)
     *
     * @param bool $enabled
     * @return self
     */
    public function setResponseException(bool $enabled): self
    {
        $this->responseExceptionEnabled = $enabled;
        $this->setResponseExceptionCalled = true;

        return $this;
    }

    /**
     * Get whether response exceptions are enabled
     */
    public function getResponseException(): bool
    {
        return $this->responseExceptionEnabled;
    }

    /**
     * Get whether response exceptions are enabled (alias)
     */
    public function getResponseExceptionEnabled(): bool
    {
        return $this->responseExceptionEnabled;
    }

    /**
     * Check if setResponseException was ever called
     */
    public function wasSetResponseExceptionCalled(): bool
    {
        return $this->setResponseExceptionCalled;
    }

    /**
     * Mock info method (for ping/health checks)
     *
     * @return array<string, mixed>
     */
    public function info(): array
    {
        $this->recordCall('info', []);
        return $this->getResponse('info');
    }

    /**
     * Mock updateByQuery method
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function updateByQuery(array $params): array
    {
        $this->recordCall('updateByQuery', $params);
        return $this->getResponse('updateByQuery');
    }

    /**
     * Mock deleteByQuery method
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function deleteByQuery(array $params): array
    {
        $this->recordCall('deleteByQuery', $params);
        return $this->getResponse('deleteByQuery');
    }
}
