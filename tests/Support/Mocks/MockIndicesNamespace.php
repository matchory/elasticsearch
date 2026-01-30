<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Support\Mocks;

use Matchory\Elasticsearch\Tests\Support\Factories\ResponseFactory;

/**
 * Mock Elasticsearch indices namespace for testing
 *
 * Provides mock implementations of Elasticsearch indices operations
 * with realistic responses and proper call tracking.
 */
class MockIndicesNamespace
{
    private MockElasticsearchClient $client;

    public function __construct(MockElasticsearchClient $client)
    {
        $this->client = $client;
    }

    /**
     * Mock create index method
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        // Record the call in the parent client
        $this->client->recordCallPublic('indices.create', $params);

        // Return the configured response or default
        $response = $this->client->getResponsePublic('indices.create');
        return $response ?: ResponseFactory::createIndex([
            'index' => $params['index'] ?? 'test_index',
        ]);
    }

    /**
     * Mock delete index method
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function delete(array $params): array
    {
        // Record the call in the parent client
        $this->client->recordCallPublic('indices.delete', $params);

        // Return the configured response or default
        $response = $this->client->getResponsePublic('indices.delete');
        return $response ?: ResponseFactory::deleteIndex();
    }

    /**
     * Mock exists index method
     *
     * Returns an object with asBool() method to match ES client v9 behavior.
     *
     * @param array<string, mixed> $params
     * @return object
     */
    public function exists(array $params): object
    {
        // Record the call in the parent client
        $this->client->recordCallPublic('indices.exists', $params);

        // Return the configured response or default
        $response = $this->client->getResponsePublic('indices.exists');
        $boolValue = is_bool($response) ? $response : true;

        // Return an object with asBool() method to match ES client v9 behavior
        return new class($boolValue) {
            public function __construct(private bool $value) {}
            public function asBool(): bool { return $this->value; }
        };
    }

    /**
     * Mock put mapping method
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function putMapping(array $params): array
    {
        // Record the call in the parent client
        $this->client->recordCallPublic('indices.putMapping', $params);

        // Return the configured response or default
        $response = $this->client->getResponsePublic('indices.putMapping');
        return $response ?: ResponseFactory::putMapping();
    }

    /**
     * Mock get mapping method
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function getMapping(array $params): array
    {
        // Record the call in the parent client
        $this->client->recordCallPublic('indices.getMapping', $params);

        // Return the configured response or default
        $response = $this->client->getResponsePublic('indices.getMapping');
        return $response ?: ResponseFactory::getMapping([
            'index' => $params['index'] ?? 'test_index',
        ]);
    }

    /**
     * Mock put settings method
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function putSettings(array $params): array
    {
        // Record the call in the parent client
        $this->client->recordCallPublic('indices.putSettings', $params);

        // Return the configured response or default
        $response = $this->client->getResponsePublic('indices.putSettings');
        return $response ?: ResponseFactory::putSettings();
    }

    /**
     * Mock get settings method
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function getSettings(array $params): array
    {
        // Record the call in the parent client
        $this->client->recordCallPublic('indices.getSettings', $params);

        // Return the configured response or default
        $response = $this->client->getResponsePublic('indices.getSettings');
        return $response ?: ResponseFactory::getSettings([
            'index' => $params['index'] ?? 'test_index',
        ]);
    }

    /**
     * Mock update aliases method
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function updateAliases(array $params): array
    {
        // Record the call in the parent client
        $this->client->recordCallPublic('indices.updateAliases', $params);

        // Return the configured response or default
        $response = $this->client->getResponsePublic('indices.updateAliases');
        return $response ?: ResponseFactory::updateAliases();
    }

    /**
     * Mock get aliases method
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function getAliases(array $params): array
    {
        // Record the call in the parent client
        $this->client->recordCallPublic('indices.getAliases', $params);

        // Return the configured response or default
        $response = $this->client->getResponsePublic('indices.getAliases');
        return $response ?: ResponseFactory::getAliases([
            'index' => $params['index'] ?? 'test_index',
        ]);
    }

    /**
     * Mock close index method
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function close(array $params): array
    {
        // Record the call in the parent client
        $this->client->recordCallPublic('indices.close', $params);

        // Return the configured response or default
        $response = $this->client->getResponsePublic('indices.close');
        return $response ?: ResponseFactory::closeIndex();
    }

    /**
     * Mock open index method
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function open(array $params): array
    {
        // Record the call in the parent client
        $this->client->recordCallPublic('indices.open', $params);

        // Return the configured response or default
        $response = $this->client->getResponsePublic('indices.open');
        return $response ?: ResponseFactory::openIndex();
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
        return [];
    }
}
