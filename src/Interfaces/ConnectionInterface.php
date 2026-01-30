<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Interfaces;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Matchory\Elasticsearch\Builder;
use Psr\SimpleCache\CacheInterface;

/**
 * Interface ConnectionInterface
 *
 * @package Matchory\Elasticsearch\Interfaces
 */
interface ConnectionInterface
{
    /**
     * Retrieves the cache instance, if configured.
     *
     * @return CacheInterface|null
     */
    public function getCache(): ?CacheInterface;

    /**
     * Retrieves the Elasticsearch client.
     *
     * Note: Return type is `object` to allow for duck-typed mock clients in
     * tests. The Elasticsearch PHP Client v9 makes the Client class final,
     * preventing extension or mocking. The returned object will have all
     * methods of the Elasticsearch Client.
     *
     * @return Client|object
     */
    public function getClient(): object;

    /**
     * Create a new query on the given index.
     *
     * @param string $index Name of the index to query.
     *
     * @return Builder Query builder instance.
     */
    public function index(string $index): Builder;

    /**
     * Adds a document to the index using the specified parameters.
     *
     * @param array $parameters Parameters to index the document with
     * @param string|null $index Index to insert the document into.
     *                                Defaults to the default index of the
     *                                connection.
     *
     * @return object
     */
    public function insert(
        array $parameters,
        ?string $index = null,
    ): object;

    /**
     * Creates a new Elasticsearch query
     *
     * @return Builder
     */
    public function newQuery(): Builder;

    /**
     * Executes a search query.
     *
     * @param array $parameters Parameters to the search endpoint.
     *
     * @return array
     */
    public function search(array $parameters): array;

    /**
     * Execute a client operation with specific HTTP status codes ignored.
     *
     * @param callable(object): mixed $operation The operation to execute
     * @param array<int> $ignores HTTP status codes to ignore
     *
     * @return mixed The operation result
     * @throws ClientResponseException If the response has an error status not in $ignores
     */
    public function executeWithIgnoredErrors(callable $operation, array $ignores = []): mixed;
}
