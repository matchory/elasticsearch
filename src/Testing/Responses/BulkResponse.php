<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Testing\Responses;

use function range;
use function array_map;

final class BulkResponse extends FakeResponse
{
    /**
     * @param array<int, array<string, mixed>> $items
     */
    public static function make(array $items = [], bool $errors = false): self
    {
        return new self([
            'took' => 1,
            'errors' => $errors,
            'items' => $items,
        ]);
    }

    public static function allSuccessful(
        int $count,
        string $index = 'test_index',
    ): self {
        $items = array_map(
            static fn(int $i) => [
                'index' => [
                    '_index' => $index,
                    '_id' => (string) $i,
                    '_version' => 1,
                    'result' => 'created',
                    '_shards' => ['total' => 2, 'successful' => 1, 'failed' => 0],
                    'status' => 201,
                    '_seq_no' => $i - 1,
                    '_primary_term' => 1,
                ],
            ],
            range(1, $count),
        );

        return self::make($items);
    }
}
