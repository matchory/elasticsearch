<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch;

use ArrayObject;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch as ElasticsearchResponse;
use Matchory\Elasticsearch\Interfaces\ConnectionInterface;
use RuntimeException;
use TypeError;

use function array_unique;
use function count;
use function is_array;
use function is_string;

/**
 * Class Index
 *
 * @package Matchory\Elasticsearch\Query
 */
class Index
{
    private const PARAM_ALIASES = 'aliases';

    private const PARAM_BODY = 'body';

    private const PARAM_INDEX = 'index';

    private const PARAM_MAPPINGS = 'mappings';

    private const PARAM_SETTINGS = 'settings';

    private const PARAM_SETTINGS_NUMBER_OF_REPLICAS = 'number_of_replicas';

    private const PARAM_SETTINGS_NUMBER_OF_SHARDS = 'number_of_shards';

    /**
     * Native elasticsearch client instance
     *
     * @var ConnectionInterface|null
     */
    private ?ConnectionInterface $connection = null;

    /**
     * Ignored HTTP errors
     *
     * @var array<int>
     */
    private array $ignores = [];

    /**
     * Mappings the index shall be configured with.
     *
     * @var array
     */
    private array $mappings = [];

    /**
     * Name of the index.
     *
     * @var string
     */
    private string $name;

    /**
     * The number of replicas the index shall be configured with.
     *
     * @var int
     */
    private int $replicas = 0;

    /**
     * The number of shards the index shall be configured with.
     *
     * @var int
     */
    private int $shards = 5;

    /**
     * Aliases the index shall be configured with.
     *
     * @var array<string, array<string, mixed>|string|ArrayObject>
     */
    private array $aliases = [];

    /**
     * Creates a new index instance.
     *
     * @param string $name Name of the index to manage.
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Retrieves the name of the new index.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * An index alias is a secondary name used to refer to one or more existing
     * indices. Most Elasticsearch APIs accept an index alias in place of
     * an index.
     *
     * APIs in Elasticsearch accept an index name when working against a
     * specific index, and several indices when applicable. The index aliases
     * API allows aliasing an index with a name, with all APIs automatically
     * converting the alias name to the actual index name. An alias can also be
     * mapped to more than one index, and when specifying it, the alias will
     * automatically expand to the aliased indices. An alias can also be
     * associated with a filter that will automatically be applied when
     * searching, and routing values. An alias cannot have the same name as
     * an index.
     *
     * @param string                        $alias   Name of the alias to add.
     * @param array|ArrayObject|string|null $options Options to pass to
     *                                               the alias.
     *
     * @return $this
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/master/indices-aliases.html
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/indices-create-index.html#create-index-aliases
     */
    public function alias(string $alias, mixed $options = null): self
    {
        if (
            $options !== null
            && !is_string($options)
            && !is_array($options)
        ) {
            throw new TypeError(
                'Alias options may be passed as an array, a string '
                . 'routing key, or literal null.',
            );
        }

        $this->aliases[$alias] = $options ?? new ArrayObject();

        return $this;
    }

    /**
     * Creates a new index with the configured settings, mappings, and aliases.
     *
     * @return array|ElasticsearchResponse
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/indices-create-index.html
     */
    public function create(): array|ElasticsearchResponse
    {
        $params = [
            self::PARAM_INDEX => $this->name,
            self::PARAM_BODY => [
                self::PARAM_SETTINGS => [
                    self::PARAM_SETTINGS_NUMBER_OF_SHARDS => $this->shards,
                    self::PARAM_SETTINGS_NUMBER_OF_REPLICAS => $this->replicas,
                ],
            ],
        ];

        if (count($this->aliases) > 0) {
            $params[self::PARAM_BODY][self::PARAM_ALIASES] = $this->aliases;
        }

        if (count($this->mappings) > 0) {
            $params[self::PARAM_BODY][self::PARAM_MAPPINGS] = $this->mappings;
        }

        return $this->executeWithIgnoredErrors(
            fn(object $client) => $client->indices()->create($params),
            $this->ignores,
        );
    }

    /**
     * Execute a client operation with specific HTTP status codes ignored.
     *
     * @param callable(object): mixed $operation The operation to execute
     * @param array<int>              $ignores   HTTP status codes to ignore
     *
     * @return mixed The operation result
     * @throws ClientResponseException If the response has an error status not in $ignores
     */
    protected function executeWithIgnoredErrors(callable $operation, array $ignores = []): mixed
    {
        return $this->getConnection()->executeWithIgnoredErrors($operation, $ignores);
    }

    /**
     * Retrieves the Elasticsearch  client instance.
     *
     * @return Client
     * @internal
     */
    public function getClient(): Client
    {
        return $this->getConnection()->getClient();
    }

    /**
     * Retrieves the active connection.
     *
     * @return ConnectionInterface
     * @internal
     */
    public function getConnection(): ConnectionInterface
    {
        if ($this->connection === null) {
            throw new RuntimeException('No connection has been set on this index.');
        }

        return $this->connection;
    }

    /**
     * Sets the active connection on the index.
     *
     * @param ConnectionInterface $connection
     *
     * @internal
     */
    public function setConnection(ConnectionInterface $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * Deletes an existing index.
     *
     * @return array|ElasticsearchResponse
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/indices-delete-index.html
     */
    public function drop(): array|ElasticsearchResponse
    {
        return $this->executeWithIgnoredErrors(
            fn(object $client) => $client->indices()->delete([
                self::PARAM_INDEX => $this->name,
            ]),
            $this->ignores,
        );
    }

    /**
     * Checks whether an index exists.
     *
     * @return bool
     * @throws RuntimeException
     */
    public function exists(): bool
    {
        return $this
            ->getConnection()
            ->getClient()
            ->indices()
            ->exists(['index' => $this->name])
            ->asBool();
    }

    /**
     * Configures the client to ignore bad HTTP requests.
     *
     * @param int ...$statusCodes HTTP Status codes to ignore.
     *
     * @return $this
     */
    public function ignores(int ...$statusCodes): self
    {
        $this->ignores = array_unique($statusCodes);

        return $this;
    }

    /**
     * Sets the fields mappings.
     *
     * @param array $mappings
     *
     * @return $this
     */
    public function mapping(array $mappings = []): self
    {
        $this->mappings = $mappings;

        return $this;
    }

    /**
     * The number of replicas each primary shard has. Defaults to `1`.
     *
     * @param int $replicas Number of replicas to configure.
     *
     * @return $this
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/master/index-modules.html#index-number-of-replicas
     */
    public function replicas(int $replicas): self
    {
        $this->replicas = $replicas;

        return $this;
    }

    /**
     * The number of primary shards that an index should have. Defaults to `1`.
     * This setting can only be set at index creation time. It cannot be changed
     * on a closed index.
     *
     * The number of shards are limited to 1024 per index. This limitation is a
     * safety limit to prevent accidental creation of indices that can
     * destabilize a cluster due to resource allocation. The limit can be
     * modified by specifying
     * `export ES_JAVA_OPTS="-Des.index.max_number_of_shards=128"` system
     * property on every node that is part of the cluster.
     *
     * @param int $shards Number of shards to configure.
     *
     * @return $this
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/master/index-modules.html#index-number-of-shards
     */
    public function shards(int $shards): self
    {
        $this->shards = $shards;

        return $this;
    }
}
