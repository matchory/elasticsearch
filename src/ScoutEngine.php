<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch;

use Illuminate\Database\Eloquent\{Collection, Model};
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;

use function array_filter;
use function array_merge;
use function array_map;
use function assert;
use function ceil;
use function collect;
use function count;
use function is_array;
use function is_callable;

class ScoutEngine extends Engine
{
    /**
     * @param object $client Elasticsearch client instance
     * @param string $index Default index name
     */
    public function __construct(protected object $client, protected string $index) {}

    /**
     * Remove the given model from the index.
     *
     * @param Collection $models
     *
     * @return void
     */
    public function delete($models): void
    {
        $params = [
            'body' => [],
        ];

        $models->each(function (Model $model) use (&$params) {
            $params['body'][] = [
                'delete' => [
                    '_id' => $model->getKey(),
                    '_index' => $this->index,
                ],
            ];
        });

        $this->client->bulk($params);
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param Model $model
     *
     * @return void
     */
    public function flush($model): void
    {
        $this->client->deleteByQuery([
            'index' => $this->index,
            'body' => [
                'query' => [
                    'match_all' => [],
                ],
            ],
        ]);
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param mixed $results
     *
     * @return int
     */
    public function getTotalCount($results): int
    {
        if (!is_array($results) || !isset($results['hits']['total'])) {
            return 0;
        }

        $total = $results['hits']['total'];

        // Handle both old format (numeric) and new format (object with value)
        if (is_array($total) && isset($total['value'])) {
            return (int) $total['value'];
        }

        return (int) $total;
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param mixed $results
     *
     * @return Collection
     */
    public function mapIds($results): Collection
    {
        if (!is_array($results) || !isset($results['hits']['hits'])) {
            return new Collection([]);
        }

        return new Collection(array_map(
            static fn(array $hit): string => $hit['_id'],
            $results['hits']['hits'],
        ));
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     * @param int     $perPage
     * @param int     $page
     *
     * @return array|callable
     */
    public function paginate(Builder $builder, $perPage, $page): array|callable
    {
        $result = $this->performSearch($builder, [
            'numericFilters' => $this->filters($builder),
            'from' => (($page * $perPage) - $perPage),
            'size' => $perPage,
        ]);

        assert(is_array($result));

        $result['nbPages'] = (int) ceil($this->getTotalCount($result) / $perPage);

        return $result;
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     * @param array   $options
     *
     * @return array|callable
     */
    protected function performSearch(
        Builder $builder,
        array $options = [],
    ): callable|array {
        $params = [
            'index' => $this->index,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                // Use simple_query_string instead of query_string
                                // to safely handle user input without throwing
                                // exceptions on special characters
                                'simple_query_string' => [
                                    'query' => $builder->query,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if (isset($options['from'])) {
            $params['body']['from'] = $options['from'];
        }

        if (isset($options['size'])) {
            $params['body']['size'] = $options['size'];
        }

        if (
            isset($options['numericFilters'])
            && count($options['numericFilters'])
        ) {
            $params['body']['query']['bool']['must'] = array_merge(
                $params['body']['query']['bool']['must'],
                $options['numericFilters'],
            );
        }

        return $this->client->search($params);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     *
     * @return array|callable
     */
    public function search(Builder $builder): array|callable
    {
        return $this->performSearch($builder, array_filter([
            'numericFilters' => $this->filters($builder),
            'size' => $builder->limit,
        ]));
    }

    /**
     * Get the filter array for the query.
     *
     * @param Builder $builder
     *
     * @return array
     */
    protected function filters(Builder $builder): array
    {
        return collect($builder->wheres)
            ->map(
                /**
                 * @param mixed      $value
                 * @param int|string $key
                 *
                 * @return array
                 */
                static fn(mixed $value, int|string $key): array
                    => [
                        'match_phrase' => [$key => $value],
                    ],
            )
            ->values()
            ->all();
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param Builder $builder
     * @param mixed   $results
     * @param Model   $model
     *
     * @return Collection
     * @throws InvalidArgumentException
     */
    public function map(Builder $builder, $results, $model): Collection
    {
        if ($this->getTotalCount($results) === 0) {
            return Collection::make();
        }

        $keys = collect($results['hits']['hits'])
            ->pluck('_id')
            ->values()
            ->all();

        $models = $model
            ->query()
            ->whereIn($model->getKeyName(), $keys)
            ->get()
            ->keyBy($model->getKeyName());

        $collection = new Collection($results['hits']['hits']);

        return $collection->map(static fn(
            array $hit,
        )
            => $models[$hit['_id']]);
    }

    /**
     * Update the given model in the index.
     *
     * @param Collection $models
     *
     * @return void
     */
    public function update($models): void
    {
        $params = [
            'body' => [],
        ];

        $models->each(function (Model $model) use (&$params) {
            $params['body'][] = [
                'update' => [
                    '_id' => $model->getKey(),
                    '_index' => $this->index,
                ],
            ];

            assert(is_callable([$model, 'toSearchableArray']));

            $params['body'][] = [
                'doc' => $model->toSearchableArray(),
                'doc_as_upsert' => true,
            ];
        });

        $this->client->bulk($params);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function lazyMap(Builder $builder, $results, $model): LazyCollection
    {
        if ($this->getTotalCount($results) === 0) {
            return LazyCollection::make();
        }

        $keys = collect($results['hits']['hits'])
            ->pluck('_id')
            ->values()
            ->all();

        $models = $model
            ->newQuery()
            ->whereIn($model->getKeyName(), $keys)
            ->get()
            ->keyBy($model->getKeyName());

        $collection = new LazyCollection($results['hits']['hits']);

        return $collection->map(static fn(array $hit) => $models[$hit['_id']]);
    }

    public function createIndex($name, array $options = []): void
    {
        $this->client->indices()->create([
            'index' => $name,
            'body' => $options,
        ]);
    }

    public function deleteIndex($name): void
    {
        $this->client->indices()->delete([
            'index' => $name,
        ]);
    }
}
