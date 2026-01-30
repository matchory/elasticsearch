<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Concerns;

use Matchory\Elasticsearch\Index;
use RuntimeException;

trait ManagesIndices
{
    /**
     * Create a new Index builder for fluent configuration.
     *
     * Example usage:
     * ```php
     * $query->index('posts')
     *       ->shards(3)
     *       ->replicas(1)
     *       ->mapping(['properties' => [...]])
     *       ->create();
     * ```
     *
     * @param string $name Name of the index to manage
     *
     * @return Index Fluent index builder
     */
    public function newIndex(string $name): Index
    {
        $index = new Index($name);
        $index->setConnection($this->getConnection());

        return $index;
    }

    /**
     * Create a new index with default settings.
     *
     * For advanced configuration (shards, replicas, mappings), use newIndex()
     * which returns a fluent builder.
     *
     * @param string $name Name of the index to create
     *
     * @return array
     */
    public function createIndex(string $name): array
    {
        return $this->newIndex($name)->create();
    }

    /**
     * Create the configured index with default settings.
     *
     * @return array
     * @throws RuntimeException If no index is configured
     */
    public function create(): array
    {
        $index = $this->getIndex();

        if (!$index) {
            throw new RuntimeException('No index configured');
        }

        return $this->createIndex($index);
    }

    /**
     * Check existence of index
     *
     * @return bool
     * @throws RuntimeException If no index is configured
     */
    public function exists(): bool
    {
        $indexName = $this->getIndex();

        if (!$indexName) {
            throw new RuntimeException('No index configured');
        }

        return $this->newIndex($indexName)->exists();
    }

    /**
     * Drop index
     *
     * @param string $name Name of the index to drop
     *
     * @return array
     */
    public function dropIndex(string $name): array
    {
        return $this->newIndex($name)->drop();
    }

    /**
     * Drop the configured index
     *
     * @return array
     * @throws RuntimeException
     */
    public function drop(): array
    {
        $index = $this->getIndex();

        if (!$index) {
            throw new RuntimeException('No index name configured');
        }

        return $this->dropIndex($index);
    }
}
