<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;
use RuntimeException;

use function array_key_exists;
use function ceil;
use function config;
use function count;
use function json_encode;

/**
 * Reindex Command
 *
 * @package Matchory\Elasticsearch
 */
class ReindexCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'es:indices:reindex {index}{new_index}
                            {--bulk-size=1000 : Scroll size}
                            {--skip-errors : Skip reindexing errors}
                            {--hide-errors : Hide reindexing errors}
                            {--scroll=2m : query scroll time}
                            {--connection= : Elasticsearch connection}';

    /**
     * The console command description.
     */
    protected $description = 'Reindex indices data';

    /**
     * ES connection name
     */
    protected string|null $connection = null;

    /**
     * Query bulk size
     */
    protected int|null $size = null;

    /**
     * Scroll time
     */
    protected string|null $scroll = null;

    /**
     * Execute the console command.
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws JsonException
     */
    public function handle(ConnectionResolverInterface $resolver): void
    {
        $this->connection = $this->option('connection') ?: null;
        $this->size = (int)$this->option('bulk-size');
        $this->scroll = (string)$this->option('scroll');

        if ($this->size <= 0) {
            $this->warn('Invalid size value');

            return;
        }

        $originalIndex = (string)$this->argument('index');
        $newIndex = $this->argument('new_index');

        if (!array_key_exists($originalIndex, config('elasticsearch.indices', config('es.indices', [])))) {
            $this->warn("Missing configuration for index: {$originalIndex}");

            return;
        }

        if (!array_key_exists($newIndex, config('elasticsearch.indices', config('es.indices', [])))) {
            $this->warn("Missing configuration for index: {$newIndex}");

            return;
        }

        $this->migrate($resolver, $originalIndex, $newIndex);
    }

    /**
     * Migrate data with Scroll queries & Bulk API
     *
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws RuntimeException
     */
    public function migrate(
        ConnectionResolverInterface $resolver,
        string $originalIndex,
        string $newIndex,
        string|null $scrollId = null,
        int $errors = 0,
        int $page = 1
    ): void {
        $connection = $resolver->connection($this->connection);

        if ($page === 1) {
            $pages = (int)ceil(
                $connection
                    ->index($originalIndex)
                    ->count() / $this->size
            );

            $this->output->progressStart($pages);

            $documents = $connection
                ->index($originalIndex)
                ->scroll($this->scroll)
                ->take($this->size)
                ->performSearch();
        } else {
            $documents = $connection
                ->index($originalIndex)
                ->scroll($this->scroll)
                ->scrollID($scrollId ?: '')
                ->performSearch();
        }

        if (
            isset($documents['hits']['hits']) &&
            count($documents['hits']['hits'])
        ) {
            $data = $documents['hits']['hits'];
            $params = [];

            foreach ($data as $row) {
                $params['body'][] = [

                    'index' => [
                        '_index' => $newIndex,
                        '_type' => $row['_type'],
                        '_id' => $row['_id'],
                    ],

                ];

                $params['body'][] = $row['_source'];
            }

            $response = $connection->getClient()->bulk($params);

            if (isset($response['errors']) && $response['errors']) {
                if (!$this->option('hide-errors')) {
                    $items = json_encode($response['items']);

                    if (!$this->option('skip-errors')) {
                        $this->warn("\n{$items}");

                        return;
                    }

                    $this->warn("\n{$items}");
                }

                $errors++;
            }

            $this->output->progressAdvance();
        } else {
            // Reindexing finished
            $this->output->progressFinish();

            $total = $connection
                ->index($originalIndex)
                ->count();

            if ($errors > 0) {
                $this->warn("{$total} documents reindexed with {$errors} errors.");

                return;
            }

            $this->info("{$total} documents reindexed successfully.");

            return;
        }

        $page++;

        $this->migrate(
            $resolver,
            $originalIndex,
            $newIndex,
            $documents['_scroll_id'],
            $errors,
            $page
        );
    }

}
