<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Classes;

use Matchory\Elasticsearch\Query;

use function is_callable;

/**
 * Class Search
 *
 * @package Matchory\Elasticsearch\Classes
 */
class Search
{
    public const PARAMETER_BOOST = 'boost';

    public const PARAMETER_FIELDS = 'fields';

    public const PARAMETER_QUERY = 'query';

    /**
     * The search query boost factor
     *
     * @var int|null
     */
    public int|null $boost = null;

    /**
     * The search fields
     *
     * @var array
     */
    public array $fields = [];

    /**
     * The query object
     *
     * @var Query
     */
    public Query $query;

    /**
     * The search query string
     *
     * @var string
     */
    public string $queryString;

    /**
     * @var array|callable|null
     */
    protected $settings;

    /**
     * @param Query $query
     * @param string $queryString
     * @param callable|array|null $settings
     */
    public function __construct(
        Query $query,
        string $queryString,
        callable|array $settings = null
    ) {
        $this->query = $query;
        $this->queryString = $queryString;

        if (is_callable($settings)) {
            $settings($this);
        }

        // TODO: What is the purpose of this property?
        $this->settings = $settings;
    }

    /**
     * Set search boost factor
     *
     * @param int $boost
     *
     * @return $this
     */
    public function boost(int $boost = 1): self
    {
        $this->boost = $boost;

        return $this;
    }

    /**
     * Build the native query
     */
    public function build(): void
    {
        $queryParams = [
            self::PARAMETER_QUERY => $this->queryString,
        ];

        if ($this->boost > 1) {
            $queryParams[self::PARAMETER_BOOST] = $this->boost;
        }

        if (count($this->fields)) {
            $queryParams[self::PARAMETER_FIELDS] = $this->fields;
        }

        $this->query->must[] = [
            'query_string' => $queryParams,
        ];
    }

    /**
     * Set searchable fields
     *
     * @param array $fields
     *
     * @return $this
     */
    public function fields(array $fields = []): self
    {
        $searchable = [];

        foreach ($fields as $field => $weight) {
            $weightSuffix = $weight > 1 ? "^{$weight}" : '';
            $searchable[] = $field . $weightSuffix;
        }

        $this->fields = $searchable;

        return $this;
    }
}
