<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Facades;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Facade;
use Matchory\Elasticsearch\Builder;
use Matchory\Elasticsearch\Collection;
use Matchory\Elasticsearch\Interfaces\ConnectionInterface;
use Matchory\Elasticsearch\Interfaces\ConnectionResolverInterface;
use Matchory\Elasticsearch\Model;
use Matchory\Elasticsearch\Testing\ElasticsearchFake;

use function class_alias;

class_alias(Elasticsearch::class, 'Matchory\Elasticsearch\Facades\ES');

/**
 * Elasticsearch Facade
 * ====================
 * This facade proxies to the default connection instance, which in turn proxies
 * to a new query instance. This provides unified access to almost all methods
 * the library has to offer.
 *
 * Connection & Query
 * @method static ConnectionInterface connection(?string $name = null)
 * @method static string getDefaultConnection()
 * @method static void setDefaultConnection(string $name)
 * @method static Builder newQuery()
 * @method static Builder index(string $index)
 * @method static Builder scroll(string $scroll)
 * @method static Builder scrollId(?string $scroll)
 * @method static Builder searchType(string $type)
 * @method static Builder ignore(...$args)
 * @method static Builder orderBy($field, string $direction = 'asc')
 * @method static Builder select(...$args)
 * @method static Builder unselect(...$args)
 * @method static Builder body(array $body = [])
 * @method static Builder groupBy(string $field)
 * @method static Builder id(?string $id = null)
 * @method static Builder skip(int $from = 0)
 * @method static Builder take(int $size = 10)
 *
 * Where Clauses
 * @method static Builder where($name, $operator = Builder::OPERATOR_EQUAL, $value = null)
 * @method static Builder firstWhere($name, $operator = Builder::OPERATOR_EQUAL, $value = null)
 * @method static Builder whereNot($name, $operator = Builder::OPERATOR_EQUAL, $value = null)
 * @method static Builder whereBetween($name, $firstValue, $lastValue)
 * @method static Builder whereNotBetween($name, $firstValue, $lastValue)
 * @method static Builder whereIn($name, array $values)
 * @method static Builder whereNotIn($name, array $values)
 * @method static Builder whereExists($name, bool $exists = true)
 *
 * Search & Filters
 * @method static Builder distance($name, $value, string $distance)
 * @method static Builder search(?string $queryString = null, $settings = null, ?int $boost = null)
 * @method static Builder nested(string $path)
 * @method static Builder highlight(...$args)
 * @method static Builder fuzzy(string $field, mixed $value, string $fuzziness = 'AUTO', ?int $prefixLength = null, ?int $maxExpansions = null)
 * @method static Builder matchPhrase(string $field, mixed $value, ?int $slop = null)
 * @method static Builder matchPhrasePrefix(string $field, mixed $value, ?int $maxExpansions = null)
 * @method static Builder minScore(float $score)
 *
 * Pagination
 * @method static Builder searchAfter(array $sortValues)
 * @method static Builder trackTotalHits(bool|int $track = true)
 *
 * Suggesters
 * @method static Builder suggest(string $name, string $text, string $field, int $size = 5, array $contexts = [], bool $skipDuplicates = false, ?string $fuzzy = null)
 * @method static Builder suggestTerm(string $name, string $text, string $field, int $size = 5)
 *
 * Scopes
 * @method static Builder withGlobalScope(string $identifier, $scope)
 * @method static Builder withoutGlobalScope($scope)
 * @method static Builder withoutGlobalScopes(array $scopes = null)
 * @method static array removedScopes()
 * @method static bool  hasNamedScope(string $scope)
 * @method static Builder scopes($scopes)
 * @method static Builder applyScopes()
 *
 * Caching & Retry
 * @method static Builder remember($ttl, ?string $key = null)
 * @method static Builder rememberForever(?string $key = null)
 * @method static Builder retry(int $attempts = 3, int $delay = 100)
 *
 * Debugging
 * @method static Builder profile(bool $enable = true)
 *
 * Aggregations
 * @method static Builder aggregate(string $name, array|string|null $settings = null)
 * @method static Builder termsAgg(string $name, string $field, int $size = 10, array $options = [])
 * @method static Builder avgAgg(string $name, string $field)
 * @method static Builder sumAgg(string $name, string $field)
 * @method static Builder minAgg(string $name, string $field)
 * @method static Builder maxAgg(string $name, string $field)
 * @method static Builder cardinalityAgg(string $name, string $field)
 * @method static Builder valueCountAgg(string $name, string $field)
 * @method static Builder statsAgg(string $name, string $field)
 * @method static Builder dateHistogramAgg(string $name, string $field, string $interval, ?string $format = null, array $options = [])
 * @method static Builder histogramAgg(string $name, string $field, int|float $interval, array $options = [])
 * @method static Builder rangeAgg(string $name, string $field, array $ranges)
 * @method static Builder filterAgg(string $name, array $filter)
 * @method static Builder subAgg(string $parentName, string $childName, array $config)
 *
 * Execution & Results
 * @method static Collection get(?string $scrollId = null)
 * @method static LengthAwarePaginator paginate(int $perPage = 10, string $pageName = 'page', ?int $page = null)
 * @method static Model|null first(?string $scrollId = null)
 * @method static Model|mixed|null firstOr(?string $scrollId = null, ?callable $callback = null)
 * @method static Model firstOrFail(?string $scrollId = null)
 * @method static int count()
 * @method static array|null performSearch(?string $scrollId = null)
 * @method static ConnectionInterface getConnection()
 *
 * Document Operations
 * @method static object insert($data, ?string $id = null)
 * @method static object update($data, ?string $id = null)
 * @method static object delete(?string $id = null)
 * @method static object script($script, array $params = [])
 * @method static object increment(string $field, int $count = 1)
 * @method static object decrement(string $field, int $count = 1)
 *
 * Bulk Operations
 * @method static object updateByQuery(array|string $script, array $params = [], bool $waitForCompletion = true)
 * @method static object deleteByQuery(bool $waitForCompletion = true)
 *
 * Index Management
 * @method static array createIndex(string $name, ?callable $callback = null)
 * @method static array dropIndex(string $name)
 *
 * @package Matchory\Elasticsearch\Facades
 */
class Elasticsearch extends Facade
{
    private static ?ConnectionResolverInterface $originalResolver = null;

    /**
     * Replace the bound resolver with a fake for testing.
     *
     * @param mixed ...$responses Initial queued responses for the FakeClient.
     */
    public static function fake(mixed ...$responses): ElasticsearchFake
    {
        $fake = new ElasticsearchFake(...$responses);

        // Stash the real resolver so we can restore it later
        if (self::$originalResolver === null) {
            self::$originalResolver = app(ConnectionResolverInterface::class);
        }

        self::swap($fake);
        Model::setConnectionResolver($fake);

        return $fake;
    }

    /**
     * Restore the original resolver after testing.
     */
    public static function unfake(): void
    {
        if (self::$originalResolver !== null) {
            app()->instance(ConnectionResolverInterface::class, self::$originalResolver);
            self::swap(self::$originalResolver);
            Model::setConnectionResolver(self::$originalResolver);
            self::$originalResolver = null;
        }
    }

    /**
     * @inheritDoc
     */
    protected static function getFacadeAccessor(): string
    {
        return ConnectionResolverInterface::class;
    }
}
