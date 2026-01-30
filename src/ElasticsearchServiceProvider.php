<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch;

use Illuminate\Contracts\{Container\BindingResolutionException,
    Events\Dispatcher,
    Foundation\Application,
    Foundation\CachesConfiguration};
use Illuminate\Log\LogManager;
use Illuminate\Support\{Facades\Config, ServiceProvider};
use Laravel\Scout\EngineManager;
use LogicException;
use Matchory\Elasticsearch\Console\Commands\{ListIndicesCommand};
use Matchory\Elasticsearch\Console\Commands\CreateIndexCommand;
use Matchory\Elasticsearch\Console\Commands\DropIndexCommand;
use Matchory\Elasticsearch\Console\Commands\ReindexCommand;
use Matchory\Elasticsearch\Console\Commands\UpdateIndexCommand;
use Matchory\Elasticsearch\Factories\ClientFactory;
use Matchory\Elasticsearch\Interfaces\{ClientFactoryInterface, ConnectionInterface, ConnectionResolverInterface};
use Psr\SimpleCache\CacheInterface;

use function class_exists;
use function config_path;
use function dirname;

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
                ConnectionResolverInterface::class,
            ),
        );

        // Enable event dispatching in all models
        Model::setEventDispatcher(
            $this->app->make(
                Dispatcher::class,
            ),
        );

        // Register the Laravel Scout Engine
        $this->registerScoutEngine();
    }


    /**
     * @throws BindingResolutionException
     */
    protected function configure(): void
    {
        $configPath = $this->packageConfigPath('elasticsearch.php');

        $this->mergeConfigFrom($configPath, 'elasticsearch');
        $this->mergeLoggingChannelsFrom($this->packageConfigPath('logging.php'));
        $this->publishes([
            $this->packageConfigPath() => config_path(),
        ], 'elasticsearch.config');
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
                    $connection = $this->app
                        ->make(ConnectionResolverInterface::class)
                        ->connection($connectionName);

                    $index = Config::get('scout.elasticsearch.index', 'scout');

                    return new ScoutEngine($connection->getClient(), $index);
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
        if (!$this->app->runningInConsole()) {
            return;
        }

        // Registering commands
        $this->commands([
            ListIndicesCommand::class,
            CreateIndexCommand::class,
            UpdateIndexCommand::class,
            DropIndexCommand::class,
            ReindexCommand::class,
        ]);
    }

    /**
     * Bind the Elasticsearch logger.
     *
     * @return void
     */
    protected function registerLogger(): void
    {
        $this->app->bind(
            'elasticsearch.logger',
            fn(Application $app)
                => $app
                ->make(LogManager::class)
                ->channel('elasticsearch'),
        );
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
            ClientFactory::class,
        );

        $this->app->bind(ClientFactory::class, fn(Application $app) => new ClientFactory(
            $app->make('elasticsearch.logger'),
        ));

        $this->app->alias(
            ClientFactoryInterface::class,
            'elasticsearch.factory',
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
                $configuration = Config::get('elasticsearch', []);
                $factory = $app->make(ClientFactoryInterface::class);
                $cache = $app->bound(CacheInterface::class)
                    ? $app->make(CacheInterface::class)
                    : null;

                return new ConnectionManager(
                    $configuration,
                    $factory,
                    $cache,
                );
            },
        );

        $this->app->alias(
            ConnectionResolverInterface::class,
            'elasticsearch.resolver',
        );

        $this->app->alias(
            ConnectionResolverInterface::class,
            'elasticsearch',
        );
    }

    /**
     * @throws LogicException
     */
    protected function registerDefaultConnection(): void
    {
        // Bind the default connection separately
        $this->app->singleton(
            ConnectionInterface::class,
            fn(Application $app): ConnectionInterface
                => $app
                ->make(ConnectionResolverInterface::class)
                ->connection(),
        );

        $this->app->alias(ConnectionInterface::class, 'elasticsearch.connection');
    }

    /**
     * @throws BindingResolutionException
     */
    private function mergeLoggingChannelsFrom(string $file): void
    {
        if (!($this->app instanceof CachesConfiguration && $this->app->configurationIsCached())) {
            $packageLoggingConfig = require $file;

            $config = $this->app->make('config');
            $config->set(
                'logging.channels',
                array_merge(
                    $packageLoggingConfig['channels'] ?? [],
                    $config->get('logging.channels', []),
                ),
            );
        }
    }

    private function packageConfigPath(string $path = ''): string
    {
        return dirname(__DIR__) . '/config' . ($path ? '/' . $path : $path);
    }
}
