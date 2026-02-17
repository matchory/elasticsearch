<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Testing\Responses;

final class IndexResponse extends FakeResponse
{
    public static function created(
        string $id = '1',
        string $index = 'test_index',
    ): self {
        return new self([
            '_index' => $index,
            '_id' => $id,
            '_version' => 1,
            'result' => 'created',
            '_shards' => ['total' => 2, 'successful' => 1, 'failed' => 0],
            '_seq_no' => 0,
            '_primary_term' => 1,
        ]);
    }

    public static function updated(
        string $id = '1',
        string $index = 'test_index',
    ): self {
        return new self([
            '_index' => $index,
            '_id' => $id,
            '_version' => 2,
            'result' => 'updated',
            '_shards' => ['total' => 2, 'successful' => 1, 'failed' => 0],
            '_seq_no' => 1,
            '_primary_term' => 1,
        ]);
    }

    public static function deleted(
        string $id = '1',
        string $index = 'test_index',
    ): self {
        return new self([
            '_index' => $index,
            '_id' => $id,
            '_version' => 2,
            'result' => 'deleted',
            '_shards' => ['total' => 2, 'successful' => 1, 'failed' => 0],
            '_seq_no' => 1,
            '_primary_term' => 1,
        ]);
    }
}
