<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests;

use Matchory\Elasticsearch\Tests\Traits\ESQueryTrait;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\MockObject\ClassAlreadyExistsException;
use PHPUnit\Framework\MockObject\ClassIsFinalException;
use PHPUnit\Framework\MockObject\ClassIsReadonlyException;
use PHPUnit\Framework\MockObject\DuplicateMethodException;
use PHPUnit\Framework\MockObject\InvalidMethodNameException;
use PHPUnit\Framework\MockObject\OriginalConstructorInvocationRequiredException;
use PHPUnit\Framework\MockObject\ReflectionException;
use PHPUnit\Framework\MockObject\RuntimeException;
use PHPUnit\Framework\MockObject\UnknownTypeException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

class SearchTest extends TestCase
{
    use ESQueryTrait;

    /**
     * Test the search() method.
     *
     * @return void
     * @throws ClassAlreadyExistsException
     * @throws ClassIsFinalException
     * @throws ClassIsReadonlyException
     * @throws DuplicateMethodException
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws InvalidMethodNameException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     * @throws \PHPUnit\Framework\InvalidArgumentException
     */
    public function testSearchMethod(): void
    {
        self::assertEquals(
            $this->getExpected('foo'),
            $this->getActual('foo'),
        );
    }

    /**
     * Get The actual results.
     *
     * @throws \PHPUnit\Framework\InvalidArgumentException
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
    protected function getActual(string $q, int $boost = 1): array
    {
        return $this->getQueryObject()
                    ->search($q, boost: $boost)
                    ->toArray();
    }

    /**
     * Get The expected results.
     */
    protected function getExpected(array|string $body, int|float $boost = 1): array
    {
        $query = $this->getQueryArray();
        $search_params = [];
        $search_params['query'] = $body;

        if ($boost > 1) {
            $search_params['boost'] = $boost;
        }

        $query['body']['query']['bool']['must'][] = [
            'query_string' => $search_params,
        ];

        return $query;
    }
}
