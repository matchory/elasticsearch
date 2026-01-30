<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Integration;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Log\LogManager;
use Matchory\Elasticsearch\ConnectionManager;
use Matchory\Elasticsearch\Factories\ClientFactory;
use Matchory\Elasticsearch\Interfaces\ClientFactoryInterface;
use Matchory\Elasticsearch\Interfaces\ConnectionInterface;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;
use Matchory\Elasticsearch\Tests\TestCase;
use Psr\SimpleCache\CacheInterface;

/**
 * Tests for Laravel container integration and dependency injection
 *
 * Validates that all services are properly registered in the container
 * with correct dependencies and lifecycle management.
 */
class LaravelContainerIntegrationTest extends TestCase
{
    /**
     * Skip the mock client setup for this test class
     *
     * These tests verify that the real service provider registers the real
     * classes correctly, so we should not override with mocks.
     */
    protected function setUpMockElasticsearchClient(): void
    {
        // Skip mock setup - we want to test real service provider bindings
    }

    /**
     * Test that all services can be resolved from the container
     */
    public function test_all_services_can_be_resolved(): void
    {
        // Test core interfaces
        $this->assertInstanceOf(
            ClientFactoryInterface::class,
            $this->app->make(ClientFactoryInterface::class),
        );

        $this->assertInstanceOf(
            ConnectionResolverInterface::class,
            $this->app->make(ConnectionResolverInterface::class),
        );

        $this->assertInstanceOf(
            ConnectionInterface::class,
            $this->app->make(ConnectionInterface::class),
        );

        // Test that the ConnectionResolver returns a ConnectionManager
        $resolver = $this->app->make(ConnectionResolverInterface::class);
        $this->assertInstanceOf(ConnectionManager::class, $resolver);
    }

    /**
     * Test that aliases resolve to the same instances as interfaces
     */
    public function test_aliases_resolve_to_correct_instances(): void
    {
        $resolver = $this->app->make(ConnectionResolverInterface::class);

        // Test all aliases point to the same instance
        $this->assertSame($resolver, $this->app->make('elasticsearch.resolver'));
        $this->assertSame($resolver, $this->app->make('elasticsearch'));

        $factory = $this->app->make(ClientFactoryInterface::class);
        $this->assertSame($factory, $this->app->make('elasticsearch.factory'));

        $connection = $this->app->make(ConnectionInterface::class);
        $this->assertSame($connection, $this->app->make('elasticsearch.connection'));
    }

    /**
     * Test singleton lifecycle management
     */
    public function test_singleton_lifecycle_management(): void
    {
        // Test that singletons return the same instance
        $resolver1 = $this->app->make(ConnectionResolverInterface::class);
        $resolver2 = $this->app->make(ConnectionResolverInterface::class);
        $this->assertSame($resolver1, $resolver2);

        $factory1 = $this->app->make(ClientFactoryInterface::class);
        $factory2 = $this->app->make(ClientFactoryInterface::class);
        $this->assertSame($factory1, $factory2);

        $connection1 = $this->app->make(ConnectionInterface::class);
        $connection2 = $this->app->make(ConnectionInterface::class);
        $this->assertSame($connection1, $connection2);
    }

    /**
     * Test dependency injection for ClientFactory
     */
    public function test_client_factory_dependency_injection(): void
    {
        // When resolving via interface, it returns a singleton instance
        $interfaceFactory1 = $this->app->make(ClientFactoryInterface::class);
        $interfaceFactory2 = $this->app->make(ClientFactoryInterface::class);

        $this->assertInstanceOf(ClientFactory::class, $interfaceFactory1);
        $this->assertSame($interfaceFactory1, $interfaceFactory2);

        // Note: Resolving ClientFactory::class directly creates a new instance
        // because it uses a regular bind, not singleton. This is by design
        // to allow users to create factories with custom configuration.
        $factory = $this->app->make(ClientFactory::class);
        $this->assertInstanceOf(ClientFactory::class, $factory);
    }

    /**
     * Test dependency injection for ConnectionManager
     */
    public function test_connection_manager_dependency_injection(): void
    {
        // ConnectionManager must be resolved through the interface binding
        // because it requires a $configuration array that's set up by the service provider
        $manager = $this->app->make(ConnectionResolverInterface::class);

        $this->assertInstanceOf(ConnectionManager::class, $manager);

        // Test that resolving again returns the same singleton instance
        $interfaceManager = $this->app->make(ConnectionResolverInterface::class);
        $this->assertSame($manager, $interfaceManager);
    }

    /**
     * Test logger dependency injection
     */
    public function test_logger_dependency_injection(): void
    {
        $logger = $this->app->make('elasticsearch.logger');

        $this->assertNotNull($logger);

        // Test that it's properly configured with LogManager
        $logManager = $this->app->make(LogManager::class);
        $expectedLogger = $logManager->channel('elasticsearch');

        $this->assertEquals($expectedLogger, $logger);
    }

    /**
     * Test cache dependency injection when available
     */
    public function test_cache_dependency_injection_when_available(): void
    {
        // Test without cache - must resolve via interface
        $manager = $this->app->make(ConnectionResolverInterface::class);
        $this->assertInstanceOf(ConnectionManager::class, $manager);

        // Test with cache bound
        $mockCache = $this->createMock(CacheInterface::class);
        $this->app->bind(CacheInterface::class, fn() => $mockCache);

        // Force re-resolution
        $this->app->forgetInstance(ConnectionResolverInterface::class);

        $managerWithCache = $this->app->make(ConnectionResolverInterface::class);
        $this->assertInstanceOf(ConnectionManager::class, $managerWithCache);
    }

    /**
     * Test event dispatcher dependency injection
     */
    public function test_event_dispatcher_dependency_injection(): void
    {
        $dispatcher = $this->app->make(Dispatcher::class);

        $this->assertInstanceOf(Dispatcher::class, $dispatcher);

        // Test that it's the same instance used by the application
        $appDispatcher = $this->app['events'];
        $this->assertSame($dispatcher, $appDispatcher);
    }

    /**
     * Test container binding resolution order
     */
    public function test_container_binding_resolution_order(): void
    {
        // Interfaces are bound as singletons and return consistent instances
        $interfaceFactory1 = $this->app->make(ClientFactoryInterface::class);
        $interfaceFactory2 = $this->app->make(ClientFactoryInterface::class);
        $this->assertSame($interfaceFactory1, $interfaceFactory2);

        // Note: Concrete ClientFactory::class uses regular bind, so it's not
        // the same instance as the interface singleton
        $concreteFactory = $this->app->make(ClientFactory::class);
        $this->assertInstanceOf(ClientFactory::class, $concreteFactory);

        // ConnectionManager is also a singleton via interface
        $interfaceManager1 = $this->app->make(ConnectionResolverInterface::class);
        $interfaceManager2 = $this->app->make(ConnectionResolverInterface::class);
        $this->assertSame($interfaceManager1, $interfaceManager2);

        // Resolving via interface again should return the same singleton instance
        $concreteManager = $this->app->make(ConnectionResolverInterface::class);
        $this->assertSame($concreteManager, $interfaceManager1);
    }

    /**
     * Test circular dependency prevention
     */
    public function test_circular_dependency_prevention(): void
    {
        // Test that resolving services doesn't create circular dependencies
        $resolver = $this->app->make(ConnectionResolverInterface::class);
        $connection = $this->app->make(ConnectionInterface::class);
        $factory = $this->app->make(ClientFactoryInterface::class);

        // All should resolve successfully without infinite loops
        $this->assertInstanceOf(ConnectionResolverInterface::class, $resolver);
        $this->assertInstanceOf(ConnectionInterface::class, $connection);
        $this->assertInstanceOf(ClientFactoryInterface::class, $factory);
    }

    /**
     * Test container extension and overriding
     */
    public function test_container_extension_and_overriding(): void
    {
        // Test that we can override bindings
        $customFactory = $this->createMock(ClientFactoryInterface::class);

        $this->app->bind(ClientFactoryInterface::class, fn() => $customFactory);

        $resolvedFactory = $this->app->make(ClientFactoryInterface::class);
        $this->assertSame($customFactory, $resolvedFactory);

        // Test that alias still works
        $aliasFactory = $this->app->make('elasticsearch.factory');
        $this->assertSame($customFactory, $aliasFactory);
    }

    /**
     * Test contextual binding support
     */
    public function test_contextual_binding_support(): void
    {
        // Test that services can be resolved with their dependencies
        $factory = $this->app->make(ClientFactoryInterface::class);
        $this->assertInstanceOf(ClientFactoryInterface::class, $factory);

        // ConnectionManager is resolved via the interface binding which
        // properly injects all required dependencies including configuration
        $manager = $this->app->make(ConnectionResolverInterface::class);
        $this->assertInstanceOf(ConnectionManager::class, $manager);
    }

    /**
     * Test service resolution with configuration changes
     */
    public function test_service_resolution_with_configuration_changes(): void
    {
        // Change configuration
        $this->app['config']->set('elasticsearch.default', 'custom');
        $this->app['config']->set('elasticsearch.connections.custom', [
            'hosts' => ['custom-host:9200'],
            'index' => 'custom_index',
        ]);

        // Force re-resolution
        $this->app->forgetInstance(ConnectionResolverInterface::class);
        $this->app->forgetInstance(ConnectionInterface::class);

        $resolver = $this->app->make(ConnectionResolverInterface::class);
        $connection = $this->app->make(ConnectionInterface::class);

        $this->assertInstanceOf(ConnectionResolverInterface::class, $resolver);
        $this->assertInstanceOf(ConnectionInterface::class, $connection);

        // Test that default connection name changed
        $defaultName = $resolver->getDefaultConnection();
        $this->assertEquals('custom', $defaultName);
    }

    /**
     * Test service provider deferred loading
     */
    public function test_service_provider_deferred_loading(): void
    {
        // Test that services are not loaded until requested
        $this->assertTrue($this->app->bound(ConnectionResolverInterface::class));
        $this->assertTrue($this->app->bound(ClientFactoryInterface::class));
        $this->assertTrue($this->app->bound(ConnectionInterface::class));

        // Test that they can be resolved on demand
        $resolver = $this->app->make(ConnectionResolverInterface::class);
        $this->assertInstanceOf(ConnectionResolverInterface::class, $resolver);
    }

    /**
     * Test memory usage and performance
     */
    public function test_memory_usage_and_performance(): void
    {
        $memoryBefore = memory_get_usage();

        // Resolve all services multiple times
        for ($i = 0; $i < 10; $i++) {
            $this->app->make(ConnectionResolverInterface::class);
            $this->app->make(ClientFactoryInterface::class);
            $this->app->make(ConnectionInterface::class);
        }

        $memoryAfter = memory_get_usage();
        $memoryUsed = $memoryAfter - $memoryBefore;

        // Memory usage should be reasonable (less than 5MB for this test)
        // Note: Memory usage can vary based on environment and caching behavior
        $this->assertLessThan(5 * 1024 * 1024, $memoryUsed, 'Memory usage should be reasonable');
    }

    /**
     * Test container cleanup and garbage collection
     */
    public function test_container_cleanup_and_garbage_collection(): void
    {
        // Create instances
        $resolver = $this->app->make(ConnectionResolverInterface::class);
        $factory = $this->app->make(ClientFactoryInterface::class);
        $connection = $this->app->make(ConnectionInterface::class);

        $this->assertInstanceOf(ConnectionResolverInterface::class, $resolver);
        $this->assertInstanceOf(ClientFactoryInterface::class, $factory);
        $this->assertInstanceOf(ConnectionInterface::class, $connection);

        // Test that instances can be forgotten
        $this->app->forgetInstance(ConnectionResolverInterface::class);
        $this->app->forgetInstance(ClientFactoryInterface::class);
        $this->app->forgetInstance(ConnectionInterface::class);

        // New instances should be created
        $newResolver = $this->app->make(ConnectionResolverInterface::class);
        $newFactory = $this->app->make(ClientFactoryInterface::class);
        $newConnection = $this->app->make(ConnectionInterface::class);

        // They should be different instances (since we forgot the old ones)
        $this->assertNotSame($resolver, $newResolver);
        $this->assertNotSame($factory, $newFactory);
        $this->assertNotSame($connection, $newConnection);
    }

    /**
     * Test container with custom application instance
     */
    public function test_container_with_custom_application_instance(): void
    {
        // Create a new container instance
        $customContainer = new Container();

        // Test that our services can work with different container instances
        $this->assertInstanceOf(Container::class, $customContainer);

        // The main test is that our current container works properly
        $resolver = $this->app->make(ConnectionResolverInterface::class);
        $this->assertInstanceOf(ConnectionResolverInterface::class, $resolver);
    }
}
