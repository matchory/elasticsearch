<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Factories;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\RuntimeException;
use Matchory\Elasticsearch\Interfaces\ClientFactoryInterface;
use Psr\Log\LoggerInterface;

use function trigger_deprecation;

class ClientFactory implements ClientFactoryInterface
{
    public function __construct(private readonly LoggerInterface|null $logger = null)
    {
    }

    /**
     * @inheritDoc
     * @throws RuntimeException
     */
    public function createClient(array $config): Client
    {
        if (isset($config['servers'])) {
            trigger_deprecation(
                'matchory/elasticsearch',
                '3.0.0',
                'The "servers" configuration key is deprecated. Use "hosts" instead.'
            );

            $config['hosts'] = $config['servers'];
            unset($config['servers']);
        }

        return ClientBuilder::fromConfig([
            ...$config,
            'logger' => $this->logger,
        ]);
    }
}
