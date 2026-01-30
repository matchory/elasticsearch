<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Console\Commands;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch as ElasticsearchResponse;
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

            // The index already exists. Update aliases and settings.
            // Remove all index aliases (ignore 404 if no aliases exist)
            $client->setResponseException(false);

            try {
                $result = $client->indices()->updateAliases([
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
                ]);

                // Re-throw non-404 errors
                if ($result instanceof ElasticsearchResponse) {
                    $statusCode = $result->getStatusCode();

                    if ($statusCode >= 400 && $statusCode !== 404) {
                        $error = new ClientResponseException(
                            sprintf('%d %s', $statusCode, $result->getReasonPhrase()),
                            $statusCode,
                        );
                        throw $error->setResponse($result);
                    }
                }
            } finally {
                $client->setResponseException(true);
            }

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
