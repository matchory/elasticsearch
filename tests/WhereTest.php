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

class WhereTest extends TestCase
{
    use ESQueryTrait;

    protected array $operators = [
        '=',
        '!=',
        '>',
        '>=',
        '<',
        '<=',
        'like',
        'exists',
    ];

    /**
     * Test the where() method.
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
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\InvalidArgumentException
     */
    public function testWhereMethod(): void
    {
        self::assertEquals(
            $this->getExpected('status', 'published'),
            $this->getActual('status', 'published')
        );

        self::assertEquals(
            $this->getExpected('status', '=', 'published'),
            $this->getActual('status', '=', 'published')
        );

        self::assertEquals(
            $this->getExpected('views', '>', 1000),
            $this->getActual('views', '>', 1000)
        );

        self::assertEquals(
            $this->getExpected('views', '>=', 1000),
            $this->getActual('views', '>=', 1000)
        );

        self::assertEquals(
            $this->getExpected('views', '<=', 1000),
            $this->getActual('views', '<=', 1000)
        );

        self::assertEquals(
            $this->getExpected('content', 'like', 'hello'),
            $this->getActual('content', 'like', 'hello')
        );

        self::assertEquals(
            $this->getExpected('website', 'exists', true),
            $this->getActual('website', 'exists', true)
        );

        self::assertEquals(
            $this->getExpected('website', 'exists', false),
            $this->getActual('website', 'exists', false)
        );
    }

    /**
     * @param mixed|null $value
     * @throws ClassAlreadyExistsException
     * @throws ClassIsFinalException
     * @throws DuplicateMethodException
     * @throws InvalidMethodNameException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\InvalidArgumentException
     * @throws ClassIsReadonlyException
     */
    protected function getActual(
        string $name,
        string $operator = '=',
        mixed $value = null
    ): array {
        return $this
            ->getQueryObject()
            ->where($name, $operator, $value)
            ->toArray();
    }

    /**
     * @param mixed|null $value
     *
     */
    protected function getExpected(
        string $name,
        string $operator = '=',
        mixed $value = null
    ): array {
        $query = $this->getQueryArray();

        if (!in_array(
            $operator,
            $this->operators,
            true
        )) {
            $value = $operator;
            $operator = '=';
        }

        $filter = [];
        $must = [];
        $must_not = [];

        if ($operator === '=') {
            $filter[] = ['term' => [$name => $value]];
        }

        if ($operator === '>') {
            $filter[] = ['range' => [$name => ['gt' => $value]]];
        }

        if ($operator === '>=') {
            $filter[] = ['range' => [$name => ['gte' => $value]]];
        }

        if ($operator === '<') {
            $filter[] = ['range' => [$name => ['lt' => $value]]];
        }

        if ($operator === '<=') {
            $filter[] = ['range' => [$name => ['lte' => $value]]];
        }

        if ($operator === 'like') {
            $must[] = ['match' => [$name => $value]];
        }

        if ($operator === 'exists') {
            if ($value) {
                $must[] = ['exists' => ['field' => $name]];
            } else {
                $must_not[] = ['exists' => ['field' => $name]];
            }
        }

        // Build query body

        $bool = [];

        if (count($must)) {
            $bool['must'] = $must;
        }

        if (count($must_not)) {
            $bool['must_not'] = $must_not;
        }

        if (count($filter)) {
            $bool['filter'] = $filter;
        }

        $query['body']['query']['bool'] = $bool;

        return $query;
    }
}
