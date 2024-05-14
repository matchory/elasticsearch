<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection as BaseCollection;
use JsonException;
use stdClass;

use function array_map;
use function is_array;
use function json_encode;

/**
 * Collection
 *
 * @template TKey of array-key
 * @template-covariant TValue of Model
 * @extends BaseCollection<TKey, TValue>
 * @package Matchory\Elasticsearch
 */
class Collection extends BaseCollection
{
    /**
     * Collection constructor.
     *
     * @param iterable $items
     * @param int|null $total
     * @param float|null $maxScore
     * @param float|null $duration
     * @param bool|null $timedOut
     * @param string|null $scrollId
     * @param stdClass|null $shards
     * @param array|null $suggestions
     * @param array|null $aggregations
     */
    public function __construct(
        iterable $items = [],
        protected int|null $total = null,
        protected float|null $maxScore = null,
        protected float|null $duration = null,
        protected bool|null $timedOut = null,
        protected string|null $scrollId = null,
        protected stdClass|null $shards = null,
        protected array|null $suggestions = null,
        protected array|null $aggregations = null
    ) {
        parent::__construct($items);
    }

    public static function fromResponse(
        array $response,
        array|null $items = null
    ): self {
        $items = $items ?? $response['hits']['hits'] ?? [];

        $maxScore = (float)$response['hits']['max_score'];
        $duration = (float)$response['took'];
        $timedOut = (bool)$response['timed_out'];
        $scrollId = (string)($response['_scroll_id'] ?? null);
        /** @var stdClass $shards */
        $shards = (object)$response['_shards'];
        $suggestions = $response['suggest'] ?? [];
        $aggregations = $response['aggregations'] ?? [];
        $total = (int)(is_array($response['hits']['total'])
            ? $response['hits']['total']['value']
            : $response['hits']['total']
        );

        return new self(
            $items,
            $total,
            $maxScore,
            $duration,
            $timedOut,
            $scrollId,
            $shards,
            $suggestions,
            $aggregations,
        );
    }

    public function getAggregations(): BaseCollection
    {
        return new BaseCollection($this->aggregations);
    }

    public function getAllSuggestions(): BaseCollection
    {
        return BaseCollection
            ::make($this->suggestions)
            ->mapInto(BaseCollection::class);
    }

    public function getDuration(): float|null
    {
        return $this->duration;
    }

    public function getMaxScore(): float|null
    {
        return $this->maxScore;
    }

    public function getScrollId(): string|null
    {
        return $this->scrollId;
    }

    public function getShards(): stdClass|null
    {
        return $this->shards;
    }

    public function getSuggestions(string $name): BaseCollection
    {
        return new BaseCollection($this->suggestions[$name] ?? []);
    }

    public function getTotal(): int|null
    {
        return $this->total;
    }

    public function isTimedOut(): bool|null
    {
        return $this->timedOut;
    }

    /**
     * Get the collection of items as JSON.
     *
     * @param int $options
     *
     * @return string
     * @throws JsonException
     */
    public function toJson($options = 0): string
    {
        return json_encode(
            $this->toArray(),
            JSON_THROW_ON_ERROR | $options
        );
    }

    /**
     * @inheritDoc
     * @psalm-suppress DocblockTypeContradiction
     */
    public function toArray(): array
    {
        return array_map(static function ($item) {
            return $item instanceof Arrayable
                ? $item->toArray()
                : $item;
        }, $this->items);
    }
}
