<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Integration;

use Matchory\Elasticsearch\Facades\Elasticsearch;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;
use Matchory\Elasticsearch\Tests\TestCase;

/**
 * Simplified tests for Elasticsearch facade resolution and method delegation
 *
 * Validates that the facade correctly resolves to the connection resolver
 * without complex method delegation testing that requires full mocking.
 */
class ElasticsearchFacadeSimpleTest extends TestCase
{
    /**
     * Test that facade resolves to the correct service
     */
    public function test_facade_resolves_to_connection_resolver(): void
    {
        $facadeRoot = Elasticsearch::getFacadeRoot();

        $this->assertInstanceOf(ConnectionResolverInterface::class, $facadeRoot);

        // Test that it's the same instance as registered in container
        $resolver = $this->app->make(ConnectionResolverInterface::class);
        $this->assertSame($resolver, $facadeRoot);
    }

    /**
     * Test facade accessor returns correct binding key
     */
    public function test_facade_accessor(): void
    {
        // Use reflection to access the protected method
        $reflection = new \ReflectionClass(Elasticsearch::class);
        $method = $reflection->getMethod('getFacadeAccessor');
        $method->setAccessible(true);
        $accessor = $method->invoke(null);

        $this->assertEquals(ConnectionResolverInterface::class, $accessor);
    }

    /**
     * Test that facade can be instantiated without errors
     */
    public function test_facade_instantiation(): void
    {
        // Test that the facade class exists and can be used
        $this->assertTrue(class_exists(Elasticsearch::class));

        // Test that the facade resolves to a ConnectionResolverInterface
        $facadeRoot = Elasticsearch::getFacadeRoot();
        $this->assertInstanceOf(ConnectionResolverInterface::class, $facadeRoot);
    }

    /**
     * Test facade static methods are callable
     */
    public function test_facade_static_methods_callable(): void
    {
        // Test that basic facade methods exist and are callable
        $this->assertTrue(method_exists(Elasticsearch::class, 'getFacadeRoot'));
        $this->assertTrue(method_exists(Elasticsearch::class, 'getFacadeAccessor'));

        // Test that the facade root can be resolved
        $root = Elasticsearch::getFacadeRoot();
        $this->assertNotNull($root);
    }

    /**
     * Test facade with Laravel container integration
     */
    public function test_facade_container_integration(): void
    {
        // Test that facade works with Laravel's container
        $resolver1 = $this->app->make(ConnectionResolverInterface::class);
        $resolver2 = Elasticsearch::getFacadeRoot();

        $this->assertSame($resolver1, $resolver2);
        $this->assertInstanceOf(ConnectionResolverInterface::class, $resolver1);
        $this->assertInstanceOf(ConnectionResolverInterface::class, $resolver2);
    }
}
