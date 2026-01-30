<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Support\Builders;

use Exception;
use Matchory\Elasticsearch\Tests\Support\Factories\ErrorResponseFactory;
use Matchory\Elasticsearch\Tests\Support\Factories\ResponseFactory;
use Matchory\Elasticsearch\Tests\Support\Mocks\MockElasticsearchClient;

/**
 * Fluent builder for configuring mock Elasticsearch scenarios
 *
 * This class provides a fluent interface for setting up complex test scenarios
 * with the mock Elasticsearch client, making tests more readable and maintainable.
 */
class MockScenarioBuilder
{
    private MockElasticsearchClient $client;

    /**
     * @var array<string, mixed>
     */
    private array $responses = [];

    /**
     * @var array<string, Exception>
     */
    private array $exceptions = [];

    public function __construct(MockElasticsearchClient $client)
    {
        $this->client = $client;
    }

    /**
     * Configure a search scenario
     *
     * @param array<string, mixed> $options
     */
    public function search(array $options = []): self
    {
        $this->responses['search'] = ResponseFactory::search($options);
        return $this;
    }

    /**
     * Configure an index scenario
     *
     * @param array<string, mixed> $options
     */
    public function index(array $options = []): self
    {
        $this->responses['index'] = ResponseFactory::index($options);
        return $this;
    }

    /**
     * Configure a get scenario
     *
     * @param array<string, mixed> $options
     */
    public function get(array $options = []): self
    {
        $this->responses['get'] = ResponseFactory::get($options);
        return $this;
    }

    /**
     * Configure a delete scenario
     *
     * @param array<string, mixed> $options
     */
    public function delete(array $options = []): self
    {
        $this->responses['delete'] = ResponseFactory::delete($options);
        return $this;
    }

    /**
     * Configure an update scenario
     *
     * @param array<string, mixed> $options
     */
    public function update(array $options = []): self
    {
        $this->responses['update'] = ResponseFactory::update($options);
        return $this;
    }

    /**
     * Configure a bulk scenario
     *
     * @param array<string, mixed> $options
     */
    public function bulk(array $options = []): self
    {
        $this->responses['bulk'] = ResponseFactory::bulk($options);
        return $this;
    }

    /**
     * Configure a count scenario
     *
     * @param array<string, mixed> $options
     */
    public function count(array $options = []): self
    {
        $this->responses['count'] = ResponseFactory::count($options);
        return $this;
    }

    /**
     * Configure an exists scenario
     *
     * @param array<string, mixed> $options
     */
    public function exists(array $options = []): self
    {
        $this->responses['exists'] = ResponseFactory::exists($options);
        return $this;
    }

    /**
     * Configure an explain scenario
     *
     * @param array<string, mixed> $options
     */
    public function explain(array $options = []): self
    {
        $this->responses['explain'] = ResponseFactory::explain($options);
        return $this;
    }

    /**
     * Configure a multi-get scenario
     *
     * @param array<string, mixed> $options
     */
    public function mget(array $options = []): self
    {
        $this->responses['mget'] = ResponseFactory::mget($options);
        return $this;
    }

    /**
     * Configure a multi-search scenario
     *
     * @param array<string, mixed> $options
     */
    public function msearch(array $options = []): self
    {
        $this->responses['msearch'] = ResponseFactory::msearch($options);
        return $this;
    }

    /**
     * Configure a scroll scenario
     *
     * @param array<string, mixed> $options
     */
    public function scroll(array $options = []): self
    {
        $this->responses['scroll'] = ResponseFactory::scroll($options);
        return $this;
    }

    /**
     * Configure a clear scroll scenario
     *
     * @param array<string, mixed> $options
     */
    public function clearScroll(array $options = []): self
    {
        $this->responses['clearScroll'] = ResponseFactory::clearScroll($options);
        return $this;
    }

    /**
     * Configure search to return specific documents
     *
     * @param array<array<string, mixed>> $documents
     * @param array<string, mixed> $options
     */
    public function searchReturns(array $documents, array $options = []): self
    {
        $hits = array_map(function ($doc, $index) {
            return [
                '_id' => $doc['id'] ?? (string) ($index + 1),
                '_source' => $doc,
                '_score' => $doc['_score'] ?? 1.0,
            ];
        }, $documents, array_keys($documents));

        $options['hits'] = $hits;
        $options['total'] = $options['total'] ?? count($hits);

        return $this->search($options);
    }

    /**
     * Configure search to return empty results
     *
     * @param array<string, mixed> $options
     */
    public function searchReturnsEmpty(array $options = []): self
    {
        $options['hits'] = [];
        $options['total'] = 0;
        $options['max_score'] = null;

        return $this->search($options);
    }

    /**
     * Configure get to return a document
     *
     * @param array<string, mixed> $document
     * @param array<string, mixed> $options
     */
    public function getReturns(array $document, array $options = []): self
    {
        $options['source'] = $document;
        $options['found'] = true;

        return $this->get($options);
    }

    /**
     * Configure get to return document not found
     *
     * @param array<string, mixed> $options
     */
    public function getReturnsNotFound(array $options = []): self
    {
        $options['found'] = false;
        unset($options['source']);

        return $this->get($options);
    }

    /**
     * Configure index to return created result
     *
     * @param array<string, mixed> $options
     */
    public function indexCreated(array $options = []): self
    {
        $options['result'] = 'created';
        $options['version'] = 1;

        return $this->index($options);
    }

    /**
     * Configure index to return updated result
     *
     * @param array<string, mixed> $options
     */
    public function indexUpdated(array $options = []): self
    {
        $options['result'] = 'updated';
        $options['version'] = $options['version'] ?? 2;

        return $this->index($options);
    }

    /**
     * Configure bulk operation with mixed results
     *
     * @param array<array<string, mixed>> $items
     * @param array<string, mixed> $options
     */
    public function bulkWithItems(array $items, array $options = []): self
    {
        $options['items'] = $items;
        $options['errors'] = $options['errors'] ?? $this->hasErrors($items);

        return $this->bulk($options);
    }

    /**
     * Configure bulk operation with all successful items
     *
     * @param int $count
     * @param array<string, mixed> $options
     */
    public function bulkAllSuccessful(int $count, array $options = []): self
    {
        $items = [];
        for ($i = 1; $i <= $count; $i++) {
            $items[] = [
                'index' => [
                    '_index' => 'test_index',
                    '_id' => (string) $i,
                    '_version' => 1,
                    'result' => 'created',
                    'status' => 201,
                ],
            ];
        }

        return $this->bulkWithItems($items, $options);
    }

    /**
     * Configure an error scenario for a specific method
     *
     * @param string $method
     * @param string $errorType
     * @param array<string, mixed> $options
     */
    public function error(string $method, string $errorType, array $options = []): self
    {
        $this->exceptions[$method] = ErrorResponseFactory::create($errorType, $options);
        return $this;
    }

    /**
     * Configure a connection timeout error
     *
     * @param string $method
     * @param array<string, mixed> $options
     */
    public function connectionTimeout(string $method = 'search', array $options = []): self
    {
        return $this->error($method, 'connection_timeout', $options);
    }

    /**
     * Configure an index not found error
     *
     * @param string $method
     * @param array<string, mixed> $options
     */
    public function indexNotFound(string $method = 'search', array $options = []): self
    {
        return $this->error($method, 'index_not_found', $options);
    }

    /**
     * Configure a document not found error
     *
     * @param string $method
     * @param array<string, mixed> $options
     */
    public function documentNotFound(string $method = 'get', array $options = []): self
    {
        return $this->error($method, 'document_not_found', $options);
    }

    /**
     * Configure a version conflict error
     *
     * @param string $method
     * @param array<string, mixed> $options
     */
    public function versionConflict(string $method = 'update', array $options = []): self
    {
        return $this->error($method, 'version_conflict', $options);
    }

    /**
     * Configure a mapping conflict error
     *
     * @param string $method
     * @param array<string, mixed> $options
     */
    public function mappingConflict(string $method = 'index', array $options = []): self
    {
        return $this->error($method, 'mapping_conflict', $options);
    }

    /**
     * Configure a query parsing error
     *
     * @param string $method
     * @param array<string, mixed> $options
     */
    public function queryParsingError(string $method = 'search', array $options = []): self
    {
        return $this->error($method, 'query_parsing_error', $options);
    }

    /**
     * Configure a custom response for any method
     *
     * @param string $method
     * @param mixed $response
     */
    public function customResponse(string $method, mixed $response): self
    {
        $this->responses[$method] = $response;
        return $this;
    }

    /**
     * Configure a custom exception for any method
     *
     * @param string $method
     * @param Exception $exception
     */
    public function customException(string $method, Exception $exception): self
    {
        $this->exceptions[$method] = $exception;
        return $this;
    }

    /**
     * Apply all configured scenarios to the mock client
     */
    public function apply(): MockElasticsearchClient
    {
        // Apply responses
        foreach ($this->responses as $method => $response) {
            $this->client->setResponse($method, $response);
        }

        // Apply exceptions
        foreach ($this->exceptions as $method => $exception) {
            $this->client->setException($method, $exception);
        }

        return $this->client;
    }

    /**
     * Reset the builder state
     */
    public function reset(): self
    {
        $this->responses = [];
        $this->exceptions = [];
        return $this;
    }

    /**
     * Create a new scenario builder instance
     */
    public static function create(MockElasticsearchClient $client): self
    {
        return new self($client);
    }

    /**
     * Check if bulk items contain errors
     *
     * @param array<array<string, mixed>> $items
     */
    private function hasErrors(array $items): bool
    {
        foreach ($items as $item) {
            foreach ($item as $operation) {
                if (isset($operation['status']) && $operation['status'] >= 400) {
                    return true;
                }
                if (isset($operation['error'])) {
                    return true;
                }
            }
        }

        return false;
    }
}
