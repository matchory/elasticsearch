<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Support\Traits;

use Matchory\Elasticsearch\Tests\Support\Factories\DocumentFactory;
use Matchory\Elasticsearch\Tests\Support\Factories\IndexConfigurationFactory;

/**
 * Trait for creating test data in tests
 */
trait CreatesTestData
{
    protected DocumentFactory $documentFactory;
    protected IndexConfigurationFactory $indexConfigurationFactory;

    /**
     * Set up test data factories
     */
    protected function setUpTestDataFactories(): void
    {
        $this->documentFactory = new DocumentFactory();
        $this->indexConfigurationFactory = new IndexConfigurationFactory();
    }

    /**
     * Create a test document
     *
     * @param array $overrides
     * @return array
     */
    protected function createTestDocument(array $overrides = []): array
    {
        return $this->documentFactory->create($overrides);
    }

    /**
     * Create multiple test documents
     *
     * @param int $count
     * @param array $overrides
     * @return array
     */
    protected function createTestDocuments(int $count, array $overrides = []): array
    {
        return $this->documentFactory->createMany($count, $overrides);
    }

    /**
     * Create a test user document
     *
     * @param array $overrides
     * @return array
     */
    protected function createTestUser(array $overrides = []): array
    {
        return $this->documentFactory->createUser($overrides);
    }

    /**
     * Create a test product document
     *
     * @param array $overrides
     * @return array
     */
    protected function createTestProduct(array $overrides = []): array
    {
        return $this->documentFactory->createProduct($overrides);
    }

    /**
     * Create test documents with edge case data
     *
     * @param array $overrides
     * @return array
     */
    protected function createEdgeCaseDocument(array $overrides = []): array
    {
        return $this->documentFactory->createEdgeCase($overrides);
    }

    /**
     * Create bulk test data
     *
     * @param int $count
     * @param string $type
     * @return array
     */
    protected function createBulkTestData(int $count, string $type = 'simple'): array
    {
        return $this->documentFactory->createBulkData($count, $type);
    }

    /**
     * Create test index configuration (alternative method name to avoid conflict)
     *
     * @param string $name
     * @param array $overrides
     * @return array
     */
    protected function createIndexConfig(string $name, array $overrides = []): array
    {
        return $this->indexConfigurationFactory->create($name, $overrides);
    }

    /**
     * Create blog index configuration
     *
     * @param string $name
     * @param array $overrides
     * @return array
     */
    protected function createBlogIndexConfig(string $name = 'test_blog', array $overrides = []): array
    {
        return $this->indexConfigurationFactory->createBlogIndex($name, $overrides);
    }

    /**
     * Create user index configuration
     *
     * @param string $name
     * @param array $overrides
     * @return array
     */
    protected function createUserIndexConfig(string $name = 'test_users', array $overrides = []): array
    {
        return $this->indexConfigurationFactory->createUserIndex($name, $overrides);
    }

    /**
     * Create product index configuration
     *
     * @param string $name
     * @param array $overrides
     * @return array
     */
    protected function createProductIndexConfig(string $name = 'test_products', array $overrides = []): array
    {
        return $this->indexConfigurationFactory->createProductIndex($name, $overrides);
    }

    /**
     * Create minimal index configuration
     *
     * @param string $name
     * @param array $overrides
     * @return array
     */
    protected function createMinimalIndexConfig(string $name = 'test_minimal', array $overrides = []): array
    {
        return $this->indexConfigurationFactory->createMinimalIndex($name, $overrides);
    }
}
