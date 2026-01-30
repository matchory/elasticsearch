<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch;

use BadMethodCallException;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch as ElasticsearchResponse;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Illuminate\Foundation\Application;
use Illuminate\Support\Traits\ForwardsCalls;
use JsonException;
use Matchory\Elasticsearch\Interfaces\ConnectionInterface;
use Psr\SimpleCache\CacheInterface;
use Sentry\Breadcrumb;
use Sentry\Laravel\ServiceProvider as SentryProvider;
use Sentry\State\HubInterface;

use function in_array;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Connection
 *
 * @package Matchory\Elasticsearch
 */
class Connection implements ConnectionInterface
{
    use ForwardsCalls;

    /**
     * Cache instance to be used for this connection. In Laravel applications,
     * this will be an instance of the Cache Repository, which is the same as
     * the instance returned from the Cache facade.
     *
     * @var CacheInterface|null
     */
    protected ?CacheInterface $cache;

    /**
     * Elasticsearch client instance used for this connection.
     *
     * Note: Type is `object` to allow for duck-typed mock clients in tests.
     * The Elasticsearch PHP Client v9 makes the Client class final.
     *
     * @var Client|object
     * @see Connection::getClient()
     */
    protected object $client;

    /**
     * @var string|null
     */
    protected ?string $index;

    /**
     * @var bool
     */
    protected bool $reportQueries;

    /**
     * Creates a new connection
     *
     * @param Client|object       $client Elasticsearch client instance. Type is
     *                                    `object` to allow for duck-typed mock
     *                                    clients in tests (Client is final).
     * @param CacheInterface|null $cache
     * @param string|null         $index
     * @param bool                $reportQueries
     */
    final public function __construct(
        object $client,
        ?CacheInterface $cache = null,
        ?string $index = null,
        bool $reportQueries = true,
    ) {
        $this->client = $client;
        $this->index = $index;
        $this->cache = $cache;
        $this->reportQueries = $reportQueries;
    }

    /**
     * @inheritDoc
     */
    public function getCache(): ?CacheInterface
    {
        return $this->cache;
    }

    public function insert(
        array $parameters,
        ?string $index = null,
    ): object {
        if (!isset($parameters[Builder::PARAM_INDEX]) && $index = $index ?? $this->index) {
            $parameters[Builder::PARAM_INDEX] = $index;
        }

        if ($this->reportQueries) {
            $this->reportQuery($parameters);
        }

        return (object) $this->client->index($parameters);
    }

    private function reportQuery(array $query): void
    {
        $app = app();
        assert($app instanceof Application);

        if (!$app->providerIsLoaded(SentryProvider::class)) {
            return;
        }

        $sentry = $app->make(HubInterface::class);
        assert($sentry instanceof HubInterface);

        try {
            /** @noinspection PhpUnhandledExceptionInspection */
            $sentry->addBreadcrumb(
                new Breadcrumb(
                    Breadcrumb::LEVEL_INFO,
                    Breadcrumb::TYPE_DEFAULT,
                    'elasticsearch.query',
                    json_encode($query, JSON_THROW_ON_ERROR) ?: '',
                ),
            );
        } catch (JsonException) {
            // We don't want errors during reporting to bubble up to
            // the application
        }
    }

    /**
     * @inheritDoc
     */
    public function index(string $index): Builder
    {
        return $this->newQuery()->index($index);
    }

    /**
     * Route the request to the query class
     *
     * @return Builder
     */
    public function newQuery(): Builder
    {
        return (new Builder($this))->index($this->index);
    }

    /**
     * @inheritdoc
     */
    public function search(array $parameters): array
    {
        if ($this->reportQueries) {
            $this->reportQuery($parameters);
        }

        return $this->getClient()->search($parameters);
    }

    /**
     * @inheritDoc
     */
    public function getClient(): object
    {
        return $this->client;
    }

    /**
     * Execute a client operation with specific HTTP status codes ignored.
     *
     * In Elasticsearch PHP client v9, the `$params['client']['ignore']` pattern
     * was removed. Instead, we must disable response exceptions, execute the
     * operation, and check the response status code manually.
     *
     * @param callable(object): mixed $operation The operation to execute
     * @param array<int> $ignores HTTP status codes to ignore
     *
     * @return mixed The operation result
     * @throws ClientResponseException If the response has an error status not in $ignores
     */
    public function executeWithIgnoredErrors(callable $operation, array $ignores = []): mixed
    {
        $client = $this->getClient();

        if (empty($ignores)) {
            return $operation($client);
        }

        $client->setResponseException(false);

        try {
            $result = $operation($client);

            if ($result instanceof ElasticsearchResponse) {
                $statusCode = $result->getStatusCode();

                if ($statusCode >= 400 && !in_array($statusCode, $ignores, true)) {
                    $error = new ClientResponseException(
                        sprintf('%d %s', $statusCode, $result->getReasonPhrase()),
                        $statusCode,
                    );
                    throw $error->setResponse($result);
                }
            }

            return $result;
        } finally {
            $client->setResponseException(true);
        }
    }

    /**
     * Check if the Elasticsearch cluster is reachable.
     *
     * @return bool True if the cluster responds to a ping, false otherwise.
     */
    public function ping(): bool
    {
        try {
            $this->client->info();

            return true;
        } catch (ClientResponseException|NoNodeAvailableException) {
            return false;
        }
    }

    /**
     * Proxy  calls to the default connection
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed
     * @throws BadMethodCallException
     */
    public function __call(string $name, array $arguments)
    {
        return $this->forwardCallTo($this->newQuery(), $name, $arguments);
    }
}
