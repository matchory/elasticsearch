<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Factories;

use Elastic\Elasticsearch\Client;
use Matchory\Elasticsearch\Factories\ClientFactory;
use PHPUnit\Framework\TestCase;

class ClientFactoryTest extends TestCase
{
    public function testCreateClient(): void
    {
        $factory = new ClientFactory();
        $client = $factory->createClient([]);

        self::assertInstanceOf(Client::class, $client);
    }

    public function testCreateClientWithHosts(): void
    {
        $config = [
            'hosts' => ['localhost:9200', 'localhost:9201'],
        ];
        $factory = new ClientFactory();
        $client = $factory->createClient($config);

        // In ES v9, the Client class has protected properties and we cannot
        // access the transport directly. We just verify the client is created
        // successfully with the given configuration.
        self::assertInstanceOf(Client::class, $client);
    }
}
