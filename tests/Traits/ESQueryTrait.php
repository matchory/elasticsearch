<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Traits;

use Elasticsearch\Client;
use Matchory\Elasticsearch\Connection;
use Matchory\Elasticsearch\Query;
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
     * @return Client
     * @throws InvalidArgumentException
     * @throws ClassAlreadyExistsException
     * @throws ClassIsFinalException
     * @throws ClassIsReadonlyException
     * @throws DuplicateMethodException
     * @throws InvalidMethodNameException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     */
    protected function getClient(): Client
    {
        return $this
            ->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @return Connection
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
     * @param Query|null $query
     *
     * @return Query
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
    protected function getQueryObject(?Query $query = null): Query
    {
        return ($query ?? new Query($this->getConnection()))
            ->index($this->index)
            ->take($this->take)
            ->skip($this->skip);
    }
}
