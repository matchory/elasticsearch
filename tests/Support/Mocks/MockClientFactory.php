<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Support\Mocks;

use Matchory\Elasticsearch\Interfaces\ClientFactoryInterface;

/**
 * Mock client factory for testing
 *
 * This factory returns MockElasticsearchClient instances for testing purposes.
 * Since Elastic\Elasticsearch\Client is final in v9, we cannot use PHPUnit
 * mocks directly. This factory returns a duck-typed mock that has the same
 * methods as the real client.
 *
 * @internal Only for testing purposes
 */
class MockClientFactory implements ClientFactoryInterface
{
    private MockElasticsearchClient $mockClient;

    public function __construct(?MockElasticsearchClient $mockClient = null)
    {
        $this->mockClient = $mockClient ?? new MockElasticsearchClient();
    }

    /**
     * Creates a mock client, ignoring the config
     *
     * @param array<string, mixed> $config
     *
     * @return MockElasticsearchClient
     */
    public function createClient(array $config): object
    {
        return $this->mockClient;
    }

    /**
     * Get the mock client instance
     */
    public function getMockClient(): MockElasticsearchClient
    {
        return $this->mockClient;
    }

    /**
     * Set the mock client to be returned
     */
    public function setMockClient(MockElasticsearchClient $mockClient): void
    {
        $this->mockClient = $mockClient;
    }
}
