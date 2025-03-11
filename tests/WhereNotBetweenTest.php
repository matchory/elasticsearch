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

class WhereNotBetweenTest extends TestCase
{
    use ESQueryTrait;

    /**
     * Test the whereNotBetween() method.
     *
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
    public function testWhereNotBetweenMethod(): void
    {

        self::assertEquals(
            $this->getExpected('views', 500, 1000),
            $this->getActual('views', 500, 1000),
        );

        self::assertEquals(
            $this->getExpected('views', [500, 1000]),
            $this->getActual('views', [500, 1000]),
        );

    }


    /**
     * Get The expected results.
     */
    protected function getExpected($name, $first_value, $second_value = null): array
    {
        $query = $this->getQueryArray();

        if (is_array($first_value) && count($first_value) == 2) {
            $second_value = $first_value[1];
            $first_value = $first_value[0];
        }

        $query['body']['query']['bool']['must_not'][] = ['range' => [$name => ['gte' => $first_value, 'lte' => $second_value]]];

        return $query;
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
    protected function getActual($name, $first_value, $second_value = null): array
    {
        return $this->getQueryObject()->whereNotBetween($name, $first_value, $second_value)->toArray();
    }
}
