<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Support\Traits;

use Matchory\Elasticsearch\Collection;
use Matchory\Elasticsearch\Model;
use PHPUnit\Framework\Assert;

/**
 * Trait for Elasticsearch-specific assertions in tests
 */
trait AssertsElasticsearch
{
    /**
     * Assert that a response has the expected structure
     *
     * @param array $response
     * @param array $expectedKeys
     * @return void
     */
    protected function assertElasticsearchResponse(array $response, array $expectedKeys = []): void
    {
        $defaultKeys = ['took', 'timed_out', 'hits'];
        $keys = empty($expectedKeys) ? $defaultKeys : $expectedKeys;

        foreach ($keys as $key) {
            Assert::assertArrayHasKey($key, $response, "Response missing key: {$key}");
        }
    }

    /**
     * Assert that a search response has the expected structure
     *
     * @param array $response
     * @return void
     */
    protected function assertSearchResponse(array $response): void
    {
        $this->assertElasticsearchResponse($response, [
            'took', 'timed_out', 'hits', '_shards',
        ]);

        Assert::assertArrayHasKey('total', $response['hits']);
        Assert::assertArrayHasKey('hits', $response['hits']);
        Assert::assertIsArray($response['hits']['hits']);
    }

    /**
     * Assert that an index response has the expected structure
     *
     * @param array $response
     * @return void
     */
    protected function assertIndexResponse(array $response): void
    {
        $expectedKeys = ['_index', '_id', '_version', 'result', '_shards'];

        foreach ($expectedKeys as $key) {
            Assert::assertArrayHasKey($key, $response, "Index response missing key: {$key}");
        }

        Assert::assertContains($response['result'], ['created', 'updated']);
    }

    /**
     * Assert that a get response has the expected structure
     *
     * @param array $response
     * @return void
     */
    protected function assertGetResponse(array $response): void
    {
        $expectedKeys = ['_index', '_id', '_version', 'found', '_source'];

        foreach ($expectedKeys as $key) {
            Assert::assertArrayHasKey($key, $response, "Get response missing key: {$key}");
        }

        Assert::assertTrue($response['found'], 'Document should be found');
        Assert::assertIsArray($response['_source']);
    }

    /**
     * Assert that a delete response has the expected structure
     *
     * @param array $response
     * @return void
     */
    protected function assertDeleteResponse(array $response): void
    {
        $expectedKeys = ['_index', '_id', '_version', 'result', '_shards'];

        foreach ($expectedKeys as $key) {
            Assert::assertArrayHasKey($key, $response, "Delete response missing key: {$key}");
        }

        Assert::assertEquals('deleted', $response['result']);
    }

    /**
     * Assert that a bulk response has the expected structure
     *
     * @param array $response
     * @return void
     */
    protected function assertBulkResponse(array $response): void
    {
        Assert::assertArrayHasKey('took', $response);
        Assert::assertArrayHasKey('errors', $response);
        Assert::assertArrayHasKey('items', $response);
        Assert::assertIsArray($response['items']);
        Assert::assertIsBool($response['errors']);
    }

    /**
     * Assert that an error response has the expected structure
     *
     * @param array $response
     * @return void
     */
    protected function assertErrorResponse(array $response): void
    {
        Assert::assertArrayHasKey('error', $response);
        Assert::assertArrayHasKey('type', $response['error']);
        Assert::assertArrayHasKey('reason', $response['error']);
    }

    /**
     * Assert that a model has the expected attributes
     *
     * @param Model $model
     * @param array $expectedAttributes
     * @return void
     */
    protected function assertModelAttributes(Model $model, array $expectedAttributes): void
    {
        foreach ($expectedAttributes as $key => $expectedValue) {
            $actualValue = $model->getAttribute($key);
            Assert::assertEquals(
                $expectedValue,
                $actualValue,
                "Model attribute '{$key}' does not match expected value",
            );
        }
    }

    /**
     * Assert that an Elasticsearch model exists (has been saved)
     *
     * @param Model $model
     * @return void
     */
    protected function assertElasticsearchModelExists(Model $model): void
    {
        Assert::assertTrue($model->exists, 'Model should exist');
        Assert::assertNotNull($model->getKey(), 'Model should have an ID');
    }

    /**
     * Assert that an Elasticsearch model does not exist
     *
     * @param Model $model
     * @return void
     */
    protected function assertElasticsearchModelDoesNotExist(Model $model): void
    {
        Assert::assertFalse($model->exists, 'Model should not exist');
    }

    /**
     * Assert that a model was recently created
     *
     * @param Model $model
     * @return void
     */
    protected function assertModelWasRecentlyCreated(Model $model): void
    {
        Assert::assertTrue($model->wasRecentlyCreated, 'Model should be recently created');
        $this->assertElasticsearchModelExists($model);
    }

    /**
     * Assert that a collection has the expected count
     *
     * @param Collection $collection
     * @param int $expectedCount
     * @return void
     */
    protected function assertCollectionCount(Collection $collection, int $expectedCount): void
    {
        Assert::assertCount($expectedCount, $collection);
        Assert::assertEquals($expectedCount, $collection->count());
    }

    /**
     * Assert that a collection contains models of the expected type
     *
     * @param Collection $collection
     * @param string $expectedModelClass
     * @return void
     */
    protected function assertCollectionContainsModels(Collection $collection, string $expectedModelClass): void
    {
        foreach ($collection as $item) {
            Assert::assertInstanceOf($expectedModelClass, $item);
        }
    }

    /**
     * Assert that a collection is empty
     *
     * @param Collection $collection
     * @return void
     */
    protected function assertCollectionEmpty(Collection $collection): void
    {
        Assert::assertTrue($collection->isEmpty());
        Assert::assertCount(0, $collection);
    }

    /**
     * Assert that a collection is not empty
     *
     * @param Collection $collection
     * @return void
     */
    protected function assertCollectionNotEmpty(Collection $collection): void
    {
        Assert::assertFalse($collection->isEmpty());
        Assert::assertGreaterThan(0, $collection->count());
    }

    /**
     * Assert that a query parameter has the expected value
     *
     * @param array $query
     * @param string $parameter
     * @param mixed $expectedValue
     * @return void
     */
    protected function assertQueryParameter(array $query, string $parameter, $expectedValue): void
    {
        Assert::assertArrayHasKey($parameter, $query, "Query missing parameter: {$parameter}");
        Assert::assertEquals(
            $expectedValue,
            $query[$parameter],
            "Query parameter '{$parameter}' does not match expected value",
        );
    }

    /**
     * Assert that a query has the expected structure
     *
     * @param array $query
     * @param array $expectedKeys
     * @return void
     */
    protected function assertQueryStructure(array $query, array $expectedKeys): void
    {
        foreach ($expectedKeys as $key) {
            Assert::assertArrayHasKey($key, $query, "Query missing key: {$key}");
        }
    }

    /**
     * Assert that an index configuration is valid
     *
     * @param array $config
     * @return void
     */
    protected function assertValidIndexConfiguration(array $config): void
    {
        Assert::assertArrayHasKey('index', $config);
        Assert::assertArrayHasKey('body', $config);

        $body = $config['body'];
        Assert::assertArrayHasKey('settings', $body);
        Assert::assertArrayHasKey('mappings', $body);

        $settings = $body['settings'];
        Assert::assertArrayHasKey('number_of_shards', $settings);
        Assert::assertArrayHasKey('number_of_replicas', $settings);

        Assert::assertIsInt($settings['number_of_shards']);
        Assert::assertIsInt($settings['number_of_replicas']);
        Assert::assertGreaterThan(0, $settings['number_of_shards']);
        Assert::assertGreaterThanOrEqual(0, $settings['number_of_replicas']);
    }

    /**
     * Assert that a mapping has the expected field type
     *
     * @param array $mapping
     * @param string $field
     * @param string $expectedType
     * @return void
     */
    protected function assertMappingFieldType(array $mapping, string $field, string $expectedType): void
    {
        Assert::assertArrayHasKey('properties', $mapping);
        Assert::assertArrayHasKey($field, $mapping['properties']);
        Assert::assertArrayHasKey('type', $mapping['properties'][$field]);
        Assert::assertEquals(
            $expectedType,
            $mapping['properties'][$field]['type'],
            "Field '{$field}' should have type '{$expectedType}'",
        );
    }

    /**
     * Assert that a connection configuration is valid
     *
     * @param array $config
     * @return void
     */
    protected function assertValidConnectionConfiguration(array $config): void
    {
        Assert::assertArrayHasKey('hosts', $config);
        Assert::assertIsArray($config['hosts']);
        Assert::assertNotEmpty($config['hosts']);

        foreach ($config['hosts'] as $host) {
            if (is_string($host)) {
                Assert::assertMatchesRegularExpression('/^.+:\d+$/', $host, 'Host should be in format host:port');
            } elseif (is_array($host)) {
                Assert::assertArrayHasKey('host', $host);
                Assert::assertArrayHasKey('port', $host);
            }
        }
    }

    /**
     * Assert that two documents are equivalent
     *
     * @param array $expected
     * @param array $actual
     * @param array $ignoreKeys Keys to ignore in comparison
     * @return void
     */
    protected function assertDocumentsEqual(array $expected, array $actual, array $ignoreKeys = []): void
    {
        foreach ($ignoreKeys as $key) {
            unset($expected[$key], $actual[$key]);
        }

        Assert::assertEquals($expected, $actual);
    }
}
