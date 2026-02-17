<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Testing\Responses;

use function array_replace_recursive;

abstract class FakeResponse
{
    /**
     * @param array<string, mixed> $data
     */
    protected function __construct(protected array $data) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return static
     */
    public function merge(array $overrides): static
    {
        $clone = clone $this;
        $clone->data = array_replace_recursive($clone->data, $overrides);

        return $clone;
    }
}
