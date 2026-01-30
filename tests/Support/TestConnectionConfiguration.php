<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Support;

/**
 * Configuration class for connection test scenarios
 */
class TestConnectionConfiguration
{
    /**
     * Get default test connection configuration
     *
     * @return array
     */
    public static function getDefault(): array
    {
        return [
            'hosts' => ['localhost:9200'],
            'retries' => 2,
            'handler' => null,
            'connectionPool' => '\Elasticsearch\ConnectionPool\StaticNoPingConnectionPool',
            'selector' => '\Elasticsearch\ConnectionPool\Selectors\RoundRobinSelector',
            'serializer' => '\Elasticsearch\Serializers\SmartSerializer',
            'sniffOnStart' => false,
            'connectionParams' => [],
            'logging' => [
                'enabled' => false,
                'level' => 'INFO',
                'location' => storage_path('logs/elasticsearch.log'),
            ],
        ];
    }

    /**
     * Get single host connection configuration
     *
     * @param string $host Host address
     * @param int $port Port number
     * @return array
     */
    public static function getSingleHost(string $host = 'localhost', int $port = 9200): array
    {
        return array_merge(self::getDefault(), [
            'hosts' => ["{$host}:{$port}"],
        ]);
    }

    /**
     * Get multiple hosts connection configuration
     *
     * @param array $hosts Array of host:port combinations
     * @return array
     */
    public static function getMultipleHosts(array $hosts = []): array
    {
        if (empty($hosts)) {
            $hosts = [
                'localhost:9200',
                'localhost:9201',
                'localhost:9202',
            ];
        }

        return array_merge(self::getDefault(), [
            'hosts' => $hosts,
        ]);
    }

    /**
     * Get connection configuration with authentication
     *
     * @param string $username Username
     * @param string $password Password
     * @param string $host Host address
     * @param int $port Port number
     * @return array
     */
    public static function getWithAuth(
        string $username = 'elastic',
        string $password = 'password',
        string $host = 'localhost',
        int $port = 9200,
    ): array {
        return array_merge(self::getDefault(), [
            'hosts' => [
                [
                    'host' => $host,
                    'port' => $port,
                    'scheme' => 'http',
                    'user' => $username,
                    'pass' => $password,
                ],
            ],
        ]);
    }

    /**
     * Get SSL/TLS connection configuration
     *
     * @param string $host Host address
     * @param int $port Port number
     * @param array $sslOptions SSL options
     * @return array
     */
    public static function getWithSSL(
        string $host = 'localhost',
        int $port = 9200,
        array $sslOptions = [],
    ): array {
        $defaultSslOptions = [
            'verify' => false,
            'ca' => null,
            'cert' => null,
            'key' => null,
        ];

        return array_merge(self::getDefault(), [
            'hosts' => [
                [
                    'host' => $host,
                    'port' => $port,
                    'scheme' => 'https',
                ],
            ],
            'connectionParams' => [
                'client' => [
                    'verify' => $sslOptions['verify'] ?? $defaultSslOptions['verify'],
                    'ca' => $sslOptions['ca'] ?? $defaultSslOptions['ca'],
                    'cert' => $sslOptions['cert'] ?? $defaultSslOptions['cert'],
                    'key' => $sslOptions['key'] ?? $defaultSslOptions['key'],
                ],
            ],
        ]);
    }

    /**
     * Get connection configuration with custom timeout
     *
     * @param int $timeout Timeout in seconds
     * @param int $connectTimeout Connection timeout in seconds
     * @return array
     */
    public static function getWithTimeout(int $timeout = 30, int $connectTimeout = 10): array
    {
        return array_merge(self::getDefault(), [
            'connectionParams' => [
                'client' => [
                    'timeout' => $timeout,
                    'connect_timeout' => $connectTimeout,
                ],
            ],
        ]);
    }

    /**
     * Get connection configuration with logging enabled
     *
     * @param string $level Log level
     * @param string $location Log file location
     * @return array
     */
    public static function getWithLogging(
        string $level = 'INFO',
        string $location = null,
    ): array {
        return array_merge(self::getDefault(), [
            'logging' => [
                'enabled' => true,
                'level' => $level,
                'location' => $location ?? storage_path('logs/elasticsearch-test.log'),
            ],
        ]);
    }

    /**
     * Get connection configuration for cloud/hosted Elasticsearch
     *
     * @param string $cloudId Cloud ID
     * @param string $username Username
     * @param string $password Password
     * @return array
     */
    public static function getCloudConfig(
        string $cloudId,
        string $username = 'elastic',
        string $password = 'password',
    ): array {
        return [
            'cloud_id' => $cloudId,
            'username' => $username,
            'password' => $password,
            'retries' => 2,
            'handler' => null,
            'connectionPool' => '\Elasticsearch\ConnectionPool\CloudConnectionPool',
            'selector' => '\Elasticsearch\ConnectionPool\Selectors\RoundRobinSelector',
            'serializer' => '\Elasticsearch\Serializers\SmartSerializer',
            'sniffOnStart' => false,
        ];
    }

    /**
     * Get connection configuration with custom connection pool
     *
     * @param string $poolClass Connection pool class
     * @param string $selectorClass Selector class
     * @return array
     */
    public static function getWithCustomPool(
        string $poolClass = '\Elasticsearch\ConnectionPool\StaticNoPingConnectionPool',
        string $selectorClass = '\Elasticsearch\ConnectionPool\Selectors\RoundRobinSelector',
    ): array {
        return array_merge(self::getDefault(), [
            'connectionPool' => $poolClass,
            'selector' => $selectorClass,
        ]);
    }

    /**
     * Get connection configuration for testing error scenarios
     *
     * @param string $scenario Error scenario type
     * @return array
     */
    public static function getErrorScenario(string $scenario = 'timeout'): array
    {
        $configs = [
            'timeout' => array_merge(self::getDefault(), [
                'connectionParams' => [
                    'client' => [
                        'timeout' => 0.001, // Very short timeout to trigger errors
                        'connect_timeout' => 0.001,
                    ],
                ],
            ]),
            'invalid_host' => array_merge(self::getDefault(), [
                'hosts' => ['invalid-host:9999'],
            ]),
            'connection_refused' => array_merge(self::getDefault(), [
                'hosts' => ['localhost:9999'], // Non-existent port
            ]),
            'auth_failure' => array_merge(self::getDefault(), [
                'hosts' => [
                    [
                        'host' => 'localhost',
                        'port' => 9200,
                        'scheme' => 'http',
                        'user' => 'invalid_user',
                        'pass' => 'invalid_password',
                    ],
                ],
            ]),
        ];

        return $configs[$scenario] ?? self::getDefault();
    }

    /**
     * Get connection configuration for performance testing
     *
     * @param int $maxConnections Maximum connections
     * @param int $maxConnectionsPerNode Maximum connections per node
     * @return array
     */
    public static function getPerformanceConfig(
        int $maxConnections = 100,
        int $maxConnectionsPerNode = 10,
    ): array {
        return array_merge(self::getDefault(), [
            'connectionParams' => [
                'client' => [
                    'timeout' => 60,
                    'connect_timeout' => 30,
                    'max_connections' => $maxConnections,
                    'max_connections_per_node' => $maxConnectionsPerNode,
                ],
            ],
            'retries' => 3,
        ]);
    }

    /**
     * Get connection configuration with custom serializer
     *
     * @param string $serializerClass Serializer class
     * @return array
     */
    public static function getWithSerializer(
        string $serializerClass = '\Elasticsearch\Serializers\SmartSerializer',
    ): array {
        return array_merge(self::getDefault(), [
            'serializer' => $serializerClass,
        ]);
    }

    /**
     * Get connection configuration for development environment
     *
     * @return array
     */
    public static function getDevelopment(): array
    {
        return array_merge(self::getDefault(), [
            'hosts' => ['localhost:9200'],
            'logging' => [
                'enabled' => true,
                'level' => 'DEBUG',
                'location' => storage_path('logs/elasticsearch-dev.log'),
            ],
            'connectionParams' => [
                'client' => [
                    'timeout' => 60,
                    'connect_timeout' => 10,
                ],
            ],
        ]);
    }

    /**
     * Get connection configuration for testing environment
     *
     * @return array
     */
    public static function getTesting(): array
    {
        return array_merge(self::getDefault(), [
            'hosts' => ['localhost:9200'],
            'logging' => [
                'enabled' => false,
            ],
            'connectionParams' => [
                'client' => [
                    'timeout' => 30,
                    'connect_timeout' => 5,
                ],
            ],
            'retries' => 1,
        ]);
    }

    /**
     * Get connection configuration for production environment
     *
     * @return array
     */
    public static function getProduction(): array
    {
        return array_merge(self::getDefault(), [
            'hosts' => [
                'es-node-1:9200',
                'es-node-2:9200',
                'es-node-3:9200',
            ],
            'logging' => [
                'enabled' => true,
                'level' => 'WARNING',
                'location' => storage_path('logs/elasticsearch-prod.log'),
            ],
            'connectionParams' => [
                'client' => [
                    'timeout' => 30,
                    'connect_timeout' => 10,
                ],
            ],
            'retries' => 3,
            'sniffOnStart' => true,
        ]);
    }

    /**
     * Validate connection configuration
     *
     * @param array $config Configuration to validate
     * @return bool
     */
    public static function validate(array $config): bool
    {
        $requiredKeys = ['hosts'];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $config)) {
                return false;
            }
        }

        if (empty($config['hosts'])) {
            return false;
        }

        return true;
    }

    /**
     * Merge configurations
     *
     * @param array $base Base configuration
     * @param array $override Override configuration
     * @return array
     */
    public static function merge(array $base, array $override): array
    {
        return array_merge_recursive($base, $override);
    }
}
