<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch;

use Elasticsearch\ClientBuilder as ElasticBuilder;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Scout\EngineManager;
use LogicException;
use Matchory\Elasticsearch\Commands\CreateIndexCommand;
use Matchory\Elasticsearch\Commands\DropIndexCommand;
use Matchory\Elasticsearch\Commands\ListIndicesCommand;
use Matchory\Elasticsearch\Commands\ReindexCommand;
use Matchory\Elasticsearch\Commands\UpdateIndexCommand;
use Matchory\Elasticsearch\Factories\ClientFactory;
use Matchory\Elasticsearch\Interfaces\ClientFactoryInterface;
use Matchory\Elasticsearch\Interfaces\ConnectionInterface;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

use function class_exists;
use function config_path;
use function file_exists;
use function method_exists;
use function trigger_deprecation;
use function version_compare;

/**
 * Class ElasticsearchServiceProvider
 *
 * @package Matchory\Elasticsearch
 */
class ElasticsearchServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        $this->configure();

        // Enable automatic connection resolution in all models
        Model::setConnectionResolver(
            $this->app->make(
                ConnectionResolverInterface::class
            )
        );

        // Enable event dispatching in all models
        Model::setEventDispatcher(
            $this->app->make(
                Dispatcher::class
            )
        );

        // TODO: Remove in next major version
        /** @noinspection PhpDeprecationInspection */
        Connection::setConnectionResolver(
            $this->app->make(
                ConnectionResolverInterface::class
            )
        );

        // Register the Laravel Scout Engine
        $this->registerScoutEngine();
    }

    protected function configure(): void
    {
        if (file_exists(__DIR__ . '/../config/es.php')) {
            $configPath = __DIR__ . '/../config/es.php';
            trigger_deprecation(
                'matchory/elasticsearch',
                '3.0.0',
                'The "es.php" configuration file is deprecated. Use "elasticsearch.php" instead.'
            );
        } else {
            $configPath = __DIR__ . '/../config/elasticsearch.php';
        }

        $configKey = basename($configPath, '.php');

        $this->mergeConfigFrom($configPath, $configKey);
        $this->publishes([
            __DIR__ . '/../config/' => config_path(),
        ], "{$configKey}.config");

        // Autoconfiguration with lumen framework.
        if (
            method_exists($this->app, 'configure') &&
            Str::contains($this->app->version(), 'Lumen')
        ) {
            $this->app->configure(ConnectionResolverInterface::class);
        }
    }

    protected function registerScoutEngine(): void
    {
        // Resolve Laravel Scout engine.
        if (!class_exists(EngineManager::class)) {
            return;
        }

        try {
            $this->app
                ->make(EngineManager::class)
                ->extend('elasticsearch', function () {
                    $connectionName = Config::get('scout.elasticsearch.connection');
                    $config = Config::get("elasticsearch.connections.{$connectionName}");
                    $elastic = ElasticBuilder
                        ::create()
                        ->setHosts($config['servers'])
                        ->build();

                    return new ScoutEngine(
                        $elastic,
                        $config['index']
                    );
                });
        } catch (BindingResolutionException) {
            // Class is not resolved.
            // Laravel Scout service provider was not loaded yet.
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     * @throws LogicException
     */
    public function register(): void
    {
        Model::clearBootedModels();

        $this->registerCommands();
        $this->registerLogger();
        $this->registerClientFactory();
        $this->registerConnectionResolver();
        $this->registerDefaultConnection();
    }

    protected function registerCommands(): void
    {
        $version = $this->app->version();

        if (
            version_compare($version, '5.1', '>=') ||
            Str::startsWith($version, 'Lumen') ||
            $this->app->runningInConsole()
        ) {
            // Registering commands
            $this->commands([
                ListIndicesCommand::class,
                CreateIndexCommand::class,
                UpdateIndexCommand::class,
                DropIndexCommand::class,
                ReindexCommand::class,
            ]);
        }
    }

    /**
     * Bind the Elasticsearch logger.
     *
     * @return void
     */
    protected function registerLogger(): void
    {
        $this->app->bind('elasticsearch.logger', fn(Application $app) => new Logger(
            $app->make('log')->channel(Config::get('elasticsearch.logger'))
        ));
    }

    /**
     * @throws LogicException
     */
    protected function registerClientFactory(): void
    {
        // Bind our default client factory on the container, so users may
        // override it if they need to build their client in a specific way
        $this->app->singleton(
            ClientFactoryInterface::class,
            ClientFactory::class
        );

        if ($this->app->bound('elasticsearch.logger')) {
            $this->app->when(ClientFactory::class)
                ->needs(LoggerInterface::class)
                ->give('elasticsearch.logger');
        }

        $this->app->alias(
            ClientFactoryInterface::class,
            'elasticsearch.factory'
        );
    }

    /**
     * @throws LogicException
     */
    protected function registerConnectionResolver(): void
    {
        // Bind the connection manager for the resolver interface as a singleton
        // on the container, so we have a single instance at all times
        $this->app->singleton(
            ConnectionResolverInterface::class,
            function (Application $app) {
                $configuration = Config::get('elasticsearch', Config::get('es', []));
                $factory = $app->make(ClientFactoryInterface::class);
                $cache = $app->bound(CacheInterface::class)
                    ? $app->make(CacheInterface::class)
                    : null;

                return new ConnectionManager(
                    $configuration,
                    $factory,
                    $cache,
                );
            }
        );

        $this->app->alias(
            ConnectionResolverInterface::class,
            'elasticsearch.resolver'
        );

        $this->app->alias(
            ConnectionResolverInterface::class,
            'elasticsearch'
        );

        $this->app->alias(
            ConnectionResolverInterface::class,
            'es'
        );

        $this->app->extend('es', function (ConnectionResolverInterface $resolver) {
            trigger_deprecation(
                'matchory/elasticsearch',
                '3.0.0',
                'The "es" alias is deprecated. Use "elasticsearch" instead.'
            );

            return $resolver;
        });
    }

    /**
     * @throws LogicException
     */
    protected function registerDefaultConnection(): void
    {
        // Bind the default connection separately
        $this->app->singleton(
            ConnectionInterface::class,
            fn(Application $app): ConnectionInterface => $app
                ->make(ConnectionResolverInterface::class)
                ->connection()
        );

        $this->app->alias(
            ConnectionInterface::class,
            'elasticsearch.connection'
        );
    }
}
