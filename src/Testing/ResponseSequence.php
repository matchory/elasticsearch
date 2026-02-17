<?php

declare(strict_types=1);

namespace Matchory\Elasticsearch\Testing;

use Matchory\Elasticsearch\Testing\Responses\FakeResponse;
use RuntimeException;

final class ResponseSequence
{
    /**
     * @var list<mixed>
     */
    private array $responses;

    private mixed $emptyResponse = null;

    public function __construct(mixed ...$responses)
    {
        $this->responses = array_values($responses);
    }

    /**
     * Set a fallback response when the sequence is exhausted.
     */
    public function whenEmpty(mixed $response): self
    {
        $this->emptyResponse = $response;

        return $this;
    }

    /**
     * @internal Called by FakeClient to get the next response.
     */
    public function next(string $method, array $params): mixed
    {
        if ($this->responses !== []) {
            return $this->resolve(array_shift($this->responses), $method, $params);
        }

        if ($this->emptyResponse !== null) {
            return $this->resolve($this->emptyResponse, $method, $params);
        }

        throw new RuntimeException('Response sequence is empty.');
    }

    private function resolve(mixed $response, string $method, array $params): mixed
    {
        if ($response instanceof FakeResponse) {
            return $response->toArray();
        }

        if (is_callable($response)) {
            return $response($method, $params);
        }

        return $response;
    }
}
