<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Unit;

use Matchory\Elasticsearch\Model;
use Matchory\Elasticsearch\Builder;
use Matchory\Elasticsearch\Tests\Support\Traits\AssertsElasticsearch;
use Matchory\Elasticsearch\Tests\Support\Traits\ConfiguresElasticsearch;
use Matchory\Elasticsearch\Tests\TestCase;

/**
 * Query Scopes Tests
 *
 * Tests global and local scope application in queries
 */
class QueryScopesTest extends TestCase
{
    use ConfiguresElasticsearch;
    use AssertsElasticsearch;

    private TestModelWithScopes $model;
    private Builder $query;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new TestModelWithScopes();
        $this->query = $this->model->newQuery();
    }

    /**
     * Test local scope is applied when called
     */
    public function testLocalScopeIsAppliedWhenCalled(): void
    {
        $result = $this->query->published()->toArray();

        $expectedFilter = [
            'term' => [
                'status' => 'published',
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test local scope with parameters
     */
    public function testLocalScopeWithParameters(): void
    {
        $result = $this->query->category('news')->toArray();

        $expectedFilter = [
            'term' => [
                'category' => 'news',
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test chaining multiple local scopes
     */
    public function testChainingMultipleLocalScopes(): void
    {
        $result = $this->query
            ->published()
            ->category('tech')
            ->toArray();

        $filters = $result['body']['query']['bool']['filter'];

        $this->assertContains(['term' => ['status' => 'published']], $filters);
        $this->assertContains(['term' => ['category' => 'tech']], $filters);
        $this->assertCount(2, $filters);
    }

    /**
     * Test local scope with complex query logic
     */
    public function testLocalScopeWithComplexQueryLogic(): void
    {
        $result = $this->query->popular()->toArray();

        // Should add both a range filter and a must condition
        $filters = $result['body']['query']['bool']['filter'];
        $must = $result['body']['query']['bool']['must'];

        $this->assertContains(['range' => ['views' => ['gte' => 1000]]], $filters);
        $this->assertContains(['exists' => ['field' => 'featured_image']], $must);
    }

    /**
     * Test global scope is automatically applied
     */
    public function testGlobalScopeIsAutomaticallyApplied(): void
    {
        $model = new TestModelWithGlobalScope();

        // Check if global scopes are registered
        $globalScopes = $model->getGlobalScopes();
        $this->assertArrayHasKey('active', $globalScopes, 'Global scope "active" should be registered');

        $query = $model->newQuery();
        $result = $query->toArray();

        $expectedFilter = [
            'term' => [
                'active' => '1',  // Boolean true gets converted to string '1'
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test global scope can be removed
     */
    public function testGlobalScopeCanBeRemoved(): void
    {
        $model = new TestModelWithGlobalScope();
        $query = $model->newQuery()->withoutGlobalScope('active');
        $result = $query->toArray();

        // Should not contain the active filter
        $filters = $result['body']['query']['bool']['filter'] ?? [];
        $this->assertNotContains(['term' => ['active' => true]], $filters);
    }

    /**
     * Test multiple global scopes are applied
     */
    public function testMultipleGlobalScopesAreApplied(): void
    {
        $model = new TestModelWithMultipleGlobalScopes();
        $query = $model->newQuery();
        $result = $query->toArray();

        $filters = $result['body']['query']['bool']['filter'];

        $this->assertContains(['term' => ['active' => '1']], $filters);
        $this->assertContains(['term' => ['visible' => '1']], $filters);
        $this->assertCount(2, $filters);
    }

    /**
     * Test withoutGlobalScopes removes all global scopes
     */
    public function testWithoutGlobalScopesRemovesAllGlobalScopes(): void
    {
        $model = new TestModelWithMultipleGlobalScopes();
        $query = $model->newQuery()->withoutGlobalScopes();
        $result = $query->toArray();

        // Should not contain any global scope filters
        $filters = $result['body']['query']['bool']['filter'] ?? [];
        $this->assertEmpty($filters);
    }

    /**
     * Test scope method exists check
     */
    public function testScopeMethodExistsCheck(): void
    {
        $this->assertTrue($this->query->hasNamedScope('published'));
        $this->assertTrue($this->query->hasNamedScope('category'));
        $this->assertFalse($this->query->hasNamedScope('nonexistent'));
    }

    /**
     * Test calling non-existent scope throws exception
     */
    public function testCallingNonExistentScopeThrowsException(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Method nonexistentScope does not exist.');

        $this->query->nonexistentScope();
    }

    /**
     * Test scope with closure parameter
     */
    public function testScopeWithClosureParameter(): void
    {
        $result = $this->query->withCondition(function (Builder $query) {
            $query->where('priority', '>', 5);
        })->toArray();

        $expectedFilter = [
            'range' => [
                'priority' => [
                    'gt' => 5,
                ],
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }

    /**
     * Test scope that modifies query parameters
     */
    public function testScopeThatModifiesQueryParameters(): void
    {
        $result = $this->query->latest()->toArray();

        $expectedSort = [
            ['created_at' => 'desc'],
        ];

        $this->assertArrayHasKey('sort', $result['body']);
        $this->assertEquals($expectedSort, $result['body']['sort']);
    }

    /**
     * Test scope that adds aggregations
     */
    public function testScopeThatAddsAggregations(): void
    {
        $result = $this->query->withCategoryStats()->toArray();

        $expectedAggregation = [
            'category_stats' => [
                'terms' => [
                    'field' => 'category',
                ],
            ],
        ];

        $this->assertArrayHasKey('aggs', $result['body']);
        $this->assertEquals($expectedAggregation, $result['body']['aggs']);
    }

    /**
     * Test global scope with closure
     */
    public function testGlobalScopeWithClosure(): void
    {
        $model = new TestModelWithClosureGlobalScope();
        $query = $model->newQuery();
        $result = $query->toArray();

        $expectedFilter = [
            'range' => [
                'created_at' => [
                    'gte' => 'now-30d',
                ],
            ],
        ];

        $this->assertContains($expectedFilter, $result['body']['query']['bool']['filter']);
    }
}

/**
 * Test model with local scopes for testing
 */
class TestModelWithScopes extends Model
{
    /**
     * Scope to filter published documents
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope to filter by category
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for popular documents
     */
    public function scopePopular(Builder $query): Builder
    {
        return $query
            ->where('views', '>=', 1000)
            ->whereExists('featured_image');
    }

    /**
     * Scope with closure parameter
     */
    public function scopeWithCondition(Builder $query, \Closure $callback): Builder
    {
        $callback($query);
        return $query;
    }

    /**
     * Scope that modifies sorting
     */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Scope that adds aggregations
     */
    public function scopeWithCategoryStats(Builder $query): Builder
    {
        return $query->aggregate('category_stats', 'category');
    }
}

/**
 * Test model with global scope
 */
class TestModelWithGlobalScope extends Model
{
    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope('active', function (Builder $query) {
            $query->where('active', '=', true);
        });
    }
}

/**
 * Test model with multiple global scopes
 */
class TestModelWithMultipleGlobalScopes extends Model
{
    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope('active', function (Builder $query) {
            $query->where('active', '=', true);
        });

        static::addGlobalScope('visible', function (Builder $query) {
            $query->where('visible', '=', true);
        });
    }
}

/**
 * Test model with closure global scope
 */
class TestModelWithClosureGlobalScope extends Model
{
    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope('recent', function (Builder $query) {
            $query->where('created_at', '>=', 'now-30d');
        });
    }
}
