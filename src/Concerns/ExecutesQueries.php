<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Concerns;

use DateTime;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Illuminate\Support\Facades\{Config, Log, Request};
use JsonException;
use Illuminate\Pagination\LengthAwarePaginator;
use Matchory\Elasticsearch\{Builder, Bulk, Collection, Exceptions\DocumentNotFoundException, Model};
use Psr\SimpleCache\{CacheInterface, InvalidArgumentException};
use stdClass;

use function array_chunk;
use function array_diff_key;
use function array_flip;
use function array_map;
use function get_class;
use function is_callable;
use function is_null;
use function md5;
use function serialize;

use const PHP_SAPI;

/**
 * @template-covariant T of Model
 */
trait ExecutesQueries
{
    /**
     * The key that should be used when caching the query.
     *
     * @var string|null
     */
    protected ?string $cacheKey = null;

    /**
     * A cache prefix.
     *
     * @var string
     */
    protected string $cachePrefix = Builder::DEFAULT_CACHE_PREFIX;

    /**
     * The number of seconds to cache the query.
     *
     * @var DateTime|int|null
     */
    protected int|DateTime|null $cacheTtl = null;

    /**
     * Maximum number of retry attempts for transient failures.
     *
     * @var int
     */
    protected int $retryAttempts = 0;

    /**
     * Base delay in milliseconds for exponential backoff.
     *
     * @var int
     */
    protected int $retryDelay = 100;

    /**
     * Execute a client operation with specific HTTP status codes ignored.
     *
     * This method delegates to the Connection's executeWithIgnoredErrors method
     * and adds support for automatic retry with exponential backoff for transient
     * failures when configured via the retry() method.
     *
     * @param callable(object): mixed $operation The operation to execute
     * @param array<int> $ignores HTTP status codes to ignore
     *
     * @return mixed The operation result
     * @throws ClientResponseException If the response has an error status not in $ignores
     */
    protected function executeWithIgnoredErrors(callable $operation, array $ignores = []): mixed
    {
        $executeOperation = fn(): mixed => $this
            ->getConnection()
            ->executeWithIgnoredErrors($operation, $ignores);

        // Use retry logic if configured
        if ($this->retryAttempts > 0) {
            return $this->executeWithRetry($executeOperation);
        }

        return $executeOperation();
    }

    /**
     * Insert multiple documents at once.
     *
     * When $batchSize is provided, large operations are automatically chunked
     * into smaller batches to avoid memory issues and timeouts.
     *
     * @param callable|array $data Dictionary of [id => data] pairs
     * @param int|null $batchSize Optional batch size for chunking large operations
     *
     * @return object|array Returns a single response object, or an array of
     *                      responses when batching is used
     */
    public function bulk(callable|array $data, ?int $batchSize = null): object|array
    {
        if (is_callable($data)) {
            $bulk = new Bulk($this);

            $data($bulk);

            $params = $bulk->body();
        } else {
            $params = [];

            foreach ($data as $key => $value) {
                $params['body'][] = [
                    'index' => [
                        '_index' => $this->getIndex(),
                        '_id' => $key,
                    ],
                ];

                $params['body'][] = $value;
            }
        }

        // If no batch size specified or body is small enough, execute as single request
        if ($batchSize === null || !isset($params['body']) || count($params['body']) <= $batchSize * 2) {
            return (object) $this->getConnection()->getClient()->bulk($params);
        }

        // Chunk the body into batches (each document has 2 entries: action + data)
        $results = [];
        $chunks = array_chunk($params['body'], $batchSize * 2);

        foreach ($chunks as $chunk) {
            $results[] = (object) $this->getConnection()->getClient()->bulk([
                'body' => $chunk,
            ]);
        }

        return $results;
    }

    /**
     * Set the cache prefix.
     *
     * @param string $prefix
     *
     * @return $this
     */
    public function cachePrefix(string $prefix): static
    {
        $this->cachePrefix = $prefix;

        return $this;
    }

    /**
     * Clear scroll query id
     *
     * @param string|null $scrollId
     *
     * @return Collection
     */
    public function clear(?string $scrollId = null): Collection
    {
        $scrollId = $scrollId ?? $this->getScrollId();

        $result = $this->executeWithIgnoredErrors(
            fn(object $client) => $client->clearScroll([
                'scroll_id' => $scrollId,
            ]),
            $this->getIgnores(),
        );

        return new Collection($result);
    }

    /**
     * Get the count of result
     *
     * @return int
     */
    public function count(): int
    {
        $query = $this->toArray();

        // Remove unsupported count query keys
        unset(
            $query[Builder::PARAM_SIZE],
            $query[Builder::PARAM_FROM],
            $query['body']['_source'],
            $query['body']['sort'],
        );

        return (int) $this
            ->getConnection()
            ->getClient()
            ->count($query)['count'];
    }

    /**
     * Increment a document field
     *
     * @param string $field
     * @param int $count
     *
     * @return object
     */
    public function decrement(string $field, int $count = 1): object
    {
        return $this->script("ctx._source.{$field} -= params.count", [
            'count' => $count,
        ]);
    }

    /**
     * Update by script
     *
     * @param mixed $script
     * @param array $params
     *
     * @return object
     */
    public function script(mixed $script, array $params = []): object
    {
        $parameters = [
            'id' => $this->getKey(),
            'body' => [
                'script' => [
                    'source' => $script,
                    'params' => $params,
                ],
            ],
        ];

        $parameters = $this->addBaseParams($parameters);

        $result = $this->executeWithIgnoredErrors(
            fn(object $client) => $client->update($parameters),
            $this->getIgnores(),
        );

        return (object) $result;
    }

    /**
     * Adds the base parameters required for all queries.
     *
     * @param array<string, mixed> $params Query parameters to hydrate
     *
     * @return array<string, mixed> Hydrated query parameters
     */
    private function addBaseParams(array $params): array
    {
        if ($index = $this->getIndex()) {
            $params[Builder::PARAM_INDEX] = $index;
        }

        return $params;
    }

    /**
     * Update a document
     *
     * @param array $attributes
     * @param int|string|null $id
     *
     * @return object
     */
    public function update(
        array $attributes,
        int|string|null $id = null,
    ): object {
        if ($id) {
            $this->key((string) $id);
        }

        unset(
            $attributes[self::FIELD_HIGHLIGHT],
            $attributes[self::FIELD_INDEX],
            $attributes[self::FIELD_SCORE],
            $attributes[self::FIELD_ID],
        );

        $parameters = [
            'id' => $this->getKey(),
            'body' => [
                'doc' => $attributes,
            ],
        ];

        $parameters = $this->addBaseParams($parameters);

        $result = $this->executeWithIgnoredErrors(
            fn(object $client) => $client->update($parameters),
            $this->getIgnores(),
        );

        return (object) $result;
    }

    /**
     * Delete a document
     *
     * @param string|null $id
     *
     * @return object
     */
    public function delete(?string $id = null): object
    {
        if ($id) {
            $this->key($id);
        }

        $parameters = [
            'id' => $this->getKey(),
        ];

        $parameters = $this->addBaseParams($parameters);

        $result = $this->executeWithIgnoredErrors(
            fn(object $client) => $client->delete($parameters),
            $this->getIgnores(),
        );

        return (object) $result;
    }

    /**
     * Bulk update documents matching the current query.
     *
     * Updates documents that match the specified query using a script.
     * This is useful for bulk operations like incrementing counters,
     * changing status fields, or modifying documents in batch.
     *
     * @param array|string $script Script to execute on each document. If string,
     *                             treated as the script source. If array, used
     *                             as the full script definition.
     * @param array $params Script parameters (only used when $script is string)
     * @param bool $waitForCompletion Whether to wait for the operation to complete
     *
     * @return object Response containing updated, deleted, and version_conflicts counts
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/docs-update-by-query.html
     */
    public function updateByQuery(
        array|string $script,
        array $params = [],
        bool $waitForCompletion = true,
    ): object {
        $query = $this->applyScopes();
        $queryBody = $query->getBody();

        $parameters = [
            'body' => [
                'query' => $queryBody['query'] ?? ['match_all' => new stdClass()],
                'script' => is_string($script)
                    ? ['source' => $script, 'params' => $params]
                    : $script,
            ],
            'wait_for_completion' => $waitForCompletion,
        ];

        $parameters = $this->addBaseParams($parameters);

        $result = $this->executeWithIgnoredErrors(
            fn(object $client) => $client->updateByQuery($parameters),
            $this->getIgnores(),
        );

        return (object) $result;
    }

    /**
     * Bulk delete documents matching the current query.
     *
     * Deletes all documents that match the specified query. Use with caution
     * as this operation cannot be undone.
     *
     * @param bool $waitForCompletion Whether to wait for the operation to complete
     *
     * @return object Response containing deleted, version_conflicts counts
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/docs-delete-by-query.html
     */
    public function deleteByQuery(bool $waitForCompletion = true): object
    {
        $query = $this->applyScopes();
        $queryBody = $query->getBody();

        $parameters = [
            'body' => [
                'query' => $queryBody['query'] ?? ['match_all' => new stdClass()],
            ],
            'wait_for_completion' => $waitForCompletion,
        ];

        $parameters = $this->addBaseParams($parameters);

        $result = $this->executeWithIgnoredErrors(
            fn(object $client) => $client->deleteByQuery($parameters),
            $this->getIgnores(),
        );

        return (object) $result;
    }

    /**
     * Get the first result or call a callback.
     *
     * @param callable|string|null $scrollId
     * @param callable|null $callback
     *
     * @return T|null
     * @throws InvalidArgumentException
     * @noinspection PhpDocSignatureInspection
     */
    public function firstOr(
        callable|string|null $scrollId = null,
        ?callable $callback = null,
    ): ?Model {
        if (is_callable($scrollId)) {
            $callback = $scrollId;
            $scrollId = null;
        }

        if (!is_null($model = $this->first($scrollId))) {
            return $model;
        }

        return $callback ? $callback() : null;
    }

    /**
     * Get the first result
     *
     * @param string|null $scrollId
     *
     * @return T|null
     * @noinspection PhpDocSignatureInspection
     */
    public function first(?string $scrollId = null): ?Model
    {
        $this->take(1);

        $result = $this->getResult($scrollId);

        if (!$result) {
            return null;
        }

        return $this->transformIntoModel($result);
    }

    /**
     * Executes the query and handles the result
     *
     * @param string|null $scrollId
     *
     * @return array|null
     */
    protected function getResult(?string $scrollId = null): ?array
    {
        if (!$this->cacheTtl) {
            return $this->performSearch($scrollId);
        }

        if ($cache = $this->getCache()) {
            try {
                return $cache->get($this->getCacheKey());
            } catch (InvalidArgumentException $e) {
                // If the cache didn't like our cache key (which should be
                // impossible), we regard it as a cache failure and perform a
                // normal search instead.
                Log::warning('Elasticsearch cache read failed', [
                    'key' => $this->getCacheKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->performSearch($scrollId);
    }

    /**
     * Get non-cached results
     *
     * @param string|null $scrollId
     *
     * @return array|null
     */
    public function performSearch(?string $scrollId = null): ?array
    {
        $scrollId = $scrollId ?? $this->getScrollId();

        $result = $this->retryAttempts > 0
            ? $this->executeWithRetry(fn() => $this->executeSearch($scrollId))
            : $this->executeSearch($scrollId);

        // Cache results if configured
        if ($this->cacheTtl && ($cache = $this->getCache())) {
            try {
                $cache->set(
                    $this->getCacheKey(),
                    $result,
                    $this->cacheTtl instanceof DateTime
                        ? $this->cacheTtl->getTimestamp()
                        : $this->cacheTtl,
                );
            } catch (InvalidArgumentException $e) {
                Log::warning('Elasticsearch cache write failed', [
                    'key' => $this->getCacheKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Execute the actual search operation.
     *
     * @param string|null $scrollId
     *
     * @return array
     */
    protected function executeSearch(?string $scrollId): array
    {
        if ($scrollId) {
            return $this->getConnection()->getClient()->scroll([
                Builder::PARAM_SCROLL => $this->getScroll(),
                Builder::PARAM_BODY => [
                    Builder::PARAM_SCROLL_ID => $scrollId,
                ],
            ]);
        }

        return $this->getConnection()->search($this->buildQuery());
    }

    /**
     * @return CacheInterface|null
     */
    protected function getCache(): ?CacheInterface
    {
        return $this->getConnection()->getCache();
    }

    /**
     * Get a unique cache key for the complete query.
     *
     * @return string
     */
    public function getCacheKey(): string
    {
        $cacheKey = $this->cacheKey ?: $this->generateCacheKey();

        return "{$this->cachePrefix}.{$cacheKey}";
    }

    /**
     * Generate the unique cache key for the query.
     *
     * @return string
     */
    public function generateCacheKey(): string
    {
        // Include app key for cache key unpredictability (security hardening)
        $appKey = Config::get('app.key', '');

        try {
            return md5($appKey . $this->toJson());
        } catch (JsonException) {
            // Fallback: serialize handles all types consistently
            return md5($appKey . serialize($this->toArray()));
        }
    }

    /**
     * Get the collection of results
     *
     * @param string|null $scrollId
     *
     * @return Collection<array-key, T>
     */
    public function get(?string $scrollId = null): Collection
    {
        $result = $this->getResult($scrollId);

        if (!$result) {
            return new Collection([]);
        }

        return $this->transformIntoCollection($result);
    }

    /**
     * Retrieves all documents from a response.
     *
     * @param array[] $response Response to extract documents from
     *
     * @return Collection<array-key, T> Collection of model instances
     *                                  representing the documents contained in
     *                                  the response
     */
    protected function transformIntoCollection(array $response = []): Collection
    {
        $results = $response[Builder::FIELD_HITS][Builder::FIELD_NESTED_HITS] ?? [];
        $documents = array_map(
            fn(array $document): Model => $this->createModelInstance($document),
            $results,
        );

        return Collection::fromResponse($response, $documents);
    }

    /**
     * Processes a result and turns it into a model instance.
     *
     * @param array<string, mixed> $document Raw document to create a model
     *                                       instance from
     *
     * @return T Model instance representing the source document
     * @noinspection PhpDocSignatureInspection
     */
    protected function createModelInstance(array $document): Model
    {
        $data = $document[Builder::FIELD_SOURCE] ?? [];
        $metadata = array_diff_key($document, array_flip([
            Builder::FIELD_SOURCE,
        ]));

        /** @var T */
        return $this->getModel()->newInstance(
            $data,
            $metadata,
            true,
            $document[Builder::FIELD_INDEX] ?? null,
        );
    }

    /**
     * Retrieve the first document from a response. If the response does not
     * contain any hits, will return `null`.
     *
     * @param array[] $response Response to extract the first document from
     *
     * @return T|null Model instance if any documents were found in the
     *                response, `null` otherwise
     * @noinspection PhpDocSignatureInspection
     */
    protected function transformIntoModel(array $response = []): ?Model
    {
        if (!isset($response[Builder::FIELD_HITS][Builder::FIELD_NESTED_HITS][0])) {
            return null;
        }

        /** @var T $instance */
        $instance = $this->createModelInstance(
            $response[Builder::FIELD_HITS][Builder::FIELD_NESTED_HITS][0],
        );

        return $instance;
    }

    /**
     * Get the first result or fail.
     *
     * @param string|null $scrollId
     *
     * @return T
     * @throws DocumentNotFoundException
     * @noinspection PhpDocSignatureInspection
     */
    public function firstOrFail(?string $scrollId = null): Model
    {
        if (!is_null($model = $this->first($scrollId))) {
            return $model;
        }

        $id = $this->getKey();

        throw (new DocumentNotFoundException())->setModel(
            get_class($this->getModel()),
            $id ?? [],
        );
    }

    /**
     * Increment a document field
     *
     * @param string $field
     * @param int $count
     *
     * @return object
     */
    public function increment(string $field, int $count = 1): object
    {
        return $this->script("ctx._source.{$field} += params.count", [
            'count' => $count,
        ]);
    }

    /**
     * Insert a document
     *
     * @param array $attributes
     * @param string|null $id
     *
     * @return object
     */
    public function insert(array $attributes, ?string $id = null): object
    {
        if ($id) {
            $this->key($id);
        }

        $parameters = [
            'body' => array_diff_key($attributes, array_flip([
                self::FIELD_ID,
                self::FIELD_INDEX,
            ])),
        ];

        $parameters = $this->addBaseParams($parameters);

        if ($id = $this->getKey()) {
            $parameters['id'] = $id;
        }

        $result = $this->executeWithIgnoredErrors(
            fn(object $client) => $client->index($parameters),
            $this->getIgnores(),
        );

        return (object) $result;
    }

    /**
     * Paginate collection of results
     *
     * @param int $perPage
     * @param string $pageName
     * @param int|null $page
     *
     * @return LengthAwarePaginator
     */
    public function paginate(
        int $perPage = 10,
        string $pageName = 'page',
        ?int $page = null,
    ): LengthAwarePaginator {
        $this->take($perPage);

        // Check if the request from PHP CLI
        if (PHP_SAPI === 'cli') {
            $page = $page ?: 1;

            $this->skip(($page * $perPage) - $perPage);

            $collection = $this->get();

            return new LengthAwarePaginator(
                $collection,
                $collection->getTotal() ?? 0,
                $perPage,
                $page,
            );
        }

        $page = $page ?: Request::get($pageName, 1);

        $this->skip(($page * $perPage) - $perPage);

        $collection = $this->get();

        return new LengthAwarePaginator(
            $collection,
            $collection->getTotal() ?? 0,
            $perPage,
            $page,
            [
                'path' => Request::url(),
                'query' => Request::query(),
            ],
        );
    }

    /**
     * Indicate that the query results should be cached forever.
     *
     * @param string|null $key
     *
     * @return $this
     */
    public function rememberForever(?string $key = null): static
    {
        return $this->remember(-1, $key);
    }

    /**
     * Indicate that the query results should be cached.
     *
     * @param DateTime|int $ttl Cache TTL in seconds.
     * @param string|null $key Cache key to use. Will be generated
     *                          automatically if omitted.
     *
     * @return $this
     */
    public function remember(DateTime|int $ttl, ?string $key = null): static
    {
        $this->cacheTtl = $ttl;
        $this->cacheKey = $key;

        return $this;
    }

    /**
     * Configure retry behavior for transient failures.
     *
     * @param int $attempts Maximum number of retry attempts (0 to disable)
     * @param int $delay Base delay in milliseconds for exponential backoff
     *
     * @return $this
     */
    public function retry(int $attempts = 3, int $delay = 100): static
    {
        $this->retryAttempts = $attempts;
        $this->retryDelay = $delay;

        return $this;
    }

    /**
     * Execute a callable with retry logic for transient failures.
     *
     * Uses exponential backoff with jitter to avoid thundering herd.
     *
     * @template TReturn
     * @param callable(): TReturn $operation
     *
     * @return TReturn
     * @throws NoNodeAvailableException
     * @throws ClientResponseException
     */
    protected function executeWithRetry(callable $operation): mixed
    {
        $attempts = 0;
        $lastException = null;

        do {
            try {
                return $operation();
            } catch (NoNodeAvailableException|ClientResponseException $e) {
                $lastException = $e;

                // Only retry on transient errors (5xx or connection issues)
                if ($e instanceof ClientResponseException) {
                    $statusCode = $e->getCode();
                    // Don't retry client errors (4xx) except 429 (rate limiting)
                    if ($statusCode >= 400 && $statusCode < 500 && $statusCode !== 429) {
                        throw $e;
                    }
                }

                $attempts++;

                if ($attempts <= $this->retryAttempts) {
                    // Exponential backoff with jitter, capped at 30 seconds
                    $delay = min($this->retryDelay * (2 ** ($attempts - 1)), 30000);
                    $jitter = random_int(0, (int) ($delay * 0.1));
                    usleep(($delay + $jitter) * 1000);

                    Log::warning('Elasticsearch operation failed, retrying', [
                        'attempt' => $attempts,
                        'max_attempts' => $this->retryAttempts,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } while ($attempts <= $this->retryAttempts);

        throw $lastException;
    }
}
