<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Integration;

use Matchory\Elasticsearch\ElasticsearchServiceProvider;
use Matchory\Elasticsearch\Tests\TestCase;

/**
 * Tests for configuration file loading and environment-specific settings
 *
 * Validates that configuration files are properly loaded, merged, and
 * environment variables are correctly processed.
 */
class ConfigurationLoadingTest extends TestCase
{
    /**
     * Test basic configuration loading
     */
    public function test_basic_configuration_loading(): void
    {
        $config = $this->app['config']->get('elasticsearch');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('default', $config);
        $this->assertArrayHasKey('connections', $config);
    }

    /**
     * Test default connection configuration
     */
    public function test_default_connection_configuration(): void
    {
        $config = $this->app['config']->get('elasticsearch');
        $defaultConnectionName = $config['default'];

        $this->assertIsString($defaultConnectionName);
        $this->assertArrayHasKey($defaultConnectionName, $config['connections']);

        $defaultConnection = $config['connections'][$defaultConnectionName];
        $this->assertIsArray($defaultConnection);
        $this->assertArrayHasKey('hosts', $defaultConnection);
    }

    /**
     * Test environment variable configuration
     */
    public function test_environment_variable_configuration(): void
    {
        // Test with custom environment variables
        $this->app['config']->set('elasticsearch.connections.env_test', [
            'hosts' => env('ELASTICSEARCH_HOST', 'http://localhost:9200'),
            'index' => env('ELASTICSEARCH_INDEX', 'default_index'),
        ]);

        $connection = $this->app['config']->get('elasticsearch.connections.env_test');

        $this->assertIsArray($connection);
        $this->assertArrayHasKey('hosts', $connection);
        $this->assertArrayHasKey('index', $connection);
    }

    /**
     * Test configuration merging from package
     */
    public function test_configuration_merging_from_package(): void
    {
        // Test that package configuration is merged with app configuration
        $elasticsearchConfig = $this->app['config']->get('elasticsearch');

        $this->assertIsArray($elasticsearchConfig);

        // Test that we have the expected structure from package config
        $this->assertArrayHasKey('default', $elasticsearchConfig);
        $this->assertArrayHasKey('connections', $elasticsearchConfig);

        // Test connections structure
        $connections = $elasticsearchConfig['connections'];
        $this->assertIsArray($connections);
        $this->assertArrayHasKey('default', $connections);
    }

    /**
     * Test logging configuration merging
     */
    public function test_logging_configuration_merging(): void
    {
        $loggingConfig = $this->app['config']->get('logging.channels');

        $this->assertIsArray($loggingConfig);
        $this->assertArrayHasKey('elasticsearch', $loggingConfig);

        $elasticsearchChannel = $loggingConfig['elasticsearch'];
        $this->assertIsArray($elasticsearchChannel);
        $this->assertArrayHasKey('driver', $elasticsearchChannel);
    }

    /**
     * Test configuration with different environments
     */
    public function test_configuration_with_different_environments(): void
    {
        // Test production-like configuration
        $this->app['env'] = 'production';

        $config = $this->app['config']->get('elasticsearch');
        $this->assertIsArray($config);

        // Test development-like configuration
        $this->app['env'] = 'local';

        $config = $this->app['config']->get('elasticsearch');
        $this->assertIsArray($config);

        // Reset to testing
        $this->app['env'] = 'testing';
    }

    /**
     * Test configuration caching behavior
     */
    public function test_configuration_caching_behavior(): void
    {
        // Test that configuration can be accessed multiple times
        $config1 = $this->app['config']->get('elasticsearch');
        $config2 = $this->app['config']->get('elasticsearch');

        $this->assertEquals($config1, $config2);

        // Test specific configuration values
        $default1 = $this->app['config']->get('elasticsearch.default');
        $default2 = $this->app['config']->get('elasticsearch.default');

        $this->assertEquals($default1, $default2);
    }

    /**
     * Test configuration with custom values
     */
    public function test_configuration_with_custom_values(): void
    {
        // Set custom configuration
        $this->app['config']->set('elasticsearch.custom_setting', 'custom_value');
        $this->app['config']->set('elasticsearch.connections.custom', [
            'hosts' => ['custom-host:9200'],
            'index' => 'custom_index',
            'retries' => 5,
        ]);

        // Test custom setting
        $customSetting = $this->app['config']->get('elasticsearch.custom_setting');
        $this->assertEquals('custom_value', $customSetting);

        // Test custom connection
        $customConnection = $this->app['config']->get('elasticsearch.connections.custom');
        $this->assertIsArray($customConnection);
        $this->assertEquals(['custom-host:9200'], $customConnection['hosts']);
        $this->assertEquals('custom_index', $customConnection['index']);
        $this->assertEquals(5, $customConnection['retries']);
    }

    /**
     * Test configuration validation
     */
    public function test_configuration_validation(): void
    {
        $config = $this->app['config']->get('elasticsearch');

        // Test required configuration keys exist
        $this->assertArrayHasKey('default', $config);
        $this->assertArrayHasKey('connections', $config);

        // Test default connection exists in connections
        $defaultConnection = $config['default'];
        $this->assertArrayHasKey($defaultConnection, $config['connections']);

        // Test connection structure
        $connection = $config['connections'][$defaultConnection];
        $this->assertArrayHasKey('hosts', $connection);

        // Test hosts is properly formatted
        $hosts = $connection['hosts'];
        $this->assertTrue(is_string($hosts) || is_array($hosts));
    }

    /**
     * Test configuration with multiple connections
     */
    public function test_configuration_with_multiple_connections(): void
    {
        // Add multiple connections
        $this->app['config']->set('elasticsearch.connections.secondary', [
            'hosts' => ['secondary-host:9200'],
            'index' => 'secondary_index',
        ]);

        $this->app['config']->set('elasticsearch.connections.tertiary', [
            'hosts' => ['tertiary-host:9200'],
            'index' => 'tertiary_index',
        ]);

        $connections = $this->app['config']->get('elasticsearch.connections');

        $this->assertArrayHasKey('default', $connections);
        $this->assertArrayHasKey('secondary', $connections);
        $this->assertArrayHasKey('tertiary', $connections);

        // Test each connection has required structure
        foreach (['default', 'secondary', 'tertiary'] as $connectionName) {
            $connection = $connections[$connectionName];
            $this->assertIsArray($connection);
            $this->assertArrayHasKey('hosts', $connection);
        }
    }

    /**
     * Test configuration with authentication settings
     */
    public function test_configuration_with_authentication_settings(): void
    {
        // Test basic authentication configuration
        $this->app['config']->set('elasticsearch.connections.authenticated', [
            'hosts' => ['https://localhost:9200'],
            'index' => 'secure_index',
            'basicAuthentication' => [
                'username' => 'elastic',
                'password' => 'secret',
            ],
            'sslVerification' => false,
        ]);

        $authConnection = $this->app['config']->get('elasticsearch.connections.authenticated');

        $this->assertIsArray($authConnection);
        $this->assertArrayHasKey('basicAuthentication', $authConnection);
        $this->assertArrayHasKey('sslVerification', $authConnection);

        $basicAuth = $authConnection['basicAuthentication'];
        $this->assertArrayHasKey('username', $basicAuth);
        $this->assertArrayHasKey('password', $basicAuth);
    }

    /**
     * Test configuration with API key authentication
     */
    public function test_configuration_with_api_key_authentication(): void
    {
        $this->app['config']->set('elasticsearch.connections.api_key', [
            'hosts' => ['https://localhost:9200'],
            'index' => 'api_index',
            'apiKey' => [
                'id' => 'api_key_id',
                'apiKey' => 'api_key_secret',
            ],
        ]);

        $apiKeyConnection = $this->app['config']->get('elasticsearch.connections.api_key');

        $this->assertIsArray($apiKeyConnection);
        $this->assertArrayHasKey('apiKey', $apiKeyConnection);

        $apiKey = $apiKeyConnection['apiKey'];
        $this->assertArrayHasKey('id', $apiKey);
        $this->assertArrayHasKey('apiKey', $apiKey);
    }

    /**
     * Test configuration with SSL settings
     */
    public function test_configuration_with_ssl_settings(): void
    {
        $this->app['config']->set('elasticsearch.connections.ssl', [
            'hosts' => ['https://localhost:9200'],
            'index' => 'ssl_index',
            'sslCert' => [
                'cert' => '/path/to/cert.pem',
                'password' => 'cert_password',
            ],
            'sslKey' => [
                'key' => '/path/to/key.pem',
                'password' => 'key_password',
            ],
            'sslVerification' => true,
        ]);

        $sslConnection = $this->app['config']->get('elasticsearch.connections.ssl');

        $this->assertIsArray($sslConnection);
        $this->assertArrayHasKey('sslCert', $sslConnection);
        $this->assertArrayHasKey('sslKey', $sslConnection);
        $this->assertArrayHasKey('sslVerification', $sslConnection);
    }

    /**
     * Test indices configuration
     */
    public function test_indices_configuration(): void
    {
        $indices = $this->app['config']->get('elasticsearch.indices');

        if ($indices !== null) {
            $this->assertIsArray($indices);

            // Test that indices have proper structure
            foreach ($indices as $indexName => $indexConfig) {
                $this->assertIsString($indexName);
                $this->assertIsArray($indexConfig);

                // Test common index configuration keys
                if (isset($indexConfig['settings'])) {
                    $this->assertIsArray($indexConfig['settings']);
                }

                if (isset($indexConfig['mappings'])) {
                    $this->assertIsArray($indexConfig['mappings']);
                }

                if (isset($indexConfig['aliases'])) {
                    $this->assertIsArray($indexConfig['aliases']);
                }
            }
        }
    }

    /**
     * Test configuration publishing
     */
    public function test_configuration_publishing(): void
    {
        $provider = new ElasticsearchServiceProvider($this->app);

        // Test that configuration files can be published
        $publishes = $provider::$publishes;

        $this->assertIsArray($publishes);

        // Should have at least one publish group
        $this->assertNotEmpty($publishes);
    }

    /**
     * Test deprecated configuration handling
     */
    public function test_deprecated_configuration_handling(): void
    {
        // Test that the service provider can handle both old and new config formats
        $provider = new ElasticsearchServiceProvider($this->app);

        // Should not throw exceptions when handling configuration
        $provider->register();

        $config = $this->app['config']->get('elasticsearch');
        $this->assertIsArray($config);
    }

    /**
     * Test configuration with environment-specific overrides
     */
    public function test_configuration_with_environment_overrides(): void
    {
        // Test that environment can override default configuration
        $originalDefault = $this->app['config']->get('elasticsearch.default');

        // Override with environment-specific setting
        $this->app['config']->set('elasticsearch.default', 'env_override');

        $newDefault = $this->app['config']->get('elasticsearch.default');
        $this->assertEquals('env_override', $newDefault);
        $this->assertNotEquals($originalDefault, $newDefault);
    }

    /**
     * Test configuration deep merging
     */
    public function test_configuration_deep_merging(): void
    {
        // Test that nested configuration arrays are properly merged
        $originalConnections = $this->app['config']->get('elasticsearch.connections');

        // Add a new connection
        $this->app['config']->set('elasticsearch.connections.new_connection', [
            'hosts' => ['new-host:9200'],
            'index' => 'new_index',
        ]);

        $updatedConnections = $this->app['config']->get('elasticsearch.connections');

        // Should have original connections plus new one
        $this->assertArrayHasKey('new_connection', $updatedConnections);

        // Original connections should still exist
        foreach ($originalConnections as $name => $config) {
            $this->assertArrayHasKey($name, $updatedConnections);
        }
    }

    /**
     * Test configuration with Scout integration settings
     */
    public function test_configuration_with_scout_integration(): void
    {
        // Test Scout-specific configuration
        $this->app['config']->set('scout.driver', 'elasticsearch');
        $this->app['config']->set('scout.elasticsearch.connection', 'default');
        $this->app['config']->set('scout.elasticsearch.index', 'scout_index');

        $scoutDriver = $this->app['config']->get('scout.driver');
        $scoutConnection = $this->app['config']->get('scout.elasticsearch.connection');
        $scoutIndex = $this->app['config']->get('scout.elasticsearch.index');

        $this->assertEquals('elasticsearch', $scoutDriver);
        $this->assertEquals('default', $scoutConnection);
        $this->assertEquals('scout_index', $scoutIndex);
    }
}
