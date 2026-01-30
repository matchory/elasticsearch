<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Support\Factories;

use Exception;
use RuntimeException;

/**
 * Factory for generating Elasticsearch error responses and exceptions
 *
 * This class provides methods to generate realistic Elasticsearch error
 * responses and exceptions for testing error handling scenarios.
 */
class ErrorResponseFactory
{
    /**
     * Create an exception based on error type
     *
     * @param string $errorType
     * @param array<string, mixed> $options
     */
    public static function create(string $errorType, array $options = []): Exception
    {
        return match ($errorType) {
            'connection_timeout' => self::connectionTimeout($options),
            'connection_refused' => self::connectionRefused($options),
            'index_not_found' => self::indexNotFound($options),
            'document_not_found' => self::documentNotFound($options),
            'version_conflict' => self::versionConflict($options),
            'mapping_conflict' => self::mappingConflict($options),
            'query_parsing_error' => self::queryParsingError($options),
            'authentication_error' => self::authenticationError($options),
            'authorization_error' => self::authorizationError($options),
            'cluster_unavailable' => self::clusterUnavailable($options),
            'shard_failure' => self::shardFailure($options),
            'too_many_requests' => self::tooManyRequests($options),
            'invalid_request' => self::invalidRequest($options),
            'server_error' => self::serverError($options),
            default => new Exception("Unknown error type: {$errorType}"),
        };
    }

    /**
     * Create a connection timeout exception
     *
     * @param array<string, mixed> $options
     */
    public static function connectionTimeout(array $options = []): Exception
    {
        $message = $options['message'] ?? 'Connection timeout after 30 seconds';

        return new RuntimeException($message, 0);
    }

    /**
     * Create a connection refused exception
     *
     * @param array<string, mixed> $options
     */
    public static function connectionRefused(array $options = []): Exception
    {
        $host = $options['host'] ?? 'localhost:9200';
        $message = $options['message'] ?? "Connection refused to {$host}";

        return new RuntimeException($message, 0);
    }

    /**
     * Create an index not found exception
     *
     * @param array<string, mixed> $options
     */
    public static function indexNotFound(array $options = []): Exception
    {
        $index = $options['index'] ?? 'test_index';
        $message = $options['message'] ?? "Index [{$index}] not found";

        $response = [
            'error' => [
                'type' => 'index_not_found_exception',
                'reason' => "no such index [{$index}]",
                'resource.type' => 'index_or_alias',
                'resource.id' => $index,
                'index_uuid' => '_na_',
                'index' => $index,
            ],
            'status' => 404,
        ];

        return new RuntimeException($message . ': ' . json_encode($response), 404);
    }

    /**
     * Create a document not found exception
     *
     * @param array<string, mixed> $options
     */
    public static function documentNotFound(array $options = []): Exception
    {
        $index = $options['index'] ?? 'test_index';
        $id = $options['id'] ?? '1';
        $message = $options['message'] ?? "Document not found: {$index}/{$id}";

        $response = [
            '_index' => $index,
            '_id' => $id,
            'found' => false,
        ];

        return new RuntimeException($message . ': ' . json_encode($response), 404);
    }

    /**
     * Create a version conflict exception
     *
     * @param array<string, mixed> $options
     */
    public static function versionConflict(array $options = []): Exception
    {
        $index = $options['index'] ?? 'test_index';
        $id = $options['id'] ?? '1';
        $currentVersion = $options['current_version'] ?? 2;
        $providedVersion = $options['provided_version'] ?? 1;

        $message = $options['message'] ?? "Version conflict: document version {$currentVersion} conflicts with provided version {$providedVersion}";

        $response = [
            'error' => [
                'type' => 'version_conflict_engine_exception',
                'reason' => "[{$id}]: version conflict, current version [{$currentVersion}] is different than the one provided [{$providedVersion}]",
                'index_uuid' => 'test_uuid',
                'shard' => '0',
                'index' => $index,
            ],
            'status' => 409,
        ];

        return new RuntimeException($message . ': ' . json_encode($response), 409);
    }

    /**
     * Create a mapping conflict exception
     *
     * @param array<string, mixed> $options
     */
    public static function mappingConflict(array $options = []): Exception
    {
        $field = $options['field'] ?? 'test_field';
        $currentType = $options['current_type'] ?? 'text';
        $newType = $options['new_type'] ?? 'keyword';

        $message = $options['message'] ?? "Mapping conflict for field {$field}: cannot change from {$currentType} to {$newType}";

        $response = [
            'error' => [
                'type' => 'illegal_argument_exception',
                'reason' => "mapper [{$field}] cannot be changed from type [{$currentType}] to [{$newType}]",
            ],
            'status' => 400,
        ];

        return new RuntimeException($message . ': ' . json_encode($response), 400);
    }

    /**
     * Create a query parsing exception
     *
     * @param array<string, mixed> $options
     */
    public static function queryParsingError(array $options = []): Exception
    {
        $query = $options['query'] ?? '{"invalid": "query"}';
        $reason = $options['reason'] ?? 'Failed to parse query';

        $message = $options['message'] ?? "Query parsing error: {$reason}";

        $response = [
            'error' => [
                'type' => 'parsing_exception',
                'reason' => $reason,
                'line' => 1,
                'col' => 1,
            ],
            'status' => 400,
        ];

        return new RuntimeException($message . ': ' . json_encode($response), 400);
    }

    /**
     * Create an authentication error exception
     *
     * @param array<string, mixed> $options
     */
    public static function authenticationError(array $options = []): Exception
    {
        $message = $options['message'] ?? 'Authentication failed';

        $response = [
            'error' => [
                'type' => 'security_exception',
                'reason' => 'missing authentication credentials for REST request',
                'header' => [
                    'WWW-Authenticate' => [
                        'Basic realm="security" charset="UTF-8"',
                        'Bearer realm="security"',
                        'ApiKey',
                    ],
                ],
            ],
            'status' => 401,
        ];

        return new RuntimeException($message . ': ' . json_encode($response), 401);
    }

    /**
     * Create an authorization error exception
     *
     * @param array<string, mixed> $options
     */
    public static function authorizationError(array $options = []): Exception
    {
        $action = $options['action'] ?? 'indices:data/read/search';
        $index = $options['index'] ?? 'test_index';

        $message = $options['message'] ?? "Authorization failed for action {$action} on index {$index}";

        $response = [
            'error' => [
                'type' => 'security_exception',
                'reason' => "action [{$action}] is unauthorized for user [test_user] on indices [{$index}]",
            ],
            'status' => 403,
        ];

        return new RuntimeException($message . ': ' . json_encode($response), 403);
    }

    /**
     * Create a cluster unavailable exception
     *
     * @param array<string, mixed> $options
     */
    public static function clusterUnavailable(array $options = []): Exception
    {
        $message = $options['message'] ?? 'Cluster is unavailable';

        $response = [
            'error' => [
                'type' => 'cluster_block_exception',
                'reason' => 'blocked by: [SERVICE_UNAVAILABLE/2/no master];',
            ],
            'status' => 503,
        ];

        return new RuntimeException($message . ': ' . json_encode($response), 503);
    }

    /**
     * Create a shard failure exception
     *
     * @param array<string, mixed> $options
     */
    public static function shardFailure(array $options = []): Exception
    {
        $index = $options['index'] ?? 'test_index';
        $shard = $options['shard'] ?? '0';

        $message = $options['message'] ?? "Shard failure on {$index}[{$shard}]";

        $response = [
            'error' => [
                'type' => 'search_phase_execution_exception',
                'reason' => 'all shards failed',
                'phase' => 'query',
                'grouped' => true,
                'failed_shards' => [
                    [
                        'shard' => (int) $shard,
                        'index' => $index,
                        'node' => 'test_node',
                        'reason' => [
                            'type' => 'query_shard_exception',
                            'reason' => 'Failed to execute query',
                            'index_uuid' => 'test_uuid',
                            'index' => $index,
                        ],
                    ],
                ],
            ],
            'status' => 500,
        ];

        return new RuntimeException($message . ': ' . json_encode($response), 500);
    }

    /**
     * Create a too many requests exception
     *
     * @param array<string, mixed> $options
     */
    public static function tooManyRequests(array $options = []): Exception
    {
        $message = $options['message'] ?? 'Too many requests';

        $response = [
            'error' => [
                'type' => 'es_rejected_execution_exception',
                'reason' => 'rejected execution of org.elasticsearch.transport.TransportService$7@1234567 on EsThreadPoolExecutor[search, queue capacity = 1000, org.elasticsearch.common.util.concurrent.EsThreadPoolExecutor@abcdef]',
            ],
            'status' => 429,
        ];

        return new RuntimeException($message . ': ' . json_encode($response), 429);
    }

    /**
     * Create an invalid request exception
     *
     * @param array<string, mixed> $options
     */
    public static function invalidRequest(array $options = []): Exception
    {
        $reason = $options['reason'] ?? 'Invalid request';
        $message = $options['message'] ?? "Bad request: {$reason}";

        $response = [
            'error' => [
                'type' => 'action_request_validation_exception',
                'reason' => "Validation Failed: 1: {$reason};",
            ],
            'status' => 400,
        ];

        return new RuntimeException($message . ': ' . json_encode($response), 400);
    }

    /**
     * Create a server error exception
     *
     * @param array<string, mixed> $options
     */
    public static function serverError(array $options = []): Exception
    {
        $message = $options['message'] ?? 'Internal server error';

        $response = [
            'error' => [
                'type' => 'exception',
                'reason' => 'Internal server error occurred',
            ],
            'status' => 500,
        ];

        return new RuntimeException($message . ': ' . json_encode($response), 500);
    }

    /**
     * Create a bulk operation error item
     *
     * @param string $operation
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function bulkErrorItem(string $operation, array $options = []): array
    {
        $index = $options['index'] ?? 'test_index';
        $id = $options['id'] ?? '1';
        $errorType = $options['error_type'] ?? 'version_conflict_engine_exception';
        $reason = $options['reason'] ?? 'version conflict';
        $status = $options['status'] ?? 409;

        return [
            $operation => [
                '_index' => $index,
                '_id' => $id,
                'status' => $status,
                'error' => [
                    'type' => $errorType,
                    'reason' => $reason,
                    'index_uuid' => 'test_uuid',
                    'shard' => '0',
                    'index' => $index,
                ],
            ],
        ];
    }

    /**
     * Create a successful bulk operation item
     *
     * @param string $operation
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function bulkSuccessItem(string $operation, array $options = []): array
    {
        $index = $options['index'] ?? 'test_index';
        $id = $options['id'] ?? '1';
        $version = $options['version'] ?? 1;
        $result = $options['result'] ?? 'created';
        $status = $options['status'] ?? 201;

        return [
            $operation => [
                '_index' => $index,
                '_id' => $id,
                '_version' => $version,
                'result' => $result,
                'status' => $status,
                '_shards' => [
                    'total' => 2,
                    'successful' => 1,
                    'failed' => 0,
                ],
                '_seq_no' => $options['seq_no'] ?? 0,
                '_primary_term' => $options['primary_term'] ?? 1,
            ],
        ];
    }

    /**
     * Generate a realistic error response array
     *
     * @param string $errorType
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function errorResponse(string $errorType, array $options = []): array
    {
        $exception = self::create($errorType, $options);
        $message = $exception->getMessage();

        // Extract JSON from message if present
        if (str_contains($message, ': {')) {
            $jsonPart = substr($message, strpos($message, ': {') + 2);
            $decoded = json_decode($jsonPart, true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        // Fallback to basic error structure
        return [
            'error' => [
                'type' => $errorType,
                'reason' => $message,
            ],
            'status' => $exception->getCode() ?: 500,
        ];
    }
}
