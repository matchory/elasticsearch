# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - Unreleased

### Added

- **Connection health check**: New `ping()` method on Connection to check Elasticsearch connectivity
- **Retry logic**: New `retry($attempts, $delay)` method for automatic retry with exponential backoff on transient failures
- **Bulk batching**: The `bulk()` method now accepts an optional `$batchSize` parameter to automatically chunk large operations
- **Query profiling**: New `profile()` method to enable Elasticsearch query profiling for debugging
- **Cache logging**: Cache read/write failures are now logged instead of silently ignored
- **Fuzzy search**: New `fuzzy($field, $value, $fuzziness)` method for typo-tolerant search
- **Match phrase**: New `matchPhrase($field, $value, $slop)` method for exact phrase matching
- **Match phrase prefix**: New `matchPhrasePrefix($field, $value)` method for autocomplete-style prefix searches
- **Min score filter**: New `minScore($score)` method to filter out low-relevance results
- **Search after pagination**: New `searchAfter($sortValues)` method for efficient deep pagination using cursor-based approach
- **Track total hits**: New `trackTotalHits($track)` method to control total hit counting for performance optimization
- **Completion suggester**: New `suggest($name, $text, $field)` method for autocomplete functionality with context and fuzzy support
- **Term suggester**: New `suggestTerm($name, $text, $field)` method for spelling correction suggestions
- **Bulk update by query**: New `updateByQuery($script, $params)` method to update all documents matching a query
- **Bulk delete by query**: New `deleteByQuery()` method to delete all documents matching a query
- **Fluent aggregation helpers**:
  - `termsAgg($name, $field, $size)` - Terms bucket aggregation
  - `avgAgg($name, $field)` - Average metric aggregation
  - `sumAgg($name, $field)` - Sum metric aggregation
  - `minAgg($name, $field)` - Minimum metric aggregation
  - `maxAgg($name, $field)` - Maximum metric aggregation
  - `cardinalityAgg($name, $field)` - Cardinality (distinct count) aggregation
  - `valueCountAgg($name, $field)` - Value count aggregation
  - `statsAgg($name, $field)` - Stats (min, max, sum, count, avg) aggregation
  - `dateHistogramAgg($name, $field, $interval)` - Date histogram bucket aggregation
  - `histogramAgg($name, $field, $interval)` - Numeric histogram bucket aggregation
  - `rangeAgg($name, $field, $ranges)` - Range bucket aggregation
  - `filterAgg($name, $filter)` - Filter bucket aggregation
  - `subAgg($parentName, $childName, $config)` - Sub-aggregation support
- **OR conditions**: New `orWhere($field, $operator, $value)` method for OR query logic using Elasticsearch's `should` clause
- **Should clause**: New `should($type, $parameters)` method for direct access to bool query should clauses
- **Minimum should match**: New `minimumShouldMatch($minimum)` method to control how many should clauses must match
- **Field exclusion**: New `except(...$fields)` method to exclude specific fields from results (clearer alternative to `unselect()`)
- **Limit alias**: New `limit($count)` method as an alias for `take()` matching Laravel Eloquent's API

### Changed

- **BREAKING**: Renamed `Query` class to `Builder` for consistency with Laravel Eloquent naming conventions
- **BREAKING**: ScoutEngine now uses ConnectionManager for client creation, respecting all connection configuration
- Scout search now uses `simple_query_string` instead of `query_string` for safer user input handling
- Cache key generation now includes the application key for improved security
- Cache key generation uses `print_r()` fallback instead of expensive `serialize()`

### Removed

- **BREAKING**: Removed deprecated `Request` class
- **BREAKING**: Removed deprecated static methods from Connection:
  - `setConnectionResolver()`
  - `configureLogging()`
  - `create()`
  - `connection()`
  - `isLoaded()`
- **BREAKING**: Removed static `$resolver` and `$clients` properties from Connection
- **BREAKING**: Removed support for deprecated `es.php` config file (use `elasticsearch.php`)
- **BREAKING**: Removed deprecated `es` container alias (use `elasticsearch`)
- **BREAKING**: Removed deprecated `ignore()` method from Index (use `ignores()`)
- Removed deprecated `$connection` parameter from `Connection::newQuery()`

### Fixed

- ScoutEngine now consistently uses `getTotalCount()` for total count extraction in `map()` and `lazyMap()` methods
- Index class no longer exposes internal properties publicly

### Deprecated

- The following Index properties are now private and should be accessed via methods:
  - `$callback`
  - `$connection`
  - `$ignores`
  - `$mappings`
  - `$name`
  - `$replicas`
  - `$shards`
  - `$aliases`
- `from($offset)` method is deprecated, use `skip($offset)` instead for Eloquent compatibility
- `size($limit)` method is deprecated, use `take($limit)` or `limit($limit)` instead for Eloquent compatibility
- `unselect(...$fields)` method is deprecated, use `except(...$fields)` instead for better clarity

## [2.x] - Previous Releases

See GitHub releases for previous changelog entries.
