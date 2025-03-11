<?php

/**
 * This file is part of elasticsearch, a Matchory application.
 *
 * Unauthorized copying of this file, via any medium, is strictly prohibited.
 * Its contents are strictly confidential and proprietary.
 *
 * @copyright 2020–2021 Matchory GmbH · All rights reserved
 * @author    Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests;

use InvalidArgumentException;
use Matchory\Elasticsearch\Interfaces\ConnectionInterface;
use Matchory\Elasticsearch\Model;
use Matchory\Elasticsearch\Query;
use Matchory\Elasticsearch\Tests\Traits\ESQueryTrait;
use PHPUnit\Framework\Exception;
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

use function assert;

class GlobalScopeTest extends TestCase
{
    use ESQueryTrait;

    /**
     * @test
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
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function getGlobalScope(): void
    {
        $model = new class extends Model {
            public static ConnectionInterface|null $connection = null;

            public static function resolveConnection(
                string|null $connection = null,
            ): ConnectionInterface {
                assert(static::$connection !== null);

                return static::$connection;
            }
        };
        $model::$connection = $this->getConnection();

        $scope = static function (Query $query): void {};

        $model::addGlobalScope('foo', $scope);

        self::assertSame($scope, $model::getGlobalScope(
            'foo',
        ));

        self::assertNull($model::getGlobalScope('bar'));
    }

    /**
     * @test
     * @throws ClassAlreadyExistsException
     * @throws ClassIsFinalException
     * @throws ClassIsReadonlyException
     * @throws DuplicateMethodException
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws InvalidMethodNameException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     * @throws \PHPUnit\Framework\InvalidArgumentException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function getGlobalScopes(): void
    {
        $model = new class extends Model {
            public static ConnectionInterface|null $connection = null;

            public static function resolveConnection(
                string|null $connection = null,
            ): ConnectionInterface {
                assert(static::$connection !== null);

                return static::$connection;
            }
        };
        $model::$connection = $this->getConnection();

        $scope = static function (Query $query): void {};

        $model::addGlobalScope('foo', $scope);

        self::assertContains($scope, $model->getGlobalScopes());
    }

    /**
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
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @test
     */
    public function addGlobalScope(): void
    {
        self::assertEquals(
            $this->getExpected('views', 500),
            $this->getActual('views', 500),
        );
    }

    /**
     * @test
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
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function hasGlobalScope(): void
    {
        $model = new class extends Model {
            public static ConnectionInterface|null $connection = null;

            public static function resolveConnection(
                string|null $connection = null,
            ): ConnectionInterface {
                assert(static::$connection !== null);

                return static::$connection;
            }
        };
        $model::$connection = $this->getConnection();
        $model::addGlobalScope('foo', static function (
            Query $query,
        ) {});

        self::assertTrue($model::hasGlobalScope('foo'));
        self::assertFalse($model::hasGlobalScope('bar'));
    }

    /**
     * @test
     * @throws ClassAlreadyExistsException
     * @throws ClassIsFinalException
     * @throws ClassIsReadonlyException
     * @throws DuplicateMethodException
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws InvalidMethodNameException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     * @throws \PHPUnit\Framework\InvalidArgumentException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function withoutGlobalScope(): void
    {
        $model = new class extends Model {
            public static ConnectionInterface|null $connection = null;

            public static function resolveConnection(
                string|null $connection = null,
            ): ConnectionInterface {
                assert(static::$connection !== null);

                return static::$connection;
            }
        };
        $model::$connection = $this->getConnection();

        $scope = static function (Query $query): void {};
        $model::addGlobalScope('foo', $scope);

        self::assertTrue($model::hasGlobalScope('foo'));
        $query = $model->newQuery();
        self::assertNotContains('foo', $query->removedScopes());
        $query = $query->withoutGlobalScope('foo');
        self::assertContains('foo', $query->removedScopes());
    }

    /**
     * @test
     * @throws ClassAlreadyExistsException
     * @throws ClassIsFinalException
     * @throws ClassIsReadonlyException
     * @throws DuplicateMethodException
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws InvalidMethodNameException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     * @throws \PHPUnit\Framework\InvalidArgumentException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function withoutGlobalScopes(): void
    {
        $model = new class extends Model {
            public static ConnectionInterface|null $connection = null;

            public static function resolveConnection(
                string|null $connection = null,
            ): ConnectionInterface {
                assert(static::$connection !== null);

                return static::$connection;
            }
        };
        $model::$connection = $this->getConnection();

        $foo = static function (Query $query): void {};
        $bar = static function (Query $query): void {};
        $model::addGlobalScope('foo', $foo);
        $model::addGlobalScope('bar', $bar);

        self::assertTrue($model::hasGlobalScope('foo'));
        $query = $model->newQuery();
        self::assertNotContains('foo', $query->removedScopes());
        self::assertNotContains('bar', $query->removedScopes());
        $query = $query->withoutGlobalScopes();
        self::assertContains('foo', $query->removedScopes());
        self::assertContains('bar', $query->removedScopes());
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return array
     * @throws InvalidArgumentException
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
    protected function getActual(string $name, mixed $value): array
    {
        $model = new class extends Model {
            public static ConnectionInterface|null $connection = null;

            public static function resolveConnection(
                string|null $connection = null,
            ): ConnectionInterface {
                assert(static::$connection !== null);

                return static::$connection;
            }
        };
        $model::$connection = $this->getConnection();
        $model::addGlobalScope('foo', fn(
            Query $query,
        ) => $query->where($name, $value));

        return $this->getQueryObject($model->newQuery())->toArray();
    }

    protected function getExpected(string $name, mixed $value): array
    {
        return $this->getQueryArray([
            'query' => [
                'bool' => [
                    'filter' => [
                        [
                            'term' => [
                                $name => $value,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
