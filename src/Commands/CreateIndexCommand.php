<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Commands;

use Illuminate\Console\Command;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;

use function array_keys;
use function config;
use function is_null;

class CreateIndexCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'es:indices:create {index?}{--connection= : Elasticsearch connection}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new index using defined setting and mapping in config file';

    /**
     * Execute the console command.
     */
    public function handle(ConnectionResolverInterface $resolver): void
    {
        $connectionName = $this->option('connection') ?: null;
        $connection = $resolver->connection($connectionName)->newQuery();
        $client = $connection->raw();

        /** @var string[] $indices */
        $indices = !is_null($this->argument('index'))
            ? [$this->argument('index')]
            : array_keys(config('elasticsearch.indices', config('elasticsearch.indices', config('es.indices', []))));

        foreach ($indices as $index) {
            $config = config("elasticsearch.indices.{$index}", config("es.indices.{$index}"));

            if (is_null($config)) {
                $this->warn("Missing configuration for index: {$index}");

                continue;
            }

            if ($client->indices()->exists(['index' => $index])) {
                $this->warn("Index {$index} already exists!");

                continue;
            }

            // Create index with settings from config file

            $this->info("Creating index: {$index}");

            $client->indices()->create([
                'index' => $index,
                'body' => [
                    'settings' => $config['settings'],
                ],
            ]);

            if (isset($config['aliases'])) {
                foreach ($config['aliases'] as $alias) {
                    $this->info("Creating alias: {$alias} for index: {$index}");

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

            if (isset($config['mappings'])) {
                foreach ($config['mappings'] as $mapping) {
                    $this->info(
                        "Creating mapping for index: {$index}",
                    );

                    // Create mapping for type from config file
                    $client->indices()->putMapping([
                        'index' => $index,
                        'body' => $mapping,
                        'include_type_name' => true,
                    ]);
                }
            }
        }
    }
}
