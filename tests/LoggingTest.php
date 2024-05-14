<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests;

use Elasticsearch\ClientBuilder;
use Matchory\Elasticsearch\Connection;
use Monolog\Level;
use PHPUnit\Framework\TestCase;

class LoggingTest extends TestCase
{

    public function testConfigureLogging(): void
    {
        $client = ClientBuilder::create();

        $newClientBuilder = Connection::configureLogging($client, [
            'logging' => [
                'enabled' => true,
                'level' => Level::Debug,
                'location' => '../src/storage/logs/elasticsearch.log',
            ],
        ]);

        self::assertInstanceOf(
            ClientBuilder::class,
            $newClientBuilder
        );
    }
}
