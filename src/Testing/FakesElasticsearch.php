<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Testing;

use Matchory\Elasticsearch\Facades\Elasticsearch;
use PHPUnit\Framework\Attributes\After;

trait FakesElasticsearch
{
    /**
     * Set up the Elasticsearch fake with optional queued responses.
     *
     * @param mixed ...$responses Initial queued responses.
     */
    protected function fakeElasticsearch(mixed ...$responses): ElasticsearchFake
    {
        return Elasticsearch::fake(...$responses);
    }

    #[After]
    protected function tearDownElasticsearchFake(): void
    {
        Elasticsearch::unfake();
    }
}
