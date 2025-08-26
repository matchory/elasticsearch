<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch;

use ArrayAccess;
use BadMethodCallException;
use Closure;
use Illuminate\Contracts\{Events\Dispatcher,
    Queue\QueueableEntity,
    Routing\UrlRoutable,
    Support\Arrayable,
    Support\Jsonable};
use Illuminate\Database\Eloquent\{Concerns\GuardsAttributes,
    Concerns\HasAttributes,
    Concerns\HasEvents,
    Concerns\HidesAttributes,
    MassAssignmentException};
use Illuminate\Support\{Arr, Collection as BaseCollection, Traits\ForwardsCalls};
use InvalidArgumentException;
use JetBrains\PhpStorm\Deprecated;
use JsonException;
use JsonSerializable;
use Matchory\Elasticsearch\Concerns\HasGlobalScopes;
use Matchory\Elasticsearch\Exceptions\DocumentNotFoundException;
use Matchory\Elasticsearch\Interfaces\{ConnectionInterface as Connection, ConnectionResolverInterface};
use ReturnTypeWillChange;

use function array_key_exists;
use function array_merge;
use function array_unique;
use function assert;
use function class_basename;
use function class_uses_recursive;
use function count;
use function func_get_args;
use function get_class;
use function in_array;
use function is_array;
use function is_null;
use function json_encode;
use function method_exists;
use function sprintf;
use function tap;
use function trigger_error;
use function ucfirst;

use const DATE_ATOM;
use const E_USER_DEPRECATED;

/**
 * Elasticsearch data model
 *
 * @property-read string|null $_id
 * @property-read string|null $_index
 * @property-read string|null $_type
 * @property-read float|null  $_score
 * @property-read array|null  $highlight
 *
 * @package Matchory\Elasticsearch
 */
class Model implements
    Arrayable,
    ArrayAccess,
    Jsonable,
    JsonSerializable,
    QueueableEntity,
    UrlRoutable
{
    use ForwardsCalls;
    use HasAttributes {
        HasAttributes::getAttribute as getModelAttribute;
    }
    use HidesAttributes;
    use HasEvents;
    use HasGlobalScopes;
    use GuardsAttributes;

    protected const FIELD_ID = '_id';

    /**
     * The event dispatcher instance.
     *
     * @var Dispatcher
     */
    protected static Dispatcher $dispatcher;

    /**
     * The array of booted models.
     *
     * @var array
     */
    protected static array $booted = [];

    /**
     * The callbacks that should be executed after the model has booted.
     *
     * @var array
     */
    protected static array $bootedCallbacks = [];

    /**
     * The array of trait initializers that will be called on each new instance.
     *
     * @var array
     */
    protected static array $traitInitializers = [];

    /**
     * The connection resolver instance.
     *
     * @var ConnectionResolverInterface|null
     */
    protected static ConnectionResolverInterface|null $resolver;

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
        bool $exists = false,
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
     * Check if the model needs to be booted and if so, do it.
     *
     * @return void
     */
    protected function bootIfNotBooted(): void
    {
        if (!isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;

            $this->fireModelEvent('booting', false);

            static::booting();
            static::boot();
            static::booted();

            $this->fireModelEvent('booted', false);
        }
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
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot(): void
    {
        static::bootTraits();
    }

    /**
     * Boot all the bootable traits on the model.
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

            if (method_exists($class, $method) && !in_array($method, $booted, true)) {
                $class::$method();

                $booted[] = $method;
            }

            if (method_exists($class, $method = 'initialize' . class_basename($trait))) {
                static::$traitInitializers[$class][] = $method;

                static::$traitInitializers[$class] = array_unique(
                    static::$traitInitializers[$class],
                );
            }
        }
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
     * Perform any actions required after the model boots.
     *
     * @return void
     */
    protected static function booted()
    {
        //
    }

    /**
     * Register a closure to be executed after the model has booted.
     *
     * @param Closure $callback
     */
    protected static function whenBooted(Closure $callback): void
    {
        static::$bootedCallbacks[static::class] ??= [];
        static::$bootedCallbacks[static::class][] = $callback;
    }

    /**
     * Clear the list of booted models so they will be re-booted.
     */
    public static function clearBootedModels(): void
    {
        static::$booted = [];
        static::$bootedCallbacks = [];
        static::$globalScopes = [];
    }

    /**
     * Fill the model with an array of attributes. Force mass assignment.
     *
     * @param array<string, mixed> $attributes
     *
     * @return $this
     * @throws MassAssignmentException
     * @noinspection PhpIncompatibleReturnTypeInspection
     */
    public function forceFill(array $attributes): static
    {
        return static::unguarded(fn() => $this->fill($attributes));
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param array<string, mixed> $attributes
     *
     * @return $this
     *
     * @throws MassAssignmentException
     */
    public function fill(array $attributes): static
    {
        $totallyGuarded = $this->totallyGuarded();

        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            // The developers may choose to place some attributes in the "fillable" array
            // which means only those attributes may be set through mass assignment to
            // the model, and all others will just get ignored for security reasons.
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            } elseif ($totallyGuarded) {
                throw new MassAssignmentException(
                    sprintf(
                        'Add [%s] to fillable property to allow mass assignment on [%s].',
                        $key,
                        get_class($this),
                    ),
                );
            }
        }

        return $this;
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
     * Save a new model and return the instance.
     *
     * @param array       $attributes
     * @param string|null $id
     *
     * @return $this
     * @psalm-suppress LessSpecificReturnStatement
     * @noinspection   PhpUnhandledExceptionInspection
     */
    public static function create(array $attributes, string|null $id = null): static
    {
        $metadata = [];
        if (!is_null($id)) {
            $metadata['_id'] = $id;
        }

        return tap(
            (new static())->newInstance($attributes, $metadata),
            static fn(self $instance) => $instance->save(),
        );
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
     *
     * @return $this
     */
    public function newInstance(
        array $attributes = [],
        array $metadata = [],
        bool $exists = false,
        string|null $index = null,
    ): static {
        $model = new static([], $exists);

        $model->setRawAttributes($attributes, true);
        $model->setConnectionName($this->getConnectionName());
        $model->setResultMetadata($metadata);
        $model->setIndex($index ?? $this->getIndex());
        $model->mergeCasts($this->casts);

        $model->fireModelEvent('retrieved', false);

        return $model;
    }

    /**
     * Save the model to the index.
     *
     * @return $this
     */
    public function save(): static
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
            $saved = !$this->isDirty() || $this->performUpdate($query);
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
     * Perform a model update operation.
     *
     * @param Query<static> $query
     *
     * @return bool
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

        $this
            ->setKeysForSaveQuery($query)
            ->update($dirty);

        $this->syncChanges();

        $this->fireModelEvent('updated', false);

        return true;
    }

    /**
     * Set the keys for a save update query.
     *
     * @template TModel of Model
     *
     * @param Query<TModel> $query
     *
     * @return Query<TModel>
     */
    protected function setKeysForSaveQuery(Query $query): Query
    {
        $query->id($this->getKeyForSaveQuery());

        return $query;
    }

    /**
     * Get the primary key value for a save query.
     *
     * @return string|null
     */
    protected function getKeyForSaveQuery(): string|null
    {
        return $this->original[self::FIELD_ID] ?? $this->getKey();
    }

    /**
     * Perform a model insert operation.
     *
     * @param Query<static> $query
     *
     * @return bool
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
     * Insert the given attributes and set the ID on the model.
     *
     * @param Query<static> $query
     * @param array         $attributes
     *
     * @return void
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
     * Begin querying the model.
     *
     * @return Query<static>
     */
    public static function query(): Query
    {
        return (new static())->newQuery();
    }

    /**
     * Delete model record
     *
     * @return bool
     */
    public function delete(): bool
    {
        $this->mergeAttributesFromClassCasts();

        // If the model doesn't exist, there is nothing to delete so we'll just
        // return immediately and not do anything else. Otherwise, we will
        // continue with a deletion process on the model, firing the proper
        // events, and so forth.
        if (!$this->exists) {
            return false;
        }

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        $this->performDeleteOnModel();

        // Once the model has been deleted, we will fire off the deleted event
        // so that the developers may hook into post-delete operations.
        $this->fireModelEvent('deleted', false);

        return true;
    }

    /**
     * Perform the actual delete query on this model instance.
     *
     * @return void
     */
    protected function performDeleteOnModel(): void
    {
        $this->setKeysForSaveQuery($this->newQuery())->delete();

        $this->exists = false;
    }

    /**
     * Retrieves a model by key or fails.
     *
     * @param string $key
     *
     * @return $this
     * @throws DocumentNotFoundException
     * @psalm-suppress MismatchingDocblockReturnType
     */
    public static function findOrFail(string $key): static
    {
        $result = static::find($key);

        if (is_null($result)) {
            throw (new DocumentNotFoundException())->setModel(
                static::class,
                $key,
            );
        }

        return $result;
    }

    /**
     * Retrieves a model by key.
     *
     * @param string $key
     *
     * @return $this|null
     */
    public static function find(string $key): static|null
    {
        return static::query()->id($key)->first();
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
        @trigger_error(
            sprintf(
                'Since matchory/elasticsearch 3.0.0: The %s method is deprecated. ' .
                'Use the connection manager to create connections instead. It provides a simpler ' .
                'way to manage connections. This method will be removed in the next major version.',
                __METHOD__,
            ),
            E_USER_DEPRECATED,
        );

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
     * Get field highlights
     *
     * @param string|null $field
     *
     * @return mixed
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
     * Get an attribute from the model.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getAttribute(string $key): mixed
    {
        if (!$key) {
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

        if ($key === '_score') {
            return $this->getScore();
        }

        return $this->getModelAttribute($key);
    }

    public function isRelation($key): false
    {
        return false;
    }

    public function relationLoaded($key): false
    {
        return false;
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
     * @inheritDoc
     */
    public function getQueueableConnection(): string|null
    {
        return $this->getConnectionName();
    }

    /**
     * @inheritDoc
     * @return string|null
     */
    public function getQueueableId(): string|null
    {
        return $this->getKey();
    }

    /**
     * Get the value of the model's primary key.
     *
     * @return string|null
     */
    public function getKey(): string|null
    {
        return $this->getAttribute(self::FIELD_ID);
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
     * @inheritDoc
     * @return float|mixed|string|null
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
     * @return $this|null
     * @throws InvalidArgumentException
     * @psalm-suppress ImplementedReturnTypeMismatch
     */
    final public function resolveChildRouteBinding(
        $childType,
        $value,
        $field = null,
    ): static|null {
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
     * @return $this|null
     * @throws InvalidArgumentException
     * @psalm-suppress ImplementedReturnTypeMismatch
     */
    public function resolveRouteBinding($value, $field = null): static|null
    {
        return $this
            ->newQuery()
            ->firstWhere(
                $field ?? $this->getRouteKeyName(),
                $value,
            );
    }

    /**
     * Get a new query builder scoped to the current model.
     *
     * @return Query<static>
     */
    public function newQuery(): Query
    {
        $query = $this->registerGlobalScopes($this->newQueryBuilder());
        $query = $query->setModel($this);

        if ($index = $this->getIndex()) {
            $query->index($index);
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
     * Get a new query builder instance for the connection.
     *
     * @return Query<self>
     */
    protected function newQueryBuilder(): Query
    {
        return static::resolveConnection($this->getConnectionName())
            ->newQuery();
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
        string|null $connection = null,
    ): Connection {
        assert(static::$resolver !== null);

        return static::$resolver->connection($connection);
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
            $parameters,
        );
    }

    /**
     * Magic getter for model properties
     *
     * @param string $name
     *
     * @return mixed|null
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
     */
    public function __isset(string $key): bool
    {
        if ($key === self::FIELD_ID) {
            return isset($this->_id);
        }

        return $this->offsetExists($key);
    }

    /**
     * Determine if the given attribute exists.
     *
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return !is_null($this->getAttribute($offset));
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
     * Check if the model exists.
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->exists;
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
            'scope' . ucfirst($scope),
        );
    }

    /**
     * Determine if two models are not the same.
     *
     * @param static|null $model
     *
     * @return bool
     */
    public function isNot(self|null $model): bool
    {
        return !$this->is($model);
    }

    /**
     * Determine if two models have the same ID and belong to the same table.
     *
     * @param static|null $model
     *
     * @return bool
     */
    public function is(self|null $model): bool
    {
        return !is_null($model) &&
            $this->getId() === $model->getId() &&
            $this->getIndex() === $model->getIndex() &&
            $this->getConnectionName() === $model->getConnectionName();
    }

    /**
     * Retrieves the model key
     *
     * @return string|null
     */
    public function getId(): string|null
    {
        $id = $this->getAttribute(self::FIELD_ID);

        return $id ? (string)$id : null;
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
     * Get the value for a given offset.
     *
     * @param mixed $offset
     *
     * @return mixed
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
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Clone the model into a new, non-existing instance.
     *
     * @param array|null $except
     *
     * @return $this
     */
    public function replicate(array|null $except = null): static
    {
        $defaults = [
            self::FIELD_ID,
        ];

        $attributes = Arr::except(
            $this->getAttributes(),
            $except
                ? array_unique(array_merge($except, $defaults))
                : $defaults,
        );

        return tap(new static(), static function (
            self $instance,
        ) use ($attributes) {
            $instance->setRawAttributes($attributes);
            $instance->fireModelEvent('replicating', false);
        });
    }

    /**
     * Save the model to the index without raising any events.
     *
     * @return $this
     */
    public function saveQuietly(): static
    {
        return static::withoutEvents(function () {
            return $this->save();
        });
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
            JSON_THROW_ON_ERROR | $options,
        );
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
     * Get model as array
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->attributesToArray();
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
}
