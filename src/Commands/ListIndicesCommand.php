<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;

use function array_key_exists;
use function config;
use function count;
use function explode;
use function is_array;
use function trim;

use const PHP_EOL;

/**
 * List Indices Command
 *
 * @package Matchory\Elasticsearch
 */
class ListIndicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'es:indices:list {--connection= : Elasticsearch connection}';

    /**
     * The console command description.
     */
    protected $description = 'List all indices';

    /**
     * Indices headers
     *
     * @var string[]
     */
    protected array $headers = [
        'configured',
        'health',
        'status',
        'index',
        'uuid',
        'pri',
        'rep',
        'docs.count',
        'docs.deleted',
        'store.size',
        'pri.store.size',
    ];

    /**
     * Execute the console command.
     */
    public function handle(ConnectionResolverInterface $resolver): void
    {
        $connectionName = $this->option('connection') ?: null;
        $connection = $resolver->connection($connectionName)->newQuery();
        $indices = $connection->raw()->cat()->indices();
        $indices = is_array($indices)
            ? $this->getIndicesFromArrayResponse($indices)
            : $this->getIndicesFromStringResponse($indices);

        if (count($indices)) {
            $this->table($this->headers, $indices);
        } else {
            $this->warn('No indices found.');
        }
    }

    /**
     * Get a list of indices data
     * Match newer versions of elasticsearch/elasticsearch package (5.1.1 or higher)
     */
    public function getIndicesFromArrayResponse(array $indices): array
    {
        $data = [];

        foreach ($indices as $row) {
            $row = array_key_exists($row['index'], config('elasticsearch.indices', config('es.indices', [])))
                ? Arr::prepend($row, 'yes')
                : Arr::prepend($row, 'no');

            $data[] = $row;
        }

        return $data;
    }

    /**
     * Get list of indices data
     * Match older versions of elasticsearch/elasticsearch package.
     */
    public function getIndicesFromStringResponse(string $indices): array
    {
        $lines = explode(PHP_EOL, trim($indices));
        $data = [];

        foreach ($lines as $line) {
            $line_array = explode(' ', trim($line));
            $row = [];

            foreach ($line_array as $item) {
                if (trim($item) !== '') {
                    $row[] = $item;
                }
            }

            $row = array_key_exists($row[2], config('elasticsearch.indices', config('es.indices', [])))
                ? Arr::prepend($row, 'yes')
                : Arr::prepend($row, 'no');

            $data[] = $row;
        }

        return $data;
    }
}
