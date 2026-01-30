<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Tests\Integration;

use Matchory\Elasticsearch\ScoutEngine;
use Matchory\Elasticsearch\Tests\Support\Mocks\MockElasticsearchClient;
use Matchory\Elasticsearch\Tests\TestCase;

/**
 * Base test case for Scout integration tests
 *
 * Extends the main TestCase to provide Scout-specific mock setup
 * and helper methods for testing Scout engine functionality.
 */
abstract class ScoutTestCase extends TestCase
{
    protected ScoutEngine $engine;
    protected string $testIndex = 'test_scout_index';

    protected function setUp(): void
    {
        parent::setUp();

        // Use the MockElasticsearchClient directly for the ScoutEngine
        // The $this->mockClient is already set up by the parent TestCase
        $this->engine = new ScoutEngine($this->mockClient, $this->testIndex);
    }
}
