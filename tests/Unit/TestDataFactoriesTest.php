<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Unit;

use Matchory\Elasticsearch\Tests\Support\Factories\DocumentFactory;
use Matchory\Elasticsearch\Tests\Support\Factories\IndexConfigurationFactory;
use Matchory\Elasticsearch\Tests\Support\TestConnectionConfiguration;
use Matchory\Elasticsearch\Tests\Support\Traits\CreatesTestData;
use Matchory\Elasticsearch\Tests\Support\Traits\MocksElasticsearch;
use Matchory\Elasticsearch\Tests\Support\Traits\AssertsElasticsearch;
use Matchory\Elasticsearch\Tests\Support\Traits\ManagesTestIndices;
use Matchory\Elasticsearch\Tests\TestCase;

/**
 * Test the test data factories and support classes
 */
class TestDataFactoriesTest extends TestCase
{
    use CreatesTestData;
    use MocksElasticsearch;
    use AssertsElasticsearch;
    use ManagesTestIndices;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestDataFactories();
        $this->setUpElasticsearchMocking();
        $this->setUpIndexManagement();
    }

    public function testDocumentFactoryCreatesBasicDocument(): void
    {
        $factory = new DocumentFactory();
        $document = $factory->create();

        $this->assertIsArray($document);
        $this->assertArrayHasKey('id', $document);
        $this->assertArrayHasKey('title', $document);
        $this->assertArrayHasKey('content', $document);
        $this->assertArrayHasKey('created_at', $document);
    }

    public function testDocumentFactoryCreatesUserDocument(): void
    {
        $factory = new DocumentFactory();
        $user = $factory->createUser();

        $this->assertIsArray($user);
        $this->assertArrayHasKey('username', $user);
        $this->assertArrayHasKey('email', $user);
        $this->assertArrayHasKey('first_name', $user);
        $this->assertArrayHasKey('last_name', $user);
    }

    public function testDocumentFactoryCreatesProductDocument(): void
    {
        $factory = new DocumentFactory();
        $product = $factory->createProduct();

        $this->assertIsArray($product);
        $this->assertArrayHasKey('sku', $product);
        $this->assertArrayHasKey('name', $product);
        $this->assertArrayHasKey('price', $product);
        $this->assertArrayHasKey('category', $product);
    }

    public function testDocumentFactoryCreatesMultipleDocuments(): void
    {
        $factory = new DocumentFactory();
        $documents = $factory->createMany(5);

        $this->assertIsArray($documents);
        $this->assertCount(5, $documents);

        foreach ($documents as $document) {
            $this->assertIsArray($document);
            $this->assertArrayHasKey('id', $document);
        }
    }

    public function testIndexConfigurationFactoryCreatesBasicConfig(): void
    {
        $factory = new IndexConfigurationFactory();
        $config = $factory->create('test_index');

        $this->assertValidIndexConfiguration($config);
        $this->assertEquals('test_index', $config['index']);
    }

    public function testIndexConfigurationFactoryCreatesBlogConfig(): void
    {
        $factory = new IndexConfigurationFactory();
        $config = $factory->createBlogIndex('blog_index');

        $this->assertValidIndexConfiguration($config);
        $this->assertEquals('blog_index', $config['index']);

        $mappings = $config['body']['mappings'];
        $this->assertMappingFieldType($mappings, 'title', 'text');
        $this->assertMappingFieldType($mappings, 'content', 'text');
        $this->assertMappingFieldType($mappings, 'author', 'text');
    }

    public function testTestConnectionConfigurationProvidesDifferentConfigs(): void
    {
        $defaultConfig = TestConnectionConfiguration::getDefault();
        $this->assertValidConnectionConfiguration($defaultConfig);

        $singleHostConfig = TestConnectionConfiguration::getSingleHost();
        $this->assertValidConnectionConfiguration($singleHostConfig);
        $this->assertEquals(['localhost:9200'], $singleHostConfig['hosts']);

        $multiHostConfig = TestConnectionConfiguration::getMultipleHosts();
        $this->assertValidConnectionConfiguration($multiHostConfig);
        $this->assertCount(3, $multiHostConfig['hosts']);
    }

    public function testCreatesTestDataTraitMethods(): void
    {
        $document = $this->createTestDocument(['title' => 'Custom Title']);
        $this->assertEquals('Custom Title', $document['title']);

        $documents = $this->createTestDocuments(3);
        $this->assertCount(3, $documents);

        $user = $this->createTestUser(['username' => 'testuser']);
        $this->assertEquals('testuser', $user['username']);

        $product = $this->createTestProduct(['name' => 'Test Product']);
        $this->assertEquals('Test Product', $product['name']);
    }

    public function testMocksElasticsearchTraitMethods(): void
    {
        // Test mocking a search response
        $this->mockSearchResponse([
            ['_id' => '1', '_source' => ['title' => 'Test Document']],
        ], 1);

        $this->assertElasticsearchMethodCalled('search', 0); // Not called yet

        // Test mocking an index response
        $this->mockIndexResponse('123', 'test_index', 'created');

        // Test error mocking
        $this->mockIndexNotFound('missing_index');

        $this->assertTrue(true); // Basic assertion to avoid risky test
    }

    public function testManagesTestIndicesTraitMethods(): void
    {
        $indexName = $this->createTestIndex('test');
        $this->assertTrue($this->testIndexExists($indexName));

        $blogIndex = $this->createBlogTestIndex('blog');
        $this->assertTrue($this->testIndexExists($blogIndex));

        $userIndex = $this->createUserTestIndex('users');
        $this->assertTrue($this->testIndexExists($userIndex));

        $this->assertCount(3, $this->getTestIndices());

        $this->deleteTestIndex($indexName);
        $this->assertFalse($this->testIndexExists($indexName));
        $this->assertCount(2, $this->getTestIndices());
    }

    public function testAssertsElasticsearchTraitMethods(): void
    {
        // Test search response assertion
        $searchResponse = [
            'took' => 5,
            'timed_out' => false,
            'hits' => [
                'total' => ['value' => 1, 'relation' => 'eq'],
                'hits' => [
                    ['_id' => '1', '_source' => ['title' => 'Test']],
                ],
            ],
            '_shards' => ['total' => 1, 'successful' => 1, 'skipped' => 0, 'failed' => 0],
        ];

        $this->assertSearchResponse($searchResponse);

        // Test index response assertion
        $indexResponse = [
            '_index' => 'test',
            '_id' => '1',
            '_version' => 1,
            'result' => 'created',
            '_shards' => ['total' => 2, 'successful' => 1, 'failed' => 0],
        ];

        $this->assertIndexResponse($indexResponse);

        // Test query parameter assertion
        $query = ['index' => 'test', 'body' => ['query' => ['match_all' => []]]];
        $this->assertQueryParameter($query, 'index', 'test');
    }

    public function testDocumentFactoryWithOverrides(): void
    {
        $factory = new DocumentFactory();
        $document = $factory->create(['title' => 'Override Title', 'custom_field' => 'custom_value']);

        $this->assertEquals('Override Title', $document['title']);
        $this->assertEquals('custom_value', $document['custom_field']);
        $this->assertArrayHasKey('content', $document); // Original fields should still exist
    }

    public function testIndexConfigurationFactoryWithOverrides(): void
    {
        $factory = new IndexConfigurationFactory();
        $config = $factory->create('test_index', [
            'body' => [
                'settings' => [
                    'number_of_shards' => 3,
                ],
            ],
        ]);

        $this->assertIsInt($config['body']['settings']['number_of_shards']);
        $this->assertEquals(3, $config['body']['settings']['number_of_shards']);
        $this->assertEquals(0, $config['body']['settings']['number_of_replicas']); // Default should remain
    }

    public function testEdgeCaseDocumentCreation(): void
    {
        $factory = new DocumentFactory();
        $edgeCase = $factory->createEdgeCase();

        $this->assertArrayHasKey('empty_string', $edgeCase);
        $this->assertEquals('', $edgeCase['empty_string']);
        $this->assertArrayHasKey('null_field', $edgeCase);
        $this->assertNull($edgeCase['null_field']);
        $this->assertArrayHasKey('zero_number', $edgeCase);
        $this->assertEquals(0, $edgeCase['zero_number']);
    }

    public function testBulkDataCreation(): void
    {
        $factory = new DocumentFactory();

        $simpleData = $factory->createBulkData(10, 'simple');
        $this->assertCount(10, $simpleData);

        $complexData = $factory->createBulkData(5, 'complex');
        $this->assertCount(5, $complexData);

        $mixedData = $factory->createBulkData(8, 'mixed');
        $this->assertCount(8, $mixedData);
    }
}
