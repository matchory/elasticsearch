<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Unit\Testing;

use Matchory\Elasticsearch\Testing\Responses\BulkResponse;
use Matchory\Elasticsearch\Testing\Responses\CountResponse;
use Matchory\Elasticsearch\Testing\Responses\IndexResponse;
use Matchory\Elasticsearch\Testing\Responses\SearchResponse;
use PHPUnit\Framework\TestCase;

class FakeResponseTest extends TestCase
{
    // ------------------------------------------------------------------
    // SearchResponse
    // ------------------------------------------------------------------

    public function testSearchResponseEmpty(): void
    {
        $response = SearchResponse::empty()->toArray();

        $this->assertSame(0, $response['hits']['total']['value']);
        $this->assertSame([], $response['hits']['hits']);
        $this->assertNull($response['hits']['max_score']);
    }

    public function testSearchResponseFromDocuments(): void
    {
        $response = SearchResponse::fromDocuments([
            ['title' => 'Foo'],
            ['title' => 'Bar'],
        ])->toArray();

        $this->assertSame(2, $response['hits']['total']['value']);
        $this->assertCount(2, $response['hits']['hits']);
        $this->assertSame('Foo', $response['hits']['hits'][0]['_source']['title']);
        $this->assertSame('1', $response['hits']['hits'][0]['_id']);
        $this->assertSame('test_index', $response['hits']['hits'][0]['_index']);
        $this->assertSame('2', $response['hits']['hits'][1]['_id']);
    }

    public function testSearchResponseFromDocumentsCustomIndex(): void
    {
        $response = SearchResponse::fromDocuments(
            [['x' => 1]],
            'products',
        )->toArray();

        $this->assertSame('products', $response['hits']['hits'][0]['_index']);
    }

    public function testSearchResponseMakeWithRawHits(): void
    {
        $hits = [
            ['_index' => 'my_idx', '_id' => 'abc', '_score' => 2.5, '_source' => ['a' => 1]],
        ];

        $response = SearchResponse::make($hits)->toArray();

        $this->assertSame(1, $response['hits']['total']['value']);
        $this->assertSame('abc', $response['hits']['hits'][0]['_id']);
    }

    public function testSearchResponseMakeWithTotalOverride(): void
    {
        $response = SearchResponse::make([], 100)->toArray();

        $this->assertSame(100, $response['hits']['total']['value']);
    }

    public function testSearchResponseWithAggregations(): void
    {
        $aggs = ['category' => ['buckets' => [['key' => 'electronics', 'doc_count' => 5]]]];
        $response = SearchResponse::empty()->withAggregations($aggs)->toArray();

        $this->assertSame($aggs, $response['aggregations']);
    }

    public function testSearchResponseWithSuggestions(): void
    {
        $suggestions = ['my_suggest' => [['text' => 'test', 'options' => []]]];
        $response = SearchResponse::empty()->withSuggestions($suggestions)->toArray();

        $this->assertSame($suggestions, $response['suggest']);
    }

    public function testSearchResponseMergeIsImmutable(): void
    {
        $original = SearchResponse::empty();
        $withAggs = $original->withAggregations(['foo' => 'bar']);

        $this->assertArrayNotHasKey('aggregations', $original->toArray());
        $this->assertArrayHasKey('aggregations', $withAggs->toArray());
    }

    // ------------------------------------------------------------------
    // CountResponse
    // ------------------------------------------------------------------

    public function testCountResponse(): void
    {
        $response = CountResponse::make(42)->toArray();

        $this->assertSame(42, $response['count']);
        $this->assertArrayHasKey('_shards', $response);
    }

    // ------------------------------------------------------------------
    // IndexResponse
    // ------------------------------------------------------------------

    public function testIndexResponseCreated(): void
    {
        $response = IndexResponse::created('42', 'products')->toArray();

        $this->assertSame('42', $response['_id']);
        $this->assertSame('products', $response['_index']);
        $this->assertSame('created', $response['result']);
        $this->assertSame(1, $response['_version']);
    }

    public function testIndexResponseUpdated(): void
    {
        $response = IndexResponse::updated()->toArray();

        $this->assertSame('updated', $response['result']);
        $this->assertSame(2, $response['_version']);
    }

    public function testIndexResponseDeleted(): void
    {
        $response = IndexResponse::deleted()->toArray();

        $this->assertSame('deleted', $response['result']);
    }

    // ------------------------------------------------------------------
    // BulkResponse
    // ------------------------------------------------------------------

    public function testBulkResponseMake(): void
    {
        $response = BulkResponse::make([], false)->toArray();

        $this->assertFalse($response['errors']);
        $this->assertSame([], $response['items']);
    }

    public function testBulkResponseAllSuccessful(): void
    {
        $response = BulkResponse::allSuccessful(3)->toArray();

        $this->assertFalse($response['errors']);
        $this->assertCount(3, $response['items']);
        $this->assertSame('created', $response['items'][0]['index']['result']);
        $this->assertSame('3', $response['items'][2]['index']['_id']);
    }
}
