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

class DistanceTest extends TestCase
{
    use ESQueryTrait;

    /**
     * Test the distance() method.
     *
     * @throws ExpectationFailedException
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
    public function testDistanceMethod(): void
    {
        self::assertEquals(
            $this->getExpected('location', ['lat' => -33.8688197, 'lon' => 151.20929550000005], '10km'),
            $this->getActual('location', ['lat' => -33.8688197, 'lon' => 151.20929550000005], '10km'),
        );

        self::assertNotEquals(
            $this->getExpected('location', ['lat' => -33.8688197, 'lon' => 151.20929550000005], '10km'),
            $this->getActual('location', ['lat' => -33.8688197, 'lon' => 151.20929550000005], '15km'),
        );
    }


    protected function getExpected(string $field, array $value, string $distance): array
    {
        $query = $this->getQueryArray();

        $query['body']['query']['bool']['filter'][] = [
            'geo_distance' => [
                $field => $value,
                'distance' => $distance,
            ],
        ];

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
    protected function getActual($field, $geo_point, $distance): array
    {
        return $this->getQueryObject()->distance($field, $geo_point, $distance)->toArray();
    }
}
