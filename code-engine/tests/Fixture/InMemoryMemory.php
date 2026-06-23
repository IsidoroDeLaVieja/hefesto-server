<?php

declare(strict_types=1);

namespace Tests\Fixture;

use App\Core\Memory;

class InMemoryMemory implements Memory
{
    /** @var array<string, mixed> */
    private array $storage = [];

    public function get(string $key): mixed
    {
        return $this->storage[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->storage[$key] = $value;
    }

    public function read(): array
    {
        return array_keys($this->storage);
    }
}