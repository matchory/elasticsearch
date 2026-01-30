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

/**
 * Tests for the ignore() method on queries.
 *
 * Note: In Elasticsearch PHP client v9, the `$params['client']['ignore']` pattern
 * was removed. Ignores are now stored internally and applied via setResponseException()
 * at execution time. The toArray() output no longer includes the 'client' key.
 */
class IgnoreTest extends TestCase
{
    use ESQueryTrait;

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
     * @test
     */
    public function ignore(): void
    {
        // Test that ignores are stored correctly via getIgnores()
        self::assertEquals(
            [404],
            $this->getActualIgnores(404),
        );
        self::assertEquals(
            [500, 404],
            $this->getActualIgnores(500, 404),
        );

        // Verify that toArray() doesn't include 'client' key (v9 change)
        $query = $this->getQueryObject()->ignore([404]);
        $result = $query->toArray();
        self::assertArrayNotHasKey('client', $result);
    }

    /**
     * Get the actual ignores from a query object.
     *
     * @throws ClassAlreadyExistsException
     * @throws ClassIsFinalException
     * @throws DuplicateMethodException
     * @throws InvalidMethodNameException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     * @throws \PHPUnit\Framework\InvalidArgumentException
     * @throws ClassIsReadonlyException
     */
    protected function getActualIgnores(int ...$args): array
    {
        return $this->getQueryObject()
                    ->ignore($args)
                    ->getIgnores();
    }
}
