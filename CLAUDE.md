# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is `matchory/elasticsearch`, a Laravel Elasticsearch integration package that provides:
- Fluent Elasticsearch query builder with Eloquent-like syntax
- Elasticsearch Models mimicking Laravel Eloquent behavior
- Laravel Scout driver for search functionality
- Artisan commands for index management

## Development Commands

```bash
# Install dependencies
composer install

# Run all tests
./vendor/bin/phpunit

# Run specific test suite
./vendor/bin/phpunit --testsuite=Unit
./vendor/bin/phpunit --testsuite=Integration
./vendor/bin/phpunit --testsuite=Feature

# Run a single test file
./vendor/bin/phpunit tests/Unit/SomeTest.php

# Run a single test method
./vendor/bin/phpunit --filter=testMethodName

# Static analysis (PHPStan level 2)
./vendor/bin/phpstan analyse

# Code formatting (Laravel Pint with PER preset)
./vendor/bin/pint

# Check formatting without fixing
./vendor/bin/pint --test
```

## Architecture

### Core Classes

- **Model** (`src/Model.php`): Base Elasticsearch model class. Uses Eloquent traits (`HasAttributes`, `HasEvents`, `GuardsAttributes`, `HidesAttributes`) to provide familiar Laravel model behavior including accessors/mutators, attribute casting, events, mass assignment protection, and route model binding.

- **Query** (`src/Query.php`): Query builder for Elasticsearch. Composed of multiple concerns:
  - `BuildsFluentQueries`: Fluent query methods (where, orderBy, etc.)
  - `ExecutesQueries`: Query execution and result handling
  - `AppliesScopes`: Global and local query scopes
  - `ManagesIndices`: Index operations
  - `ExplainsQueries`: Query explanation utilities

- **Connection** (`src/Connection.php`): Wraps the Elasticsearch PHP client. Handles client configuration, caching, and query execution.

- **ConnectionManager** (`src/ConnectionManager.php`): Manages multiple named Elasticsearch connections.

- **ScoutEngine** (`src/ScoutEngine.php`): Laravel Scout driver implementation for Elasticsearch.

### Artisan Commands

Commands are in `src/Commands/`:
- `es:indices:list` - List all indices
- `es:indices:create` - Create indices from config
- `es:indices:update` - Update index mappings/settings
- `es:indices:drop` - Drop indices
- `es:indices:reindex` - Reindex data between indices

### Configuration

- `config/elasticsearch.php` - Connection and index configuration
- `config/scout.php` - Scout driver settings

## Key Patterns

### Query Building
The Query class uses method chaining with immutable-style returns. Query methods are defined in the `BuildsFluentQueries` trait.

### Model Design
Models extend `Matchory\Elasticsearch\Model` and can define:
- `$index` - Target Elasticsearch index
- `$connection` - Named connection to use
- `$fillable`/`$guarded` - Mass assignment protection
- `$casts` - Attribute type casting

### Scopes
Both global and local scopes are supported, similar to Eloquent:
- Global: Implement `ScopeInterface` and register in `booted()`
- Local: Define `scope{Name}(Query $query)` methods on models

## Testing

Tests are organized in:
- `tests/Unit/` - Unit tests
- `tests/Integration/` - Integration tests (require Elasticsearch)
- `tests/Feature/` - Feature tests
- `tests/Support/` - Test utilities and helpers