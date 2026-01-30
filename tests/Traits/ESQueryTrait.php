<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Traits;

use Matchory\Elasticsearch\Connection;
use Matchory\Elasticsearch\Builder;
use Matchory\Elasticsearch\Tests\Support\Mocks\MockElasticsearchClient;
use PHPUnit\Framework\InvalidArgumentException;
use PHPUnit\Framework\MockObject\ClassAlreadyExistsException;
use PHPUnit\Framework\MockObject\ClassIsFinalException;
use PHPUnit\Framework\MockObject\ClassIsReadonlyException;
use PHPUnit\Framework\MockObject\DuplicateMethodException;
use PHPUnit\Framework\MockObject\InvalidMethodNameException;
use PHPUnit\Framework\MockObject\OriginalConstructorInvocationRequiredException;
use PHPUnit\Framework\MockObject\ReflectionException;
use PHPUnit\Framework\MockObject\RuntimeException;
use PHPUnit\Framework\MockObject\UnknownTypeException;

/**
 * Class ESQueryTrait
 *
 * Provides helpers for tests that need to create Query and Connection objects.
 * Uses MockElasticsearchClient since the real Client class is final in v9.
 */
trait ESQueryTrait
{
    /**
     * Test index name
     *
     * @var string
     */
    protected string $index = 'my_index';

    /**
     * Test query offset
     *
     * @var int
     */
    protected int $skip = 0;

    /**
     * Test query limit
     *
     * @var int
     */
    protected int $take = 10;

    /**
     * Get a mock Elasticsearch client
     *
     * Note: In Elasticsearch PHP client v9, the Client class is final and
     * cannot be mocked with PHPUnit. We use MockElasticsearchClient instead.
     *
     * @return MockElasticsearchClient
     */
    protected function getClient(): MockElasticsearchClient
    {
        return new MockElasticsearchClient();
    }

    /**
     * @return Connection
     */
    protected function getConnection(): Connection
    {
        return new Connection($this->getClient());
    }

    /**
     * Expected query array
     *
     * @param array $body
     *
     * @return array
     */
    protected function getQueryArray(array $body = []): array
    {
        return [
            'index' => $this->index,
            'body' => $body,
            'from' => $this->skip,
            'size' => $this->take,
        ];
    }

    /**
     * ES query object
     *
     * @param Builder|null $query
     *
     * @return Builder
     * @throws ClassAlreadyExistsException
     * @throws ClassIsFinalException
     * @throws ClassIsReadonlyException
     * @throws DuplicateMethodException
     * @throws InvalidArgumentException
     * @throws InvalidMethodNameException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     */
    protected function getQueryObject(?Builder $query = null): Builder
    {
        return ($query ?? new Builder($this->getConnection()))
            ->index($this->index)
            ->take($this->take)
            ->skip($this->skip);
    }
}
