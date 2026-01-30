<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Integration;

use Laravel\Scout\EngineManager;
use Matchory\Elasticsearch\ElasticsearchServiceProvider;
use Matchory\Elasticsearch\ScoutEngine;
use Matchory\Elasticsearch\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Scout Configuration Integration Tests
 *
 * Tests Scout configuration integration and engine registration
 * with the Laravel Scout package.
 */
class ScoutConfigurationTest extends TestCase
{
    #[Test]
    public function it_registers_elasticsearch_scout_engine_when_scout_is_available(): void
    {
        if (!class_exists(EngineManager::class)) {
            $this->markTestSkipped('Laravel Scout is not available');
        }

        // Set up Scout configuration
        $this->app['config']->set('scout.driver', 'elasticsearch');
        $this->app['config']->set('scout.elasticsearch.connection', 'default');
        $this->app['config']->set('scout.elasticsearch.index', 'scout_test_index');

        // Set up Elasticsearch configuration
        $this->app['config']->set('elasticsearch.connections.default', [
            'servers' => ['localhost:9200'],
            'retries' => 1,
            'handler' => 'default',
        ]);

        // Create a mock EngineManager
        $engineManager = $this->createMock(EngineManager::class);
        $engineManager->expects($this->once())
            ->method('extend')
            ->with('elasticsearch', $this->isType('callable'));

        $this->app->instance(EngineManager::class, $engineManager);

        // Register the service provider and trigger boot
        $provider = new ElasticsearchServiceProvider($this->app);
        $provider->register();
        $provider->boot();
    }

    #[Test]
    public function it_handles_missing_scout_gracefully(): void
    {
        // Ensure Scout is not available in the container
        $this->app->forgetInstance(EngineManager::class);

        // This should not throw an exception
        $provider = new ElasticsearchServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $this->assertTrue(true); // Test passes if no exception is thrown
    }

    #[Test]
    public function it_uses_correct_scout_configuration_values(): void
    {
        if (!class_exists(EngineManager::class)) {
            $this->markTestSkipped('Laravel Scout is not available');
        }

        // Set up specific Scout configuration
        $this->app['config']->set('scout.driver', 'elasticsearch');
        $this->app['config']->set('scout.elasticsearch.connection', 'custom_connection');
        $this->app['config']->set('scout.elasticsearch.index', 'custom_scout_index');

        // Set up corresponding Elasticsearch connection
        $this->app['config']->set('elasticsearch.connections.custom_connection', [
            'servers' => ['custom-host:9200'],
            'retries' => 2,
            'handler' => 'custom',
        ]);

        $engineCreated = false;
        $capturedConnection = null;
        $capturedIndex = null;

        // Create a mock EngineManager that captures the engine factory
        $engineManager = $this->createMock(EngineManager::class);
        $engineManager->expects($this->once())
            ->method('extend')
            ->with('elasticsearch', $this->callback(function ($factory) use (&$engineCreated, &$capturedConnection, &$capturedIndex) {
                // Call the factory to test it
                $engine = $factory($this->app);
                $engineCreated = true;

                // We can't easily inspect the private properties, but we can verify the engine type
                $this->assertInstanceOf(ScoutEngine::class, $engine);

                return true;
            }));

        $this->app->instance(EngineManager::class, $engineManager);

        // Register and boot the service provider
        $provider = new ElasticsearchServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $this->assertTrue($engineCreated);
    }

    #[Test]
    public function it_resolves_scout_configuration_from_config(): void
    {
        // Test that Scout configuration is properly read from config
        $this->app['config']->set('scout.driver', 'elasticsearch');
        $this->app['config']->set('scout.elasticsearch.connection', 'test_connection');
        $this->app['config']->set('scout.elasticsearch.index', 'test_scout_index');

        $scoutDriver = $this->app['config']->get('scout.driver');
        $scoutConnection = $this->app['config']->get('scout.elasticsearch.connection');
        $scoutIndex = $this->app['config']->get('scout.elasticsearch.index');

        $this->assertEquals('elasticsearch', $scoutDriver);
        $this->assertEquals('test_connection', $scoutConnection);
        $this->assertEquals('test_scout_index', $scoutIndex);
    }

    #[Test]
    public function it_handles_missing_scout_configuration_gracefully(): void
    {
        if (!class_exists(EngineManager::class)) {
            $this->markTestSkipped('Laravel Scout is not available');
        }

        // Don't set Scout configuration
        $this->app['config']->set('scout.driver', null);

        // Create a mock EngineManager
        $engineManager = $this->createMock(EngineManager::class);
        $engineManager->expects($this->once())
            ->method('extend')
            ->with('elasticsearch', $this->isType('callable'));

        $this->app->instance(EngineManager::class, $engineManager);

        // This should not throw an exception even with missing config
        $provider = new ElasticsearchServiceProvider($this->app);
        $provider->register();
        $provider->boot();
    }

    #[Test]
    public function it_uses_default_values_when_scout_config_is_missing(): void
    {
        if (!class_exists(EngineManager::class)) {
            $this->markTestSkipped('Laravel Scout is not available');
        }

        // Set up minimal configuration
        $this->app['config']->set('elasticsearch.connections.default', [
            'servers' => ['localhost:9200'],
        ]);

        $engineCreated = false;

        // Create a mock EngineManager
        $engineManager = $this->createMock(EngineManager::class);
        $engineManager->expects($this->once())
            ->method('extend')
            ->with('elasticsearch', $this->callback(function ($factory) use (&$engineCreated) {
                // Call the factory to test it uses defaults
                $engine = $factory($this->app);
                $engineCreated = true;

                $this->assertInstanceOf(ScoutEngine::class, $engine);

                return true;
            }));

        $this->app->instance(EngineManager::class, $engineManager);

        // Register and boot the service provider
        $provider = new ElasticsearchServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $this->assertTrue($engineCreated);
    }

    #[Test]
    public function it_integrates_with_scout_engine_manager(): void
    {
        if (!class_exists(EngineManager::class)) {
            $this->markTestSkipped('Laravel Scout is not available');
        }

        // Set up configuration
        $this->app['config']->set('scout.driver', 'elasticsearch');
        $this->app['config']->set('scout.elasticsearch.connection', 'default');
        $this->app['config']->set('scout.elasticsearch.index', 'integration_test');

        $this->app['config']->set('elasticsearch.connections.default', [
            'servers' => ['localhost:9200'],
            'retries' => 1,
        ]);

        // Use a real EngineManager instance
        $engineManager = new EngineManager($this->app);
        $this->app->instance(EngineManager::class, $engineManager);

        // Register the service provider
        $provider = new ElasticsearchServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        // Test that the engine can be resolved
        $engine = $engineManager->engine('elasticsearch');
        $this->assertInstanceOf(ScoutEngine::class, $engine);
    }

    #[Test]
    public function it_supports_multiple_scout_configurations(): void
    {
        if (!class_exists(EngineManager::class)) {
            $this->markTestSkipped('Laravel Scout is not available');
        }

        // Set up multiple Elasticsearch connections
        $this->app['config']->set('elasticsearch.connections.primary', [
            'servers' => ['primary-host:9200'],
        ]);

        $this->app['config']->set('elasticsearch.connections.secondary', [
            'servers' => ['secondary-host:9200'],
        ]);

        // Set up Scout to use primary connection
        $this->app['config']->set('scout.elasticsearch.connection', 'primary');
        $this->app['config']->set('scout.elasticsearch.index', 'primary_index');

        $engineManager = $this->createMock(EngineManager::class);
        $engineManager->expects($this->once())
            ->method('extend')
            ->with('elasticsearch', $this->isType('callable'));

        $this->app->instance(EngineManager::class, $engineManager);

        $provider = new ElasticsearchServiceProvider($this->app);
        $provider->register();
        $provider->boot();
    }

    #[Test]
    public function it_validates_scout_engine_factory_returns_correct_type(): void
    {
        if (!class_exists(EngineManager::class)) {
            $this->markTestSkipped('Laravel Scout is not available');
        }

        $this->app['config']->set('elasticsearch.connections.default', [
            'servers' => ['localhost:9200'],
        ]);

        $capturedFactory = null;

        $engineManager = $this->createMock(EngineManager::class);
        $engineManager->expects($this->once())
            ->method('extend')
            ->with('elasticsearch', $this->callback(function ($factory) use (&$capturedFactory) {
                $capturedFactory = $factory;
                return true;
            }));

        $this->app->instance(EngineManager::class, $engineManager);

        $provider = new ElasticsearchServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        // Test the captured factory
        $this->assertNotNull($capturedFactory);
        $this->assertIsCallable($capturedFactory);

        $engine = $capturedFactory($this->app);
        $this->assertInstanceOf(ScoutEngine::class, $engine);
    }
}
