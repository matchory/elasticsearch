<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Feature;

use Matchory\Elasticsearch\Tests\Support\Factories\ResponseFactory;
use Matchory\Elasticsearch\Tests\TestCase;

/**
 * Index Settings Management Feature Tests
 *
 * Tests for index settings configuration, updates, and retrieval.
 * This covers requirement 7.2 for index settings configuration and updates.
 */
class IndexSettingsManagementTest extends TestCase
{
    /**
     * Test getting index settings
     *
     * @test
     */
    public function it_gets_index_settings(): void
    {
        // Arrange
        $indexName = 'test_settings_index';
        $expectedSettings = [
            'index' => [
                'number_of_shards' => '3',
                'number_of_replicas' => '1',
                'refresh_interval' => '30s',
                'max_result_window' => '10000',
            ],
        ];
        $expectedResponse = ResponseFactory::getSettings([
            'index' => $indexName,
            'settings' => $expectedSettings,
        ]);

        $this->getMockClient()->setResponse('indices.getSettings', $expectedResponse);

        // Act
        $response = $this->getMockClient()->indices()->getSettings(['index' => $indexName]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.getSettings', [
            'index' => $indexName,
        ]));
    }

    /**
     * Test updating index settings
     *
     * @test
     */
    public function it_updates_index_settings(): void
    {
        // Arrange
        $indexName = 'test_update_settings_index';
        $newSettings = [
            'index' => [
                'number_of_replicas' => 2,
                'refresh_interval' => '60s',
                'max_result_window' => 20000,
            ],
        ];
        $expectedResponse = ResponseFactory::putSettings();

        $this->getMockClient()->setResponse('indices.putSettings', $expectedResponse);

        // Act
        $response = $this->getMockClient()->indices()->putSettings([
            'index' => $indexName,
            'body' => $newSettings,
        ]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.putSettings', [
            'index' => $indexName,
            'body' => $newSettings,
        ]));
    }

    /**
     * Test updating multiple index settings
     *
     * @test
     */
    public function it_updates_multiple_index_settings(): void
    {
        // Arrange
        $indices = ['index1', 'index2', 'index3'];
        $indexPattern = implode(',', $indices);
        $newSettings = [
            'index' => [
                'number_of_replicas' => 1,
                'refresh_interval' => '30s',
            ],
        ];
        $expectedResponse = ResponseFactory::putSettings();

        $this->getMockClient()->setResponse('indices.putSettings', $expectedResponse);

        // Act
        $response = $this->getMockClient()->indices()->putSettings([
            'index' => $indexPattern,
            'body' => $newSettings,
        ]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.putSettings', [
            'index' => $indexPattern,
            'body' => $newSettings,
        ]));
    }

    /**
     * Test updating settings with analysis configuration
     *
     * @test
     */
    public function it_updates_settings_with_analysis_configuration(): void
    {
        // Arrange
        $indexName = 'test_analysis_settings_index';
        $analysisSettings = [
            'analysis' => [
                'analyzer' => [
                    'custom_analyzer' => [
                        'type' => 'custom',
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'stop', 'snowball'],
                    ],
                ],
                'tokenizer' => [
                    'custom_tokenizer' => [
                        'type' => 'pattern',
                        'pattern' => '[\\W&&[^-]]',
                    ],
                ],
                'filter' => [
                    'custom_stop' => [
                        'type' => 'stop',
                        'stopwords' => ['the', 'and', 'or'],
                    ],
                ],
            ],
        ];
        $expectedResponse = ResponseFactory::putSettings();

        $this->getMockClient()->setResponse('indices.putSettings', $expectedResponse);

        // Act
        $response = $this->getMockClient()->indices()->putSettings([
            'index' => $indexName,
            'body' => $analysisSettings,
        ]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.putSettings', [
            'index' => $indexName,
            'body' => $analysisSettings,
        ]));
    }

    /**
     * Test getting settings for multiple indices
     *
     * @test
     */
    public function it_gets_settings_for_multiple_indices(): void
    {
        // Arrange
        $indices = ['index1', 'index2'];
        $indexPattern = implode(',', $indices);
        $expectedResponse = [
            'index1' => [
                'settings' => [
                    'index' => [
                        'number_of_shards' => '1',
                        'number_of_replicas' => '0',
                    ],
                ],
            ],
            'index2' => [
                'settings' => [
                    'index' => [
                        'number_of_shards' => '2',
                        'number_of_replicas' => '1',
                    ],
                ],
            ],
        ];

        $this->getMockClient()->setResponse('indices.getSettings', $expectedResponse);

        // Act
        $response = $this->getMockClient()->indices()->getSettings(['index' => $indexPattern]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.getSettings', [
            'index' => $indexPattern,
        ]));
    }

    /**
     * Test updating settings with performance optimizations
     *
     * @test
     */
    public function it_updates_settings_with_performance_optimizations(): void
    {
        // Arrange
        $indexName = 'test_performance_settings_index';
        $performanceSettings = [
            'index' => [
                'refresh_interval' => '30s',
                'number_of_replicas' => 0,
                'translog' => [
                    'flush_threshold_size' => '1gb',
                    'sync_interval' => '30s',
                ],
                'merge' => [
                    'policy' => [
                        'max_merge_at_once' => 5,
                        'segments_per_tier' => 10,
                    ],
                ],
                'mapping' => [
                    'total_fields' => [
                        'limit' => 2000,
                    ],
                ],
                'max_result_window' => 100000,
            ],
        ];
        $expectedResponse = ResponseFactory::putSettings();

        $this->getMockClient()->setResponse('indices.putSettings', $expectedResponse);

        // Act
        $response = $this->getMockClient()->indices()->putSettings([
            'index' => $indexName,
            'body' => $performanceSettings,
        ]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.putSettings', [
            'index' => $indexName,
            'body' => $performanceSettings,
        ]));
    }

    /**
     * Test getting specific settings
     *
     * @test
     */
    public function it_gets_specific_settings(): void
    {
        // Arrange
        $indexName = 'test_specific_settings_index';
        $settingName = 'number_of_replicas';
        $expectedResponse = ResponseFactory::getSettings([
            'index' => $indexName,
            'settings' => [
                'index' => [
                    'number_of_replicas' => '1',
                ],
            ],
        ]);

        $this->getMockClient()->setResponse('indices.getSettings', $expectedResponse);

        // Act
        $response = $this->getMockClient()->indices()->getSettings([
            'index' => $indexName,
            'name' => $settingName,
        ]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.getSettings', [
            'index' => $indexName,
            'name' => $settingName,
        ]));
    }

    /**
     * Test updating settings with validation
     *
     * @test
     */
    public function it_validates_settings_before_update(): void
    {
        // Arrange
        $indexName = 'test_validation_settings_index';
        $validSettings = [
            'index' => [
                'number_of_replicas' => 2,
                'refresh_interval' => '1s',
                'max_result_window' => 10000,
            ],
        ];
        $expectedResponse = ResponseFactory::putSettings();

        $this->getMockClient()->setResponse('indices.putSettings', $expectedResponse);

        // Act
        $response = $this->getMockClient()->indices()->putSettings([
            'index' => $indexName,
            'body' => $validSettings,
        ]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.putSettings', [
            'index' => $indexName,
            'body' => $validSettings,
        ]));
    }

    /**
     * Test closing and opening index for settings update
     *
     * @test
     */
    public function it_closes_and_opens_index_for_settings_update(): void
    {
        // Arrange
        $indexName = 'test_close_open_index';
        $closeResponse = ResponseFactory::closeIndex();
        $openResponse = ResponseFactory::openIndex();

        $this->getMockClient()->setResponse('indices.close', $closeResponse);
        $this->getMockClient()->setResponse('indices.open', $openResponse);

        // Act - Close index
        $closeResult = $this->getMockClient()->indices()->close(['index' => $indexName]);

        // Act - Open index
        $openResult = $this->getMockClient()->indices()->open(['index' => $indexName]);

        // Assert
        $this->assertEquals($closeResponse, $closeResult);
        $this->assertEquals($openResponse, $openResult);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.close', [
            'index' => $indexName,
        ]));
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.open', [
            'index' => $indexName,
        ]));
    }

    /**
     * Test updating settings with timeout
     *
     * @test
     */
    public function it_updates_settings_with_timeout(): void
    {
        // Arrange
        $indexName = 'test_timeout_settings_index';
        $settings = [
            'index' => [
                'number_of_replicas' => 1,
            ],
        ];
        $timeout = '30s';
        $expectedResponse = ResponseFactory::putSettings();

        $this->getMockClient()->setResponse('indices.putSettings', $expectedResponse);

        // Act
        $response = $this->getMockClient()->indices()->putSettings([
            'index' => $indexName,
            'body' => $settings,
            'timeout' => $timeout,
        ]);

        // Assert
        $this->assertEquals($expectedResponse, $response);
        $this->assertTrue($this->getMockClient()->wasMethodCalled('indices.putSettings', [
            'index' => $indexName,
            'body' => $settings,
            'timeout' => $timeout,
        ]));
    }
}
