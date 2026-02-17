<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Unit\Testing;

use Matchory\Elasticsearch\Testing\FakeClient;
use Matchory\Elasticsearch\Testing\Responses\CountResponse;
use Matchory\Elasticsearch\Testing\Responses\SearchResponse;
use Matchory\Elasticsearch\Testing\ResponseSequence;
use PHPUnit\Framework\TestCase;

class FakeClientTest extends TestCase
{
    // ------------------------------------------------------------------
    // Default responses
    // ------------------------------------------------------------------

    public function testDefaultSearchResponse(): void
    {
        $client = new FakeClient();
        $result = $client->search(['index' => 'test']);

        $this->assertSame(0, $result['hits']['total']['value']);
        $this->assertSame([], $result['hits']['hits']);
    }

    public function testDefaultCountResponse(): void
    {
        $client = new FakeClient();
        $result = $client->count();

        $this->assertSame(0, $result['count']);
    }

    public function testDefaultIndexResponse(): void
    {
        $client = new FakeClient();
        $result = $client->index(['body' => ['title' => 'Test']]);

        $this->assertSame('created', $result['result']);
    }

    public function testDefaultExistsResponse(): void
    {
        $client = new FakeClient();

        $this->assertTrue($client->exists(['index' => 'test', 'id' => '1']));
    }

    // ------------------------------------------------------------------
    // Queued responses (FIFO)
    // ------------------------------------------------------------------

    public function testQueuedResponsesConsumedFifo(): void
    {
        $client = new FakeClient(
            SearchResponse::fromDocuments([['title' => 'First']])->toArray(),
            SearchResponse::empty()->toArray(),
        );

        $first = $client->search();
        $second = $client->search();

        $this->assertCount(1, $first['hits']['hits']);
        $this->assertCount(0, $second['hits']['hits']);
    }

    public function testQueuedResponsesUsedBeforeStubs(): void
    {
        $client = new FakeClient(
            ['custom' => 'queued'],
        );
        $client->stubMethod('search', ['custom' => 'stubbed']);

        $this->assertSame('queued', $client->search()['custom']);
        // Queue exhausted, now uses stub
        $this->assertSame('stubbed', $client->search()['custom']);
    }

    public function testQueuedResponsesFallToDefaults(): void
    {
        $client = new FakeClient(['custom' => true]);
        $client->search(); // consume queued
        $result = $client->search(); // falls to default

        $this->assertArrayHasKey('hits', $result);
    }

    public function testPushResponse(): void
    {
        $client = new FakeClient();
        $client->pushResponse(CountResponse::make(99)->toArray());

        $result = $client->count();

        $this->assertSame(99, $result['count']);
    }

    // ------------------------------------------------------------------
    // Stubbed responses
    // ------------------------------------------------------------------

    public function testStubbedResponsesNotConsumed(): void
    {
        $client = new FakeClient();
        $client->stubMethod('search', SearchResponse::fromDocuments([['t' => 1]])->toArray());

        $first = $client->search();
        $second = $client->search();

        $this->assertCount(1, $first['hits']['hits']);
        $this->assertCount(1, $second['hits']['hits']);
    }

    // ------------------------------------------------------------------
    // Callable responses
    // ------------------------------------------------------------------

    public function testCallableResponse(): void
    {
        $client = new FakeClient(
            fn(string $method, array $params) => ['index' => $params['index'] ?? null],
        );

        $result = $client->search(['index' => 'products']);

        $this->assertSame('products', $result['index']);
    }

    // ------------------------------------------------------------------
    // ResponseSequence
    // ------------------------------------------------------------------

    public function testResponseSequence(): void
    {
        $client = new FakeClient(
            new ResponseSequence(
                SearchResponse::fromDocuments([['title' => 'First']]),
                SearchResponse::empty(),
            ),
        );

        $first = $client->search();
        $second = $client->search();

        $this->assertCount(1, $first['hits']['hits']);
        $this->assertCount(0, $second['hits']['hits']);
    }

    // ------------------------------------------------------------------
    // Recording
    // ------------------------------------------------------------------

    public function testRecordsCalls(): void
    {
        $client = new FakeClient();
        $client->search(['index' => 'products']);
        $client->search(['index' => 'users']);
        $client->count(['index' => 'products']);

        $this->assertCount(2, $client->recorded('search'));
        $this->assertCount(1, $client->recorded('count'));
        $this->assertSame('products', $client->recorded('search')[0]['index']);
    }

    public function testRecordedAllReturnsByMethod(): void
    {
        $client = new FakeClient();
        $client->search();
        $client->index(['body' => ['x' => 1]]);

        $all = $client->recordedAll();

        $this->assertArrayHasKey('search', $all);
        $this->assertArrayHasKey('index', $all);
    }

    public function testRecordedReturnsEmptyForUncalledMethod(): void
    {
        $client = new FakeClient();

        $this->assertSame([], $client->recorded('search'));
    }

    public function testReset(): void
    {
        $client = new FakeClient();
        $client->search();
        $client->pushResponse(['custom' => true]);
        $client->stubMethod('count', ['count' => 42]);
        $client->reset();

        $this->assertSame([], $client->recorded('search'));
        // After reset, queue and stubs are cleared, falls to defaults
        $this->assertSame(0, $client->count()['count']);
    }

    // ------------------------------------------------------------------
    // Indices namespace
    // ------------------------------------------------------------------

    public function testIndicesNamespace(): void
    {
        $client = new FakeClient();
        $result = $client->indices()->create(['index' => 'test']);

        $this->assertTrue($result['acknowledged']);
        $this->assertCount(1, $client->recorded('indices.create'));
        $this->assertSame('test', $client->recorded('indices.create')[0]['index']);
    }

    // ------------------------------------------------------------------
    // setResponseException duck-typing
    // ------------------------------------------------------------------

    public function testSetResponseExceptionReturnsSelf(): void
    {
        $client = new FakeClient();

        $this->assertSame($client, $client->setResponseException(false));
    }

    // ------------------------------------------------------------------
    // __call fallback
    // ------------------------------------------------------------------

    public function testMagicCallRecordsAndReturnsDefault(): void
    {
        $client = new FakeClient();
        $result = $client->someUnknownMethod(['x' => 1]);

        $this->assertSame([], $result);
        $this->assertCount(1, $client->recorded('someUnknownMethod'));
    }

    // ------------------------------------------------------------------
    // All client methods record correctly
    // ------------------------------------------------------------------

    public function testAllMethodsRecord(): void
    {
        $client = new FakeClient();

        $client->search();
        $client->count();
        $client->index();
        $client->get();
        $client->update();
        $client->delete();
        $client->bulk();
        $client->scroll();
        $client->clearScroll();
        $client->updateByQuery();
        $client->deleteByQuery();
        $client->exists();
        $client->info();
        $client->explain();
        $client->mget();
        $client->msearch();

        $all = $client->recordedAll();

        $this->assertCount(16, $all);
    }
}
