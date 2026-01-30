<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Support\Traits;

use Matchory\Elasticsearch\Tests\Support\Factories\IndexConfigurationFactory;

/**
 * Trait for managing test indices in tests
 */
trait ManagesTestIndices
{
    protected array $testIndices = [];
    protected IndexConfigurationFactory $indexFactory;

    /**
     * Set up index management
     */
    protected function setUpIndexManagement(): void
    {
        $this->indexFactory = new IndexConfigurationFactory();
        $this->testIndices = [];
    }

    /**
     * Create a test index
     *
     * @param string $name
     * @param array $config
     * @return string
     */
    protected function createTestIndex(string $name, array $config = []): string
    {
        $indexName = $this->generateTestIndexName($name);
        $indexConfig = $this->indexFactory->create($indexName, $config);

        // Mock the index creation
        $this->mockIndexCreation($indexConfig);

        $this->testIndices[] = $indexName;

        return $indexName;
    }

    /**
     * Create a blog test index
     *
     * @param string $name
     * @param array $config
     * @return string
     */
    protected function createBlogTestIndex(string $name = 'blog', array $config = []): string
    {
        $indexName = $this->generateTestIndexName($name);
        $indexConfig = $this->indexFactory->createBlogIndex($indexName, $config);

        $this->mockIndexCreation($indexConfig);
        $this->testIndices[] = $indexName;

        return $indexName;
    }

    /**
     * Create a user test index
     *
     * @param string $name
     * @param array $config
     * @return string
     */
    protected function createUserTestIndex(string $name = 'users', array $config = []): string
    {
        $indexName = $this->generateTestIndexName($name);
        $indexConfig = $this->indexFactory->createUserIndex($indexName, $config);

        $this->mockIndexCreation($indexConfig);
        $this->testIndices[] = $indexName;

        return $indexName;
    }

    /**
     * Create a product test index
     *
     * @param string $name
     * @param array $config
     * @return string
     */
    protected function createProductTestIndex(string $name = 'products', array $config = []): string
    {
        $indexName = $this->generateTestIndexName($name);
        $indexConfig = $this->indexFactory->createProductIndex($indexName, $config);

        $this->mockIndexCreation($indexConfig);
        $this->testIndices[] = $indexName;

        return $indexName;
    }

    /**
     * Create a minimal test index
     *
     * @param string $name
     * @param array $config
     * @return string
     */
    protected function createMinimalTestIndex(string $name = 'minimal', array $config = []): string
    {
        $indexName = $this->generateTestIndexName($name);
        $indexConfig = $this->indexFactory->createMinimalIndex($indexName, $config);

        $this->mockIndexCreation($indexConfig);
        $this->testIndices[] = $indexName;

        return $indexName;
    }

    /**
     * Delete a test index
     *
     * @param string $indexName
     * @return void
     */
    protected function deleteTestIndex(string $indexName): void
    {
        // Mock the index deletion
        $this->mockIndexDeletion($indexName);

        $this->testIndices = array_filter(
            $this->testIndices,
            fn($name) => $name !== $indexName,
        );
    }

    /**
     * Clean up all test indices
     *
     * @return void
     */
    protected function cleanUpTestIndices(): void
    {
        foreach ($this->testIndices as $indexName) {
            $this->mockIndexDeletion($indexName);
        }

        $this->testIndices = [];
    }

    /**
     * Check if a test index exists
     *
     * @param string $indexName
     * @return bool
     */
    protected function testIndexExists(string $indexName): bool
    {
        return in_array($indexName, $this->testIndices);
    }

    /**
     * Get all test indices
     *
     * @return array
     */
    protected function getTestIndices(): array
    {
        return $this->testIndices;
    }

    /**
     * Generate a unique test index name
     *
     * @param string $baseName
     * @return string
     */
    protected function generateTestIndexName(string $baseName): string
    {
        $timestamp = time();
        $random = mt_rand(1000, 9999);

        return "test_{$baseName}_{$timestamp}_{$random}";
    }

    /**
     * Mock index creation
     *
     * @param array $indexConfig
     * @return void
     */
    protected function mockIndexCreation(array $indexConfig): void
    {
        if (method_exists($this, 'getMockClient')) {
            $response = [
                'acknowledged' => true,
                'shards_acknowledged' => true,
                'index' => $indexConfig['index'],
            ];

            $this->getMockClient()->setResponse('indices.create', $response);
        }
    }

    /**
     * Mock index deletion
     *
     * @param string $indexName
     * @return void
     */
    protected function mockIndexDeletion(string $indexName): void
    {
        if (method_exists($this, 'getMockClient')) {
            $response = [
                'acknowledged' => true,
            ];

            $this->getMockClient()->setResponse('indices.delete', $response);
        }
    }

    /**
     * Mock index existence check
     *
     * @param string $indexName
     * @param bool $exists
     * @return void
     */
    protected function mockIndexExists(string $indexName, bool $exists = true): void
    {
        if (method_exists($this, 'getMockClient')) {
            if ($exists) {
                $this->getMockClient()->setResponse('indices.exists', true);
            } else {
                $exception = new \Exception(json_encode([
                    'error' => [
                        'type' => 'index_not_found_exception',
                        'reason' => "no such index [{$indexName}]",
                        'index' => $indexName,
                    ],
                ]));
                $this->getMockClient()->setException('indices.exists', $exception);
            }
        }
    }

    /**
     * Get index configuration for a test index
     *
     * @param string $indexName
     * @param string $type
     * @return array
     */
    protected function getTestIndexConfig(string $indexName, string $type = 'basic'): array
    {
        return match ($type) {
            'blog' => $this->indexFactory->createBlogIndex($indexName),
            'user' => $this->indexFactory->createUserIndex($indexName),
            'product' => $this->indexFactory->createProductIndex($indexName),
            'minimal' => $this->indexFactory->createMinimalIndex($indexName),
            'field_types' => $this->indexFactory->createFieldTypesIndex($indexName),
            'performance' => $this->indexFactory->createPerformanceIndex($indexName),
            default => $this->indexFactory->create($indexName),
        };
    }

    /**
     * Assert that an index was created
     *
     * @param string $indexName
     * @return void
     */
    protected function assertIndexCreated(string $indexName): void
    {
        if (method_exists($this, 'assertElasticsearchMethodCalled')) {
            $this->assertElasticsearchMethodCalled('indices.create');
        }

        $this->assertTrue($this->testIndexExists($indexName));
    }

    /**
     * Assert that an index was deleted
     *
     * @param string $indexName
     * @return void
     */
    protected function assertIndexDeleted(string $indexName): void
    {
        if (method_exists($this, 'assertElasticsearchMethodCalled')) {
            $this->assertElasticsearchMethodCalled('indices.delete');
        }

        $this->assertFalse($this->testIndexExists($indexName));
    }
}
