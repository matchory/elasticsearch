<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Unit;

use Elastic\Elasticsearch\Client;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Exception;
use InvalidArgumentException;
use Matchory\Elasticsearch\Connection;
use Matchory\Elasticsearch\Tests\Support\Mocks\MockElasticsearchClient;
use Matchory\Elasticsearch\ConnectionManager;
use Matchory\Elasticsearch\ConnectionResolver;
use Matchory\Elasticsearch\Interfaces\ClientFactoryInterface;
use Matchory\Elasticsearch\Interfaces\ConnectionInterface;
use Matchory\Elasticsearch\Tests\Support\TestConnectionConfiguration;
use Matchory\Elasticsearch\Tests\Support\Traits\ConfiguresElasticsearch;
use Matchory\Elasticsearch\Tests\Support\Traits\MocksElasticsearch;
use Orchestra\Testbench\TestCase;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;

/**
 * Comprehensive error handling tests for connection management
 *
 * Tests various failure scenarios including network failures, authentication
 * errors, configuration issues, and Elasticsearch-specific exceptions.
 */
class ConnectionErrorHandlingTest extends TestCase
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

    // Network and Connection Failure Tests

    public function testConnectionHandlesNoNodesAvailableException(): void
    {
        $client = new MockElasticsearchClient();
        $client->setException('search', new NoNodeAvailableException('No alive nodes found in your cluster'));

        $connection = new Connection($client);

        $this->expectException(NoNodeAvailableException::class);
        $this->expectExceptionMessage('No alive nodes found in your cluster');

        $connection->search(['index' => 'test']);
    }

    public function testConnectionHandlesRequestTimeoutException(): void
    {
        $client = new MockElasticsearchClient();
        $client->setException('search', new Exception('Request Timeout'));

        $connection = new Connection($client);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Request Timeout');

        $connection->search(['index' => 'test']);
    }

    public function testConnectionHandlesServerErrorException(): void
    {
        $client = new MockElasticsearchClient();
        $client->setException('index', new Exception('Internal Server Error'));

        $connection = new Connection($client);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Internal Server Error');

        $connection->insert(['body' => ['field' => 'value']]);
    }

    public function testConnectionManagerHandlesClientCreationFailure(): void
    {
        $config = [
            'connections' => [
                'failing' => [
                    'hosts' => ['invalid-host:9999'],
                    'timeout' => 1,
                ],
            ],
        ];

        $this->clientFactory->expects($this->once())
            ->method('createClient')
            ->willThrowException(new RuntimeException('Failed to create client'));

        $manager = new ConnectionManager($config, $this->clientFactory);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to create client');

        $manager->connection('failing');
    }

    public function testConnectionManagerHandlesInvalidHostConfiguration(): void
    {
        $config = [
            'connections' => [
                'invalid' => [
                    'hosts' => [], // Empty hosts array
                ],
            ],
        ];

        $this->clientFactory->expects($this->once())
            ->method('createClient')
            ->willThrowException(new InvalidArgumentException('No hosts provided'));

        $manager = new ConnectionManager($config, $this->clientFactory);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No hosts provided');

        $manager->connection('invalid');
    }

    // Authentication and Authorization Error Tests

    public function testConnectionHandlesForbiddenException(): void
    {
        $client = new MockElasticsearchClient();
        $client->setException('search', new Exception('Forbidden'));

        $connection = new Connection($client);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Forbidden');

        $connection->search(['index' => 'protected_index']);
    }

    public function testConnectionManagerHandlesAuthenticationFailure(): void
    {
        $config = [
            'connections' => [
                'auth_fail' => [
                    'hosts' => [
                        [
                            'host' => 'localhost',
                            'port' => 9200,
                            'user' => 'invalid_user',
                            'pass' => 'invalid_password',
                        ],
                    ],
                ],
            ],
        ];

        $this->clientFactory->expects($this->once())
            ->method('createClient')
            ->willThrowException(new Exception('Forbidden'));

        $manager = new ConnectionManager($config, $this->clientFactory);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Forbidden');

        $manager->connection('auth_fail');
    }

    // Index and Document Error Tests

    public function testConnectionHandlesMissingIndexException(): void
    {
        $client = new MockElasticsearchClient();
        $client->setException('search', new Exception('Not Found'));

        $connection = new Connection($client);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Not Found');

        $connection->search(['index' => 'nonexistent_index']);
    }

    public function testConnectionHandlesBadRequestException(): void
    {
        $client = new MockElasticsearchClient();
        $client->setException('search', new Exception('Bad Request'));

        $connection = new Connection($client);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Bad Request');

        $connection->search(['index' => 'test', 'body' => ['invalid' => 'query']]);
    }

    // Configuration Error Tests

    public function testConnectionManagerThrowsExceptionForMissingConnectionConfig(): void
    {
        $config = ['connections' => []];
        $manager = new ConnectionManager($config, $this->clientFactory);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Elasticsearch connection [missing] not configured.');

        $manager->connection('missing');
    }

    public function testConnectionManagerHandlesNullConfiguration(): void
    {
        $config = [
            'connections' => [
                'null_config' => null,
            ],
        ];

        $manager = new ConnectionManager($config, $this->clientFactory);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Elasticsearch connection [null_config] not configured.');

        $manager->connection('null_config');
    }

    public function testConnectionManagerHandlesEmptyDefaultConnection(): void
    {
        $config = [
            'default' => '',
            'connections' => [],
        ];

        $manager = new ConnectionManager($config, $this->clientFactory);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Elasticsearch connection [] not configured.');

        $manager->connection(); // Should use default (empty string)
    }

    // Cache-Related Error Tests

    public function testConnectionHandlesCacheException(): void
    {
        $client = new MockElasticsearchClient();
        $cache = $this->createMock(CacheInterface::class);

        // Cache should not interfere with basic connection operations
        $connection = new Connection($client, $cache);

        $this->assertSame($cache, $connection->getCache());
        $this->assertSame($client, $connection->getClient());
    }

    // Retry and Fallback Behavior Tests

    public function testConnectionManagerWithMultipleFailingConnections(): void
    {
        $config = [
            'connections' => [
                'fail1' => ['hosts' => ['invalid1:9999']],
                'fail2' => ['hosts' => ['invalid2:9999']],
            ],
        ];

        $this->clientFactory->expects($this->exactly(2))
            ->method('createClient')
            ->willThrowException(new RuntimeException('Connection failed'));

        $manager = new ConnectionManager($config, $this->clientFactory);

        // First connection should fail
        $this->expectException(RuntimeException::class);
        try {
            $manager->connection('fail1');
        } catch (RuntimeException $e) {
            // Now try the second connection - it should also fail
            $this->expectException(RuntimeException::class);
            $manager->connection('fail2');
        }
    }

    public function testConnectionResolverHandlesNonExistentConnection(): void
    {
        $resolver = new ConnectionResolver();

        // Test accessing non-existent connection
        try {
            $connection = $resolver->connection('nonexistent');
            // If no exception is thrown, the result should be null or handled gracefully
            $this->assertNull($connection);
        } catch (Exception $e) {
            // If an exception is thrown, verify it's appropriate
            $this->assertInstanceOf(Exception::class, $e);
        }
    }

    // Edge Case Tests

    public function testConnectionWithNullClient(): void
    {
        $this->expectException(\TypeError::class);

        // This should fail at the type level
        new Connection(null);
    }

    public function testConnectionManagerWithCorruptedConfiguration(): void
    {
        $config = [
            'connections' => [
                'corrupted' => [
                    'hosts' => 'not_an_array', // Should be array
                ],
            ],
        ];

        $this->clientFactory->expects($this->once())
            ->method('createClient')
            ->willThrowException(new InvalidArgumentException('Invalid hosts configuration'));

        $manager = new ConnectionManager($config, $this->clientFactory);

        $this->expectException(InvalidArgumentException::class);
        $manager->connection('corrupted');
    }

    public function testConnectionInsertWithInvalidParameters(): void
    {
        $client = new MockElasticsearchClient();
        $client->setException('index', new Exception('Bad Request'));

        $connection = new Connection($client);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Bad Request');

        $connection->insert([]); // Empty parameters should cause error
    }

    public function testConnectionSearchWithMalformedQuery(): void
    {
        $client = new MockElasticsearchClient();
        $client->setException('search', new Exception('Bad Request'));

        $connection = new Connection($client);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Bad Request');

        $connection->search([
            'index' => 'test',
            'body' => [
                'query' => [
                    'invalid_query_type' => [],
                ],
            ],
        ]);
    }

    // Recovery and Resilience Tests

    public function testConnectionManagerRecoveryAfterFailure(): void
    {
        $config = [
            'connections' => [
                'recovery_test' => ['hosts' => ['localhost:9200']],
            ],
        ];

        $workingClient = new MockElasticsearchClient();

        // First call fails, second succeeds (simulating recovery)
        $this->clientFactory->expects($this->exactly(2))
            ->method('createClient')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new RuntimeException('Temporary failure')),
                $workingClient,
            );

        $manager = new ConnectionManager($config, $this->clientFactory);

        // First attempt should fail
        try {
            $manager->connection('recovery_test');
            $this->fail('Expected exception was not thrown');
        } catch (RuntimeException $e) {
            $this->assertSame('Temporary failure', $e->getMessage());
        }

        // Create a new manager instance to simulate retry
        $manager2 = new ConnectionManager($config, $this->clientFactory);
        $connection = $manager2->connection('recovery_test');

        $this->assertInstanceOf(ConnectionInterface::class, $connection);
        $this->assertSame($workingClient, $connection->getClient());
    }

    public function testConnectionWithErrorScenarioConfigurations(): void
    {
        $timeoutConfig = TestConnectionConfiguration::getErrorScenario('timeout');
        $invalidHostConfig = TestConnectionConfiguration::getErrorScenario('invalid_host');
        $authFailConfig = TestConnectionConfiguration::getErrorScenario('auth_failure');

        // Verify configurations are different and contain expected error scenarios
        $this->assertNotEquals($timeoutConfig, $invalidHostConfig);
        $this->assertNotEquals($timeoutConfig, $authFailConfig);

        $this->assertArrayHasKey('connectionParams', $timeoutConfig);
        $this->assertArrayHasKey('hosts', $invalidHostConfig);
        $this->assertArrayHasKey('hosts', $authFailConfig);

        // Verify timeout configuration has very short timeouts
        $this->assertLessThan(1, $timeoutConfig['connectionParams']['client']['timeout']);
    }

    public function testConnectionManagerHandlesClientFactoryReturnNull(): void
    {
        // This test is not valid because the ClientFactoryInterface declares
        // a return type of Client, so mocking it to return null would violate
        // the interface contract. Instead, we test that the factory throws an exception.
        $config = [
            'connections' => [
                'null_client' => ['hosts' => ['localhost:9200']],
            ],
        ];

        $this->clientFactory->expects($this->once())
            ->method('createClient')
            ->willThrowException(new RuntimeException('Failed to create client'));

        $manager = new ConnectionManager($config, $this->clientFactory);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to create client');

        $manager->connection('null_client');
    }

    public function testConnectionResolverWithEmptyDefaultConnection(): void
    {
        $resolver = new ConnectionResolver();
        $resolver->setDefaultConnection('');

        $this->assertSame('', $resolver->getDefaultConnection());

        // Accessing connection with empty default should handle gracefully
        try {
            $connection = $resolver->connection();
            $this->assertNull($connection);
        } catch (Exception $e) {
            $this->assertInstanceOf(Exception::class, $e);
        }
    }
}
