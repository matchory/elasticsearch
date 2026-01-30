<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests;

use Illuminate\Foundation\Application;
use Matchory\Elasticsearch\ElasticsearchServiceProvider;
use Matchory\Elasticsearch\Interfaces\ClientFactoryInterface;
use Matchory\Elasticsearch\Interfaces\ConnectionInterface;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;
use Matchory\Elasticsearch\Model;
use Matchory\Elasticsearch\Tests\Support\Mocks\MockClientFactory;
use Matchory\Elasticsearch\Tests\Support\Mocks\MockElasticsearchClient;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * Base test case for Elasticsearch package tests
 *
 * Provides common setup and configuration for all tests in the package.
 * Extends Orchestra Testbench to provide Laravel application context.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Mock Elasticsearch client instance
     */
    protected MockElasticsearchClient $mockClient;

    /**
     * Mock client factory instance
     */
    protected MockClientFactory $mockFactory;

    /**
     * Setup the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMockElasticsearchClient();
        $this->setUpElasticsearchConfiguration();
    }

    /**
     * Get package providers
     *
     * @param Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ElasticsearchServiceProvider::class,
        ];
    }

    /**
     * Define environment setup
     *
     * @param Application $app
     */
    protected function defineEnvironment($app): void
    {
        // Set up basic Laravel configuration for testing
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set up Elasticsearch configuration
        $app['config']->set('elasticsearch.default', 'default');
        $app['config']->set('elasticsearch.connections.default', [
            'hosts' => ['localhost:9200'],
            'retries' => 2,
            'handler' => null,
        ]);

        // Set up Scout configuration for Scout integration tests
        $app['config']->set('scout.driver', 'elasticsearch');
        $app['config']->set('scout.elasticsearch.index', 'test_index');
    }

    /**
     * Set up mock Elasticsearch client
     *
     * Note: In Elasticsearch PHP client v9, the Client class is final and cannot
     * be mocked with PHPUnit. We use MockClientFactory which implements the
     * ClientFactoryInterface and returns MockElasticsearchClient instances.
     */
    protected function setUpMockElasticsearchClient(): void
    {
        $this->mockClient = new MockElasticsearchClient();
        $this->mockFactory = new MockClientFactory($this->mockClient);

        // Bind the mock factory in the container
        $this->app->bind(ClientFactoryInterface::class, fn() => $this->mockFactory);

        // Force recreation of the ConnectionResolver singleton so it picks up
        // the mock factory. This is necessary because the singleton might have
        // been created during application boot with the real factory.
        $this->app->forgetInstance(ConnectionResolverInterface::class);
        $this->app->forgetInstance(ConnectionInterface::class);
        $this->app->forgetInstance('elasticsearch');
        $this->app->forgetInstance('elasticsearch.resolver');

        // Re-set the Model's static resolver so it uses the new ConnectionManager
        // with our mock factory
        Model::setConnectionResolver(
            $this->app->make(ConnectionResolverInterface::class),
        );
    }

    /**
     * Set up Elasticsearch configuration for tests
     */
    protected function setUpElasticsearchConfiguration(): void
    {
        // Additional Elasticsearch-specific test setup can be added here
        // This method can be overridden in specific test classes for custom setup
    }

    /**
     * Get the mock Elasticsearch client
     */
    protected function getMockClient(): MockElasticsearchClient
    {
        return $this->mockClient;
    }

    /**
     * Reset the mock client state between tests
     */
    protected function resetMockClient(): void
    {
        $this->mockClient->reset();
    }

    /**
     * Create a test index configuration
     *
     * @param string $name
     * @param array<string, mixed> $mappings
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    protected function createTestIndexConfig(
        string $name = 'test_index',
        array $mappings = [],
        array $settings = [],
    ): array {
        return [
            'index' => $name,
            'body' => [
                'mappings' => $mappings ?: [
                    'properties' => [
                        'title' => ['type' => 'text'],
                        'content' => ['type' => 'text'],
                        'created_at' => ['type' => 'date'],
                    ],
                ],
                'settings' => $settings ?: [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                ],
            ],
        ];
    }

    /**
     * Create test document data
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function createTestDocument(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'title' => 'Test Document',
            'content' => 'This is a test document content',
            'created_at' => '2024-01-01T00:00:00Z',
            'status' => 'published',
        ], $overrides);
    }

    /**
     * Assert that the mock client received a specific method call
     *
     * @param string $method
     * @param array<mixed> $expectedParams
     */
    protected function assertClientMethodCalled(string $method, array $expectedParams = []): void
    {
        $this->assertTrue(
            $this->mockClient->wasMethodCalled($method, $expectedParams),
            "Expected Elasticsearch client method '{$method}' to be called",
        );
    }

    /**
     * Assert that the mock client did not receive a specific method call
     *
     * @param string $method
     */
    protected function assertClientMethodNotCalled(string $method): void
    {
        $this->assertFalse(
            $this->mockClient->wasMethodCalled($method),
            "Expected Elasticsearch client method '{$method}' to not be called",
        );
    }
}
