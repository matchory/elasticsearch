<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Console\Commands;

use Illuminate\Console\Command;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;

use function array_keys;
use function config;
use function is_null;

/**
 * Drop Index Command
 *
 * @bundle Matchory\Elasticsearch
 */
class DropIndexCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'es:indices:drop {index?}
                            {--connection= : Elasticsearch connection}
                            {--force : Drop indices without any confirmation messages}';

    /**
     * The console command description.
     */
    protected $description = 'Drop an index';

    /**
     * Execute the console command.
     */
    public function handle(ConnectionResolverInterface $resolver): void
    {
        $connectionName = $this->option('connection') ?: null;
        $connection = $resolver->connection($connectionName);
        $force = $this->option('force') || 0;
        $client = $connection->getClient();
        $indices = !is_null($this->argument('index'))
            ? [$this->argument('index')]
            : array_keys(config('elasticsearch.indices', config('es.indices', [])));

        foreach ($indices as $index) {
            if (!$client->indices()->exists(['index' => $index])) {
                $this->warn("Index '{$index}' does not exist.");

                continue;
            }

            if (
                $force
                || $this->confirm("Are you sure you want to drop the index '{$index}'?")
            ) {
                $this->info("Dropping index: {$index}");
                $client->indices()->delete(['index' => $index]);
            }
        }
    }
}
