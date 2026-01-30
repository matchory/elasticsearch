<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Support\Factories;

/**
 * Factory for generating realistic Elasticsearch API responses
 *
 * This class provides methods to generate realistic Elasticsearch responses
 * for various operations, making tests more accurate and maintainable.
 */
class ResponseFactory
{
    /**
     * Generate a search response
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function search(array $options = []): array
    {
        $hits = $options['hits'] ?? [];
        $total = $options['total'] ?? count($hits);
        $maxScore = $options['max_score'] ?? ($total > 0 ? 1.0 : null);
        $took = $options['took'] ?? rand(1, 50);
        $timedOut = $options['timed_out'] ?? false;

        return [
            'took' => $took,
            'timed_out' => $timedOut,
            '_shards' => [
                'total' => $options['shards']['total'] ?? 1,
                'successful' => $options['shards']['successful'] ?? 1,
                'skipped' => $options['shards']['skipped'] ?? 0,
                'failed' => $options['shards']['failed'] ?? 0,
            ],
            'hits' => [
                'total' => [
                    'value' => $total,
                    'relation' => $options['relation'] ?? 'eq',
                ],
                'max_score' => $maxScore,
                'hits' => array_map(fn($hit) => self::formatHit($hit), $hits),
            ],
            'aggregations' => $options['aggregations'] ?? [],
        ];
    }

    /**
     * Generate an index response
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function index(array $options = []): array
    {
        return [
            '_index' => $options['index'] ?? 'test_index',
            '_id' => $options['id'] ?? '1',
            '_version' => $options['version'] ?? 1,
            'result' => $options['result'] ?? 'created',
            '_shards' => [
                'total' => $options['shards']['total'] ?? 2,
                'successful' => $options['shards']['successful'] ?? 1,
                'failed' => $options['shards']['failed'] ?? 0,
            ],
            '_seq_no' => $options['seq_no'] ?? 0,
            '_primary_term' => $options['primary_term'] ?? 1,
        ];
    }

    /**
     * Generate a get response
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function get(array $options = []): array
    {
        $found = $options['found'] ?? true;

        $response = [
            '_index' => $options['index'] ?? 'test_index',
            '_id' => $options['id'] ?? '1',
            '_version' => $options['version'] ?? 1,
            '_seq_no' => $options['seq_no'] ?? 0,
            '_primary_term' => $options['primary_term'] ?? 1,
            'found' => $found,
        ];

        if ($found) {
            $response['_source'] = $options['source'] ?? [];
        }

        return $response;
    }

    /**
     * Generate a delete response
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function delete(array $options = []): array
    {
        return [
            '_index' => $options['index'] ?? 'test_index',
            '_id' => $options['id'] ?? '1',
            '_version' => $options['version'] ?? 2,
            'result' => $options['result'] ?? 'deleted',
            '_shards' => [
                'total' => $options['shards']['total'] ?? 2,
                'successful' => $options['shards']['successful'] ?? 1,
                'failed' => $options['shards']['failed'] ?? 0,
            ],
            '_seq_no' => $options['seq_no'] ?? 1,
            '_primary_term' => $options['primary_term'] ?? 1,
        ];
    }

    /**
     * Generate an update response
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function update(array $options = []): array
    {
        return [
            '_index' => $options['index'] ?? 'test_index',
            '_id' => $options['id'] ?? '1',
            '_version' => $options['version'] ?? 2,
            'result' => $options['result'] ?? 'updated',
            '_shards' => [
                'total' => $options['shards']['total'] ?? 2,
                'successful' => $options['shards']['successful'] ?? 1,
                'failed' => $options['shards']['failed'] ?? 0,
            ],
            '_seq_no' => $options['seq_no'] ?? 1,
            '_primary_term' => $options['primary_term'] ?? 1,
        ];
    }

    /**
     * Generate a bulk response
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function bulk(array $options = []): array
    {
        $items = $options['items'] ?? [];
        $errors = $options['errors'] ?? false;
        $took = $options['took'] ?? rand(1, 100);

        return [
            'took' => $took,
            'errors' => $errors,
            'items' => $items,
        ];
    }

    /**
     * Generate a count response
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function count(array $options = []): array
    {
        return [
            'count' => $options['count'] ?? 0,
            '_shards' => [
                'total' => $options['shards']['total'] ?? 1,
                'successful' => $options['shards']['successful'] ?? 1,
                'skipped' => $options['shards']['skipped'] ?? 0,
                'failed' => $options['shards']['failed'] ?? 0,
            ],
        ];
    }

    /**
     * Generate an exists response
     *
     * @param array<string, mixed> $options
     */
    public static function exists(array $options = []): bool
    {
        return $options['exists'] ?? true;
    }

    /**
     * Generate an explain response
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function explain(array $options = []): array
    {
        return [
            '_index' => $options['index'] ?? 'test_index',
            '_id' => $options['id'] ?? '1',
            'matched' => $options['matched'] ?? true,
            'explanation' => $options['explanation'] ?? [
                'value' => 1.0,
                'description' => 'match on required clause',
                'details' => [],
            ],
        ];
    }

    /**
     * Generate a multi-get response
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function mget(array $options = []): array
    {
        return [
            'docs' => $options['docs'] ?? [],
        ];
    }

    /**
     * Generate a multi-search response
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function msearch(array $options = []): array
    {
        return [
            'responses' => $options['responses'] ?? [],
        ];
    }

    /**
     * Generate a scroll response
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function scroll(array $options = []): array
    {
        $hits = $options['hits'] ?? [];
        $total = $options['total'] ?? count($hits);
        $scrollId = $options['scroll_id'] ?? 'scroll_' . uniqid();

        return [
            'took' => $options['took'] ?? rand(1, 50),
            'timed_out' => $options['timed_out'] ?? false,
            '_scroll_id' => $scrollId,
            '_shards' => [
                'total' => $options['shards']['total'] ?? 1,
                'successful' => $options['shards']['successful'] ?? 1,
                'skipped' => $options['shards']['skipped'] ?? 0,
                'failed' => $options['shards']['failed'] ?? 0,
            ],
            'hits' => [
                'total' => [
                    'value' => $total,
                    'relation' => $options['relation'] ?? 'eq',
                ],
                'max_score' => $options['max_score'] ?? ($total > 0 ? 1.0 : null),
                'hits' => array_map(fn($hit) => self::formatHit($hit), $hits),
            ],
        ];
    }

    /**
     * Generate a clear scroll response
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function clearScroll(array $options = []): array
    {
        return [
            'succeeded' => $options['succeeded'] ?? true,
            'num_freed' => $options['num_freed'] ?? 1,
        ];
    }

    /**
     * Generate index creation response
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function createIndex(array $options = []): array
    {
        return [
            'acknowledged' => $options['acknowledged'] ?? true,
            'shards_acknowledged' => $options['shards_acknowledged'] ?? true,
            'index' => $options['index'] ?? 'test_index',
        ];
    }

    /**
     * Generate index deletion response
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function deleteIndex(array $options = []): array
    {
        return [
            'acknowledged' => $options['acknowledged'] ?? true,
        ];
    }

    /**
     * Generate put mapping response
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function putMapping(array $options = []): array
    {
        return [
            'acknowledged' => $options['acknowledged'] ?? true,
        ];
    }

    /**
     * Generate get mapping response
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function getMapping(array $options = []): array
    {
        $index = $options['index'] ?? 'test_index';

        return [
            $index => [
                'mappings' => $options['mappings'] ?? [
                    'properties' => [],
                ],
            ],
        ];
    }

    /**
     * Generate put settings response
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function putSettings(array $options = []): array
    {
        return [
            'acknowledged' => $options['acknowledged'] ?? true,
        ];
    }

    /**
     * Generate get settings response
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function getSettings(array $options = []): array
    {
        $index = $options['index'] ?? 'test_index';

        return [
            $index => [
                'settings' => $options['settings'] ?? [
                    'index' => [
                        'number_of_shards' => '1',
                        'number_of_replicas' => '0',
                        'creation_date' => '1640995200000',
                        'uuid' => 'test-uuid-123',
                        'version' => [
                            'created' => '7170399',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate update aliases response
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function updateAliases(array $options = []): array
    {
        return [
            'acknowledged' => $options['acknowledged'] ?? true,
        ];
    }

    /**
     * Generate get aliases response
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function getAliases(array $options = []): array
    {
        $index = $options['index'] ?? 'test_index';

        return [
            $index => [
                'aliases' => $options['aliases'] ?? [],
            ],
        ];
    }

    /**
     * Generate close index response
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function closeIndex(array $options = []): array
    {
        return [
            'acknowledged' => $options['acknowledged'] ?? true,
            'shards_acknowledged' => $options['shards_acknowledged'] ?? true,
        ];
    }

    /**
     * Generate open index response
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function openIndex(array $options = []): array
    {
        return [
            'acknowledged' => $options['acknowledged'] ?? true,
            'shards_acknowledged' => $options['shards_acknowledged'] ?? true,
        ];
    }

    /**
     * Format a hit for search results
     *
     * @param array<string, mixed> $hit
     * @return array<string, mixed>
     */
    private static function formatHit(array $hit): array
    {
        return [
            '_index' => $hit['_index'] ?? 'test_index',
            '_id' => $hit['_id'] ?? '1',
            '_score' => $hit['_score'] ?? 1.0,
            '_source' => $hit['_source'] ?? $hit['source'] ?? [],
        ];
    }

    /**
     * Generate a realistic aggregation response
     *
     * @param string $name
     * @param string $type
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function aggregation(string $name, string $type, array $options = []): array
    {
        return match ($type) {
            'terms' => [
                $name => [
                    'doc_count_error_upper_bound' => 0,
                    'sum_other_doc_count' => 0,
                    'buckets' => $options['buckets'] ?? [],
                ],
            ],
            'avg', 'sum', 'min', 'max' => [
                $name => [
                    'value' => $options['value'] ?? 0,
                ],
            ],
            'cardinality' => [
                $name => [
                    'value' => $options['value'] ?? 0,
                ],
            ],
            'date_histogram' => [
                $name => [
                    'buckets' => $options['buckets'] ?? [],
                ],
            ],
            default => [
                $name => $options,
            ],
        };
    }

    /**
     * Generate a search response with documents
     *
     * @param array<array<string, mixed>> $documents
     * @param int|null $total
     * @param int|null $took
     * @param bool|null $timedOut
     * @return array<string, mixed>
     */
    public static function searchResponse(array $documents, ?int $total = null, ?int $took = null, ?bool $timedOut = null): array
    {
        $total = $total ?? count($documents);
        $took = $took ?? rand(1, 50);
        $timedOut = $timedOut ?? false;

        return [
            'took' => $took,
            'timed_out' => $timedOut,
            '_shards' => [
                'total' => 1,
                'successful' => 1,
                'skipped' => 0,
                'failed' => 0,
            ],
            'hits' => [
                'total' => [
                    'value' => $total,
                    'relation' => 'eq',
                ],
                'max_score' => $total > 0 ? 1.0 : null,
                'hits' => $documents,
            ],
        ];
    }

    /**
     * Generate a count response
     *
     * @param int $count
     * @return array<string, mixed>
     */
    public static function countResponse(int $count): array
    {
        return [
            'count' => $count,
            '_shards' => [
                'total' => 1,
                'successful' => 1,
                'skipped' => 0,
                'failed' => 0,
            ],
        ];
    }

    /**
     * Generate an index response
     *
     * @param string $id
     * @param string $result
     * @return array<string, mixed>
     */
    public static function indexResponse(string $id, string $result = 'created'): array
    {
        return [
            '_index' => 'test_index',
            '_id' => $id,
            '_version' => 1,
            'result' => $result,
            '_shards' => [
                'total' => 2,
                'successful' => 1,
                'failed' => 0,
            ],
        ];
    }

    /**
     * Generate an update response
     *
     * @param string $id
     * @return array<string, mixed>
     */
    public static function updateResponse(string $id): array
    {
        return [
            '_index' => 'test_index',
            '_id' => $id,
            '_version' => 2,
            'result' => 'updated',
            '_shards' => [
                'total' => 2,
                'successful' => 1,
                'failed' => 0,
            ],
        ];
    }

    /**
     * Generate a delete response
     *
     * @param string $id
     * @return array<string, mixed>
     */
    public static function deleteResponse(string $id): array
    {
        return [
            '_index' => 'test_index',
            '_id' => $id,
            '_version' => 2,
            'result' => 'deleted',
            '_shards' => [
                'total' => 2,
                'successful' => 1,
                'failed' => 0,
            ],
        ];
    }

    /**
     * Generate a bulk response
     *
     * @param array<array<string, mixed>> $items
     * @return array<string, mixed>
     */
    public static function bulkResponse(array $items): array
    {
        return [
            'took' => rand(1, 100),
            'errors' => false,
            'items' => $items,
        ];
    }

    /**
     * Generate a scroll response
     *
     * @param array<array<string, mixed>> $documents
     * @param string $scrollId
     * @return array<string, mixed>
     */
    public static function scrollResponse(array $documents, string $scrollId): array
    {
        return [
            'took' => rand(1, 50),
            'timed_out' => false,
            '_scroll_id' => $scrollId,
            '_shards' => [
                'total' => 1,
                'successful' => 1,
                'skipped' => 0,
                'failed' => 0,
            ],
            'hits' => [
                'total' => [
                    'value' => count($documents),
                    'relation' => 'eq',
                ],
                'max_score' => count($documents) > 0 ? 1.0 : null,
                'hits' => $documents,
            ],
        ];
    }

    /**
     * Generate a clear scroll response
     *
     * @return array<string, mixed>
     */
    public static function clearScrollResponse(): array
    {
        return [
            'succeeded' => true,
            'num_freed' => 1,
        ];
    }
}
