<?php

declare(strict_types=1);

namespace App\Core;

class ExecutionTimeMemory implements Memory
{
    private array $storage = [];

    public function get(string $key): mixed
    {
        return $this->storage[strtoupper($key)] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->storage[strtoupper($key)] = $value;
    }

    public function read(): array
    {
        return $this->storage;
    }
}