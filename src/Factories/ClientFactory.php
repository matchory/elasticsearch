<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Factories;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Matchory\Elasticsearch\Interfaces\ClientFactoryInterface;
use Psr\Log\LoggerInterface;

use function is_array;
use function is_string;

readonly class ClientFactory implements ClientFactoryInterface
{
    public function __construct(private ?LoggerInterface $logger = null) {}

    /**
     * @inheritDoc
     */
    public function createClient(array $config): object
    {
        $builder = ClientBuilder::create();

        // Set hosts
        if (isset($config['hosts'])) {
            $hosts = $config['hosts'];

            if (is_string($hosts)) {
                $hosts = [$hosts];
            }

            $builder->setHosts($hosts);
        }

        // Set logger
        if ($this->logger !== null) {
            $builder->setLogger($this->logger);
        }

        // Basic authentication
        if (isset($config['basicAuthentication'])) {
            $builder->setBasicAuthentication(
                $config['basicAuthentication']['username'],
                $config['basicAuthentication']['password'],
            );
        }

        // API key authentication
        if (isset($config['apiKey'])) {
            if (is_array($config['apiKey'])) {
                $builder->setApiKey(
                    $config['apiKey']['id'],
                    $config['apiKey']['apiKey'],
                );
            } else {
                $builder->setApiKey($config['apiKey']);
            }
        }

        // Elastic Cloud ID
        if (isset($config['elasticCloudId'])) {
            $builder->setElasticCloudId($config['elasticCloudId']);
        }

        // SSL verification
        if (isset($config['sslVerification'])) {
            $builder->setSSLVerification($config['sslVerification']);
        }

        // CA bundle
        if (isset($config['caBundle'])) {
            $builder->setCABundle($config['caBundle']);
        }

        // Retries
        if (isset($config['retries'])) {
            $builder->setRetries($config['retries']);
        }

        // Elastic meta header
        if (isset($config['elasticMetaHeader'])) {
            $builder->setElasticMetaHeader($config['elasticMetaHeader']);
        }

        // HTTP client options
        if (isset($config['httpClientOptions'])) {
            $builder->setHttpClientOptions($config['httpClientOptions']);
        }

        return $builder->build();
    }
}
