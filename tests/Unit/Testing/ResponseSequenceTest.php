<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Unit\Testing;

use Matchory\Elasticsearch\Testing\Responses\SearchResponse;
use Matchory\Elasticsearch\Testing\ResponseSequence;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ResponseSequenceTest extends TestCase
{
    public function testReturnsResponsesInOrder(): void
    {
        $sequence = new ResponseSequence(
            ['first' => true],
            ['second' => true],
        );

        $this->assertSame(['first' => true], $sequence->next('search', []));
        $this->assertSame(['second' => true], $sequence->next('search', []));
    }

    public function testThrowsWhenExhaustedWithoutFallback(): void
    {
        $sequence = new ResponseSequence(['only' => true]);
        $sequence->next('search', []);

        $this->expectException(RuntimeException::class);
        $sequence->next('search', []);
    }

    public function testWhenEmptyFallback(): void
    {
        $sequence = new ResponseSequence(['first' => true]);
        $sequence->whenEmpty(['fallback' => true]);

        $this->assertSame(['first' => true], $sequence->next('search', []));
        $this->assertSame(['fallback' => true], $sequence->next('search', []));
        $this->assertSame(['fallback' => true], $sequence->next('search', []));
    }

    public function testFakeResponseInstancesAreConverted(): void
    {
        $sequence = new ResponseSequence(
            SearchResponse::fromDocuments([['title' => 'Test']]),
        );

        $result = $sequence->next('search', []);

        $this->assertSame(1, $result['hits']['total']['value']);
    }

    public function testCallableResponses(): void
    {
        $sequence = new ResponseSequence(
            fn(string $method, array $params) => ['method' => $method],
        );

        $result = $sequence->next('count', []);

        $this->assertSame('count', $result['method']);
    }
}
