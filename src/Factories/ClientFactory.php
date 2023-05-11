<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Factories;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Matchory\Elasticsearch\Interfaces\ClientFactoryInterface;
use Psr\Log\LoggerInterface;

class ClientFactory implements ClientFactoryInterface
{
    /**
     * @inheritDoc
     */
    public function createClient(
        array $hosts,
        LoggerInterface|null $logger = null,
        callable|null $handler = null
    ): Client {
        $builder = new ClientBuilder();
        $builder->setHosts($hosts);

        if ($logger) {
            $builder->setLogger($logger);
        }

        if ($handler) {
            $builder->setHandler($handler);
        }

        return $builder->build();
    }
}
