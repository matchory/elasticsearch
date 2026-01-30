<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Support\Traits;

use Matchory\Elasticsearch\Tests\Support\TestConnectionConfiguration;

/**
 * Trait for configuring Elasticsearch connections in tests
 */
trait ConfiguresElasticsearch
{
    /**
     * Get default test connection configuration
     *
     * @return array
     */
    protected function getDefaultConnectionConfig(): array
    {
        return TestConnectionConfiguration::getDefault();
    }

    /**
     * Get single host connection configuration
     *
     * @param string $host
     * @param int $port
     * @return array
     */
    protected function getSingleHostConfig(string $host = 'localhost', int $port = 9200): array
    {
        return TestConnectionConfiguration::getSingleHost($host, $port);
    }

    /**
     * Get multiple hosts connection configuration
     *
     * @param array $hosts
     * @return array
     */
    protected function getMultipleHostsConfig(array $hosts = []): array
    {
        return TestConnectionConfiguration::getMultipleHosts($hosts);
    }

    /**
     * Get connection configuration with authentication
     *
     * @param string $username
     * @param string $password
     * @param string $host
     * @param int $port
     * @return array
     */
    protected function getAuthConnectionConfig(
        string $username = 'elastic',
        string $password = 'password',
        string $host = 'localhost',
        int $port = 9200,
    ): array {
        return TestConnectionConfiguration::getWithAuth($username, $password, $host, $port);
    }

    /**
     * Get SSL connection configuration
     *
     * @param string $host
     * @param int $port
     * @param array $sslOptions
     * @return array
     */
    protected function getSSLConnectionConfig(
        string $host = 'localhost',
        int $port = 9200,
        array $sslOptions = [],
    ): array {
        return TestConnectionConfiguration::getWithSSL($host, $port, $sslOptions);
    }

    /**
     * Get connection configuration with timeout
     *
     * @param int $timeout
     * @param int $connectTimeout
     * @return array
     */
    protected function getTimeoutConnectionConfig(int $timeout = 30, int $connectTimeout = 10): array
    {
        return TestConnectionConfiguration::getWithTimeout($timeout, $connectTimeout);
    }

    /**
     * Get connection configuration with logging
     *
     * @param string $level
     * @param string|null $location
     * @return array
     */
    protected function getLoggingConnectionConfig(string $level = 'INFO', ?string $location = null): array
    {
        return TestConnectionConfiguration::getWithLogging($level, $location);
    }

    /**
     * Get cloud connection configuration
     *
     * @param string $cloudId
     * @param string $username
     * @param string $password
     * @return array
     */
    protected function getCloudConnectionConfig(
        string $cloudId,
        string $username = 'elastic',
        string $password = 'password',
    ): array {
        return TestConnectionConfiguration::getCloudConfig($cloudId, $username, $password);
    }

    /**
     * Get connection configuration for error scenarios
     *
     * @param string $scenario
     * @return array
     */
    protected function getErrorScenarioConfig(string $scenario = 'timeout'): array
    {
        return TestConnectionConfiguration::getErrorScenario($scenario);
    }

    /**
     * Get performance connection configuration
     *
     * @param int $maxConnections
     * @param int $maxConnectionsPerNode
     * @return array
     */
    protected function getPerformanceConnectionConfig(
        int $maxConnections = 100,
        int $maxConnectionsPerNode = 10,
    ): array {
        return TestConnectionConfiguration::getPerformanceConfig($maxConnections, $maxConnectionsPerNode);
    }

    /**
     * Get development environment connection configuration
     *
     * @return array
     */
    protected function getDevelopmentConnectionConfig(): array
    {
        return TestConnectionConfiguration::getDevelopment();
    }

    /**
     * Get testing environment connection configuration
     *
     * @return array
     */
    protected function getTestingConnectionConfig(): array
    {
        return TestConnectionConfiguration::getTesting();
    }

    /**
     * Get production environment connection configuration
     *
     * @return array
     */
    protected function getProductionConnectionConfig(): array
    {
        return TestConnectionConfiguration::getProduction();
    }

    /**
     * Validate connection configuration
     *
     * @param array $config
     * @return bool
     */
    protected function validateConnectionConfig(array $config): bool
    {
        return TestConnectionConfiguration::validate($config);
    }

    /**
     * Merge connection configurations
     *
     * @param array $base
     * @param array $override
     * @return array
     */
    protected function mergeConnectionConfigs(array $base, array $override): array
    {
        return TestConnectionConfiguration::merge($base, $override);
    }

    /**
     * Set up connection configuration for test
     *
     * @param array $config
     * @return void
     */
    protected function setUpConnectionConfig(array $config = []): void
    {
        $defaultConfig = $this->getTestingConnectionConfig();
        $finalConfig = empty($config) ? $defaultConfig : $this->mergeConnectionConfigs($defaultConfig, $config);

        // Store configuration for use in tests
        $this->connectionConfig = $finalConfig;

        // If using Laravel, bind configuration
        if (method_exists($this, 'app') && $this->app) {
            $this->app['config']->set('elasticsearch.default', $finalConfig);
        }
    }

    /**
     * Get the current connection configuration
     *
     * @return array
     */
    protected function getConnectionConfig(): array
    {
        return $this->connectionConfig ?? $this->getTestingConnectionConfig();
    }
}
