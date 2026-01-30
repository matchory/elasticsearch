<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Unit;

use Matchory\Elasticsearch\Tests\Support\Mocks\MockElasticsearchClient;
use Matchory\Elasticsearch\Tests\TestCase;

/**
 * Test the testing infrastructure setup
 */
class TestInfrastructureTest extends TestCase
{
    public function testMockClientIsAvailable(): void
    {
        $client = $this->getMockClient();

        $this->assertInstanceOf(MockElasticsearchClient::class, $client);
    }

    public function testMockClientCanRecordCalls(): void
    {
        $client = $this->getMockClient();

        $client->search(['index' => 'test_index']);

        $this->assertTrue($client->wasMethodCalled('search'));
        $this->assertTrue($client->wasMethodCalled('search', ['index' => 'test_index']));
        $this->assertFalse($client->wasMethodCalled('index'));
    }

    public function testHelperMethodsWork(): void
    {
        $indexConfig = $this->createTestIndexConfig('my_index');
        $this->assertEquals('my_index', $indexConfig['index']);

        $document = $this->createTestDocument(['title' => 'Test Title']);
        $this->assertEquals('Test Title', $document['title']);
    }
}
