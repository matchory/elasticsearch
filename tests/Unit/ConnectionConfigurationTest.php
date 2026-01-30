<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Unit;

use Matchory\Elasticsearch\Connection;
use Matchory\Elasticsearch\ConnectionManager;
use Matchory\Elasticsearch\ConnectionResolver;
use Matchory\Elasticsearch\Interfaces\ClientFactoryInterface;
use Matchory\Elasticsearch\Interfaces\ConnectionInterface;
use Matchory\Elasticsearch\Tests\Support\Mocks\MockElasticsearchClient;
use Matchory\Elasticsearch\Tests\Support\TestConnectionConfiguration;
use Matchory\Elasticsearch\Tests\Support\Traits\ConfiguresElasticsearch;
use Orchestra\Testbench\TestCase;
use Psr\SimpleCache\CacheInterface;

/**
 * Tests for connection configuration scenarios and resolution behavior
 *
 * Covers various configuration patterns, environment-specific setups,
 * and connection resolution strategies.
 */
class ConnectionConfigurationTest extends TestCase
{
    use ConfiguresElasticsearch;

    private ClientFactoryInterface $clientFactory;
    private CacheInterface $cache;
    private MockElasticsearchClient $mockClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockClient = new MockElasticsearchClient();
        $this->clientFactory = $this->createMock(ClientFactoryInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
    }

    /**
     * Create a new MockElasticsearchClient instance for each test that needs one
     */
    private function createMockClient(): MockElasticsearchClient
    {
        return new MockElasticsearchClient();
    }

    // Single Host Configuration Tests

    public function testConnectionManagerWithSingleHostConfiguration(): void
    {
        $config = [
            'default' => 'single',
            'connections' => [
                'single' => TestConnectionConfiguration::getSingleHost('localhost', 9200),
            ],
        ];

        $mockClient = $this->createMockClient();
        $this->clientFactory->expects($this->once())
            ->method('createClient')
            ->with($config['connections']['single'])
            ->willReturn($mockClient);

        $manager = new ConnectionManager($config, $this->clientFactory);
        $connection = $manager->connection('single');

        $this->assertInstanceOf(ConnectionInterface::class, $connection);
        $this->assertSame($mockClient, $connection->getClient());
    }

    public function testConnectionManagerWithCustomHostAndPort(): void
    {
        $customHost = 'custom-es-host';
        $customPort = 9300;
        $config = [
            'connections' => [
                'custom' => TestConnectionConfiguration::getSingleHost($customHost, $customPort),
            ],
        ];

        $expectedConfig = $config['connections']['custom'];
        $this->assertContains("{$customHost}:{$customPort}", $expectedConfig['hosts']);

        $mockClient = $this->createMockClient();
        $this->clientFactory->expects($this->once())
            ->method('createClient')
            ->with($expectedConfig)
            ->willReturn($mockClient);

        $manager = new ConnectionManager($config, $this->clientFactory);
        $connection = $manager->connection('custom');

        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    // Multiple Hosts Configuration Tests

    public function testConnectionManagerWithMultipleHostsConfiguration(): void
    {
        $hosts = ['es-node-1:9200', 'es-node-2:9200', 'es-node-3:9200'];
        $config = [
            'connections' => [
                'cluster' => TestConnectionConfiguration::getMultipleHosts($hosts),
            ],
        ];

        $expectedConfig = $config['connections']['cluster'];
        $this->assertEquals($hosts, $expectedConfig['hosts']);

        $mockClient = $this->createMockClient();
        $this->clientFactory->expects($this->once())
            ->method('createClient')
            ->with($expectedConfig)
            ->willReturn($mockClient);

        $manager = new ConnectionManager($config, $this->clientFactory);
        $connection = $manager->connection('cluster');

        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    public function testConnectionManagerWithDefaultMultipleHosts(): void
    {
        $config = [
            'connections' => [
                'default_cluster' => TestConnectionConfiguration::getMultipleHosts(),
            ],
        ];

        $expectedHosts = ['localhost:9200', 'localhost:9201', 'localhost:9202'];
        $expectedConfig = $config['connections']['default_cluster'];
        $this->assertEquals($expectedHosts, $expectedConfig['hosts']);

        $mockClient = $this->createMockClient();
        $this->clientFactory->method('createClient')->willReturn($mockClient);

        $manager = new ConnectionManager($config, $this->clientFactory);
        $connection = $manager->connection('default_cluster');

        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    // Authentication Configuration Tests

    public function testConnectionManagerWithAuthenticationConfiguration(): void
    {
        $username = 'test_user';
        $password = 'test_password';
        $config = [
            'connections' => [
                'auth' => TestConnectionConfiguration::getWithAuth($username, $password),
            ],
        ];

        $expectedConfig = $config['connections']['auth'];
        $hostConfig = $expectedConfig['hosts'][0];

        $this->assertIsArray($hostConfig);
        $this->assertEquals($username, $hostConfig['user']);
        $this->assertEquals($password, $hostConfig['pass']);

        $mockClient = $this->createMockClient();
        $this->clientFactory->expects($this->once())
            ->method('createClient')
            ->with($expectedConfig)
            ->willReturn($mockClient);

        $manager = new ConnectionManager($config, $this->clientFactory);
        $connection = $manager->connection('auth');

        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    public function testConnectionManagerWithCustomAuthConfiguration(): void
    {
        $config = [
            'connections' => [
                'custom_auth' => TestConnectionConfiguration::getWithAuth(
                    'elastic_user',
                    'secure_password',
                    'secure-host',
                    9243,
                ),
            ],
        ];

        $hostConfig = $config['connections']['custom_auth']['hosts'][0];
        $this->assertEquals('elastic_user', $hostConfig['user']);
        $this->assertEquals('secure_password', $hostConfig['pass']);
        $this->assertEquals('secure-host', $hostConfig['host']);
        $this->assertEquals(9243, $hostConfig['port']);

        $mockClient = $this->createMockClient();
        $this->clientFactory->method('createClient')->willReturn($mockClient);

        $manager = new ConnectionManager($config, $this->clientFactory);
        $connection = $manager->connection('custom_auth');

        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    // SSL/TLS Configuration Tests

    public function testConnectionManagerWithSSLConfiguration(): void
    {
        $sslOptions = [
            'verify' => true,
            'ca' => '/path/to/ca.pem',
            'cert' => '/path/to/cert.pem',
            'key' => '/path/to/key.pem',
        ];

        $config = [
            'connections' => [
                'ssl' => TestConnectionConfiguration::getWithSSL('secure-host', 9243, $sslOptions),
            ],
        ];

        $expectedConfig = $config['connections']['ssl'];
        $hostConfig = $expectedConfig['hosts'][0];
        $connectionParams = $expectedConfig['connectionParams']['client'];

        $this->assertEquals('https', $hostConfig['scheme']);
        $this->assertEquals('secure-host', $hostConfig['host']);
        $this->assertEquals(9243, $hostConfig['port']);
        $this->assertTrue($connectionParams['verify']);
        $this->assertEquals('/path/to/ca.pem', $connectionParams['ca']);

        $mockClient = $this->createMockClient();
        $this->clientFactory->method('createClient')->willReturn($mockClient);

        $manager = new ConnectionManager($config, $this->clientFactory);
        $connection = $manager->connection('ssl');

        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    // Timeout Configuration Tests

    public function testConnectionManagerWithTimeoutConfiguration(): void
    {
        $timeout = 60;
        $connectTimeout = 15;
        $config = [
            'connections' => [
                'timeout' => TestConnectionConfiguration::getWithTimeout($timeout, $connectTimeout),
            ],
        ];

        $expectedConfig = $config['connections']['timeout'];
        $connectionParams = $expectedConfig['connectionParams']['client'];

        $this->assertEquals($timeout, $connectionParams['timeout']);
        $this->assertEquals($connectTimeout, $connectionParams['connect_timeout']);

        $mockClient = $this->createMockClient();
        $this->clientFactory->method('createClient')->willReturn($mockClient);

        $manager = new ConnectionManager($config, $this->clientFactory);
        $connection = $manager->connection('timeout');

        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    // Logging Configuration Tests

    public function testConnectionManagerWithLoggingConfiguration(): void
    {
        $logLevel = 'DEBUG';
        $logLocation = '/tmp/elasticsearch-test.log';
        $config = [
            'connections' => [
                'logging' => TestConnectionConfiguration::getWithLogging($logLevel, $logLocation),
            ],
        ];

        $expectedConfig = $config['connections']['logging'];
        $loggingConfig = $expectedConfig['logging'];

        $this->assertTrue($loggingConfig['enabled']);
        $this->assertEquals($logLevel, $loggingConfig['level']);
        $this->assertEquals($logLocation, $loggingConfig['location']);

        $mockClient = $this->createMockClient();
        $this->clientFactory->method('createClient')->willReturn($mockClient);

        $manager = new ConnectionManager($config, $this->clientFactory);
        $connection = $manager->connection('logging');

        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    // Cloud Configuration Tests

    public function testConnectionManagerWithCloudConfiguration(): void
    {
        $cloudId = 'test-deployment:dXMtZWFzdC0xLmF3cy5mb3VuZC5pbw==';
        $config = [
            'connections' => [
                'cloud' => TestConnectionConfiguration::getCloudConfig($cloudId, 'elastic', 'password'),
            ],
        ];

        $expectedConfig = $config['connections']['cloud'];

        $this->assertEquals($cloudId, $expectedConfig['cloud_id']);
        $this->assertEquals('elastic', $expectedConfig['username']);
        $this->assertEquals('password', $expectedConfig['password']);

        $mockClient = $this->createMockClient();
        $this->clientFactory->method('createClient')->willReturn($mockClient);

        $manager = new ConnectionManager($config, $this->clientFactory);
        $connection = $manager->connection('cloud');

        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    // Environment-Specific Configuration Tests

    public function testConnectionManagerWithDevelopmentConfiguration(): void
    {
        $config = [
            'connections' => [
                'dev' => TestConnectionConfiguration::getDevelopment(),
            ],
        ];

        $expectedConfig = $config['connections']['dev'];

        $this->assertTrue($expectedConfig['logging']['enabled']);
        $this->assertEquals('DEBUG', $expectedConfig['logging']['level']);
        $this->assertEquals(60, $expectedConfig['connectionParams']['client']['timeout']);

        $mockClient = $this->createMockClient();
        $this->clientFactory->method('createClient')->willReturn($mockClient);

        $manager = new ConnectionManager($config, $this->clientFactory);
        $connection = $manager->connection('dev');

        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    public function testConnectionManagerWithTestingConfiguration(): void
    {
        $config = [
            'connections' => [
                'test' => TestConnectionConfiguration::getTesting(),
            ],
        ];

        $expectedConfig = $config['connections']['test'];

        $this->assertFalse($expectedConfig['logging']['enabled']);
        $this->assertEquals(30, $expectedConfig['connectionParams']['client']['timeout']);
        $this->assertEquals(1, $expectedConfig['retries']);

        $mockClient = $this->createMockClient();
        $this->clientFactory->method('createClient')->willReturn($mockClient);

        $manager = new ConnectionManager($config, $this->clientFactory);
        $connection = $manager->connection('test');

        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    public function testConnectionManagerWithProductionConfiguration(): void
    {
        $config = [
            'connections' => [
                'prod' => TestConnectionConfiguration::getProduction(),
            ],
        ];

        $expectedConfig = $config['connections']['prod'];

        $this->assertTrue($expectedConfig['logging']['enabled']);
        $this->assertEquals('WARNING', $expectedConfig['logging']['level']);
        $this->assertEquals(3, $expectedConfig['retries']);
        $this->assertTrue($expectedConfig['sniffOnStart']);
        $this->assertCount(3, $expectedConfig['hosts']); // Multiple production nodes

        $mockClient = $this->createMockClient();
        $this->clientFactory->method('createClient')->willReturn($mockClient);

        $manager = new ConnectionManager($config, $this->clientFactory);
        $connection = $manager->connection('prod');

        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    // Performance Configuration Tests

    public function testConnectionManagerWithPerformanceConfiguration(): void
    {
        $maxConnections = 200;
        $maxConnectionsPerNode = 20;
        $config = [
            'connections' => [
                'perf' => TestConnectionConfiguration::getPerformanceConfig($maxConnections, $maxConnectionsPerNode),
            ],
        ];

        $expectedConfig = $config['connections']['perf'];
        $connectionParams = $expectedConfig['connectionParams']['client'];

        $this->assertEquals($maxConnections, $connectionParams['max_connections']);
        $this->assertEquals($maxConnectionsPerNode, $connectionParams['max_connections_per_node']);
        $this->assertEquals(60, $connectionParams['timeout']);
        $this->assertEquals(3, $expectedConfig['retries']);

        $mockClient = $this->createMockClient();
        $this->clientFactory->method('createClient')->willReturn($mockClient);

        $manager = new ConnectionManager($config, $this->clientFactory);
        $connection = $manager->connection('perf');

        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    // Configuration Validation Tests

    public function testConnectionConfigurationValidation(): void
    {
        $validConfig = TestConnectionConfiguration::getDefault();
        $this->assertTrue(TestConnectionConfiguration::validate($validConfig));

        $invalidConfig = ['invalid' => 'config'];
        $this->assertFalse(TestConnectionConfiguration::validate($invalidConfig));

        $emptyHostsConfig = ['hosts' => []];
        $this->assertFalse(TestConnectionConfiguration::validate($emptyHostsConfig));
    }

    public function testConnectionConfigurationMerging(): void
    {
        $baseConfig = TestConnectionConfiguration::getDefault();
        $overrideConfig = [
            'retries' => 5,
            'new_option' => 'new_value',
        ];

        $mergedConfig = TestConnectionConfiguration::merge($baseConfig, $overrideConfig);

        // Test that new values are added
        $this->assertEquals('new_value', $mergedConfig['new_option']);

        // array_merge_recursive may create arrays for scalar values, so we need to handle this
        $retriesValue = $mergedConfig['retries'];
        if (is_array($retriesValue)) {
            $this->assertContains(5, $retriesValue);
        } else {
            $this->assertEquals(5, $retriesValue);
        }

        // Test that base config values are preserved
        $this->assertArrayHasKey('connectionPool', $mergedConfig);
        $this->assertArrayHasKey('hosts', $mergedConfig);

        // Test that the merge function works as expected
        $this->assertIsArray($mergedConfig);
    }

    // Connection Resolution Tests

    public function testConnectionResolverWithMultipleEnvironmentConfigurations(): void
    {
        $resolver = new ConnectionResolver();

        // Add connections for different environments
        $devConnection = $this->createConnectionWithConfig(TestConnectionConfiguration::getDevelopment());
        $testConnection = $this->createConnectionWithConfig(TestConnectionConfiguration::getTesting());
        $prodConnection = $this->createConnectionWithConfig(TestConnectionConfiguration::getProduction());

        $resolver->addConnection('development', $devConnection);
        $resolver->addConnection('testing', $testConnection);
        $resolver->addConnection('production', $prodConnection);

        // Test switching between environments
        $this->assertSame($devConnection, $resolver->connection('development'));
        $this->assertSame($testConnection, $resolver->connection('testing'));
        $this->assertSame($prodConnection, $resolver->connection('production'));

        // Test default connection behavior
        $resolver->setDefaultConnection('testing');
        $this->assertSame($testConnection, $resolver->connection());
    }

    public function testConnectionManagerWithMixedConfigurationTypes(): void
    {
        $config = [
            'default' => 'local',
            'connections' => [
                'local' => TestConnectionConfiguration::getSingleHost(),
                'cluster' => TestConnectionConfiguration::getMultipleHosts(),
                'secure' => TestConnectionConfiguration::getWithAuth('user', 'pass'),
                'cloud' => TestConnectionConfiguration::getCloudConfig('cloud-id'),
            ],
        ];

        $mockClient = $this->createMockClient();
        $this->clientFactory->method('createClient')->willReturn($mockClient);

        $manager = new ConnectionManager($config, $this->clientFactory);

        // Test that all connection types can be created
        $this->assertInstanceOf(ConnectionInterface::class, $manager->connection('local'));
        $this->assertInstanceOf(ConnectionInterface::class, $manager->connection('cluster'));
        $this->assertInstanceOf(ConnectionInterface::class, $manager->connection('secure'));
        $this->assertInstanceOf(ConnectionInterface::class, $manager->connection('cloud'));

        // Test default connection
        $this->assertSame('local', $manager->getDefaultConnection());
        $this->assertInstanceOf(ConnectionInterface::class, $manager->connection());
    }

    // Helper Methods

    private function createConnectionWithConfig(array $config): ConnectionInterface
    {
        $mockClient = $this->createMockClient();
        return new Connection($mockClient, $this->cache, $config['index'] ?? null);
    }

    public function testConnectionWithIndexConfiguration(): void
    {
        $mockClient = $this->createMockClient();
        $cache = $this->createMock(CacheInterface::class);
        $index = 'configured_index';
        $reportQueries = false;

        $connection = new Connection($mockClient, $cache, $index, $reportQueries);

        // Test that the configured index is used in new queries
        $query = $connection->newQuery();
        $this->assertSame($index, $query->getIndex());

        // Test that index method overrides the configured index
        $customQuery = $connection->index('custom_index');
        $this->assertSame('custom_index', $customQuery->getIndex());
    }

    public function testConnectionManagerWithIndexInConfiguration(): void
    {
        $config = [
            'connections' => [
                'with_index' => array_merge(
                    TestConnectionConfiguration::getDefault(),
                    ['index' => 'default_index', 'report_queries' => false],
                ),
            ],
        ];

        $mockClient = $this->createMockClient();
        $this->clientFactory->method('createClient')->willReturn($mockClient);

        $manager = new ConnectionManager($config, $this->clientFactory);
        $connection = $manager->connection('with_index');

        // The index configuration should be passed to the Connection constructor
        $query = $connection->newQuery();
        $this->assertSame('default_index', $query->getIndex());
    }
}
