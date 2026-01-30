<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Integration;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Log\LogManager;
use Matchory\Elasticsearch\Factories\ClientFactory;
use Matchory\Elasticsearch\Interfaces\ClientFactoryInterface;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;
use Matchory\Elasticsearch\Tests\TestCase;

/**
 * Simplified tests for Laravel container integration and dependency injection
 *
 * Validates that core services are properly registered in the container
 * without complex dependency resolution testing.
 */
class LaravelContainerSimpleTest extends TestCase
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
     * Test that core interfaces can be resolved from the container
     */
    public function test_core_interfaces_can_be_resolved(): void
    {
        // Test core interfaces
        $this->assertTrue($this->app->bound(ClientFactoryInterface::class));
        $this->assertTrue($this->app->bound(ConnectionResolverInterface::class));

        // Test that they can be resolved
        $factory = $this->app->make(ClientFactoryInterface::class);
        $this->assertInstanceOf(ClientFactoryInterface::class, $factory);

        $resolver = $this->app->make(ConnectionResolverInterface::class);
        $this->assertInstanceOf(ConnectionResolverInterface::class, $resolver);
    }

    /**
     * Test that aliases are properly bound
     */
    public function test_aliases_are_bound(): void
    {
        // Test all aliases are bound
        $this->assertTrue($this->app->bound('elasticsearch.factory'));
        $this->assertTrue($this->app->bound('elasticsearch.resolver'));
        $this->assertTrue($this->app->bound('elasticsearch'));
        $this->assertTrue($this->app->bound('elasticsearch.logger'));
    }

    /**
     * Test singleton lifecycle for core services
     */
    public function test_singleton_lifecycle(): void
    {
        // Test that singletons return the same instance
        $factory1 = $this->app->make(ClientFactoryInterface::class);
        $factory2 = $this->app->make(ClientFactoryInterface::class);
        $this->assertSame($factory1, $factory2);

        $resolver1 = $this->app->make(ConnectionResolverInterface::class);
        $resolver2 = $this->app->make(ConnectionResolverInterface::class);
        $this->assertSame($resolver1, $resolver2);
    }

    /**
     * Test that concrete classes can be resolved
     */
    public function test_concrete_classes_resolution(): void
    {
        // Test concrete class resolution
        $factory = $this->app->make(ClientFactory::class);
        $this->assertInstanceOf(ClientFactory::class, $factory);

        // Test that interface resolves to ClientFactory instance
        $interfaceFactory = $this->app->make(ClientFactoryInterface::class);
        $this->assertInstanceOf(ClientFactory::class, $interfaceFactory);

        // Note: They may not be the same instance since concrete class binding
        // is separate from interface binding in the service provider
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
     * Test container binding overrides
     */
    public function test_container_binding_overrides(): void
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
     * Test service resolution performance
     */
    public function test_service_resolution_performance(): void
    {
        $memoryBefore = memory_get_usage();

        // Resolve services multiple times
        for ($i = 0; $i < 5; $i++) {
            $this->app->make(ConnectionResolverInterface::class);
            $this->app->make(ClientFactoryInterface::class);
        }

        $memoryAfter = memory_get_usage();
        $memoryUsed = $memoryAfter - $memoryBefore;

        // Memory usage should be reasonable (less than 512KB for this test)
        $this->assertLessThan(512 * 1024, $memoryUsed, 'Memory usage should be reasonable');
    }

    /**
     * Test container cleanup
     */
    public function test_container_cleanup(): void
    {
        // Create instances
        $resolver = $this->app->make(ConnectionResolverInterface::class);
        $factory = $this->app->make(ClientFactoryInterface::class);

        $this->assertInstanceOf(ConnectionResolverInterface::class, $resolver);
        $this->assertInstanceOf(ClientFactoryInterface::class, $factory);

        // Test that instances can be forgotten
        $this->app->forgetInstance(ConnectionResolverInterface::class);
        $this->app->forgetInstance(ClientFactoryInterface::class);

        // New instances should be created
        $newResolver = $this->app->make(ConnectionResolverInterface::class);
        $newFactory = $this->app->make(ClientFactoryInterface::class);

        // They should be different instances (since we forgot the old ones)
        $this->assertNotSame($resolver, $newResolver);
        $this->assertNotSame($factory, $newFactory);
    }
}
