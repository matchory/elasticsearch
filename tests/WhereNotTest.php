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

class WhereNotTest extends TestCase
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
     * Test the whereNot() method.
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
    public function testWhereNotMethod(): void
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

        $must = [];
        $must_not = [];

        if ($operator === '=') {
            $must_not[] = ['term' => [$name => $value]];
        }

        if ($operator === '>') {
            $must_not[] = ['range' => [$name => ['gt' => $value]]];
        }

        if ($operator === '>=') {
            $must_not[] = ['range' => [$name => ['gte' => $value]]];
        }

        if ($operator === '<') {
            $must_not[] = ['range' => [$name => ['lt' => $value]]];
        }

        if ($operator === '<=') {
            $must_not[] = ['range' => [$name => ['lte' => $value]]];
        }

        if ($operator === 'like') {
            $must_not[] = ['match' => [$name => $value]];
        }

        if ($operator === 'exists') {
            if ($value) {
                $must_not[] = ['exists' => ['field' => $name]];
            } else {
                $must[] = ['exists' => ['field' => $name]];
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

        $query['body']['query']['bool'] = $bool;

        return $query;
    }

    /**
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
    protected function getActual(string $name, string|null $operator = '=', mixed $value = null): array
    {
        return $this->getQueryObject()->whereNot($name, $operator, $value)->toArray();
    }
}
