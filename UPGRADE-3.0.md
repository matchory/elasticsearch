# Upgrade Guide: v2.x to v3.0

This guide covers the breaking changes and migration steps required when upgrading from matchory/elasticsearch v2.x to v3.0.

## Breaking Changes

### 1. Query Class Renamed to Builder

The `Query` class has been renamed to `Builder` for consistency with Laravel Eloquent naming conventions.

**Before (v2.x):**
```php
use Matchory\Elasticsearch\Query;

$query = new Query($connection);
$query->where('status', 'active');

// Type hints
public function scopeActive(Query $query): Query
{
    return $query->where('active', true);
}
```

**After (v3.0):**
```php
use Matchory\Elasticsearch\Builder;

$builder = new Builder($connection);
$builder->where('status', 'active');

// Type hints
public function scopeActive(Builder $builder): Builder
{
    return $builder->where('active', true);
}
```

### 2. Removed Deprecated Static Methods from Connection

The following deprecated static methods have been removed from `Connection`:

- `Connection::setConnectionResolver()` - Use dependency injection instead
- `Connection::configureLogging()` - Configure logging via config file
- `Connection::create()` - Use `ConnectionManager` to get connections
- `Connection::connection()` - Use `ConnectionManager::connection()` instead
- `Connection::isLoaded()` - No longer needed

**Before (v2.x):**
```php
use Matchory\Elasticsearch\Connection;

$connection = Connection::create(['hosts' => ['localhost:9200']]);
```

**After (v3.0):**
```php
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;

// Via dependency injection
public function __construct(ConnectionResolverInterface $resolver)
{
    $connection = $resolver->connection();
}

// Or via facade
$connection = Elasticsearch::connection();
```

### 3. Request Class Removed

The deprecated `Request` class has been removed entirely. Use the `Builder` class for all query operations.

### 4. Bulk Class Relocated

The `Bulk` class has been moved from `Matchory\Elasticsearch\Classes\Bulk` to `Matchory\Elasticsearch\Bulk`.

**Before (v2.x):**
```php
use Matchory\Elasticsearch\Classes\Bulk;
```

**After (v3.0):**
```php
use Matchory\Elasticsearch\Bulk;
```

### 5. Config File Renamed

The deprecated `es.php` config file is no longer supported. Rename your config file to `elasticsearch.php`.

**Before (v2.x):**
```
config/es.php
```

**After (v3.0):**
```
config/elasticsearch.php
```

### 6. Deprecated Container Alias Removed

The `es` container alias has been removed. Use `elasticsearch` instead.

**Before (v2.x):**
```php
$connection = app('es');
```

**After (v3.0):**
```php
$connection = app('elasticsearch');
// Or better, use the interface
$resolver = app(ConnectionResolverInterface::class);
$connection = $resolver->connection();
```

### 7. Model Connection Methods Renamed

The `getConnection()` and `setConnection()` methods on Model have been removed. Use the renamed methods instead:

**Before (v2.x):**
```php
$model->setConnection('analytics');
$connectionName = $model->getConnection();
```

**After (v3.0):**
```php
$model->setConnectionName('analytics');
$connectionName = $model->getConnectionName();
```

### 8. Index API Redesigned (Fluent Builder)

The Index class callback constructor parameter has been removed. Use the fluent builder pattern instead:

**Before (v2.x):**
```php
ES::createIndex('posts', function ($index) {
    $index->shards(3)->replicas(1)->mapping([...]);
});
```

**After (v3.0):**
```php
ES::newIndex('posts')
    ->shards(3)
    ->replicas(1)
    ->mapping([...])
    ->create();
```

The following properties in `Index` are now private (use getter methods):

- `$ignores` - Use `getIgnores()` or `ignores()` to modify
- `$mappings` - Use appropriate methods
- `$name` - Use `getName()`
- `$settings` - Use appropriate methods
- `$aliases` - Use appropriate methods

### 9. ScoutEngine Uses ConnectionManager

The Scout engine now uses `ConnectionManager` for client creation, which means:
- Authentication settings from your config are now respected
- SSL/TLS settings are properly applied
- Logging configuration works correctly

Configure your Scout connection in `config/scout.php`:
```php
'elasticsearch' => [
    'connection' => 'default', // Name of your Elasticsearch connection
    'index' => 'scout',
],
```

## New Features in v3.0

### Connection Health Check

Check if your Elasticsearch connection is healthy:

```php
if ($connection->ping()) {
    // Connection is healthy
}
```

### Retry Logic for Transient Failures

Configure automatic retry with exponential backoff:

```php
$results = Model::query()
    ->retry(attempts: 3, delay: 100)
    ->where('status', 'active')
    ->get();
```

### Bulk Operation Batching

Batch large bulk operations to avoid memory issues:

```php
// Automatically chunks into batches of 500
$builder->bulk($documents, batchSize: 500);
```

### Query Profiling

Enable Elasticsearch query profiling for debugging:

```php
$results = Model::query()
    ->profile()
    ->where('status', 'active')
    ->get();
```

### Improved Scout Search Safety

Scout now uses `simple_query_string` instead of `query_string` for safer handling of user input without throwing exceptions on special characters.

### Better Cache Key Security

Cache keys now include the application key for improved security and unpredictability.

### OR Conditions with orWhere()

You can now use `orWhere()` to add OR conditions to your queries:

```php
// Find documents where status is "published" OR "featured"
Model::query()
    ->orWhere('status', 'published')
    ->orWhere('status', 'featured')
    ->get();

// Combine AND and OR conditions
Model::query()
    ->where('category', 'tech')
    ->orWhere('status', 'published')
    ->orWhere('status', 'featured')
    ->get();

// Control how many OR conditions must match
Model::query()
    ->orWhere('tag', 'php')
    ->orWhere('tag', 'laravel')
    ->orWhere('tag', 'elasticsearch')
    ->minimumShouldMatch(2)
    ->get();
```

### Field Exclusion with except()

The new `except()` method provides a clearer way to exclude fields from results:

```php
// Exclude sensitive fields
Model::query()->except('password', 'api_key')->get();

// Combine with select
Model::query()
    ->select('title', 'content', 'author')
    ->except('author.email')
    ->get();
```

### limit() Alias

The `limit()` method is now available as an alias for `take()`, matching Laravel Eloquent's API:

```php
Model::query()->limit(10)->get();
```

## Deprecations in v3.0

The following methods are deprecated and will be removed in a future version:

### from() and size() Methods

The Elasticsearch-specific `from()` and `size()` methods are deprecated. Use `skip()` and `take()`/`limit()` instead for consistency with Laravel Eloquent:

**Before (deprecated):**
```php
ES::index('my_index')->from(10)->size(20)->get();
```

**After:**
```php
ES::index('my_index')->skip(10)->take(20)->get();
// or
ES::index('my_index')->skip(10)->limit(20)->get();
```

### unselect() Method

The `unselect()` method is deprecated. Use `except()` instead for better clarity:

**Before (deprecated):**
```php
ES::index('my_index')->unselect('password', 'secret')->get();
```

**After:**
```php
ES::index('my_index')->except('password', 'secret')->get();
```

## Deprecations to Address Before Upgrading

Before upgrading to v3.0, ensure you've addressed these deprecations:

1. Rename `Query` imports and type hints to `Builder`
2. Replace static `Connection` method calls with `ConnectionManager`
3. Rename your `es.php` config to `elasticsearch.php`
4. Replace `app('es')` with `app('elasticsearch')` or interface resolution
5. Update any code accessing `Index` properties directly to use methods

## Quick Migration Checklist

- [ ] Update composer.json to require `matchory/elasticsearch: ^3.0`
- [ ] Run `composer update matchory/elasticsearch`
- [ ] Search and replace `use Matchory\Elasticsearch\Query` with `use Matchory\Elasticsearch\Builder`
- [ ] Search and replace `use Matchory\Elasticsearch\Classes\Bulk` with `use Matchory\Elasticsearch\Bulk`
- [ ] Update type hints from `Query` to `Builder`
- [ ] Rename `config/es.php` to `config/elasticsearch.php` if applicable
- [ ] Replace `app('es')` with `app('elasticsearch')`
- [ ] Remove any usage of `Connection::create()`, `Connection::connection()`, etc.
- [ ] Replace `$model->getConnection()` with `$model->getConnectionName()`
- [ ] Replace `$model->setConnection()` with `$model->setConnectionName()`
- [ ] Update `createIndex()` callbacks to use fluent `newIndex()` builder
- [ ] Update Scout config if using custom connection settings
- [ ] Run tests to verify everything works correctly
