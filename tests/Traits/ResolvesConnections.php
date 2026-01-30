<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Traits;

use Illuminate\Foundation\Application;
use Matchory\Elasticsearch\Connection;
use Matchory\Elasticsearch\ConnectionResolver;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;
use Matchory\Elasticsearch\Tests\Support\Mocks\MockElasticsearchClient;

/**
 * Trait ResolvesConnections
 *
 * Provides helper methods for tests that need to create connections with
 * mock Elasticsearch clients. Uses MockElasticsearchClient since the real
 * Client class is final in v9.
 */
trait ResolvesConnections
{
    /**
     * Mock Elasticsearch client
     */
    protected ?MockElasticsearchClient $elasticsearchClient = null;

    /**
     * Get or create the mock Elasticsearch client
     *
     * @return MockElasticsearchClient
     */
    public function mockClient(): MockElasticsearchClient
    {
        if ($this->elasticsearchClient === null) {
            $this->elasticsearchClient = new MockElasticsearchClient();
        }

        return $this->elasticsearchClient;
    }

    /**
     * Create a connection resolver with the mock client
     *
     * @return ConnectionResolver
     */
    public function createConnectionResolver(): ConnectionResolver
    {
        $mock = $this->mockClient();

        $connection = new Connection($mock);
        $connectionName = $this->getDefaultConnectionName();

        $resolver = new ConnectionResolver([
            $connectionName => $connection,
        ]);

        $resolver->setDefaultConnection($connectionName);

        return $resolver;
    }

    protected function getDefaultConnectionName(): string
    {
        return 'default';
    }

    /**
     * Register the mock connection resolver in the application
     *
     * @param Application $application
     */
    protected function registerResolver(Application $application): void
    {
        $resolver = $this->createConnectionResolver();

        $application->instance(
            ConnectionResolverInterface::class,
            $resolver,
        );
    }
}
