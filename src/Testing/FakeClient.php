<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Testing;

use Matchory\Elasticsearch\Testing\Responses\FakeResponse;

/**
 * Duck-typed Elasticsearch client replacement for testing.
 *
 * Records all calls and returns configured responses in this order:
 * 1. Queued responses (FIFO)
 * 2. Stubbed per-method responses
 * 3. Sensible defaults
 */
final class FakeClient
{
    /**
     * @var list<mixed> Queued responses consumed FIFO.
     */
    private array $queue = [];

    /**
     * @var array<string, mixed> Per-method stubs (not consumed).
     */
    private array $stubs = [];

    /**
     * @var array<string, list<array<string, mixed>>> Recorded calls keyed by method.
     */
    private array $recorded = [];

    private FakeIndicesNamespace $indices;

    /**
     * @param mixed ...$responses Initial queued responses.
     */
    public function __construct(mixed ...$responses)
    {
        $this->queue = array_values($responses);
        $this->indices = new FakeIndicesNamespace($this);
    }

    // ------------------------------------------------------------------
    // Response configuration
    // ------------------------------------------------------------------

    public function pushResponse(mixed ...$responses): void
    {
        array_push($this->queue, ...$responses);
    }

    public function stubMethod(string $method, mixed $response): void
    {
        $this->stubs[$method] = $response;
    }

    // ------------------------------------------------------------------
    // Recorded calls
    // ------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    public function recorded(string $method): array
    {
        return $this->recorded[$method] ?? [];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function recordedAll(): array
    {
        return $this->recorded;
    }

    public function reset(): void
    {
        $this->queue = [];
        $this->stubs = [];
        $this->recorded = [];
    }

    // ------------------------------------------------------------------
    // Internal: record + resolve (used by FakeIndicesNamespace too)
    // ------------------------------------------------------------------

    /**
     * @internal
     */
    public function recordAndResolve(string $method, array $params): mixed
    {
        $this->recorded[$method][] = $params;

        return $this->resolveResponse($method, $params);
    }

    private function resolveResponse(string $method, array $params): mixed
    {
        // 1. Queued (FIFO)
        if ($this->queue !== []) {
            $response = array_shift($this->queue);

            return $this->normalizeResponse($response, $method, $params);
        }

        // 2. Stubbed
        if (isset($this->stubs[$method])) {
            return $this->normalizeResponse($this->stubs[$method], $method, $params);
        }

        // 3. Default
        return $this->defaultResponse($method);
    }

    private function normalizeResponse(mixed $response, string $method, array $params): mixed
    {
        if ($response instanceof ResponseSequence) {
            return $response->next($method, $params);
        }

        if ($response instanceof FakeResponse) {
            return $response->toArray();
        }

        if (is_callable($response)) {
            return $response($method, $params);
        }

        return $response;
    }

    private function defaultResponse(string $method): mixed
    {
        return match ($method) {
            'search' => [
                'took' => 1,
                'timed_out' => false,
                '_shards' => ['total' => 1, 'successful' => 1, 'skipped' => 0, 'failed' => 0],
                'hits' => [
                    'total' => ['value' => 0, 'relation' => 'eq'],
                    'max_score' => null,
                    'hits' => [],
                ],
            ],
            'count' => [
                'count' => 0,
                '_shards' => ['total' => 1, 'successful' => 1, 'skipped' => 0, 'failed' => 0],
            ],
            'index' => [
                '_index' => 'test_index',
                '_id' => '1',
                '_version' => 1,
                'result' => 'created',
            ],
            'get' => [
                '_index' => 'test_index',
                '_id' => '1',
                '_version' => 1,
                'found' => true,
                '_source' => [],
            ],
            'update' => [
                '_index' => 'test_index',
                '_id' => '1',
                '_version' => 2,
                'result' => 'updated',
            ],
            'delete' => [
                '_index' => 'test_index',
                '_id' => '1',
                '_version' => 2,
                'result' => 'deleted',
            ],
            'bulk' => [
                'took' => 1,
                'errors' => false,
                'items' => [],
            ],
            'exists' => true,
            'info' => [
                'name' => 'test-node',
                'cluster_name' => 'test-cluster',
                'version' => ['number' => '8.0.0'],
            ],
            'scroll' => [
                'took' => 1,
                'timed_out' => false,
                '_scroll_id' => 'fake_scroll_id',
                'hits' => [
                    'total' => ['value' => 0, 'relation' => 'eq'],
                    'max_score' => null,
                    'hits' => [],
                ],
            ],
            'clearScroll' => ['succeeded' => true, 'num_freed' => 1],
            'explain' => ['matched' => true, 'explanation' => ['value' => 1.0, 'description' => 'match']],
            'mget' => ['docs' => []],
            'msearch' => ['responses' => []],
            'updateByQuery' => ['took' => 1, 'timed_out' => false, 'total' => 0, 'updated' => 0, 'deleted' => 0, 'failures' => []],
            'deleteByQuery' => ['took' => 1, 'timed_out' => false, 'total' => 0, 'deleted' => 0, 'failures' => []],
            'indices.create' => ['acknowledged' => true, 'shards_acknowledged' => true, 'index' => 'test_index'],
            'indices.delete' => ['acknowledged' => true],
            'indices.exists' => true,
            'indices.putMapping' => ['acknowledged' => true],
            'indices.getMapping' => [],
            'indices.putSettings' => ['acknowledged' => true],
            'indices.getSettings' => [],
            'indices.updateAliases' => ['acknowledged' => true],
            'indices.getAliases' => [],
            'indices.close' => ['acknowledged' => true],
            'indices.open' => ['acknowledged' => true],
            default => [],
        };
    }

    // ------------------------------------------------------------------
    // Client method duck-typing
    // ------------------------------------------------------------------

    public function search(array $params = []): array
    {
        return $this->recordAndResolve('search', $params);
    }

    public function count(array $params = []): array
    {
        return $this->recordAndResolve('count', $params);
    }

    public function index(array $params = []): array
    {
        return $this->recordAndResolve('index', $params);
    }

    public function get(array $params = []): array
    {
        return $this->recordAndResolve('get', $params);
    }

    public function update(array $params = []): array
    {
        return $this->recordAndResolve('update', $params);
    }

    public function delete(array $params = []): array
    {
        return $this->recordAndResolve('delete', $params);
    }

    public function bulk(array $params = []): array
    {
        return $this->recordAndResolve('bulk', $params);
    }

    public function scroll(array $params = []): array
    {
        return $this->recordAndResolve('scroll', $params);
    }

    public function clearScroll(array $params = []): array
    {
        return $this->recordAndResolve('clearScroll', $params);
    }

    public function updateByQuery(array $params = []): array
    {
        return $this->recordAndResolve('updateByQuery', $params);
    }

    public function deleteByQuery(array $params = []): array
    {
        return $this->recordAndResolve('deleteByQuery', $params);
    }

    public function exists(array $params = []): bool
    {
        $response = $this->recordAndResolve('exists', $params);

        return is_bool($response) ? $response : true;
    }

    public function info(): array
    {
        return $this->recordAndResolve('info', []);
    }

    public function explain(array $params = []): array
    {
        return $this->recordAndResolve('explain', $params);
    }

    public function mget(array $params = []): array
    {
        return $this->recordAndResolve('mget', $params);
    }

    public function msearch(array $params = []): array
    {
        return $this->recordAndResolve('msearch', $params);
    }

    public function indices(): FakeIndicesNamespace
    {
        return $this->indices;
    }

    public function setResponseException(bool $enabled): self
    {
        return $this;
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->recordAndResolve($method, $arguments[0] ?? []);
    }
}
