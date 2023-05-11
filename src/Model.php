<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch;

use ArrayAccess;
use BadMethodCallException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\QueueableEntity;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Concerns\GuardsAttributes;
use Illuminate\Database\Eloquent\Concerns\HasAttributes;
use Illuminate\Database\Eloquent\Concerns\HasEvents;
use Illuminate\Database\Eloquent\Concerns\HidesAttributes;
use Illuminate\Database\Eloquent\InvalidCastException;
use Illuminate\Database\Eloquent\JsonEncodingException;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\ForwardsCalls;
use InvalidArgumentException;
use JetBrains\PhpStorm\Deprecated;
use JsonException;
use JsonSerializable;
use Matchory\Elasticsearch\Concerns\HasGlobalScopes;
use Matchory\Elasticsearch\Exceptions\DocumentNotFoundException;
use Matchory\Elasticsearch\Interfaces\ConnectionInterface as Connection;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;
use ReturnTypeWillChange;

use function array_key_exists;
use function array_merge;
use function array_unique;
use function assert;
use function class_basename;
use function class_uses_recursive;
use function count;
use function forward_static_call;
use function func_get_args;
use function get_class;
use function in_array;
use function is_array;
use function is_null;
use function json_encode;
use function method_exists;
use function settype;
use function sprintf;
use function tap;
use function ucfirst;

use const DATE_ATOM;

/**
 * Elasticsearch data model
 *
 * @property-read string|null _id
 * @property-read string|null _index
 * @property-read string|null _type
 * @property-read float|null  _score
 * @property-read array|null  highlight
 *
 * @package Matchory\Elasticsearch
 */
class Model implements Arrayable,
                       ArrayAccess,
                       Jsonable,
                       JsonSerializable,
                       QueueableEntity,
                       UrlRoutable
{
    use ForwardsCalls;
    use HasAttributes;
    use HidesAttributes;
    use HasEvents;
    use HasGlobalScopes;
    use GuardsAttributes;

    protected const FIELD_ID = '_id';

    /**
     * The array of booted models.
     *
     * @var array<string, bool>
     */
    protected static array $booted = [];

    /**
     * The event dispatcher instance.
     *
     * @var Dispatcher
     */
    protected static Dispatcher $dispatcher;

    /**
     * The connection resolver instance.
     *
     * @var ConnectionResolverInterface|null
     */
    protected static ConnectionResolverInterface|null $resolver;

    /**
     * The array of trait initializers that will be called on each new instance.
     *
     * @var array<string, string[]>
     */
    protected static array $traitInitializers = [];

    /**
     * Indicates if the model was inserted during the current request lifecycle.
     *
     * @var bool
     */
    public bool $wasRecentlyCreated = false;

    /**
     * Model connection name. If `null` it will use the default connection.
     *
     * @var string|null
     */
    protected string|null $connectionName = null;

    /**
     * Indicates whether the model exists in the Elasticsearch index.
     *
     * @var bool
     */
    protected bool $exists = false;

    /**
     * Index name
     *
     * @var string|null
     */
    protected string|null $index = null;

    /**
     * Metadata received from Elasticsearch as part of the response
     *
     * @var array<string, mixed>
     */
    protected array $resultMetadata = [];

    /**
     * Model selectable fields
     *
     * @var string[]
     */
    protected array $selectable = [];

    /**
     * Document mapping type
     *
     * @var string|null
     */
    protected string|null $type = null;

    /**
     * Model unselectable fields
     *
     * @var string[]
     */
    protected array $unselectable = [];

    /**
     * Create a new Elasticsearch model instance.
     * Note the two inspection overrides in the docblock: In most cases, the
     * mass assignment exception will _not_ be thrown, just as with Eloquent
     * models; additionally, it should actually rather be an assertion, as this
     * specific error should pop up in development.
     * Therefore, we've decided to inherit this from Eloquent, which simply does
     * not add the `throws` annotation to their constructor.
     *
     * @param array<string, mixed> $attributes
     * @param bool                 $exists
     *
     * @noinspection PhpUnhandledExceptionInspection
     * @noinspection PhpDocMissingThrowsInspection
     */
    final public function __construct(
        array $attributes = [],
        bool $exists = false
    ) {
        $this->exists = $exists;

        $this->bootIfNotBooted();
        $this->initializeTraits();
        $this->syncOriginal();

        // Force-fill the attributes if the model class is used on its own, a
        // quirk of this specific implementation. As users can't set the
        // fillable property in that case, the constructor must be unguarded.
        if (static::class === self::class) {
            $this->forceFill($attributes);
        } else {
            $this->fill($attributes);
        }
    }

    /**
     * Get an attribute from the model.
     *
     * @param string $key
     *
     * @return mixed
     * @throws InvalidCastException
     * @noinspection PhpDeprecationInspection
     */
    public function getAttribute(string $key): mixed
    {
        if ( ! $key) {
            return null;
        }

        // If the attribute exists in the metadata array, we will get the value
        // from there.
        if (array_key_exists($key, $this->resultMetadata)) {
            return $this->getResultMetadataValue($key);
        }

        if ($key === '_index') {
            return $this->getIndex();
        }

        if ($key === '_type') {
            return $this->getType();
        }

        if ($key === '_score') {
            return $this->getScore();
        }

        // If the attribute exists in the attribute array or has a "get" mutator
        // we will get the attribute's value.
        if (
            array_key_exists($key, $this->attributes) ||
            array_key_exists($key, $this->casts) ||
            $this->hasGetMutator($key) ||
            $this->isClassCastable($key)
        ) {
            return $this->getAttributeValue($key);
        }

        return null;
    }

    /**
     * Get all of the current attributes on the model.
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get the casts array.
     *
     * @return array
     */
    public function getCasts(): array
    {
        return $this->casts;
    }

    /**
     * Get the format for database stored dates.
     *
     * @return string
     */
    public function getDateFormat(): string
    {
        return $this->dateFormat ?: DATE_ATOM;
    }

    /**
     * Set a given attribute on the model.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return mixed
     * @throws InvalidCastException
     * @throws JsonEncodingException
     */
    public function setAttribute(string $key, mixed $value): mixed
    {
        // First we will check for the presence of a mutator for the set
        // operation which simply lets the developers tweak the attribute as it
        // is set on the model, such as "json_encoding" an listing of data
        // for storage.
        if ($this->hasSetMutator($key)) {
            return $this->setMutatedAttributeValue($key, $value);
        }

        if ($value && $this->isDateAttribute($key)) {
            $value = $this->fromDateTime($value);
        }

        // If an attribute is listed as a "date", we'll convert it from a
        // DateTime instance into a form proper for storage on the index.
        // We will auto set the values.
        if ($this->isClassCastable($key)) {
            $this->setClassCastableAttribute($key, $value);

            return $this;
        }

        if ( ! is_null($value) && $this->isJsonCastable($key)) {
            $value = $this->castAttributeAsJson($key, $value);
        }

        // If this attribute contains a JSON ->, we'll set the proper value in
        // the attribute's underlying array. This takes care of properly nesting
        // an attribute in the array's value in the case of deeply nested items.
        if (Str::contains($key, '->')) {
            return $this->fillJsonAttribute($key, $value);
        }

        if ( ! is_null($value) && $this->isEncryptedCastable($key)) {
            $value = $this->castAttributeAsEncryptedString($key, $value);
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Set the date format used by the model.
     *
     * @param string $format
     *
     * @return static
     */
    public function setDateFormat(string $format): self
    {
        $this->dateFormat = $format;

        return $this;
    }

    /**
     * Transform a raw model value using mutators, casts, etc.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return mixed
     */
    protected function transformModelValue(string $key, mixed $value): mixed
    {
        // If the attribute has a get mutator, we will call that, then return
        // what it returns as the value, which is useful for transforming values
        // on  retrieval from the model to a form that is more useful for usage.
        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }

        // If the attribute exists within the cast array, we will convert it to
        // an appropriate native PHP type dependent upon the associated value
        // given with the key in the pair. Dayle made this comment line up.
        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $value);
        }

        return $value;
    }

    /**
     * Get current connection name
     *
     * @return string|null
     * @deprecated Use getConnectionName instead. This method will be changed in
     *             the next major version to return the connection instance
     *             instead.
     * @see        Model::getConnectionName()
     */
    #[Deprecated(replacement: '%class%->getConnectionName()')]
    public function getConnection(): string|null
    {
        return $this->getConnectionName();
    }

    /**
     * Get current connection name
     *
     * @return string|null
     */
    public function getConnectionName(): string|null
    {
        return $this->connectionName ?: null;
    }

    /**
     * Set current connection name
     *
     * @param string|null $connectionName
     *
     * @return void
     */
    public function setConnectionName(string|null $connectionName): void
    {
        $this->connectionName = $connectionName;
    }

    /**
     * Get the connection resolver instance.
     *
     * @return ConnectionResolverInterface
     * @internal This method is used by the package during initialization to get
     *           the models to resolve the Elasticsearch connection. You won't
     *           need it during normal operation. It may change at any time.
     */
    public static function getConnectionResolver(): ConnectionResolverInterface
    {
        assert(static::$resolver !== null);

        return static::$resolver;
    }

    /**
     * Retrieves the result highlights.
     *
     * @return array<string, mixed>|null
     * @internal
     */
    public function getHighlight(): array|null
    {
        return $this->getResultMetadataValue('highlight');
    }

    /**
     * Get field highlights
     *
     * @param string|null $field
     *
     * @return mixed
     * @throws InvalidCastException
     */
    public function getHighlights(string|null $field = null): mixed
    {
        $highlights = $this->getAttribute('highlight');

        if ($field && array_key_exists($field, $highlights)) {
            return $highlights[$field];
        }

        return $highlights;
    }

    /**
     * Retrieves the model key
     *
     * @return string|null
     * @throws InvalidCastException
     */
    public function getId(): string|null
    {
        $id = $this->getAttribute(self::FIELD_ID);

        return $id ? (string)$id : null;
    }

    /**
     * Get index name
     *
     * @return string|null
     */
    public function getIndex(): string|null
    {
        return $this->index;
    }

    /**
     * Set index name
     *
     * @param string|null $index
     *
     * @return void
     */
    public function setIndex(string|null $index): void
    {
        $this->index = $index;
    }

    /**
     * Get the value of the model's primary key.
     *
     * @return string|null
     * @throws InvalidCastException
     */
    public function getKey(): string|null
    {
        return $this->getAttribute(self::FIELD_ID);
    }

    /**
     * @inheritDoc
     */
    public function getQueueableConnection(): string|null
    {
        return $this->getConnectionName();
    }

    /**
     * @inheritDoc
     * @return string|null
     * @throws InvalidCastException
     */
    public function getQueueableId(): string|null
    {
        return $this->getKey();
    }

    /**
     * @inheritDoc
     */
    public function getQueueableRelations(): array
    {
        // Elasticsearch does not implement the concept of relations
        return [];
    }

    /**
     * Retrieves result metadata retrieved from the query
     *
     * @return array
     */
    public function getResultMetadata(): array
    {
        return $this->resultMetadata;
    }

    /**
     * Sets the result metadata retrieved from the query. This is mainly useful
     * during model hydration.
     *
     * @param array $resultMetadata
     *
     * @internal
     */
    public function setResultMetadata(array $resultMetadata): void
    {
        $this->resultMetadata = $resultMetadata;
    }

    /**
     * Retrieves result metadata retrieved from the query
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getResultMetadataValue(string $key): mixed
    {
        return array_key_exists($key, $this->resultMetadata)
            ? $this->transformModelValue($key, $this->resultMetadata[$key])
            : null;
    }

    /**
     * @inheritDoc
     * @return float|mixed|string|null
     * @throws InvalidCastException
     */
    public function getRouteKey(): mixed
    {
        return $this->getAttribute($this->getRouteKeyName());
    }

    /**
     * @inheritDoc
     */
    public function getRouteKeyName(): string
    {
        return self::FIELD_ID;
    }

    /**
     * Retrieve the child model for a bound value.
     * Elasticsearch does not support relations, so any resolution request will
     * be proxied to the usual route binding resolution method.
     *
     * @param string      $childType
     * @param mixed       $value
     * @param string|null $field
     *
     * @return Model|null
     * @throws InvalidArgumentException
     * @psalm-suppress ImplementedReturnTypeMismatch
     */
    final public function resolveChildRouteBinding(
        $childType,
        $value,
        $field = null
    ): self|null {
        return $this->resolveRouteBinding($value, $field);
    }

    /**
     * Resolves a route binding to a model instance. Note that the interface
     * specifies Eloquent models in its documentation comment,
     * a rather short-sighted decision.
     * Route bindings using Elasticsearch models should work fine regardless.
     *
     * @param mixed       $value
     * @param string|null $field
     *
     * @return Model|null
     * @throws InvalidArgumentException
     * @psalm-suppress ImplementedReturnTypeMismatch
     */
    public function resolveRouteBinding($value, $field = null): self|null
    {
        return $this
            ->newQuery()
            ->firstWhere(
                $field ?? $this->getRouteKeyName(),
                $value
            );
    }

    /**
     * Retrieves the result score.
     *
     * @return float|null
     * @internal
     */
    public function getScore(): float|null
    {
        return $this->getResultMetadataValue('_score');
    }

    /**
     * Get selectable fields
     *
     * @return array
     */
    public function getSelectable(): array
    {
        return $this->selectable ?: [];
    }

    /**
     * Retrieves the document mapping type.
     *
     * @return string|null
     * @deprecated Mapping types are deprecated as of Elasticsearch 7.0.0
     * @see        https://www.elastic.co/guide/en/elasticsearch/reference/7.10/removal-of-types.html
     */
    #[Deprecated('Mapping types are deprecated as of Elasticsearch 7.0.0')]
    public function getType(): string|null
    {
        return $this->type;
    }

    /**
     * Sets the document mapping type.
     *
     * @param string|null $type
     *
     * @return void
     * @deprecated Mapping types are deprecated as of Elasticsearch 7.0.0
     * @see        https://www.elastic.co/guide/en/elasticsearch/reference/7.10/removal-of-types.html
     */
    #[Deprecated(reason: 'Mapping types are deprecated as of Elasticsearch 7.0.0')]
    public function setType(string|null $type): void
    {
        $this->type = $type;
    }

    /**
     * Get selectable fields
     *
     * @return array
     */
    public function getUnSelectable(): array
    {
        return $this->unselectable ?: [];
    }

    /**
     * Set current connection name
     *
     * @param string $connectionName
     *
     * @return void
     * @deprecated Use setConnectionName instead. This method will be removed in
     *             the next major version.
     * @see        Model::setConnectionName()
     */
    #[Deprecated(replacement: '%class%->setConnectionName(%parameter0%)')]
    public function setConnection(string $connectionName): void
    {
        $this->setConnectionName($connectionName);
    }

    /**
     * Set the connection resolver instance.
     *
     * @param ConnectionResolverInterface $resolver
     *
     * @return void
     * @internal This method is used by the package during initialization to get
     *           the models to resolve the Elasticsearch connection. You won't
     *           need it during normal operation. It may change at any time.
     */
    public static function setConnectionResolver(ConnectionResolverInterface $resolver): void
    {
        static::$resolver = $resolver;
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters)
    {
        return (new static())->$method(...$parameters);
    }

    /**
     * Retrieves all model documents.
     *
     * @param string|null $scrollId
     *
     * @return Collection
     */
    public static function all(string|null $scrollId = null): Collection
    {
        return static::query()->get($scrollId);
    }

    /**
     * Clear the list of booted models so they will be re-booted.
     *
     * @return void
     */
    public static function clearBootedModels(): void
    {
        static::$booted = [];
        static::$globalScopes = [];
    }

    /**
     * Save a new model and return the instance.
     *
     * @param array       $attributes
     * @param string|null $id
     *
     * @return static
     * @throws InvalidCastException
     * @throws JsonEncodingException
     * @psalm-suppress LessSpecificReturnStatement
     * @noinspection   PhpUnhandledExceptionInspection
     */
    public static function create(array $attributes, string|null $id = null): self
    {
        $metadata = [];
        if ( ! is_null($id)) {
            $metadata['_id'] = $id;
        }

        return tap(
            (new static())->newInstance($attributes, $metadata),
            static fn(self $instance) => $instance->save()
        );
    }

    /**
     * Destroy the models for the given IDs.
     *
     * @param array|int|string|BaseCollection $ids
     *
     * @return int
     */
    public static function destroy(array|BaseCollection|int|string $ids): int
    {
        if ($ids instanceof BaseCollection) {
            $ids = $ids->all();
        }

        $ids = is_array($ids) ? $ids : func_get_args();

        if (count($ids) === 0) {
            return 0;
        }

        // We will actually pull the models from the index and call delete on
        // each of them individually so that their events get fired properly
        // with a correct set of attributes in case the developers wants to
        // check these.
        $count = 0;
        $query = (new static())
            ->newQuery()
            ->whereIn(self::FIELD_ID, $ids)
            ->get();

        foreach ($query as $model) {
            if ($model->delete()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Retrieves a model by key.
     *
     * @param string $key
     *
     * @return static|null
     */
    public static function find(string $key): static|null
    {
        return static::query()->id($key)->first();
    }

    /**
     * Retrieves a model by key or fails.
     *
     * @param string $key
     *
     * @return static
     * @throws DocumentNotFoundException
     * @psalm-suppress MismatchingDocblockReturnType
     */
    public static function findOrFail(string $key): self
    {
        $result = static::find($key);

        if (is_null($result)) {
            throw (new DocumentNotFoundException())->setModel(
                static::class,
                $key
            );
        }

        return $result;
    }

    /**
     * Begin querying the model.
     *
     * @return Query<static>
     */
    public static function query(): Query
    {
        return (new static())->newQuery();
    }

    /**
     * Resolve a connection instance.
     *
     * @param string|null $connection
     *
     * @return Connection
     * @internal This method is used by the package during initialization to get
     *           the models to resolve the Elasticsearch connection. You won't
     *           need it during normal operation. It may change at any time.
     */
    public static function resolveConnection(
        string|null $connection = null
    ): Connection {
        assert(static::$resolver !== null);

        return static::$resolver->connection($connection);
    }

    /**
     * Unset the connection resolver for models.
     *
     * @return void
     * @internal This method is used by the package during initialization to get
     *           the models to resolve the Elasticsearch connection. You won't
     *           need it during normal operation. It may change at any time.
     */
    public static function unsetConnectionResolver(): void
    {
        static::$resolver = null;
    }

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot(): void
    {
        static::bootTraits();
    }

    /**
     * Boot all bootable traits on the model.
     *
     * @return void
     */
    protected static function bootTraits(): void
    {
        $class = static::class;
        $booted = [];

        static::$traitInitializers[$class] = [];

        foreach (class_uses_recursive($class) as $trait) {
            $method = 'boot' . class_basename($trait);

            if (
                method_exists($class, $method) &&
                ! in_array($method, $booted, true)
            ) {
                forward_static_call([$class, $method]);

                $booted[] = $method;
            }

            if (method_exists(
                $class,
                $method = 'initialize' . class_basename($trait)
            )) {
                /** @noinspection UnsupportedStringOffsetOperationsInspection */
                static::$traitInitializers[$class][] = $method;

                static::$traitInitializers[$class] = array_unique(
                    static::$traitInitializers[$class]
                );
            }
        }
    }

    /**
     * Perform any actions required after the model boots.
     *
     * @return void
     */
    protected static function booted(): void
    {
        //
    }

    /**
     * Perform any actions required before the model boots.
     *
     * @return void
     */
    protected static function booting(): void
    {
        //
    }

    /**
     * Handle dynamic method calls into the model.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     * @throws BadMethodCallException
     */
    public function __call(string $method, array $parameters)
    {
        return $this->forwardCallTo(
            $this->newQuery(),
            $method,
            $parameters
        );
    }

    /**
     * Magic getter for model properties
     *
     * @param string $name
     *
     * @return mixed|null
     * @throws InvalidCastException
     */
    public function __get(string $name)
    {
        return $this->getAttribute($name);
    }

    /**
     * Handle model properties setter
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return void
     * @throws InvalidCastException
     * @throws JsonEncodingException
     */
    public function __set(string $name, mixed $value): void
    {
        $this->setAttribute($name, $value);
    }

    /**
     * Determine if an attribute exists on the model.
     *
     * @param string $key
     *
     * @return bool
     * @throws InvalidCastException
     */
    public function __isset(string $key): bool
    {
        if ($key === self::FIELD_ID) {
            return isset($this->_id);
        }

        return $this->offsetExists($key);
    }

    /**
     * Unset an attribute on the model.
     *
     * @param string $key
     *
     * @return void
     */
    public function __unset(string $key)
    {
        $this->offsetUnset($key);
    }

    /**
     * Apply the given named scope if possible.
     *
     * @param string $scope
     * @param array  $parameters
     *
     * @return mixed
     */
    public function callNamedScope(string $scope, array $parameters = []): mixed
    {
        return $this->{'scope' . ucfirst($scope)}(...$parameters);
    }

    /**
     * Delete model record
     *
     * @return void
     * @throws InvalidCastException
     */
    public function delete(): void
    {
        $this->mergeAttributesFromClassCasts();

        // If the model doesn't exist, there is nothing to delete so we'll just
        // return immediately and not do anything else. Otherwise, we will
        // continue with a deletion process on the model, firing the proper
        // events, and so forth.
        if ( ! $this->exists) {
            return;
        }

        if ($this->fireModelEvent('deleting') === false) {
            return;
        }

        $this->performDeleteOnModel();

        // Once the model has been deleted, we will fire off the deleted event
        // so that the developers may hook into post-delete operations.
        $this->fireModelEvent('deleted', false);
    }

    /**
     * Check model is exists
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param array<string, mixed> $attributes
     *
     * @return static
     *
     * @throws InvalidCastException
     * @throws JsonEncodingException
     * @throws MassAssignmentException
     */
    public function fill(array $attributes): self
    {
        $totallyGuarded = $this->totallyGuarded();

        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            // The developers may choose to place some attributes in the "fillable" array
            // which means only those attributes may be set through mass assignment to
            // the model, and all others will just get ignored for security reasons.
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            } elseif ($totallyGuarded) {
                throw new MassAssignmentException(sprintf(
                    'Add [%s] to fillable property to allow mass assignment on [%s].',
                    $key, get_class($this)
                ));
            }
        }

        return $this;
    }

    /**
     * Fill the model with an array of attributes. Force mass assignment.
     *
     * @param array $attributes
     *
     * @return static
     * @throws InvalidCastException
     * @throws JsonEncodingException
     * @throws MassAssignmentException
     */
    public function forceFill(array $attributes): self
    {
        return static::unguarded(function () use ($attributes) {
            return $this->fill($attributes);
        });
    }

    /**
     * Determine if the model has a given scope.
     *
     * @param string $scope
     *
     * @return bool
     */
    public function hasNamedScope(string $scope): bool
    {
        return method_exists(
            $this,
            'scope' . ucfirst($scope)
        );
    }

    /**
     * Determine if two models have the same ID and belong to the same table.
     *
     * @param static|null $model
     *
     * @return bool
     * @throws InvalidCastException
     * @noinspection PhpDeprecationInspection
     */
    public function is(self|null $model): bool
    {
        return ! is_null($model) &&
               $this->getId() === $model->getId() &&
               $this->getType() === $model->getType() &&
               $this->getIndex() === $model->getIndex() &&
               $this->getConnectionName() === $model->getConnectionName();
    }

    /**
     * Determine if two models are not the same.
     *
     * @param static|null $model
     *
     * @return bool
     * @throws InvalidCastException
     */
    public function isNot(self|null $model): bool
    {
        return ! $this->is($model);
    }

    /**
     * @inheritDoc
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Creates a new collection instance.
     *
     * @param static[] $models
     *
     * @return Collection
     */
    public function newCollection(array $models = []): Collection
    {
        return new Collection($models);
    }

    /**
     * Create a new instance of the given model.
     * This method just provides a convenient way for us to generate fresh
     * model instances of this current model. It is particularly useful during
     * the hydration of new objects via the Query instance.
     *
     * @param array       $attributes Model attributes
     * @param array       $metadata   Query result metadata
     * @param bool        $exists     Whether the document exists
     * @param string|null $index      Name of the index the document lives in
     * @param string|null $type       (Deprecated) Mapping type of the document
     *
     * @return static
     * @noinspection PhpDeprecationInspection
     */
    public function newInstance(
        array $attributes = [],
        array $metadata = [],
        bool $exists = false,
        string|null $index = null,
        string|null $type = null
    ): self {
        $model = new static([], $exists);

        $model->setRawAttributes($attributes, true);
        $model->setConnectionName($this->getConnectionName());
        $model->setResultMetadata($metadata);
        $model->setIndex($index ?? $this->getIndex());
        $model->setType($type ?? $this->getType());
        $model->mergeCasts($this->casts);

        $model->fireModelEvent('retrieved', false);

        return $model;
    }

    /**
     * Get a new query builder scoped to the current model.
     *
     * @return Query<static>
     * @noinspection PhpDeprecationInspection
     */
    public function newQuery(): Query
    {
        $query = $this->registerGlobalScopes($this->newQueryBuilder());
        $query = $query->setModel($this);

        if ($index = $this->getIndex()) {
            $query->index($index);
        }

        if ($type = $this->getType()) {
            $query->type($type);
        }

        if ($fields = $this->getSelectable()) {
            $query->select($fields);
        }

        if ($fields = $this->getUnSelectable()) {
            $query->unselect($fields);
        }

        return $query;
    }

    /**
     * Determine if the given attribute exists.
     *
     * @param mixed $offset
     *
     * @return bool
     * @throws InvalidCastException
     */
    public function offsetExists(mixed $offset): bool
    {
        return ! is_null($this->getAttribute($offset));
    }

    /**
     * Get the value for a given offset.
     *
     * @param mixed $offset
     *
     * @return mixed
     * @throws InvalidCastException
     */
    #[ReturnTypeWillChange]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->getAttribute($offset);
    }

    /**
     * Set the value for a given offset.
     *
     * @param mixed $offset
     * @param mixed $value
     *
     * @return void
     * @throws InvalidCastException
     * @throws JsonEncodingException
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Unset the value for a given offset.
     *
     * @param mixed $offset
     *
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Register the global scopes for this builder instance.
     *
     * @template TModel of Model
     *
     * @param Query<TModel> $query
     *
     * @return Query<TModel>
     */
    public function registerGlobalScopes(Query $query): Query
    {
        foreach ($this->getGlobalScopes() as $identifier => $scope) {
            $query->withGlobalScope($identifier, $scope);
        }

        return $query;
    }

    /**
     * Clone the model into a new, non-existing instance.
     *
     * @param array|null $except
     *
     * @return static
     */
    public function replicate(array $except = null): self
    {
        $defaults = [
            self::FIELD_ID,
        ];

        $attributes = Arr::except($this->getAttributes(), $except
            ? array_unique(array_merge($except, $defaults))
            : $defaults
        );

        return tap(new static(), static function (
            self $instance
        ) use ($attributes) {
            $instance->setRawAttributes($attributes);
            $instance->fireModelEvent('replicating', false);
        });
    }

    /**
     * Save the model to the index.
     *
     * @return static
     * @throws InvalidCastException
     * @throws JsonEncodingException
     */
    public function save(): self
    {
        $this->mergeAttributesFromClassCasts();

        $query = $this->newQuery();

        // If the "saving" event returns false we'll bail out of the save and
        // return false, indicating that the save failed. This provides a chance
        // for any listeners to cancel save operations if validations fail
        // or whatever.
        if ($this->fireModelEvent('saving') === false) {
            return $this;
        }

        // If the model already exists in the index we can just update our
        // record that is already in this index using the current ID to only
        // update this model. Otherwise, we'll just insert it.
        if ($this->exists) {
            $saved = ! $this->isDirty() || $this->performUpdate($query);
        }

        // If the model is brand new, we'll insert it into our index and set the
        // ID attribute on the model to the value of the newly inserted ID.
        else {
            $saved = $this->performInsert($query);
        }

        // If the model is successfully saved, we need to do a few more things
        // once that is done. We will call the "saved" method here to run any
        // actions we need to happen after a model gets successfully saved
        // right here.
        if ($saved) {
            $this->finishSave();
        }

        return $this;
    }

    /**
     * Save the model to the index without raising any events.
     *
     * @return static
     * @throws InvalidCastException
     * @throws JsonEncodingException
     */
    public function saveQuietly(): self
    {
        return static::withoutEvents(function () {
            return $this->save();
        });
    }

    /**
     * Get model as array
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->attributesToArray();
    }

    /**
     * Convert the model to a JSON string.
     *
     * @param int $options
     *
     * @return string
     * @throws JsonException
     */
    public function toJson($options = 0): string
    {
        return json_encode(
            $this->jsonSerialize(),
            JSON_THROW_ON_ERROR | $options
        );
    }

    /**
     * Determine if the model uses timestamps.
     *
     * @return bool
     */
    final public function usesTimestamps(): bool
    {
        return false;
    }

    /**
     * Check if the model needs to be booted and if so, do it.
     *
     * @return void
     */
    protected function bootIfNotBooted(): void
    {
        if ( ! isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;

            $this->fireModelEvent('booting', false);

            static::booting();
            static::boot();
            static::booted();

            $this->fireModelEvent('booted', false);
        }
    }

    /**
     * Perform any actions that are necessary after the model is saved.
     *
     * @return void
     */
    protected function finishSave(): void
    {
        $this->fireModelEvent('saved', false);

        $this->syncOriginal();
    }

    /**
     * Get the primary key value for a save query.
     *
     * @return string|null
     * @throws InvalidCastException
     */
    protected function getKeyForSaveQuery(): string|null
    {
        return $this->original[self::FIELD_ID] ?? $this->getKey();
    }

    /**
     * Initialize any initializable traits on the model.
     *
     * @return void
     */
    protected function initializeTraits(): void
    {
        foreach (static::$traitInitializers[static::class] as $method) {
            $this->{$method}();
        }
    }

    /**
     * Insert the given attributes and set the ID on the model.
     *
     * @param Query<static> $query
     * @param array         $attributes
     *
     * @return void
     * @throws InvalidCastException
     * @throws JsonEncodingException
     */
    protected function insertAndSetId(Query $query, array $attributes): void
    {
        $result = $query->insert($attributes);

        if (isset($result->_index)) {
            $this->setIndex($result->_index);
        }

        if (isset($result->_type)) {
            $this->setAttribute('_type', $result->_type);
        }

        $this->setAttribute(self::FIELD_ID, $result->_id);
    }

    /**
     * Get a new query builder instance for the connection.
     *
     * @return Query<self>
     */
    protected function newQueryBuilder(): Query
    {
        return static
            ::resolveConnection($this->getConnectionName())
            ->newQuery();
    }

    /**
     * Perform the actual delete query on this model instance.
     *
     * @return void
     * @throws InvalidCastException
     */
    protected function performDeleteOnModel(): void
    {
        $this->setKeysForSaveQuery($this->newQuery())->delete();

        $this->exists = false;
    }

    /**
     * Perform a model insert operation.
     *
     * @param Query<static> $query
     *
     * @return bool
     * @throws InvalidCastException
     * @throws JsonEncodingException
     */
    protected function performInsert(Query $query): bool
    {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        $attributes = $this->getAttributes();

        if ($id = $this->getKey()) {
            if (empty($attributes)) {
                return true;
            }

            $result = $query->insert($attributes, $id);
            $this->setAttribute('_type', $result->_type ?? null);
        } else {
            $this->insertAndSetId($query, $attributes);
        }

        // We will go ahead and set the exists property to true, so that it is
        // set when the created event is fired, just in case the developer tries
        // to update it  during the event. This will allow them to do so and run
        // an update here.
        $this->exists = true;

        $this->wasRecentlyCreated = true;

        $this->fireModelEvent('created', false);

        return true;
    }

    /**
     * Perform a model update operation.
     *
     * @param Query<static> $query
     *
     * @return bool
     * @throws InvalidCastException
     */
    protected function performUpdate(Query $query): bool
    {
        // If the updating event returns false, we will cancel the update
        // operation so developers can hook Validation systems into their models
        // and cancel this  operation if the model does not pass validation.
        // Otherwise, we update.
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        // Once we have run the update operation, we will fire the "updated"
        // event for this model instance. This will allow developers to hook
        // into these after models are updated, giving them a chance to do any
        // special processing.
        $dirty = $this->getDirty();

        if (count($dirty) === 0) {
            return true;
        }

        $this->setKeysForSaveQuery($query)
             ->update($dirty);

        $this->syncChanges();

        $this->fireModelEvent('updated', false);

        return true;
    }

    /**
     * Set attributes casting
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return mixed
     * @deprecated This method will be removed in the next major version.
     */
    #[Deprecated('This method will be removed in the next major version.')]
    protected function setAttributeType(string $name, mixed $value): mixed
    {
        $castTypes = [
            'boolean',
            'bool',
            'integer',
            'int',
            'float',
            'double',
            'string',
            'array',
            'object',
            'null',
        ];

        if (
            array_key_exists($name, $this->casts) &&
            in_array(
                $this->casts[$name],
                $castTypes,
                true
            )
        ) {
            settype($value, $this->casts[$name]);
        }

        return $value;
    }

    /**
     * Set the keys for a save update query.
     *
     * @template TModel of Model
     *
     * @param Query<TModel> $query
     *
     * @return Query<TModel>
     * @throws InvalidCastException
     */
    protected function setKeysForSaveQuery(Query $query): Query
    {
        $query->id($this->getKeyForSaveQuery());

        return $query;
    }
}
