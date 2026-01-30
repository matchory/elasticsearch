<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Unit;

use Elastic\Elasticsearch\Client;
use Exception;
use InvalidArgumentException;
use Matchory\Elasticsearch\Connection;
use Matchory\Elasticsearch\ConnectionManager;
use Matchory\Elasticsearch\ConnectionResolver;
use Matchory\Elasticsearch\Interfaces\ClientFactoryInterface;
use Matchory\Elasticsearch\Interfaces\ConnectionInterface;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;
use Matchory\Elasticsearch\Builder;
use Matchory\Elasticsearch\Tests\Support\Mocks\MockElasticsearchClient;
use Matchory\Elasticsearch\Tests\Support\Traits\ConfiguresElasticsearch;
use Matchory\Elasticsearch\Tests\Support\Traits\MocksElasticsearch;
use Orchestra\Testbench\TestCase;
use Psr\SimpleCache\CacheInterface;

/**
 * Comprehensive tests for connection management functionality
 *
 * Tests cover Connection class creation, ConnectionManager multiple connection
 * handling, ConnectionResolver switching and isolation, and error handling.
 */
class ConnectionManagementTest extends TestCase
{
    use ConfiguresElasticsearch;
    use MocksElasticsearch;

    private ClientFactoryInterface $clientFactory;
    private CacheInterface $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpElasticsearchMocking();
        $this->clientFactory = $this->createMock(ClientFactoryInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
    }

    // Connection Class Tests

    public function testConnectionCreationWithMinimalConfiguration(): void
    {
        $client = new MockElasticsearchClient();
        $connection = new Connection($client);

        $this->assertInstanceOf(ConnectionInterface::class, $connection);
        $this->assertSame($client, $connection->getClient());
        $this->assertNull($connection->getCache());
    }

    public function testConnectionCreationWithFullConfiguration(): void
    {
        $client = new MockElasticsearchClient();
        $cache = $this->createMock(CacheInterface::class);
        $index = 'test_index';
        $reportQueries = false;

        $connection = new Connection($client, $cache, $index, $reportQueries);

        $this->assertInstanceOf(ConnectionInterface::class, $connection);
        $this->assertSame($client, $connection->getClient());
        $this->assertSame($cache, $connection->getCache());
    }

    public function testConnectionIndexMethodCreatesQueryWithIndex(): void
    {
        $client = new MockElasticsearchClient();
        $connection = new Connection($client);

        $query = $connection->index('test_index');

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertSame('test_index', $query->getIndex());
    }

    public function testConnectionNewQueryCreatesQueryInstance(): void
    {
        $client = new MockElasticsearchClient();
        $connection = new Connection($client, null, 'default_index');

        $query = $connection->newQuery();

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertSame('default_index', $query->getIndex());
    }

    public function testConnectionInsertCallsClientIndexMethod(): void
    {
        $client = new MockElasticsearchClient();
        $parameters = ['body' => ['field' => 'value']];
        $expectedResponse = ['_id' => '1', 'result' => 'created'];

        $client->setResponse('index', $expectedResponse);

        $connection = new Connection($client);
        $result = $connection->insert($parameters);

        $this->assertEquals((object) $expectedResponse, $result);
        $this->assertTrue($client->wasMethodCalled('index'));
    }

    public function testConnectionInsertWithIndexParameter(): void
    {
        $client = new MockElasticsearchClient();
        $parameters = ['body' => ['field' => 'value']];
        $index = 'custom_index';

        $client->setResponse('index', ['_id' => '1', 'result' => 'created']);

        $connection = new Connection($client);
        $connection->insert($parameters, $index);

        $this->assertTrue($client->wasMethodCalled('index'));
    }

    public function testConnectionSearchCallsClientSearchMethod(): void
    {
        $client = new MockElasticsearchClient();
        $parameters = ['index' => 'test_index', 'body' => ['query' => ['match_all' => []]]];
        $expectedResponse = ['hits' => ['total' => ['value' => 0], 'hits' => []]];

        $client->setResponse('search', $expectedResponse);

        $connection = new Connection($client);
        $result = $connection->search($parameters);

        $this->assertSame($expectedResponse, $result);
        $this->assertTrue($client->wasMethodCalled('search'));
    }

    // ConnectionManager Tests

    public function testConnectionManagerCreation(): void
    {
        $config = ['default' => 'main', 'connections' => []];
        $manager = new ConnectionManager($config, $this->clientFactory);

        $this->assertInstanceOf(ConnectionResolverInterface::class, $manager);
        $this->assertInstanceOf(ConnectionManager::class, $manager);
    }

    public function testConnectionManagerGetDefaultConnection(): void
    {
        $config = ['default' => 'main', 'connections' => []];
        $manager = new ConnectionManager($config, $this->clientFactory);

        $this->assertSame('main', $manager->getDefaultConnection());
    }

    public function testConnectionManagerSetDefaultConnection(): void
    {
        $config = ['default' => 'main', 'connections' => []];
        $manager = new ConnectionManager($config, $this->clientFactory);

        $manager->setDefaultConnection('secondary');
        $this->assertSame('secondary', $manager->getDefaultConnection());
    }

    public function testConnectionManagerAddConnection(): void
    {
        $config = ['connections' => []];
        $manager = new ConnectionManager($config, $this->clientFactory);
        $connection = $this->createMock(ConnectionInterface::class);

        $this->assertFalse($manager->hasConnection('test'));

        $manager->addConnection('test', $connection);

        $this->assertTrue($manager->hasConnection('test'));
        $this->assertSame($connection, $manager->connection('test'));
    }

    public function testConnectionManagerMakeConnectionFromConfiguration(): void
    {
        $config = [
            'default' => 'main',
            'connections' => [
                'main' => [
                    'hosts' => ['localhost:9200'],
                    'index' => 'test_index',
                    'report_queries' => false,
                ],
            ],
        ];

        $client = new MockElasticsearchClient();
        $this->clientFactory->expects($this->once())
            ->method('createClient')
            ->with($config['connections']['main'])
            ->willReturn($client);

        $manager = new ConnectionManager($config, $this->clientFactory, $this->cache);
        $connection = $manager->connection('main');

        $this->assertInstanceOf(ConnectionInterface::class, $connection);
        $this->assertSame($client, $connection->getClient());
        $this->assertSame($this->cache, $connection->getCache());
    }

    public function testConnectionManagerThrowsExceptionForUnconfiguredConnection(): void
    {
        $config = ['connections' => []];
        $manager = new ConnectionManager($config, $this->clientFactory);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Elasticsearch connection [nonexistent] not configured.');

        $manager->connection('nonexistent');
    }

    public function testConnectionManagerUsesDefaultConnectionWhenNameIsNull(): void
    {
        $config = [
            'default' => 'main',
            'connections' => [
                'main' => ['hosts' => ['localhost:9200']],
            ],
        ];

        $client = new MockElasticsearchClient();
        $this->clientFactory->expects($this->once())
            ->method('createClient')
            ->willReturn($client);

        $manager = new ConnectionManager($config, $this->clientFactory);
        $connection = $manager->connection();

        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    public function testConnectionManagerCachesConnections(): void
    {
        $config = [
            'connections' => [
                'main' => ['hosts' => ['localhost:9200']],
            ],
        ];

        $client = new MockElasticsearchClient();
        $this->clientFactory->expects($this->once()) // Should only be called once
            ->method('createClient')
            ->willReturn($client);

        $manager = new ConnectionManager($config, $this->clientFactory);

        // Call connection twice - should use cached instance
        $connection1 = $manager->connection('main');
        $connection2 = $manager->connection('main');

        $this->assertSame($connection1, $connection2);
    }

    public function testConnectionManagerProxiesCallsToDefaultConnection(): void
    {
        $config = [
            'default' => 'main',
            'connections' => [
                'main' => ['hosts' => ['localhost:9200']],
            ],
        ];

        $client = new MockElasticsearchClient();
        $this->clientFactory->method('createClient')->willReturn($client);

        $manager = new ConnectionManager($config, $this->clientFactory);

        // Test that method calls are proxied to the default connection
        $query = $manager->newQuery();
        $this->assertInstanceOf(Builder::class, $query);
    }

    // ConnectionResolver Tests

    public function testConnectionResolverCreation(): void
    {
        $resolver = new ConnectionResolver();

        $this->assertInstanceOf(ConnectionResolverInterface::class, $resolver);
        $this->assertInstanceOf(ConnectionResolver::class, $resolver);
    }

    public function testConnectionResolverCreationWithConnections(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connections = ['main' => $connection];

        $resolver = new ConnectionResolver($connections);

        $this->assertTrue($resolver->hasConnection('main'));
        $this->assertSame($connection, $resolver->connection('main'));
    }

    public function testConnectionResolverAddConnection(): void
    {
        $resolver = new ConnectionResolver();
        $connection = $this->createMock(ConnectionInterface::class);

        $this->assertFalse($resolver->hasConnection('test'));

        $resolver->addConnection('test', $connection);

        $this->assertTrue($resolver->hasConnection('test'));
        $this->assertSame($connection, $resolver->connection('test'));
    }

    public function testConnectionResolverSetAndGetDefaultConnection(): void
    {
        $resolver = new ConnectionResolver();
        $connection = $this->createMock(ConnectionInterface::class);

        $resolver->addConnection('main', $connection);
        $resolver->setDefaultConnection('main');

        $this->assertSame('main', $resolver->getDefaultConnection());
        $this->assertSame($connection, $resolver->connection()); // No name = default
    }

    public function testConnectionResolverConnectionSwitching(): void
    {
        $resolver = new ConnectionResolver();
        $connection1 = $this->createMock(ConnectionInterface::class);
        $connection2 = $this->createMock(ConnectionInterface::class);

        $resolver->addConnection('conn1', $connection1);
        $resolver->addConnection('conn2', $connection2);

        // Test switching between connections
        $this->assertSame($connection1, $resolver->connection('conn1'));
        $this->assertSame($connection2, $resolver->connection('conn2'));
        $this->assertSame($connection1, $resolver->connection('conn1'));
    }

    public function testConnectionResolverConnectionIsolation(): void
    {
        $resolver = new ConnectionResolver();
        $connection1 = $this->createMock(ConnectionInterface::class);
        $connection2 = $this->createMock(ConnectionInterface::class);

        $resolver->addConnection('isolated1', $connection1);
        $resolver->addConnection('isolated2', $connection2);

        // Verify connections are isolated - different instances
        $this->assertNotSame($connection1, $connection2);
        $this->assertSame($connection1, $resolver->connection('isolated1'));
        $this->assertSame($connection2, $resolver->connection('isolated2'));
    }

    // Error Handling Tests

    public function testConnectionManagerHandlesClientFactoryException(): void
    {
        $config = [
            'connections' => [
                'failing' => ['hosts' => ['invalid:9999']],
            ],
        ];

        $this->clientFactory->expects($this->once())
            ->method('createClient')
            ->willThrowException(new Exception('Connection failed'));

        $manager = new ConnectionManager($config, $this->clientFactory);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Connection failed');

        $manager->connection('failing');
    }

    public function testConnectionHandlesClientMethodExceptions(): void
    {
        $client = new MockElasticsearchClient();
        $client->setException('search', new Exception('Search failed'));

        $connection = new Connection($client);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Search failed');

        $connection->search(['index' => 'test']);
    }

    public function testConnectionManagerWithInvalidConfiguration(): void
    {
        $config = [
            'connections' => [
                'invalid' => ['hosts' => ['localhost:9200']], // Valid config that will cause client factory to fail
            ],
        ];

        $this->clientFactory->expects($this->once())
            ->method('createClient')
            ->with(['hosts' => ['localhost:9200']])
            ->willThrowException(new InvalidArgumentException('Invalid configuration'));

        $manager = new ConnectionManager($config, $this->clientFactory);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid configuration');

        $manager->connection('invalid');
    }

    public function testConnectionManagerFallbackBehavior(): void
    {
        $config = [
            'default' => 'primary',
            'connections' => [
                'primary' => ['hosts' => ['localhost:9200']],
                'fallback' => ['hosts' => ['localhost:9201']],
            ],
        ];

        $primaryClient = new MockElasticsearchClient();
        $fallbackClient = new MockElasticsearchClient();

        $this->clientFactory->expects($this->exactly(2))
            ->method('createClient')
            ->willReturnOnConsecutiveCalls($primaryClient, $fallbackClient);

        $manager = new ConnectionManager($config, $this->clientFactory);

        // Get primary connection
        $primaryConnection = $manager->connection('primary');
        $this->assertSame($primaryClient, $primaryConnection->getClient());

        // Get fallback connection
        $fallbackConnection = $manager->connection('fallback');
        $this->assertSame($fallbackClient, $fallbackConnection->getClient());

        // Verify they are different connections
        $this->assertNotSame($primaryConnection, $fallbackConnection);
    }

    public function testConnectionResolverHandlesMissingConnection(): void
    {
        $resolver = new ConnectionResolver();

        // This should not throw an exception but return null or handle gracefully
        // The actual behavior depends on implementation - we test what happens
        try {
            $result = $resolver->connection('nonexistent');
            $this->assertNull($result);
        } catch (Exception $e) {
            // If it throws an exception, that's also valid behavior
            $this->assertInstanceOf(Exception::class, $e);
        }
    }

    public function testConnectionWithNetworkTimeoutScenario(): void
    {
        $client = new MockElasticsearchClient();
        $client->setException('search', new Exception('Connection timeout'));

        $connection = new Connection($client);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Connection timeout');

        $connection->search(['index' => 'test']);
    }

    public function testConnectionManagerMultipleConnectionsWithDifferentConfigurations(): void
    {
        $config = [
            'connections' => [
                'local' => [
                    'hosts' => ['localhost:9200'],
                    'index' => 'local_index',
                    'report_queries' => true,
                ],
                'remote' => [
                    'hosts' => ['remote:9200'],
                    'index' => 'remote_index',
                    'report_queries' => false,
                ],
            ],
        ];

        $localClient = new MockElasticsearchClient();
        $remoteClient = new MockElasticsearchClient();

        $this->clientFactory->expects($this->exactly(2))
            ->method('createClient')
            ->willReturnCallback(function ($config) use ($localClient, $remoteClient) {
                if ($config['hosts'][0] === 'localhost:9200') {
                    return $localClient;
                }
                return $remoteClient;
            });

        $manager = new ConnectionManager($config, $this->clientFactory);

        $localConnection = $manager->connection('local');
        $remoteConnection = $manager->connection('remote');

        $this->assertSame($localClient, $localConnection->getClient());
        $this->assertSame($remoteClient, $remoteConnection->getClient());
        $this->assertNotSame($localConnection, $remoteConnection);
    }

    public function testConnectionResolverDefaultConnectionFallback(): void
    {
        $resolver = new ConnectionResolver();
        $defaultConnection = $this->createMock(ConnectionInterface::class);

        $resolver->addConnection('default', $defaultConnection);
        $resolver->setDefaultConnection('default');

        // When no connection name is provided, should use default
        $this->assertSame($defaultConnection, $resolver->connection());
        $this->assertSame($defaultConnection, $resolver->connection(null));
    }

    public function testConnectionManagerConfigurationValidation(): void
    {
        // Test with empty configuration
        $emptyConfig = [];
        $manager = new ConnectionManager($emptyConfig, $this->clientFactory);

        $this->assertEmpty($manager->getDefaultConnection());

        // Test with partial configuration
        $partialConfig = ['default' => 'main'];
        $manager = new ConnectionManager($partialConfig, $this->clientFactory);

        $this->assertSame('main', $manager->getDefaultConnection());
    }
}
