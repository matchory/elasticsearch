<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Testing\Responses;

final class CountResponse extends FakeResponse
{
    public static function make(int $count): self
    {
        return new self([
            'count' => $count,
            '_shards' => [
                'total' => 1,
                'successful' => 1,
                'skipped' => 0,
                'failed' => 0,
            ],
        ]);
    }
}
