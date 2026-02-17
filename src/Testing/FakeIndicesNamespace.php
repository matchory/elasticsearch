<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Testing;

/**
 * Duck-typed replacement for the Elasticsearch indices() sub-client.
 * All calls are delegated to FakeClient with "indices.{method}" keys.
 */
final class FakeIndicesNamespace
{
    public function __construct(private readonly FakeClient $client) {}

    public function create(array $params = []): array
    {
        return $this->client->recordAndResolve('indices.create', $params);
    }

    public function delete(array $params = []): array
    {
        return $this->client->recordAndResolve('indices.delete', $params);
    }

    public function exists(array $params = []): mixed
    {
        return $this->client->recordAndResolve('indices.exists', $params);
    }

    public function putMapping(array $params = []): array
    {
        return $this->client->recordAndResolve('indices.putMapping', $params);
    }

    public function getMapping(array $params = []): array
    {
        return $this->client->recordAndResolve('indices.getMapping', $params);
    }

    public function putSettings(array $params = []): array
    {
        return $this->client->recordAndResolve('indices.putSettings', $params);
    }

    public function getSettings(array $params = []): array
    {
        return $this->client->recordAndResolve('indices.getSettings', $params);
    }

    public function updateAliases(array $params = []): array
    {
        return $this->client->recordAndResolve('indices.updateAliases', $params);
    }

    public function getAliases(array $params = []): array
    {
        return $this->client->recordAndResolve('indices.getAliases', $params);
    }

    public function close(array $params = []): array
    {
        return $this->client->recordAndResolve('indices.close', $params);
    }

    public function open(array $params = []): array
    {
        return $this->client->recordAndResolve('indices.open', $params);
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->client->recordAndResolve(
            "indices.{$method}",
            $arguments[0] ?? [],
        );
    }
}
