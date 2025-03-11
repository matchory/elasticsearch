<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Concerns;

use DateTime;
use Illuminate\Support\Facades\Request;
use JsonException;
use Matchory\Elasticsearch\{Classes\Bulk, Collection, Exceptions\DocumentNotFoundException, Model, Pagination, Query};
use Psr\SimpleCache\{CacheInterface, InvalidArgumentException};

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
    protected string|null $cacheKey = null;

    /**
     * A cache prefix.
     *
     * @var string
     */
    protected string $cachePrefix = Query::DEFAULT_CACHE_PREFIX;

    /**
     * The number of seconds to cache the query.
     *
     * @var DateTime|int|null
     */
    protected int|null|DateTime $cacheTtl = null;

    /**
     * Insert multiple documents at once.
     *
     * @param callable|array $data Dictionary of [id => data] pairs
     *
     * @return object
     */
    public function bulk(callable|array $data): object
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

        return (object) $this->getConnection()->getClient()->bulk(
            $params,
        );
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
    public function clear(string|null $scrollId = null): Collection
    {
        $scrollId = $scrollId ?? $this->getScrollId();

        return new Collection(
            $this->getConnection()->getClient()->clearScroll([
                'scroll_id' => $scrollId,
                'client' => ['ignore' => $this->getIgnores()],
            ]),
        );
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
            $query[Query::PARAM_SIZE],
            $query[Query::PARAM_FROM],
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
            'id' => $this->getId(),
            'body' => [
                'script' => [
                    'inline' => $script,
                    'params' => $params,
                ],
            ],
            'client' => ['ignore' => $this->getIgnores()],
        ];

        $parameters = $this->addBaseParams($parameters);

        return (object) $this->getConnection()->getClient()->update(
            $parameters,
        );
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
            $params[Query::PARAM_INDEX] = $index;
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
            $this->id((string) $id);
        }

        unset(
            $attributes[self::FIELD_HIGHLIGHT],
            $attributes[self::FIELD_INDEX],
            $attributes[self::FIELD_SCORE],
            $attributes[self::FIELD_TYPE],
            $attributes[self::FIELD_ID],
        );

        $parameters = [
            'id' => $this->getId(),
            'body' => [
                'doc' => $attributes,
            ],
            'client' => [
                'ignore' => $this->getIgnores(),
            ],
        ];

        $parameters = $this->addBaseParams($parameters);

        return (object) $this->getConnection()->getClient()->update(
            $parameters,
        );
    }

    /**
     * Delete a document
     *
     * @param string|null $id
     *
     * @return object
     */
    public function delete(string|null $id = null): object
    {
        if ($id) {
            $this->id($id);
        }

        $parameters = [
            'id' => $this->getId(),
            'client' => ['ignore' => $this->getIgnores()],
        ];

        $parameters = $this->addBaseParams($parameters);

        return (object) $this->getConnection()->getClient()->delete(
            $parameters,
        );
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
        callable|null $callback = null,
    ): Model|null {
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
    public function first(string|null $scrollId = null): Model|null
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
    protected function getResult(string|null $scrollId = null): array|null
    {
        if (!$this->cacheTtl) {
            return $this->performSearch($scrollId);
        }

        if ($cache = $this->getCache()) {
            try {
                return $cache->get($this->getCacheKey());
            } catch (InvalidArgumentException) {
                // If the cache didn't like our cache key (which should be
                // impossible), we regard it as a cache failure and perform a
                // normal search instead.
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
    public function performSearch(string|null $scrollId = null): array|null
    {
        $scrollId = $scrollId ?? $this->getScrollId();

        if ($scrollId) {
            $result = $this
                ->getConnection()
                ->getClient()
                ->scroll([
                    Query::PARAM_SCROLL => $this->getScroll(),
                    Query::PARAM_BODY => [
                        Query::PARAM_SCROLL_ID => $scrollId,
                    ],
                ]);
        } else {
            $query = $this->buildQuery();
            $result = $this->getConnection()->search($query);
        }

        // We attempt to cache the results if we have a cache instance, and the
        // TTl is truthy. This allows to use values such as `-1` to flush it.
        if ($this->cacheTtl && ($cache = $this->getCache())) {
            try {
                $cache->set(
                    $this->getCacheKey(),
                    $result,
                    $this->cacheTtl instanceof DateTime
                        ? $this->cacheTtl->getTimestamp()
                        : $this->cacheTtl,
                );
            } catch (InvalidArgumentException) {
            }
        }

        return $result;
    }

    /**
     * @return CacheInterface|null
     */
    protected function getCache(): CacheInterface|null
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
        try {
            return md5($this->toJson());
        } catch (JsonException) {
            return md5(serialize($this));
        }
    }

    /**
     * Get the collection of results
     *
     * @param string|null $scrollId
     *
     * @return Collection<array-key, T>
     */
    public function get(string|null $scrollId = null): Collection
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
        $results = $response[Query::FIELD_HITS][Query::FIELD_NESTED_HITS] ?? [];
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
        $data = $document[Query::FIELD_SOURCE] ?? [];
        $metadata = array_diff_key($document, array_flip([
            Query::FIELD_SOURCE,
        ]));

        /** @var T */
        return $this->getModel()->newInstance(
            $data,
            $metadata,
            true,
            $document[Query::FIELD_INDEX] ?? null,
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
    protected function transformIntoModel(array $response = []): Model|null
    {
        if (!isset($response[Query::FIELD_HITS][Query::FIELD_NESTED_HITS][0])) {
            return null;
        }

        /** @var T $instance */
        $instance = $this->createModelInstance(
            $response[Query::FIELD_HITS][Query::FIELD_NESTED_HITS][0],
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
    public function firstOrFail(string|null $scrollId = null): Model
    {
        if (!is_null($model = $this->first($scrollId))) {
            return $model;
        }

        $id = $this->getId();

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
    public function insert(array $attributes, string|null $id = null): object
    {
        if ($id) {
            $this->id($id);
        }

        $parameters = [
            'body' => array_diff_key($attributes, array_flip([
                self::FIELD_ID,
                self::FIELD_TYPE,
                self::FIELD_INDEX,
            ])),
            'client' => ['ignore' => $this->getIgnores()],
        ];

        $parameters = $this->addBaseParams($parameters);

        if ($id = $this->getId()) {
            $parameters['id'] = $id;
        }

        return $this->getConnection()->insert($parameters);
    }

    /**
     * Paginate collection of results
     *
     * @param int $perPage
     * @param string $pageName
     * @param int|null $page
     *
     * @return Pagination
     */
    public function paginate(
        int $perPage = 10,
        string $pageName = 'page',
        int|null $page = null,
    ): Pagination {
        $this->take($perPage);

        // Check if the request from PHP CLI
        if (PHP_SAPI === 'cli') {
            $page = $page ?: 1;

            $this->skip(($page * $perPage) - $perPage);

            $collection = $this->get();

            return new Pagination(
                $collection,
                $collection->getTotal() ?? 0,
                $perPage,
                $page,
            );
        }

        $page = $page ?: Request::get($pageName, 1);

        $this->skip(($page * $perPage) - $perPage);

        $collection = $this->get();

        return new Pagination(
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
    public function rememberForever(string|null $key = null): static
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
    public function remember(DateTime|int $ttl, string|null $key = null): static
    {
        $this->cacheTtl = $ttl;
        $this->cacheKey = $key;

        return $this;
    }
}
