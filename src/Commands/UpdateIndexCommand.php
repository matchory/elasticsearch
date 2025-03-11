<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Commands;

use Illuminate\Console\Command;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;

use function array_keys;
use function config;
use function is_null;

/**
 * Update Index Command
 *
 * @bundle Matchory\Elasticsearch
 */
class UpdateIndexCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'es:indices:update {index?}{--connection= : Elasticsearch connection}';

    /**
     * The console command description.
     */
    protected $description = 'Update index using defined setting and mapping in config file';

    /**
     * Execute the console command.
     */
    public function handle(ConnectionResolverInterface $resolver): void
    {
        $connectionName = $this->option('connection') ?: null;
        $connection = $resolver->connection($connectionName);
        $client = $connection->getClient();
        $indices = !is_null($this->argument('index'))
            ? [$this->argument('index')]
            : array_keys(config('elasticsearch.indices', config('es.indices', [])));

        foreach ($indices as $index) {
            $config = config("elasticsearch.indices.{$index}", config("es.indices.{$index}"));

            if (is_null($config)) {
                $this->warn("Missing configuration for index: {$index}");
                continue;
            }

            if (!$client->indices()->exists(['index' => $index])) {
                $this->call('es:indices:create', [
                    'index' => $index,
                ]);

                return;
            }

            $this->info("Removing aliases for index: {$index}");

            // The index is already exists. update aliases and setting
            // Remove all index aliases
            $client->indices()->updateAliases([
                'body' => [
                    'actions' => [
                        [
                            'remove' => [
                                'index' => $index,
                                'alias' => '*',
                            ],
                        ],
                    ],

                ],

                'client' => ['ignore' => [404]],
            ]);

            // Update index aliases from config
            if (isset($config['aliases'])) {
                foreach ($config['aliases'] as $alias) {
                    $this->info(
                        "Creating alias: {$alias} for index: {$index}",
                    );

                    $client->indices()->updateAliases([
                        'body' => [
                            'actions' => [
                                [
                                    'add' => [
                                        'index' => $index,
                                        'alias' => $alias,
                                    ],
                                ],
                            ],

                        ],
                    ]);
                }
            }

            // Create mapping for type from config file
            if (isset($config['mappings'])) {
                foreach ($config['mappings'] as $mapping) {
                    $this->info(
                        "Creating mapping for index: {$index}",
                    );

                    $client->indices()->putMapping([
                        'index' => $index,
                        'body' => $mapping,
                    ]);
                }
            }
        }
    }
}
