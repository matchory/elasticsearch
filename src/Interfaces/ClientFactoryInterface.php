<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Interfaces;

use Elastic\Elasticsearch\Client;

interface ClientFactoryInterface
{
    /**
     * Creates a new client from the given configuration array.
     *
     * Note: Return type is `object` to allow for duck-typed mock clients in
     * tests. The Elasticsearch PHP Client v9 makes the Client class final,
     * preventing extension or mocking. The returned object will have all
     * methods of the Elasticsearch Client.
     *
     * @param array<string, mixed> $config
     *
     * @return Client|object
     */
    public function createClient(array $config): object;
}
