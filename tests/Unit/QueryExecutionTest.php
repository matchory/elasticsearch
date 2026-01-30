<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Unit;

use Illuminate\Pagination\LengthAwarePaginator;
use Matchory\Elasticsearch\Builder;
use Matchory\Elasticsearch\Collection;
use Matchory\Elasticsearch\Model;
use Matchory\Elasticsearch\Tests\Support\Factories\DocumentFactory;
use Matchory\Elasticsearch\Tests\Support\Factories\ResponseFactory;
use Matchory\Elasticsearch\Tests\Support\Traits\AssertsElasticsearch;
use Matchory\Elasticsearch\Tests\Support\Traits\ConfiguresElasticsearch;
use Matchory\Elasticsearch\Tests\TestCase;

/**
 * Query Execution and Result Parsing Tests
 *
 * Tests query execution, result parsing, and model hydration functionality
 */
class QueryExecutionTest extends TestCase
{
    use ConfiguresElasticsearch;
    use AssertsElasticsearch;

    private Builder $query;
    private Model $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new Model();
        $this->query = $this->model->newQuery();
    }

    /**
     * Test basic query execution returns collection
     */
    public function testBasicQueryExecutionReturnsCollection(): void
    {
        // Mock search response
        $searchResponse = ResponseFactory::searchResponse([
            DocumentFactory::createDocument(['id' => 1, 'title' => 'Test Document 1']),
            DocumentFactory::createDocument(['id' => 2, 'title' => 'Test Document 2']),
        ]);

        $this->getMockClient()->setResponse('search', $searchResponse);

        $result = $this->query->get();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
        $this->assertEquals('Test Document 1', $result[0]->title);
        $this->assertEquals('Test Document 2', $result[1]->title);
    }

    /**
     * Test empty query result returns empty collection
     */
    public function testEmptyQueryResultReturnsEmptyCollection(): void
    {
        $searchResponse = ResponseFactory::searchResponse([]);

        $this->getMockClient()->setResponse('search', $searchResponse);

        $result = $this->query->get();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    /**
     * Test first method returns single model
     */
    public function testFirstMethodReturnsSingleModel(): void
    {
        $searchResponse = ResponseFactory::searchResponse([
            DocumentFactory::createDocument(['id' => 1, 'title' => 'First Document']),
        ]);

        $this->getMockClient()->setResponse('search', $searchResponse);

        $result = $this->query->first();

        $this->assertInstanceOf(Model::class, $result);
        $this->assertEquals('First Document', $result->title);
        $this->assertEquals('1', $result->_id);
    }

    /**
     * Test first method returns null when no results
     */
    public function testFirstMethodReturnsNullWhenNoResults(): void
    {
        $searchResponse = ResponseFactory::searchResponse([]);

        $this->getMockClient()->setResponse('search', $searchResponse);

        $result = $this->query->first();

        $this->assertNull($result);
    }

    /**
     * Test firstOr method with callback
     */
    public function testFirstOrMethodWithCallback(): void
    {
        $searchResponse = ResponseFactory::searchResponse([]);

        $this->getMockClient()->setResponse('search', $searchResponse);

        $defaultModel = new Model(['title' => 'Default']);

        $result = $this->query->firstOr(function () use ($defaultModel) {
            return $defaultModel;
        });

        $this->assertInstanceOf(Model::class, $result);
        $this->assertEquals('Default', $result->title);
    }

    /**
     * Test firstOrFail throws exception when no results
     */
    public function testFirstOrFailThrowsExceptionWhenNoResults(): void
    {
        $searchResponse = ResponseFactory::searchResponse([]);

        $this->getMockClient()->setResponse('search', $searchResponse);

        $this->expectException(\Matchory\Elasticsearch\Exceptions\DocumentNotFoundException::class);

        $this->query->firstOrFail();
    }

    /**
     * Test model hydration with metadata
     */
    public function testModelHydrationWithMetadata(): void
    {
        $document = DocumentFactory::createDocument([
            'id' => 1,
            'title' => 'Test Document',
            'content' => 'Test content',
        ], [
            '_score' => 1.5,
            'highlight' => [
                'title' => ['<em>Test</em> Document'],
            ],
        ]);

        $searchResponse = ResponseFactory::searchResponse([$document]);

        $this->getMockClient()->setResponse('search', $searchResponse);

        $result = $this->query->first();

        $this->assertEquals('Test Document', $result->title);
        $this->assertEquals('Test content', $result->content);
        $this->assertEquals(1.5, $result->_score);
        $this->assertEquals(['title' => ['<em>Test</em> Document']], $result->highlight);
    }

    /**
     * Test model hydration preserves index information
     */
    public function testModelHydrationPreservesIndexInformation(): void
    {
        $document = DocumentFactory::createDocument(['id' => 1, 'title' => 'Test'], [], 'custom_index');

        $searchResponse = ResponseFactory::searchResponse([$document]);

        $this->getMockClient()->setResponse('search', $searchResponse);

        $result = $this->query->first();

        $this->assertEquals('custom_index', $result->_index);
    }

    /**
     * Test count method returns correct count
     */
    public function testCountMethodReturnsCorrectCount(): void
    {
        $countResponse = ResponseFactory::countResponse(42);

        $this->getMockClient()->setResponse('count', $countResponse);

        $result = $this->query->count();

        $this->assertEquals(42, $result);
    }

    /**
     * Test count method removes unsupported parameters
     */
    public function testCountMethodRemovesUnsupportedParameters(): void
    {
        $this->query
            ->size(20)
            ->from(10)
            ->select(['title'])
            ->orderBy('created_at');

        $countResponse = ResponseFactory::countResponse(5);

        $this->getMockClient()->setResponse('count', $countResponse);

        $result = $this->query->count();

        // Verify that count was called without size, from, _source, and sort
        $this->assertTrue($this->getMockClient()->wasMethodCalled('count'));
        $calls = $this->getMockClient()->getMethodCalls('count');
        $params = $calls[0];

        $this->assertArrayNotHasKey('size', $params);
        $this->assertArrayNotHasKey('from', $params);
        $this->assertArrayNotHasKey('_source', $params['body'] ?? []);
        $this->assertArrayNotHasKey('sort', $params['body'] ?? []);
    }

    /**
     * Test pagination returns Pagination instance
     */
    public function testPaginationReturnsPaginationInstance(): void
    {
        $documents = [
            DocumentFactory::createDocument(['id' => 1, 'title' => 'Document 1']),
            DocumentFactory::createDocument(['id' => 2, 'title' => 'Document 2']),
            DocumentFactory::createDocument(['id' => 3, 'title' => 'Document 3']),
        ];

        $searchResponse = ResponseFactory::searchResponse($documents, 25); // Total of 25 documents

        $this->getMockClient()->setResponse('search', $searchResponse);

        $result = $this->query->paginate(10, 'page', 1);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(3, $result->items());
        $this->assertEquals(25, $result->total());
        $this->assertEquals(10, $result->perPage());
        $this->assertEquals(1, $result->currentPage());
    }

    /**
     * Test pagination calculates correct offset
     */
    public function testPaginationCalculatesCorrectOffset(): void
    {
        $documents = [
            DocumentFactory::createDocument(['id' => 11, 'title' => 'Document 11']),
            DocumentFactory::createDocument(['id' => 12, 'title' => 'Document 12']),
        ];

        $searchResponse = ResponseFactory::searchResponse($documents, 25);

        $this->getMockClient()->setResponse('search', $searchResponse);

        $this->query->paginate(10, 'page', 2);

        // Verify the query was executed with correct from parameter
        $this->assertTrue($this->getMockClient()->wasMethodCalled('search'));
        $calls = $this->getMockClient()->getMethodCalls('search');
        $params = $calls[0];

        $this->assertEquals(10, $params['from']); // Page 2 with 10 per page = offset 10
        $this->assertEquals(10, $params['size']);
    }

    /**
     * Test scroll functionality
     */
    public function testScrollFunctionality(): void
    {
        $scrollResponse = ResponseFactory::scrollResponse([
            DocumentFactory::createDocument(['id' => 1, 'title' => 'Scroll Document 1']),
        ], 'scroll_id_123');

        $this->getMockClient()->setResponse('scroll', $scrollResponse);

        $result = $this->query->get('existing_scroll_id');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
        $this->assertEquals('Scroll Document 1', $result[0]->title);

        // Verify scroll was called with correct parameters
        $this->assertTrue($this->getMockClient()->wasMethodCalled('scroll'));
        $calls = $this->getMockClient()->getMethodCalls('scroll');
        $params = $calls[0];

        $this->assertArrayHasKey('body', $params);
        $this->assertArrayHasKey('scroll_id', $params['body']);
        $this->assertEquals('existing_scroll_id', $params['body']['scroll_id']);
    }

    /**
     * Test clear scroll functionality
     */
    public function testClearScrollFunctionality(): void
    {
        $clearResponse = ResponseFactory::clearScrollResponse();

        $this->getMockClient()->setResponse('clearScroll', $clearResponse);

        $result = $this->query->clear('scroll_id_123');

        $this->assertInstanceOf(Collection::class, $result);

        // Verify clearScroll was called with correct parameters
        $this->assertTrue($this->getMockClient()->wasMethodCalled('clearScroll'));
        $calls = $this->getMockClient()->getMethodCalls('clearScroll');
        $params = $calls[0];

        $this->assertEquals('scroll_id_123', $params['scroll_id']);
    }

    /**
     * Test bulk insert functionality
     */
    public function testBulkInsertFunctionality(): void
    {
        $bulkResponse = ResponseFactory::bulkResponse([
            ['index' => ['_id' => '1', 'status' => 201]],
            ['index' => ['_id' => '2', 'status' => 201]],
        ]);

        $this->getMockClient()->setResponse('bulk', $bulkResponse);

        $data = [
            '1' => ['title' => 'Document 1', 'content' => 'Content 1'],
            '2' => ['title' => 'Document 2', 'content' => 'Content 2'],
        ];

        $result = $this->query->bulk($data);

        $this->assertIsObject($result);

        // Verify bulk was called with correct structure
        $this->assertTrue($this->getMockClient()->wasMethodCalled('bulk'));
        $calls = $this->getMockClient()->getMethodCalls('bulk');
        $params = $calls[0];

        $this->assertArrayHasKey('body', $params);
        $this->assertIsArray($params['body']);
        $this->assertCount(4, $params['body']); // 2 documents * 2 lines each (action + source)
    }

    /**
     * Test insert functionality
     */
    public function testInsertFunctionality(): void
    {
        $insertResponse = ResponseFactory::indexResponse('1', 'created');

        $this->getMockClient()->setResponse('index', $insertResponse);

        $attributes = ['title' => 'New Document', 'content' => 'New content'];

        $result = $this->query->insert($attributes, '1');

        $this->assertIsObject($result);

        // Verify index was called with correct parameters
        $this->assertTrue($this->getMockClient()->wasMethodCalled('index'));
        $calls = $this->getMockClient()->getMethodCalls('index');
        $params = $calls[0];

        $this->assertEquals('1', $params['id']);
        $this->assertEquals($attributes, $params['body']);
    }

    /**
     * Test update functionality
     */
    public function testUpdateFunctionality(): void
    {
        $updateResponse = ResponseFactory::updateResponse('1');

        $this->getMockClient()->setResponse('update', $updateResponse);

        $attributes = ['title' => 'Updated Document'];

        $result = $this->query->update($attributes, '1');

        $this->assertIsObject($result);

        // Verify update was called with correct parameters
        $this->assertTrue($this->getMockClient()->wasMethodCalled('update'));
        $calls = $this->getMockClient()->getMethodCalls('update');
        $params = $calls[0];

        $this->assertEquals('1', $params['id']);
        $this->assertArrayHasKey('doc', $params['body']);
        $this->assertEquals($attributes, $params['body']['doc']);
    }

    /**
     * Test delete functionality
     */
    public function testDeleteFunctionality(): void
    {
        $deleteResponse = ResponseFactory::deleteResponse('1');

        $this->getMockClient()->setResponse('delete', $deleteResponse);

        $result = $this->query->delete('1');

        $this->assertIsObject($result);

        // Verify delete was called with correct parameters
        $this->assertTrue($this->getMockClient()->wasMethodCalled('delete'));
        $calls = $this->getMockClient()->getMethodCalls('delete');
        $params = $calls[0];

        $this->assertEquals('1', $params['id']);
    }

    /**
     * Test script update functionality
     */
    public function testScriptUpdateFunctionality(): void
    {
        $updateResponse = ResponseFactory::updateResponse('1');

        $this->getMockClient()->setResponse('update', $updateResponse);

        $script = 'ctx._source.views += params.increment';
        $params = ['increment' => 1];

        $result = $this->query->script($script, $params);

        $this->assertIsObject($result);

        // Verify update was called with script parameters
        $this->assertTrue($this->getMockClient()->wasMethodCalled('update'));
        $calls = $this->getMockClient()->getMethodCalls('update');
        $updateParams = $calls[0];

        $this->assertArrayHasKey('script', $updateParams['body']);
        $this->assertEquals($script, $updateParams['body']['script']['source']);
        $this->assertEquals($params, $updateParams['body']['script']['params']);
    }

    /**
     * Test increment functionality
     */
    public function testIncrementFunctionality(): void
    {
        $updateResponse = ResponseFactory::updateResponse('1');

        $this->getMockClient()->setResponse('update', $updateResponse);

        $result = $this->query->increment('views', 5);

        $this->assertIsObject($result);

        // Verify update was called with increment script
        $this->assertTrue($this->getMockClient()->wasMethodCalled('update'));
        $calls = $this->getMockClient()->getMethodCalls('update');
        $params = $calls[0];

        $this->assertStringContainsString('ctx._source.views += params.count', $params['body']['script']['source']);
        $this->assertEquals(['count' => 5], $params['body']['script']['params']);
    }

    /**
     * Test decrement functionality
     */
    public function testDecrementFunctionality(): void
    {
        $updateResponse = ResponseFactory::updateResponse('1');

        $this->getMockClient()->setResponse('update', $updateResponse);

        $result = $this->query->decrement('views', 3);

        $this->assertIsObject($result);

        // Verify update was called with decrement script
        $this->assertTrue($this->getMockClient()->wasMethodCalled('update'));
        $calls = $this->getMockClient()->getMethodCalls('update');
        $params = $calls[0];

        $this->assertStringContainsString('ctx._source.views -= params.count', $params['body']['script']['source']);
        $this->assertEquals(['count' => 3], $params['body']['script']['params']);
    }

    /**
     * Test collection metadata is preserved
     */
    public function testCollectionMetadataIsPreserved(): void
    {
        $documents = [
            DocumentFactory::createDocument(['id' => 1, 'title' => 'Document 1']),
        ];

        $searchResponse = ResponseFactory::searchResponse($documents, 100, 5, true);

        $this->getMockClient()->setResponse('search', $searchResponse);

        $result = $this->query->get();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEquals(100, $result->getTotal());
        $this->assertEquals(5, $result->getTook());
        $this->assertTrue($result->getTimedOut());
    }

    /**
     * Test query execution with error handling
     */
    public function testQueryExecutionWithErrorHandling(): void
    {
        $this->getMockClient()->setException('search', new \Exception('Search failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Search failed');

        $this->query->get();
    }
}
