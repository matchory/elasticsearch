<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Factories;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\RuntimeException;
use Matchory\Elasticsearch\Interfaces\ClientFactoryInterface;
use Psr\Log\LoggerInterface;

use function trigger_error;

use const E_USER_DEPRECATED;

readonly class ClientFactory implements ClientFactoryInterface
{
    public function __construct(private LoggerInterface|null $logger = null)
    {
    }

    /**
     * @inheritDoc
     * @throws RuntimeException
     */
    public function createClient(array $config): Client
    {
        if (isset($config['servers'])) {
            @trigger_error(
                "Since matchory/elasticsearch 3.0.0: The 'servers' configuration key is deprecated. " .
                "Use 'hosts' instead.",
                E_USER_DEPRECATED
            );

            $config['hosts'] = $config['servers'];
            unset($config['servers']);
        }

        if ($this->logger !== null) {
            $config['logger'] = $this->logger;
        }

        unset($config['index']);

        return ClientBuilder::fromConfig($config);
    }
}
