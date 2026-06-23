<?php

declare(strict_types=1);

namespace Tests\Integration\Adapters;

use App\Core\Engine;
use App\Core\Queue;

/**
 * In-memory implementation of the Queue interface for integration tests.
 * Stores pushed engines in an array, avoiding any Redis dependency.
 */
class InMemoryQueue implements Queue
{
    /** @var array<int, array{engine: Engine, delay: int}> */
    private array $pushed = [];

    /** @var array<int, Engine|null> */
    private array $nextResults = [];

    public function push(Engine $engine, int $delay): void
    {
        $this->pushed[] = ['engine' => $engine, 'delay' => $delay];
    }

    public function next(): ?Engine
    {
        return array_shift($this->nextResults) ?? null;
    }

    public function fail(string $id): void
    {
        // no-op for testing
    }

    public function success(string $id, string $org, string $env): void
    {
        // no-op for testing
    }

    public function requeueFailed(string $org, string $env): int
    {
        return 0;
    }

    public function flush(string $org, string $env): int
    {
        return 0;
    }

    public function count(string $org, string $env): array
    {
        return ['requests' => 0, 'waiting' => 0, 'processing' => 0, 'failed' => 0, 'success' => 0];
    }

    /**
     * Returns the list of recorded push calls.
     * @return array<int, array{engine: Engine, delay: int}>
     */
    public function getPushed(): array
    {
        return $this->pushed;
    }

    /**
     * Clears all recorded data.
     */
    public function reset(): void
    {
        $this->pushed = [];
        $this->nextResults = [];
    }
}