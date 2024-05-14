<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Interfaces;

use Elasticsearch\Client;

interface ClientFactoryInterface
{
    /**
     * Creates a new client from the given configuration array.
     *
     * @param array<string, mixed> $config
     *
     * @return Client
     */
    public function createClient(array $config): Client;
}
