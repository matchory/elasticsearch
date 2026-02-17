<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Testing\Responses;

use function array_map;
use function count;

final class SearchResponse extends FakeResponse
{
    /**
     * @param array<int, array<string, mixed>> $hits Raw hit arrays with _id, _source, etc.
     * @param int|null $total Total count override.
     */
    public static function make(array $hits = [], ?int $total = null): self
    {
        $total ??= count($hits);

        return new self([
            'took' => 1,
            'timed_out' => false,
            '_shards' => [
                'total' => 1,
                'successful' => 1,
                'skipped' => 0,
                'failed' => 0,
            ],
            'hits' => [
                'total' => [
                    'value' => $total,
                    'relation' => 'eq',
                ],
                'max_score' => $total > 0 ? 1.0 : null,
                'hits' => $hits,
            ],
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $documents Plain source arrays.
     * @param string $index Index name for generated hits.
     */
    public static function fromDocuments(
        array $documents,
        string $index = 'test_index',
    ): self {
        $hits = array_map(
            static fn(array $doc, int $i) => [
                '_index' => $index,
                '_id' => (string) ($i + 1),
                '_score' => 1.0,
                '_source' => $doc,
            ],
            $documents,
            array_keys($documents),
        );

        return self::make($hits);
    }

    public static function empty(): self
    {
        return self::make();
    }

    /**
     * @param array<string, mixed> $aggregations
     */
    public function withAggregations(array $aggregations): self
    {
        return $this->merge(['aggregations' => $aggregations]);
    }

    /**
     * @param array<string, mixed> $suggestions
     */
    public function withSuggestions(array $suggestions): self
    {
        return $this->merge(['suggest' => $suggestions]);
    }
}
