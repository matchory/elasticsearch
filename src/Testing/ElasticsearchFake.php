<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Testing;

use Closure;
use Matchory\Elasticsearch\Connection;
use Matchory\Elasticsearch\Interfaces\ConnectionInterface;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;
use PHPUnit\Framework\Assert as PHPUnit;

use function count;

final class ElasticsearchFake implements ConnectionResolverInterface
{
    private FakeClient $client;

    private string $defaultConnection = 'default';

    /**
     * @var array<string, ConnectionInterface>
     */
    private array $connections = [];

    /**
     * @param mixed ...$responses Initial queued responses for the FakeClient.
     */
    public function __construct(mixed ...$responses)
    {
        $this->client = new FakeClient(...$responses);
    }

    public function getClient(): FakeClient
    {
        return $this->client;
    }

    // ------------------------------------------------------------------
    // ConnectionResolverInterface
    // ------------------------------------------------------------------

    public function connection(?string $name = null): ConnectionInterface
    {
        $name ??= $this->defaultConnection;

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = new Connection(
                $this->client,
                reportQueries: false,
            );
        }

        return $this->connections[$name];
    }

    public function getDefaultConnection(): string
    {
        return $this->defaultConnection;
    }

    public function setDefaultConnection(string $name): void
    {
        $this->defaultConnection = $name;
    }

    // ------------------------------------------------------------------
    // Assertions
    // ------------------------------------------------------------------

    /**
     * Assert that a client method was called, optionally matching params.
     *
     * @param string $method Client method name (e.g. 'search', 'index').
     * @param Closure(array): bool|null $callback Optional filter.
     */
    public function assertSent(string $method, ?Closure $callback = null): void
    {
        $calls = $this->client->recorded($method);

        PHPUnit::assertNotEmpty(
            $calls,
            "Expected [{$method}] to be called, but it was not.",
        );

        if ($callback !== null) {
            $matching = array_filter($calls, $callback);

            PHPUnit::assertNotEmpty(
                $matching,
                "Expected [{$method}] to be called with matching parameters, but no matching call was found.",
            );
        }
    }

    /**
     * Assert that a client method was NOT called, optionally with matching params.
     */
    public function assertNotSent(string $method, ?Closure $callback = null): void
    {
        $calls = $this->client->recorded($method);

        if ($callback === null) {
            PHPUnit::assertEmpty(
                $calls,
                "Unexpected call to [{$method}].",
            );

            return;
        }

        $matching = array_filter($calls, $callback);

        PHPUnit::assertEmpty(
            $matching,
            "Unexpected [{$method}] call with matching parameters.",
        );
    }

    public function assertNothingSent(): void
    {
        $all = $this->client->recordedAll();

        PHPUnit::assertEmpty(
            $all,
            'Expected no Elasticsearch calls, but ' . count($all) . ' method(s) were called.',
        );
    }

    public function assertSentCount(string $method, int $count): void
    {
        $actual = count($this->client->recorded($method));

        PHPUnit::assertSame(
            $count,
            $actual,
            "Expected [{$method}] to be called {$count} time(s), but was called {$actual} time(s).",
        );
    }

    /**
     * @return list<array<string, mixed>>|array<string, list<array<string, mixed>>>
     */
    public function recorded(?string $method = null): array
    {
        if ($method !== null) {
            return $this->client->recorded($method);
        }

        return $this->client->recordedAll();
    }
}
