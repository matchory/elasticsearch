<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Unit\Testing;

use Matchory\Elasticsearch\Facades\Elasticsearch;
use Matchory\Elasticsearch\Model;
use Matchory\Elasticsearch\Testing\ElasticsearchFake;
use Matchory\Elasticsearch\Testing\Responses\SearchResponse;
use Matchory\Elasticsearch\Tests\TestCase;
use PHPUnit\Framework\ExpectationFailedException;

class ElasticsearchFakeTest extends TestCase
{
    // ------------------------------------------------------------------
    // Facade swap
    // ------------------------------------------------------------------

    public function testFakeSwapsFacadeAndModelResolver(): void
    {
        $fake = Elasticsearch::fake();

        $this->assertInstanceOf(ElasticsearchFake::class, $fake);
        $this->assertInstanceOf(
            ElasticsearchFake::class,
            Model::getConnectionResolver(),
        );
    }

    public function testUnfakeRestoresOriginalResolver(): void
    {
        $originalResolver = Model::getConnectionResolver();

        Elasticsearch::fake();
        Elasticsearch::unfake();

        $restored = Model::getConnectionResolver();

        $this->assertNotInstanceOf(ElasticsearchFake::class, $restored);
        $this->assertSame($originalResolver, $restored);
    }

    public function testUnfakeIsIdempotent(): void
    {
        Elasticsearch::unfake();
        Elasticsearch::unfake();

        // Should not throw
        $this->assertTrue(true);
    }

    // ------------------------------------------------------------------
    // Connection resolution
    // ------------------------------------------------------------------

    public function testConnectionReturnsConnectionInterface(): void
    {
        $fake = new ElasticsearchFake();
        $connection = $fake->connection();

        $this->assertInstanceOf(
            \Matchory\Elasticsearch\Interfaces\ConnectionInterface::class,
            $connection,
        );
    }

    public function testConnectionCachesPerName(): void
    {
        $fake = new ElasticsearchFake();

        $this->assertSame($fake->connection('a'), $fake->connection('a'));
        $this->assertNotSame($fake->connection('a'), $fake->connection('b'));
    }

    public function testDefaultConnectionName(): void
    {
        $fake = new ElasticsearchFake();
        $fake->setDefaultConnection('custom');

        $this->assertSame('custom', $fake->getDefaultConnection());
    }

    // ------------------------------------------------------------------
    // Assertions
    // ------------------------------------------------------------------

    public function testAssertSentPasses(): void
    {
        $fake = Elasticsearch::fake(
            SearchResponse::empty()->toArray(),
        );

        Model::query()->get();

        $fake->assertSent('search');
    }

    public function testAssertSentWithCallback(): void
    {
        $fake = Elasticsearch::fake(
            SearchResponse::empty()->toArray(),
        );

        Model::query()->index('products')->get();

        $fake->assertSent('search', fn(array $params) => $params['index'] === 'products');
    }

    public function testAssertSentFailsWhenNotCalled(): void
    {
        $fake = Elasticsearch::fake();

        $this->expectException(ExpectationFailedException::class);
        $fake->assertSent('search');
    }

    public function testAssertNotSentPasses(): void
    {
        $fake = Elasticsearch::fake();

        $fake->assertNotSent('search');
    }

    public function testAssertNotSentFailsWhenCalled(): void
    {
        $fake = Elasticsearch::fake(
            SearchResponse::empty()->toArray(),
        );

        Model::query()->get();

        $this->expectException(ExpectationFailedException::class);
        $fake->assertNotSent('search');
    }

    public function testAssertNotSentWithCallback(): void
    {
        $fake = Elasticsearch::fake(
            SearchResponse::empty()->toArray(),
        );

        Model::query()->index('products')->get();

        // Should pass: no call with index=users
        $fake->assertNotSent('search', fn(array $params) => ($params['index'] ?? null) === 'users');
    }

    public function testAssertNothingSentPasses(): void
    {
        $fake = Elasticsearch::fake();

        $fake->assertNothingSent();
    }

    public function testAssertNothingSentFails(): void
    {
        $fake = Elasticsearch::fake(
            SearchResponse::empty()->toArray(),
        );

        Model::query()->get();

        $this->expectException(ExpectationFailedException::class);
        $fake->assertNothingSent();
    }

    public function testAssertSentCount(): void
    {
        $fake = Elasticsearch::fake(
            SearchResponse::empty()->toArray(),
            SearchResponse::empty()->toArray(),
        );

        Model::query()->get();
        Model::query()->get();

        $fake->assertSentCount('search', 2);
    }

    public function testAssertSentCountFails(): void
    {
        $fake = Elasticsearch::fake(
            SearchResponse::empty()->toArray(),
        );

        Model::query()->get();

        $this->expectException(ExpectationFailedException::class);
        $fake->assertSentCount('search', 2);
    }

    // ------------------------------------------------------------------
    // Recorded
    // ------------------------------------------------------------------

    public function testRecordedByMethod(): void
    {
        $fake = Elasticsearch::fake(
            SearchResponse::empty()->toArray(),
        );

        Model::query()->index('test')->get();

        $calls = $fake->recorded('search');

        $this->assertCount(1, $calls);
        $this->assertSame('test', $calls[0]['index']);
    }

    public function testRecordedAll(): void
    {
        $fake = Elasticsearch::fake(
            SearchResponse::empty()->toArray(),
        );

        Model::query()->get();

        $all = $fake->recorded();

        $this->assertArrayHasKey('search', $all);
    }

    // ------------------------------------------------------------------
    // End-to-end: Model returns hydrated results
    // ------------------------------------------------------------------

    public function testModelReturnsHydratedModels(): void
    {
        Elasticsearch::fake(
            SearchResponse::fromDocuments([
                ['title' => 'Widget', 'price' => 9.99],
                ['title' => 'Gadget', 'price' => 19.99],
            ])->toArray(),
        );

        $results = Model::query()->index('products')->get();

        $this->assertCount(2, $results);
        $this->assertSame('Widget', $results[0]->title);
        $this->assertSame(19.99, $results[1]->price);
    }

    public function testModelFirstReturnsHydratedModel(): void
    {
        Elasticsearch::fake(
            SearchResponse::fromDocuments([
                ['title' => 'First'],
            ])->toArray(),
        );

        $model = Model::query()->index('test')->first();

        $this->assertNotNull($model);
        $this->assertSame('First', $model->title);
    }

    protected function tearDown(): void
    {
        Elasticsearch::unfake();
        parent::tearDown();
    }
}
