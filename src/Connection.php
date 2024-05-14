<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch;

use BadMethodCallException;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Traits\ForwardsCalls;
use InvalidArgumentException;
use JetBrains\PhpStorm\Deprecated;
use JsonException;
use Matchory\Elasticsearch\Interfaces\ClientFactoryInterface;
use Matchory\Elasticsearch\Interfaces\ConnectionInterface;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface as Resolver;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\SimpleCache\CacheInterface;
use Sentry\Breadcrumb;
use Sentry\Laravel\ServiceProvider as SentryProvider;
use Sentry\State\HubInterface;
use function assert;
use function json_encode;
use function trigger_deprecation;
use const JSON_THROW_ON_ERROR;

/**
 * Connection
 *
 * @package Matchory\Elasticsearch
 */
class Connection implements ConnectionInterface
{
    use ForwardsCalls;

    private const DEFAULT_LOGGER_NAME = 'elasticsearch';

    /**
     * @var Resolver
     * @deprecated
     * @todo remove in next major version
     */
    #[Deprecated]
    private static Resolver $resolver;

    /**
     * Cache instance to be used for this connection. In Laravel applications,
     * this will be an instance of the Cache Repository, which is the same as
     * the instance returned from the Cache facade.
     *
     * @var CacheInterface|null
     * @see Repository
     * @see Cache
     */
    protected CacheInterface|null $cache;

    /**
     * Elasticsearch client instance used for this connection.
     *
     * @var Client
     * @see Connection::getClient()
     */
    protected Client $client;

    /**
     * Used to hold all connections.
     *
     * @var Client[]
     * @deprecated
     * @todo remove in next major version
     */
    #[Deprecated]
    protected array $clients = [];

    /**
     * @var string|null
     */
    protected string|null $index;

    /**
     * @var bool
     */
    protected bool $reportQueries;

    /**
     * Creates a new connection
     *
     * @param Client $client
     * @param CacheInterface|null $cache
     * @param string|null $index
     * @param bool $reportQueries
     */
    final public function __construct(
        Client              $client,
        CacheInterface|null $cache = null,
        string|null         $index = null,
        bool                $reportQueries = true
    )
    {
        $this->client = $client;
        $this->index = $index;
        $this->cache = $cache;
        $this->reportQueries = $reportQueries;
    }

    /**
     * @inheritDoc
     */
    public function getCache(): CacheInterface|null
    {
        return $this->cache;
    }

    /**
     * @inheritDoc
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * @inheritDoc
     */
    public function index(string $index): Query
    {
        return $this->newQuery()->index($index);
    }

    public function insert(
        array       $parameters,
        string|null $index = null,
        string|null $type = null
    ): object
    {
        if (!isset($parameters[Query::PARAM_INDEX]) && $index = $index ?? $this->index) {
            $parameters[Query::PARAM_INDEX] = $index;
        }

        if ($type) {
            $parameters[Query::PARAM_TYPE] = $type;
        }

        if ($this->reportQueries) {
            $this->reportQuery($parameters);
        }

        return (object)$this->client->index($parameters);
    }

    /**
     * Route the request to the query class
     *
     * @param string|null $connection Deprecated parameter: Use the proper
     *                                connection instance directly instead of
     *                                passing the name. This parameter will be
     *                                removed in the next major version.
     *
     * @return Query
     */
    public function newQuery(string|null $connection = null): Query
    {
        // TODO: This is deprecated behaviour and should be removed in the next
        //       major version.
        if ($connection) {
            trigger_deprecation(
                'matchory/elasticsearch',
                '3.0.0',
                'Passing the connection name to %s is deprecated. Use the proper connection instance directly ' .
                'instead. This parameter will be removed in the next major version.',
                __METHOD__
            );

            /** @noinspection PhpDeprecationInspection */
            return static::$resolver->connection($connection)->newQuery();
        }

        return (new Query($this))->index($this->index);
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
     * Set the connection resolver instance.
     *
     * @param Resolver $resolver
     *
     * @return void
     * @internal
     * @deprecated
     * @todo         remove in next major version
     * @noinspection PhpDeprecationInspection
     */
    #[Deprecated]
    public static function setConnectionResolver(Resolver $resolver): void
    {
        static::$resolver = $resolver;
    }

    /**
     * @param ClientBuilder $clientBuilder
     * @param array $config
     *
     * @return ClientBuilder
     * @throws InvalidArgumentException
     * @deprecated Use the connection manager to create connections instead. It
     *             provides a simpler way to manage connections. This method
     *             will be removed in the next major version.
     * @see        ConnectionManager
     */
    #[Deprecated(reason: 'Use the connection manager to create connections instead.')]
    public static function configureLogging(
        ClientBuilder $clientBuilder,
        array         $config
    ): ClientBuilder
    {
        trigger_deprecation(
            'matchory/elasticsearch',
            '3.0.0',
            'The %s method is deprecated. Use the connection manager to create connections instead. It provides a ' .
            'simpler way to manage connections. This method will be removed in the next major version.',
            __METHOD__
        );

        if (Arr::get($config, 'logging.enabled')) {
            $logger = new Logger(self::DEFAULT_LOGGER_NAME);
            $logger->pushHandler(new StreamHandler(Arr::get($config, 'logging.location'),
                Arr::get($config, 'logging.level', Level::Info)));

            $clientBuilder->setLogger($logger);
        }

        return $clientBuilder;
    }

    /**
     * Create a native connection suitable for any non-laravel or non-lumen apps
     * any composer based frameworks
     *
     * @param mixed $config
     *
     * @return Query
     * @throws BindingResolutionException
     * @deprecated Use the connection manager to create connections instead. It
     *             provides a simpler way to manage connections. This method
     *             will be removed in the next major version.
     * @see        ConnectionManager
     */
    #[Deprecated(reason: 'Use the connection manager to create connections instead.')]
    public static function create(mixed $config): Query
    {
        trigger_deprecation(
            'matchory/elasticsearch',
            '3.0.0',
            'The %s method is deprecated. Use the connection manager to create connections instead. It provides a ' .
            'simpler way to manage connections. This method will be removed in the next major version.',
            __METHOD__
        );

        $app = App::getFacadeApplication();
        $client = $app->make(ClientFactoryInterface::class)->createClient(
            $config['servers'],
            $config['handler'] ?? null
        );

        return (new static($client, $config['index'] ?? null))->newQuery();
    }

    /**
     * Proxy  calls to the default connection
     *
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     * @throws BadMethodCallException
     */
    public function __call(string $name, array $arguments)
    {
        return $this->forwardCallTo($this->newQuery(), $name, $arguments);
    }

    /**
     * Create a connection for laravel or lumen frameworks
     *
     * @param string $name
     *
     * @return Query
     * @deprecated Use the connection manager to create connections instead. It
     *             provides a simpler way to manage connections. This method
     *             will be removed in the next major version.
     * @see        ConnectionManager
     */
    #[Deprecated(reason: 'Use the connection manager to create connections instead.')]
    public function connection(string $name): Query
    {
        trigger_deprecation(
            'matchory/elasticsearch',
            '3.0.0',
            'The %s method is deprecated. Use the connection manager to create connections instead. It provides a ' .
            'simpler way to manage connections. This method will be removed in the next major version.',
            __METHOD__
        );

        return $this->newQuery($name);
    }

    /**
     * Check if the connection is already loaded
     *
     * @param string $name
     *
     * @return bool
     * @deprecated   Use the connection manager to create connections instead. It
     *             provides a simpler way to manage connections. This method
     *             will be removed in the next major version.
     * @see          ConnectionManager
     * @noinspection PhpDeprecationInspection
     */
    #[Deprecated(reason: 'Use the connection manager to create connections instead.')]
    public function isLoaded(string $name): bool
    {
        trigger_deprecation(
            'matchory/elasticsearch',
            '3.0.0',
            'The %s method is deprecated. Use the connection manager to create connections instead. It provides a ' .
            'simpler way to manage connections. This method will be removed in the next major version.',
            __METHOD__
        );

        return (bool)static::$resolver->connection($name);
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
            $sentry->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_INFO, Breadcrumb::TYPE_DEFAULT,
                'elasticsearch.query', json_encode($query, JSON_THROW_ON_ERROR) ?: '',));
        } catch (JsonException) {
            // We don't want errors during reporting to bubble up to
            // the application
        }
    }
}
