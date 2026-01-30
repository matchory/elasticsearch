<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Support\Factories;

/**
 * Factory for creating test index configurations and mappings
 */
class IndexConfigurationFactory
{
    /**
     * Create a basic index configuration
     *
     * @param string $name Index name
     * @param array $overrides Configuration overrides
     * @return array
     */
    public function create(string $name, array $overrides = []): array
    {
        $config = [
            'index' => $name,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'analysis' => [
                        'analyzer' => [
                            'standard_analyzer' => [
                                'type' => 'standard',
                                'stopwords' => '_english_',
                            ],
                        ],
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'id' => [
                            'type' => 'keyword',
                        ],
                        'title' => [
                            'type' => 'text',
                            'analyzer' => 'standard',
                        ],
                        'content' => [
                            'type' => 'text',
                            'analyzer' => 'standard',
                        ],
                        'created_at' => [
                            'type' => 'date',
                            'format' => 'strict_date_optional_time||epoch_millis',
                        ],
                        'updated_at' => [
                            'type' => 'date',
                            'format' => 'strict_date_optional_time||epoch_millis',
                        ],
                    ],
                ],
            ],
        ];

        return $this->mergeConfig($config, $overrides);
    }

    /**
     * Create a blog index configuration
     *
     * @param string $name Index name
     * @param array $overrides Configuration overrides
     * @return array
     */
    public function createBlogIndex(string $name = 'test_blog', array $overrides = []): array
    {
        $config = [
            'index' => $name,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'analysis' => [
                        'analyzer' => [
                            'blog_analyzer' => [
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => ['lowercase', 'stop', 'snowball'],
                            ],
                        ],
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'keyword'],
                        'title' => [
                            'type' => 'text',
                            'analyzer' => 'blog_analyzer',
                            'fields' => [
                                'keyword' => [
                                    'type' => 'keyword',
                                    'ignore_above' => 256,
                                ],
                            ],
                        ],
                        'content' => [
                            'type' => 'text',
                            'analyzer' => 'blog_analyzer',
                        ],
                        'author' => [
                            'type' => 'text',
                            'fields' => [
                                'keyword' => [
                                    'type' => 'keyword',
                                    'ignore_above' => 256,
                                ],
                            ],
                        ],
                        'email' => ['type' => 'keyword'],
                        'status' => ['type' => 'keyword'],
                        'category' => ['type' => 'keyword'],
                        'tags' => ['type' => 'keyword'],
                        'view_count' => ['type' => 'integer'],
                        'is_featured' => ['type' => 'boolean'],
                        'metadata' => [
                            'type' => 'object',
                            'properties' => [
                                'word_count' => ['type' => 'integer'],
                                'reading_time' => ['type' => 'integer'],
                                'language' => ['type' => 'keyword'],
                            ],
                        ],
                        'created_at' => [
                            'type' => 'date',
                            'format' => 'strict_date_optional_time||epoch_millis',
                        ],
                        'updated_at' => [
                            'type' => 'date',
                            'format' => 'strict_date_optional_time||epoch_millis',
                        ],
                    ],
                ],
            ],
        ];

        return $this->mergeConfig($config, $overrides);
    }

    /**
     * Create a user index configuration
     *
     * @param string $name Index name
     * @param array $overrides Configuration overrides
     * @return array
     */
    public function createUserIndex(string $name = 'test_users', array $overrides = []): array
    {
        $config = [
            'index' => $name,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'keyword'],
                        'username' => [
                            'type' => 'text',
                            'fields' => [
                                'keyword' => [
                                    'type' => 'keyword',
                                    'ignore_above' => 256,
                                ],
                            ],
                        ],
                        'email' => ['type' => 'keyword'],
                        'first_name' => ['type' => 'text'],
                        'last_name' => ['type' => 'text'],
                        'bio' => ['type' => 'text'],
                        'avatar_url' => ['type' => 'keyword'],
                        'location' => ['type' => 'text'],
                        'website' => ['type' => 'keyword'],
                        'social_links' => [
                            'type' => 'object',
                            'properties' => [
                                'twitter' => ['type' => 'keyword'],
                                'linkedin' => ['type' => 'keyword'],
                                'github' => ['type' => 'keyword'],
                            ],
                        ],
                        'preferences' => [
                            'type' => 'object',
                            'properties' => [
                                'theme' => ['type' => 'keyword'],
                                'notifications' => ['type' => 'boolean'],
                                'newsletter' => ['type' => 'boolean'],
                            ],
                        ],
                        'created_at' => [
                            'type' => 'date',
                            'format' => 'strict_date_optional_time||epoch_millis',
                        ],
                        'last_login' => [
                            'type' => 'date',
                            'format' => 'strict_date_optional_time||epoch_millis',
                        ],
                        'is_active' => ['type' => 'boolean'],
                        'role' => ['type' => 'keyword'],
                    ],
                ],
            ],
        ];

        return $this->mergeConfig($config, $overrides);
    }

    /**
     * Create a product index configuration
     *
     * @param string $name Index name
     * @param array $overrides Configuration overrides
     * @return array
     */
    public function createProductIndex(string $name = 'test_products', array $overrides = []): array
    {
        $config = [
            'index' => $name,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'analysis' => [
                        'analyzer' => [
                            'product_analyzer' => [
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => ['lowercase', 'stop'],
                            ],
                        ],
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'keyword'],
                        'sku' => ['type' => 'keyword'],
                        'name' => [
                            'type' => 'text',
                            'analyzer' => 'product_analyzer',
                            'fields' => [
                                'keyword' => [
                                    'type' => 'keyword',
                                    'ignore_above' => 256,
                                ],
                            ],
                        ],
                        'description' => [
                            'type' => 'text',
                            'analyzer' => 'product_analyzer',
                        ],
                        'price' => ['type' => 'float'],
                        'currency' => ['type' => 'keyword'],
                        'category' => ['type' => 'keyword'],
                        'brand' => [
                            'type' => 'text',
                            'fields' => [
                                'keyword' => [
                                    'type' => 'keyword',
                                    'ignore_above' => 256,
                                ],
                            ],
                        ],
                        'in_stock' => ['type' => 'boolean'],
                        'stock_quantity' => ['type' => 'integer'],
                        'weight' => ['type' => 'float'],
                        'dimensions' => [
                            'type' => 'object',
                            'properties' => [
                                'length' => ['type' => 'float'],
                                'width' => ['type' => 'float'],
                                'height' => ['type' => 'float'],
                            ],
                        ],
                        'images' => ['type' => 'keyword'],
                        'rating' => ['type' => 'float'],
                        'review_count' => ['type' => 'integer'],
                        'created_at' => [
                            'type' => 'date',
                            'format' => 'strict_date_optional_time||epoch_millis',
                        ],
                        'updated_at' => [
                            'type' => 'date',
                            'format' => 'strict_date_optional_time||epoch_millis',
                        ],
                    ],
                ],
            ],
        ];

        return $this->mergeConfig($config, $overrides);
    }

    /**
     * Create an index configuration with all field types for testing
     *
     * @param string $name Index name
     * @param array $overrides Configuration overrides
     * @return array
     */
    public function createFieldTypesIndex(string $name = 'test_field_types', array $overrides = []): array
    {
        $config = [
            'index' => $name,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                ],
                'mappings' => [
                    'properties' => [
                        'string_field' => ['type' => 'text'],
                        'text_field' => ['type' => 'text'],
                        'keyword_field' => ['type' => 'keyword'],
                        'integer_field' => ['type' => 'integer'],
                        'long_field' => ['type' => 'long'],
                        'float_field' => ['type' => 'float'],
                        'double_field' => ['type' => 'double'],
                        'boolean_field' => ['type' => 'boolean'],
                        'date_field' => [
                            'type' => 'date',
                            'format' => 'strict_date_optional_time||epoch_millis',
                        ],
                        'object_field' => [
                            'type' => 'object',
                            'properties' => [
                                'nested_string' => ['type' => 'text'],
                                'nested_number' => ['type' => 'integer'],
                                'nested_boolean' => ['type' => 'boolean'],
                            ],
                        ],
                        'array_field' => ['type' => 'keyword'],
                        'geo_point_field' => ['type' => 'geo_point'],
                        'ip_field' => ['type' => 'ip'],
                        'completion_field' => [
                            'type' => 'completion',
                            'analyzer' => 'simple',
                            'preserve_separators' => true,
                            'preserve_position_increments' => true,
                            'max_input_length' => 50,
                        ],
                    ],
                ],
            ],
        ];

        return $this->mergeConfig($config, $overrides);
    }

    /**
     * Create a minimal index configuration for testing
     *
     * @param string $name Index name
     * @param array $overrides Configuration overrides
     * @return array
     */
    public function createMinimalIndex(string $name = 'test_minimal', array $overrides = []): array
    {
        $config = [
            'index' => $name,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'keyword'],
                        'title' => ['type' => 'text'],
                    ],
                ],
            ],
        ];

        return $this->mergeConfig($config, $overrides);
    }

    /**
     * Create an index configuration with aliases
     *
     * @param string $name Index name
     * @param array $aliases Aliases to add
     * @param array $overrides Configuration overrides
     * @return array
     */
    public function createWithAliases(string $name, array $aliases = [], array $overrides = []): array
    {
        $config = $this->create($name, $overrides);

        if (!empty($aliases)) {
            $config['body']['aliases'] = [];
            foreach ($aliases as $alias => $options) {
                if (is_numeric($alias)) {
                    // Simple alias name without options
                    $config['body']['aliases'][$options] = new \stdClass();
                } else {
                    // Alias with options
                    $config['body']['aliases'][$alias] = $options;
                }
            }
        }

        return $config;
    }

    /**
     * Create an index configuration with custom analyzers
     *
     * @param string $name Index name
     * @param array $analyzers Custom analyzers
     * @param array $overrides Configuration overrides
     * @return array
     */
    public function createWithAnalyzers(string $name, array $analyzers = [], array $overrides = []): array
    {
        $config = $this->create($name);

        if (!empty($analyzers)) {
            $config['body']['settings']['analysis']['analyzer'] = array_merge(
                $config['body']['settings']['analysis']['analyzer'] ?? [],
                $analyzers,
            );
        }

        return $this->mergeConfig($config, $overrides);
    }

    /**
     * Create an index configuration for performance testing
     *
     * @param string $name Index name
     * @param int $shards Number of shards
     * @param int $replicas Number of replicas
     * @param array $overrides Configuration overrides
     * @return array
     */
    public function createPerformanceIndex(
        string $name = 'test_performance',
        int $shards = 3,
        int $replicas = 1,
        array $overrides = [],
    ): array {
        $config = [
            'index' => $name,
            'body' => [
                'settings' => [
                    'number_of_shards' => $shards,
                    'number_of_replicas' => $replicas,
                    'refresh_interval' => '30s',
                    'index.mapping.total_fields.limit' => 2000,
                    'index.max_result_window' => 100000,
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'keyword'],
                        'title' => ['type' => 'text'],
                        'content' => ['type' => 'text'],
                        'created_at' => [
                            'type' => 'date',
                            'format' => 'strict_date_optional_time||epoch_millis',
                        ],
                    ],
                ],
            ],
        ];

        return $this->mergeConfig($config, $overrides);
    }

    /**
     * Get mapping for a specific field type
     *
     * @param string $type Field type
     * @param array $options Additional options
     * @return array
     */
    public function getFieldMapping(string $type, array $options = []): array
    {
        $mappings = [
            'text' => ['type' => 'text'],
            'keyword' => ['type' => 'keyword'],
            'integer' => ['type' => 'integer'],
            'long' => ['type' => 'long'],
            'float' => ['type' => 'float'],
            'double' => ['type' => 'double'],
            'boolean' => ['type' => 'boolean'],
            'date' => [
                'type' => 'date',
                'format' => 'strict_date_optional_time||epoch_millis',
            ],
            'geo_point' => ['type' => 'geo_point'],
            'ip' => ['type' => 'ip'],
            'completion' => ['type' => 'completion'],
            'nested' => ['type' => 'nested'],
            'object' => ['type' => 'object'],
        ];

        $mapping = $mappings[$type] ?? ['type' => 'text'];

        return array_merge($mapping, $options);
    }

    /**
     * Merge configuration arrays properly (avoiding array_merge_recursive issues with numeric keys)
     *
     * @param array $base
     * @param array $override
     * @return array
     */
    private function mergeConfig(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->mergeConfig($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
